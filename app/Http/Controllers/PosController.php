<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Customer;
use App\Models\StoreProfile;
use App\Models\PaymentMethod;
use App\Models\PrintVendor;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\AreaPricingService;

class PosController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->where('is_active', true)->get();
        $customers = Customer::orderBy('name')->get();
        $paymentMethods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $printVendors = PrintVendor::where('is_active', true)->orderBy('name')->get();
        $profile = StoreProfile::firstOrCreate([], [
            'store_name' => 'Era Digital',
            'address' => 'Jl. Raya Utama No. 45, Kebayoran Baru, Jakarta Selatan',
            'phone' => '0812-3456-7890',
            'signature_path' => null,
            'saldo_digital' => 350000.00,
        ]);
        return Inertia::render('POS/Index', [
            'products' => $products,
            'customers' => $customers,
            'paymentMethods' => $paymentMethods,
            'printVendors' => $printVendors,
            'profile' => $profile,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cart' => 'required|array|min:1',
            'cart.*.id' => 'required',
            'cart.*.quantity' => 'required|numeric|min:0.1',
            'cart.*.price' => 'required|numeric|min:0',
            'cart.*.print_vendor_id' => 'nullable|exists:print_vendors,id',
            'total' => 'required|numeric|min:0',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'uang_diterima' => 'nullable|numeric|min:0',
            'customer_id' => 'nullable|exists:customers,id',
            'invoice_customer_name' => 'nullable|string|max:255',
            'invoice_customer_phone' => 'nullable|string|max:30',
            'keterangan' => 'nullable|string',
        ]);

        // Area-based items (per m²): quantity = pcs (integer >= 1), length & width > 0.
        foreach ($request->cart as $index => $item) {
            $product = null;
            if (is_numeric($item['id'] ?? null) && ($item['id'] ?? 0) < 10000000000) {
                $product = Product::find($item['id']);
            }

            $isAreaBased = $product
                ? AreaPricingService::isAreaBased($product)
                : (bool) ($item['is_area_based'] ?? false);

            if (! $isAreaBased) {
                continue;
            }

            $length = (float) ($item['length'] ?? 0);
            $width = (float) ($item['width'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);

            if ($length <= 0) {
                throw ValidationException::withMessages([
                    "cart.{$index}.length" => 'Panjang harus lebih besar dari 0 (nol) untuk produk berbasis luas.',
                ]);
            }

            if ($width <= 0) {
                throw ValidationException::withMessages([
                    "cart.{$index}.width" => 'Lebar harus lebih besar dari 0 (nol) untuk produk berbasis luas.',
                ]);
            }

            if ($quantity < 1 || floor($quantity) !== $quantity) {
                throw ValidationException::withMessages([
                    "cart.{$index}.quantity" => 'Jumlah pcs produk berbasis luas harus bilangan bulat minimal 1.',
                ]);
            }
        }

        DB::beginTransaction();

        try {
            $date = now()->format('Ymd');
            
            $latestTransaction = Transaction::whereDate('created_at', now()->toDateString())
                ->where('invoice_number', 'like', "TRX-{$date}-%")
                ->orderBy('invoice_number', 'desc')
                ->first();

            if ($latestTransaction) {
                $parts = explode('-', $latestTransaction->invoice_number);
                $lastSeq = intval(end($parts));
                $nextSeq = $lastSeq + 1;
            } else {
                $nextSeq = 1;
            }
            
            $increment = str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
            $invoiceNumber = "TRX-{$date}-{$increment}";

            $customerId = $request->customer_id;
            
            // Check if there is a PPOB (digital) item in the cart to auto-create customer if missing
            $hasPpob = false;
            $ppobAccountNumber = null;
            $ppobAccountName = null;
            $ppobDigitalType = 'phone';
            
            foreach ($request->cart as $item) {
                $itemType = $item['type'] ?? 'fisik';
                if ($itemType === 'digital' || $itemType === 'ppob') {
                    $hasPpob = true;
                    $ppobAccountNumber = $item['account_number'] ?? null;
                    $ppobAccountName = $item['account_name'] ?? null;
                    $ppobDigitalType = $item['digital_type'] ?? 'phone';
                    break; 
                }
            }

            if ($hasPpob && !$customerId && !$request->invoice_customer_name && $ppobAccountNumber && $ppobAccountName) {
                // Auto-create customer using PPOB account details
                $existingCustomer = Customer::where('phone', $ppobAccountNumber)->first();
                if (!$existingCustomer) {
                    $existingCustomer = Customer::create([
                        'name' => $ppobAccountName,
                        'customer_type' => $ppobDigitalType === 'token' ? 'token' : 'operator',
                        'phone' => $ppobAccountNumber,
                    ]);
                }
                $customerId = $existingCustomer->id;
            }

            $customerName = null;
            $customerPhone = null;
            if ($customerId) {
                $customer = Customer::find($customerId);
                if ($customer) {
                    $customerName = $customer->name;
                    $customerPhone = $customer->phone;
                    
                    // If customer exists but has no phone number, update it
                    if ($hasPpob && !$customerPhone && $ppobAccountNumber) {
                        $customer->update(['phone' => $ppobAccountNumber]);
                        $customerPhone = $ppobAccountNumber;
                    }
                }
            } else {
                $customerName = $request->invoice_customer_name;
                $customerPhone = $request->invoice_customer_phone;
            }

            $paymentMethod = PaymentMethod::where('is_active', true)->findOrFail($request->payment_method_id);
            $totalPrice = (float) $request->total;
            $uangDiterima = $paymentMethod->is_cash
                ? (float) ($request->uang_diterima ?? 0)
                : $totalPrice;
            $jumlahDibayar = min($uangDiterima, $totalPrice);
            $kembalian = $paymentMethod->is_cash ? max(0, $uangDiterima - $totalPrice) : 0;
            $sisaTagihan = max(0, $totalPrice - $jumlahDibayar);

            if ($jumlahDibayar >= $totalPrice) {
                $statusBayar = 'lunas';
                $sisaTagihan = 0;
                $paymentStatus = 'paid';
            } elseif ($jumlahDibayar > 0) {
                $statusBayar = 'dp';
                $paymentStatus = 'partial';
            } else {
                $statusBayar = 'piutang';
                $paymentStatus = 'unpaid';
            }

            $transaction = Transaction::create([
                'invoice_number' => $invoiceNumber,
                'cashier_id' => auth()->id() ?? 1,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'total_base_price' => 0,
                'total_price' => $totalPrice,
                'total_profit' => 0,
                'payment_method' => $paymentMethod->code,
                'payment_method_id' => $paymentMethod->id,
                'payment_status' => $paymentStatus,
                'status_bayar' => $statusBayar,
                'jumlah_dibayar' => $jumlahDibayar,
                'uang_diterima' => $uangDiterima,
                'kembalian' => $kembalian,
                'sisa_tagihan' => $sisaTagihan,
                'keterangan' => $request->keterangan,
            ]);

            $totalBasePrice = 0;
            $totalProfit = 0;
            $totalPriceComputed = 0;

            foreach ($request->cart as $item) {
                $product = null;
                if (is_numeric($item['id']) && $item['id'] < 10000000000) {
                     $product = Product::find($item['id']);
                }

                $basePrice = $product ? $product->base_price : ($item['base_price'] ?? 0);
                $isAreaBased = $product
                    ? AreaPricingService::isAreaBased($product)
                    : (bool) ($item['is_area_based'] ?? false);

                if (isset($item['type']) && $item['type'] === 'cetak') {
                    // Modal cetak = estimasi biaya vendor jika tidak ada produk DB
                    if (!$product || $product->base_price <= 0) {
                        $basePrice = $item['price'] / 1.3;
                    }
                } elseif (isset($item['type']) && ($item['type'] === 'digital' || $item['type'] === 'ppob')) {
                    // Modal PPOB = nominal yang dikirim ke pelanggan/distributor
                    // Keuntungan = admin_fee = total_harga - nominal
                    $basePrice = $item['nominal'] ?? 0;
                }

                $metadata = [
                    'detail'    => $item['detail'] ?? '',
                    'admin_fee' => $item['admin_fee'] ?? 0,
                    'nominal'   => $item['nominal'] ?? null,
                ];

                $quantity = (float) $item['quantity'];
                $unit = $product ? $product->unit : 'pcs';
                $basePricePerPiece = 0.0;
                $sellingPricePerPiece = 0.0;

                if ($isAreaBased) {
                    // AREA-BASED PRINTING (spanduk/banner/stiker per m²).
                    // product.selling_price = RATE per m², product.base_price = HPP rate per m².
                    // quantity = jumlah pcs (validated integer >= 1), ukuran disimpan di metadata.
                    $length = (float) ($item['length'] ?? 0);
                    $width = (float) ($item['width'] ?? 0);
                    $areaPerPiece = AreaPricingService::roundArea(
                        (float) ($item['area_per_piece'] ?? AreaPricingService::areaPerPiece($length, $width))
                    );

                    $sellingRate = (float) ($item['selling_rate'] ?? ($product ? $product->selling_price : $item['price']));
                    $baseRate = (float) ($item['base_rate'] ?? ($product && $product->base_price > 0 ? $product->base_price : 0));

                    $sellingPricePerPiece = AreaPricingService::pricePerPiece($sellingRate, $areaPerPiece);
                    $basePricePerPiece = AreaPricingService::pricePerPiece($baseRate, $areaPerPiece);

                    $quantity = (int) $quantity; // pcs
                    $subtotalPrice = round($sellingPricePerPiece * $quantity, 2);
                    $subtotalBase = round($basePricePerPiece * $quantity, 2);
                    $profit = round($subtotalPrice - $subtotalBase, 2);

                    $metadata = array_merge($metadata, [
                        'length'         => $length,
                        'width'          => $width,
                        'area_per_piece' => $areaPerPiece,
                        'total_area'     => AreaPricingService::totalArea($areaPerPiece, $quantity),
                        'selling_rate'   => $sellingRate,
                        'base_rate'      => $baseRate,
                    ]);
                } else {
                    $subtotalBase = $basePrice * $quantity;
                    $subtotalPrice = $item['price'] * $quantity; // price = nominal + admin_fee untuk ppob
                    $profit = $subtotalPrice - $subtotalBase; // profit = admin_fee ✓
                }

                $itemType = $item['type'] ?? 'fisik';
                if ($itemType === 'cetak' || $itemType === 'fotokopi') {
                    $itemType = 'jasa';
                } elseif ($itemType === 'digital') {
                    $itemType = 'ppob';
                }

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product ? $product->id : null,
                    'print_vendor_id' => $item['print_vendor_id'] ?? null,
                    'item_name' => $item['name'],
                    'type' => $itemType,
                    'unit' => $unit,
                    'quantity' => $quantity,
                    'base_price' => $isAreaBased ? $basePricePerPiece : $basePrice,
                    'selling_price' => $isAreaBased ? $sellingPricePerPiece : $item['price'],
                    'subtotal_base' => $subtotalBase,
                    'subtotal_price' => $subtotalPrice,
                    'profit' => $profit,
                    'service_status' => $itemType === 'jasa' ? 'menunggu_file' : 'none',
                    'metadata' => $metadata,
                ]);

                if ($itemType === 'ppob') {
                    $digitalType = $item['digital_type'] ?? 'phone';
                    $accountNumber = $item['account_number'] ?? null;
                    $accountName = $item['account_name'] ?? null;
                    $nominalVal = $item['nominal'] ?? $item['price'] ?? 0;

                    if ($accountNumber && $accountName) {
                        \App\Models\CustomerDigitalAccount::firstOrCreate([
                            'type' => $digitalType,
                            'account_number' => $accountNumber,
                        ], [
                            'customer_id' => $customerId,
                            'account_name' => $accountName,
                        ]);
                    }

                    // Decrement shop's digital balance in database
                    $profile = StoreProfile::first();
                    if ($profile) {
                        $profile->decrement('saldo_digital', $nominalVal);
                    }
                }

                $totalBasePrice += $subtotalBase;
                $totalPriceComputed += $subtotalPrice;
                $totalProfit += $profit;
                
                if ($product && $product->category->type === 'fisik') {
                    $product->decrement('stock', $item['quantity']);
                }
            }

            // Rekonsiliasi total transaksi dengan jumlah seluruh item (authoritative).
            $transaction->update([
                'total_base_price' => round($totalBasePrice, 2),
                'total_price' => round($totalPriceComputed, 2),
                'total_profit' => round($totalProfit, 2),
            ]);

            // Record payment history for initial paid amount
            $transaction->paymentHistories()->create([
                'jumlah_bayar' => $jumlahDibayar,
                'tanggal_bayar' => now(),
                'metode_bayar' => $paymentMethod->code,
                'keterangan' => $statusBayar === 'piutang' ? 'Piutang Awal' : ($statusBayar === 'dp' ? 'Uang Muka / DP Awal' : 'Pembayaran Lunas'),
            ]);

            // Refresh with relationships
            $freshTransaction = Transaction::with(['items', 'customer', 'paymentHistories', 'paymentMethodMaster'])->find($transaction->id);

            DB::commit();

            return redirect()->back()->with([
                'success' => 'Transaksi berhasil disimpan!',
                'print_invoice' => $invoiceNumber,
                'recent_transaction' => $freshTransaction
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses transaksi: ' . $e->getMessage());
        }
    }

    public function printInvoice($invoiceNumber)
    {
        $transaction = Transaction::with(['items.product', 'cashier', 'paymentMethodMaster'])
            ->where('invoice_number', $invoiceNumber)
            ->firstOrFail();

        $profile = StoreProfile::first() ?? StoreProfile::create([
            'store_name' => 'Era Digital',
            'address' => 'Jl. Raya Utama No. 45, Kebayoran Baru, Jakarta Selatan',
            'phone' => '0812-3456-7890',
        ]);

        $customer = $transaction->customer;

        return Inertia::render('POS/PrintInvoice', [
            'transaction' => $transaction,
            'profile' => $profile,
            'customer' => $customer,
        ]);
     }

     public function searchDigitalAccounts(Request $request)
     {
         $query = $request->query('query');
         $type = $request->query('type'); // 'token' or 'phone'

         $accounts = \App\Models\CustomerDigitalAccount::where('type', $type)
             ->where(function ($builder) use ($query) {
                 $builder->where('account_number', 'like', "%{$query}%")
                     ->orWhere('account_name', 'like', "%{$query}%");
             })
             ->limit(5)
             ->get();

         return response()->json($accounts);
     }
}
