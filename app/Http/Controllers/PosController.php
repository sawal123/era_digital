<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Customer;
use App\Models\StoreProfile;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->where('is_active', true)->get();
        $customers = Customer::orderBy('name')->get();
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
            'total' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'jumlah_dibayar' => 'required|numeric|min:0',
            'customer_id' => 'nullable|exists:customers,id',
            'keterangan' => 'nullable|string',
        ]);

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

            if ($hasPpob && !$customerId && $ppobAccountNumber && $ppobAccountName) {
                // Auto-create customer using PPOB account details
                $existingCustomer = Customer::where('phone', $ppobAccountNumber)->first();
                if (!$existingCustomer) {
                    $existingCustomer = Customer::create([
                        'name' => $ppobAccountName,
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
            }

            $totalPrice = $request->total;
            $jumlahDibayar = $request->jumlah_dibayar;
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
                'payment_method' => $request->payment_method,
                'payment_status' => $paymentStatus,
                'status_bayar' => $statusBayar,
                'jumlah_dibayar' => $jumlahDibayar,
                'sisa_tagihan' => $sisaTagihan,
                'keterangan' => $request->keterangan,
            ]);

            $totalBasePrice = 0;
            $totalProfit = 0;

            foreach ($request->cart as $item) {
                $product = null;
                if (is_numeric($item['id']) && $item['id'] < 10000000000) {
                     $product = Product::find($item['id']);
                }

                $basePrice = $product ? $product->base_price : ($item['base_price'] ?? 0);
                
                if (isset($item['type']) && $item['type'] === 'cetak') {
                    // Modal cetak = 77% dari harga jual (estimasi biaya vendor) jika tidak ada produk DB
                    if (!$product || $product->base_price <= 0) {
                        $basePrice = $item['price'] / 1.3;
                    }
                } elseif (isset($item['type']) && ($item['type'] === 'digital' || $item['type'] === 'ppob')) {
                    // Modal PPOB = nominal yang dikirim ke pelanggan/distributor
                    // Keuntungan = admin_fee = total_harga - nominal
                    $basePrice = $item['nominal'] ?? 0;
                }

                $subtotalBase = $basePrice * $item['quantity'];
                $subtotalPrice = $item['price'] * $item['quantity']; // price = nominal + admin_fee
                $profit = $subtotalPrice - $subtotalBase; // profit = admin_fee ✓

                $itemType = $item['type'] ?? 'fisik';
                if ($itemType === 'cetak' || $itemType === 'fotokopi') {
                    $itemType = 'jasa';
                } elseif ($itemType === 'digital') {
                    $itemType = 'ppob';
                }

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product ? $product->id : null,
                    'item_name' => $item['name'],
                    'type' => $itemType,
                    'unit' => $product ? $product->unit : 'pcs',
                    'quantity' => $item['quantity'],
                    'base_price' => $basePrice,
                    'selling_price' => $item['price'],
                    'subtotal_base' => $subtotalBase,
                    'subtotal_price' => $subtotalPrice,
                    'profit' => $profit,
                    'service_status' => $itemType === 'jasa' ? 'menunggu_file' : 'none',
                    'metadata' => [
                        'detail'    => $item['detail'] ?? '',
                        'admin_fee' => $item['admin_fee'] ?? 0,
                        'nominal'   => $item['nominal'] ?? null,
                    ]
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
                $totalProfit += $profit;
                
                if ($product && $product->category->type === 'fisik') {
                    $product->decrement('stock', $item['quantity']);
                }
            }

            $transaction->update([
                'total_base_price' => $totalBasePrice,
                'total_profit' => $totalProfit,
            ]);

            // Record payment history for initial paid amount
            $transaction->paymentHistories()->create([
                'jumlah_bayar' => $jumlahDibayar,
                'tanggal_bayar' => now(),
                'metode_bayar' => $request->payment_method ?? 'cash',
                'keterangan' => $statusBayar === 'piutang' ? 'Piutang Awal' : ($statusBayar === 'dp' ? 'Uang Muka / DP Awal' : 'Pembayaran Lunas'),
            ]);

            // Refresh with relationships
            $freshTransaction = Transaction::with(['items', 'customer', 'paymentHistories'])->find($transaction->id);

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
        $transaction = Transaction::with(['items.product', 'cashier'])
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
         $number = $request->query('number');
         $type = $request->query('type'); // 'token' or 'phone'

         $accounts = \App\Models\CustomerDigitalAccount::where('type', $type)
             ->where('account_number', 'like', "%{$number}%")
             ->limit(5)
             ->get();

         return response()->json($accounts);
     }
}
