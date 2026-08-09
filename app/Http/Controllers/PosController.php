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
            'cart.*.id' => 'required|integer|exists:products,id',
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

        // ------------------------------------------------------------------
        // 1. Muat semua product DB dari cart (sekali saja).
        // 2. Hitung setiap item SERVER-SIDE (authoritative). Untuk produk area
        //    dari DB, nilai finansial dari frontend (price, area_per_piece,
        //    selling_rate, base_rate, total_area) DIABAIKAN — hanya length,
        //    width, quantity & product id yang dipakai, sisanya dihitung ulang
        //    dari master product.
        // ------------------------------------------------------------------
        $productsById = $this->loadCartProducts($request->cart);

        $computedItems = [];
        foreach ($request->cart as $index => $item) {
            $product = $productsById[(int) ($item['id'] ?? 0)] ?? null;

            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages([
                    "cart.{$index}.id" => 'Produk tidak ditemukan atau tidak aktif.',
                ]);
            }

            $computedItems[] = $this->computeCartItem($item, $product, $index);
        }

        // ------------------------------------------------------------------
        // 3. Total transaksi = jumlah seluruh item (authoritative).
        //    request.total TIDAK dipakai sebagai source of truth.
        // ------------------------------------------------------------------
        $totalPriceComputed = round(array_sum(array_column($computedItems, 'subtotal_price')), 2);
        $totalBasePrice = round(array_sum(array_column($computedItems, 'subtotal_base')), 2);
        $totalProfit = round(array_sum(array_column($computedItems, 'profit')), 2);

        $paymentMethod = PaymentMethod::where('is_active', true)->findOrFail($request->payment_method_id);

        // ------------------------------------------------------------------
        // 4. Status pembayaran dihitung dari TOTAL SERVER (bukan request.total).
        // ------------------------------------------------------------------
        $uangDiterima = $paymentMethod->is_cash
            ? (float) ($request->uang_diterima ?? 0)
            : $totalPriceComputed;
        $jumlahDibayar = min($uangDiterima, $totalPriceComputed);
        $kembalian = $paymentMethod->is_cash ? max(0, $uangDiterima - $totalPriceComputed) : 0;
        $sisaTagihan = max(0, $totalPriceComputed - $jumlahDibayar);

        if ($jumlahDibayar >= $totalPriceComputed) {
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

            // ------------------------------------------------------------------
            // 5. Customer (termasuk auto-create untuk PPOB) — logika existing,
            //    tetap di dalam DB transaction agar bisa di-rollback.
            // ------------------------------------------------------------------
            $customerId = $request->customer_id;

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

            if ($hasPpob && ! $customerId && ! $request->invoice_customer_name && $ppobAccountNumber && $ppobAccountName) {
                // Auto-create customer using PPOB account details
                $existingCustomer = Customer::where('phone', $ppobAccountNumber)->first();
                if (! $existingCustomer) {
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
                    if ($hasPpob && ! $customerPhone && $ppobAccountNumber) {
                        $customer->update(['phone' => $ppobAccountNumber]);
                        $customerPhone = $ppobAccountNumber;
                    }
                }
            } else {
                $customerName = $request->invoice_customer_name;
                $customerPhone = $request->invoice_customer_phone;
            }

            $transaction = Transaction::create([
                'invoice_number' => $invoiceNumber,
                'cashier_id' => auth()->id() ?? 1,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'total_base_price' => $totalBasePrice,
                'total_price' => $totalPriceComputed,
                'total_profit' => $totalProfit,
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

            foreach ($computedItems as $computedItem) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $computedItem['product'] ? $computedItem['product']->id : null,
                    'print_vendor_id' => $computedItem['print_vendor_id'],
                    'item_name' => $computedItem['item_name'],
                    'type' => $computedItem['item_type'],
                    'unit' => $computedItem['unit'],
                    'quantity' => $computedItem['quantity'],
                    'base_price' => $computedItem['base_price'],
                    'selling_price' => $computedItem['selling_price'],
                    'subtotal_base' => $computedItem['subtotal_base'],
                    'subtotal_price' => $computedItem['subtotal_price'],
                    'profit' => $computedItem['profit'],
                    'service_status' => $computedItem['item_type'] === 'jasa' ? 'menunggu_file' : 'none',
                    'metadata' => $computedItem['metadata'],
                ]);

                if ($computedItem['item_type'] === 'ppob') {
                    $digitalType = $computedItem['digital_type'];
                    $accountNumber = $computedItem['account_number'];
                    $accountName = $computedItem['account_name'];
                    $nominalVal = $computedItem['nominal'];

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

                if ($computedItem['product'] && $computedItem['product']->category->type === 'fisik') {
                    $computedItem['product']->decrement('stock', $computedItem['quantity']);
                }
            }

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

    /**
     * Muat semua product DB yang direferensikan cart (sekali saja).
     *
     * @return array<int, Product|null>
     */
    private function loadCartProducts(array $cart): array
    {
        $productsById = [];

        foreach ($cart as $item) {
            $id = $item['id'] ?? null;
            if (! is_numeric($id) || (int) $id <= 0 || (int) $id >= 10000000000) {
                continue;
            }
            $id = (int) $id;
            if (! array_key_exists($id, $productsById)) {
                // Eager-load category karena item type diambil dari kategori produk.
                $productsById[$id] = Product::with('category')->find($id);
            }
        }

        return $productsById;
    }

    /**
     * Hitung satu item cart secara server-side (authoritative).
     *
     * @param  int  $index  indeks cart untuk pesan validasi
     *
     * @throws ValidationException
     *
     * @return array<string, mixed>
     */
    private function computeCartItem(array $item, Product $product, int $index): array
    {
        // Tipe item diambil dari KATEGORI produk DB (bukan dari request),
        // sehingga manipulasi `type` dari frontend tidak berpengaruh.
        $itemType = $product->category->type;

        $isAreaBased = AreaPricingService::isAreaBased($product);

        // Untuk produk area, quantity disimpan sebagai jumlah pcs, sehingga
        // snapshot `unit` = pcs (bukan meter). Satuan pricing disimpan di metadata.
        $unit = $isAreaBased ? 'pcs' : ($product->unit ?: 'pcs');
        $quantity = (float) ($item['quantity'] ?? 0);

        $metadata = [
            'detail'    => $item['detail'] ?? '',
            'admin_fee' => $item['admin_fee'] ?? 0,
            'nominal'   => $item['nominal'] ?? null,
        ];

        if ($isAreaBased) {
            return $this->computeDbAreaItem($item, $product, $index, $itemType, $unit, $metadata);
        }

        // ---- Non-area (per piece / lembar / rim) — behavior lama dipertahankan ----
        $basePrice = $product->base_price;

        if ($itemType === 'ppob') {
            // Modal PPOB = nominal yang dikirim ke pelanggan/distributor
            // Keuntungan = admin_fee = total_harga - nominal
            $basePrice = $item['nominal'] ?? 0;
        } elseif ($itemType === 'jasa' && $product->base_price <= 0) {
            // Estimasi biaya vendor untuk jasa non-area tanpa HPP master
            $basePrice = $item['price'] / 1.3;
        }

        $subtotalBase = $basePrice * $quantity;
        $subtotalPrice = $item['price'] * $quantity;
        $profit = $subtotalPrice - $subtotalBase;

        return $this->buildComputedItem(
            $item,
            $product,
            $itemType,
            $unit,
            $quantity,
            $basePrice,
            $item['price'],
            $subtotalBase,
            $subtotalPrice,
            $profit,
            $metadata,
            false
        );
    }

    /**
     * Item area dari DB: SEMUA nilai finansial dihitung ulang dari master
     * product. Nilai dari request (area_per_piece, selling_rate, base_rate,
     * price) DIABAIKAN. HPP rate wajib > 0 untuk produk area DB.
     *
     * @return array<string, mixed>
     */
    private function computeDbAreaItem(array $item, Product $product, int $index, string $itemType, string $unit, array $metadata): array
    {
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

        if ((float) $product->base_price <= 0) {
            throw ValidationException::withMessages([
                "cart.{$index}.base_rate" => "HPP/modal per m² untuk produk {$product->name} belum diatur. Isi harga modal terlebih dahulu sebelum transaksi.",
            ]);
        }

        // ---- Server-authoritative calculation ----
        $areaPerPiece = AreaPricingService::areaPerPiece($length, $width);
        $sellingRate = (float) $product->selling_price;
        $baseRate = (float) $product->base_price;

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
            'pricing_unit'   => 'm2',
            'selling_rate'   => $sellingRate,
            'base_rate'      => $baseRate,
        ]);

        return $this->buildComputedItem(
            $item,
            $product,
            $itemType,
            $unit,
            $quantity,
            $basePricePerPiece,
            $sellingPricePerPiece,
            $subtotalBase,
            $subtotalPrice,
            $profit,
            $metadata,
            true
        );
    }

    /**
     * Bentuk seragam hasil kalkulasi item untuk persistensi.
     *
     * @return array<string, mixed>
     */
    private function buildComputedItem(array $item, ?Product $product, string $itemType, string $unit, float $quantity, float $basePrice, float $sellingPrice, float $subtotalBase, float $subtotalPrice, float $profit, array $metadata, bool $isAreaBased): array
    {
        return [
            'product'         => $product,
            'item_name'       => $item['name'] ?? '',
            'item_type'       => $itemType,
            'unit'            => $unit,
            'quantity'        => $quantity,
            'base_price'      => $basePrice,
            'selling_price'   => $sellingPrice,
            'subtotal_base'   => $subtotalBase,
            'subtotal_price'  => $subtotalPrice,
            'profit'          => $profit,
            'metadata'        => $metadata,
            'is_area_based'   => $isAreaBased,
            'print_vendor_id' => $item['print_vendor_id'] ?? null,
            'digital_type'    => $item['digital_type'] ?? 'phone',
            'account_number'  => $item['account_number'] ?? null,
            'account_name'    => $item['account_name'] ?? null,
            'nominal'         => $item['nominal'] ?? $item['price'] ?? 0,
        ];
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
