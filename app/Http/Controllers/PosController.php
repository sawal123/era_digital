<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDigitalAccount;
use App\Models\PaymentMethod;
use App\Models\PrintVendor;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StoreProfile;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\AreaPricingService;
use App\Services\CapitalLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

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
        $ledger = app(CapitalLedgerService::class);
        $digitalAccount = $ledger->ensureAccounts()->firstWhere('slug', CapitalLedgerService::DIGITAL_ACCOUNT_SLUG);
        if ($digitalAccount && abs((float) $digitalAccount->current_balance - (float) $profile->saldo_digital) >= 0.01) {
            $ledger->syncDigitalBalance(
                (float) $digitalAccount->current_balance,
                (float) $profile->saldo_digital,
                now()->toDateString(),
            );
        }

        $digitalCostSuggestions = TransactionItem::query()
            ->where('type', 'ppob')
            ->whereNotNull('provider_cost')
            ->latest('id')
            ->get(['product_id', 'provider_cost', 'metadata'])
            ->map(fn (TransactionItem $item) => [
                'product_id' => $item->product_id,
                'nominal' => data_get($item->metadata, 'nominal'),
                'provider_cost' => (float) $item->provider_cost,
            ])
            ->filter(fn (array $suggestion) => $suggestion['product_id'] && $suggestion['nominal'] !== null)
            ->unique(fn (array $suggestion) => $suggestion['product_id'].'|'.number_format((float) $suggestion['nominal'], 2, '.', ''))
            ->values();

        return Inertia::render('POS/Index', [
            'products' => $products,
            'customers' => $customers,
            'paymentMethods' => $paymentMethods,
            'printVendors' => $printVendors,
            'profile' => $profile,
            'digitalCostSuggestions' => $digitalCostSuggestions,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cart' => 'required|array|min:1',
            'cart.*.id' => 'required|integer|exists:products,id',
            'cart.*.quantity' => 'required|numeric|min:0.1',
            'cart.*.price' => 'required|numeric|min:0',
            'cart.*.provider_cost' => 'nullable|numeric|gt:0',
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

        StoreProfile::firstOrCreate([], [
            'store_name' => 'Era Digital',
            'address' => 'Jl. Raya Utama No. 45, Kebayoran Baru, Jakarta Selatan',
            'phone' => '0812-3456-7890',
            'signature_path' => null,
            'saldo_digital' => 350000.00,
        ]);
        app(CapitalLedgerService::class)->ensureAccounts();

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
                    $ppobAccountNumber = CustomerDigitalAccount::normalizeNumber($item['account_number'] ?? null);
                    $ppobAccountName = $item['account_name'] ?? null;
                    $ppobDigitalType = $item['digital_type'] ?? 'phone';
                    break;
                }
            }

            if (! $customerId && $hasPpob && $ppobAccountNumber) {
                $linkedDigitalAccount = CustomerDigitalAccount::query()
                    ->where('type', $ppobDigitalType)
                    ->where('account_number', $ppobAccountNumber)
                    ->first();

                $customerId = $linkedDigitalAccount?->customer_id;
            }

            if ($hasPpob && ! $customerId && ! $request->invoice_customer_name && $ppobAccountNumber && $ppobAccountName) {
                // Reuse customer berdasarkan nama sebelum membuat record baru.
                $existingCustomer = Customer::query()
                    ->where('name_key', Customer::normalizeName($ppobAccountName))
                    ->orderBy('id')
                    ->first();

                if (! $existingCustomer) {
                    $existingCustomer = Customer::create([
                        'name' => $ppobAccountName,
                        'customer_type' => $ppobDigitalType === 'token' ? 'token' : 'operator',
                        'phone' => null,
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

            foreach ($computedItems as $itemIndex => $computedItem) {
                $transactionItem = TransactionItem::create([
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
                    'provider_cost' => $computedItem['provider_cost'],
                    'subtotal_price' => $computedItem['subtotal_price'],
                    'profit' => $computedItem['profit'],
                    'service_status' => $computedItem['item_type'] === 'jasa' ? 'menunggu_file' : 'none',
                    'metadata' => $computedItem['metadata'],
                ]);

                if ($computedItem['item_type'] === 'ppob') {
                    $digitalType = $computedItem['digital_type'];
                    $accountNumber = CustomerDigitalAccount::normalizeNumber($computedItem['account_number']);
                    $accountName = $computedItem['account_name'];
                    $providerCostVal = $computedItem['subtotal_base'];

                    if ($accountNumber && $accountName) {
                        $digitalAccount = CustomerDigitalAccount::firstOrCreate([
                            'type' => $digitalType,
                            'account_number' => $accountNumber,
                        ], [
                            'customer_id' => $customerId,
                            'account_name' => $accountName,
                        ]);

                        if (! $digitalAccount->customer_id && $customerId) {
                            $digitalAccount->update(['customer_id' => $customerId]);
                        }
                    }

                    // Saldo provider berkurang sesuai biaya aktual Mitra Shopee,
                    // bukan nominal pulsa yang diterima customer.
                    $profile = StoreProfile::query()->lockForUpdate()->first();
                    if ($profile) {
                        $saldoDigital = (float) $profile->saldo_digital;

                        if ($saldoDigital <= 0) {
                            throw ValidationException::withMessages([
                                "cart.{$itemIndex}.provider_cost" => 'Saldo digital toko habis. Isi kembali saldo sebelum menjual layanan digital.',
                            ]);
                        }

                        if ($saldoDigital + 0.00001 < $providerCostVal) {
                            throw ValidationException::withMessages([
                                "cart.{$itemIndex}.provider_cost" => 'Saldo digital provider tidak mencukupi untuk transaksi ini.',
                            ]);
                        }

                        $profile->decrement('saldo_digital', $providerCostVal);
                    }
                }

                if ($computedItem['product'] && $computedItem['product']->category->type === 'fisik') {
                    $stockProduct = Product::query()
                        ->whereKey($computedItem['product']->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $quantity = (float) $computedItem['quantity'];

                    if ((float) ($stockProduct->stock ?? 0) + 0.00001 < $quantity) {
                        throw ValidationException::withMessages([
                            "cart.{$itemIndex}.quantity" => "Stok {$stockProduct->name} tidak mencukupi.",
                        ]);
                    }

                    $stockProduct->decrement('stock', $quantity);
                    StockMovement::create([
                        'product_id' => $stockProduct->id,
                        'type' => 'out',
                        'quantity' => $quantity,
                        'reference_type' => 'transaction',
                        'reference_id' => $transaction->id,
                        'note' => 'Penjualan: '.$transaction->invoice_number,
                    ]);
                }

                app(CapitalLedgerService::class)->syncTransactionItemUsage($transactionItem);
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
                'recent_transaction' => $freshTransaction,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Gagal memproses transaksi: '.$e->getMessage());
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
     * @return array<string, mixed>
     *
     * @throws ValidationException
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
            'detail' => $item['detail'] ?? '',
            'note' => $item['note'] ?? '',
            'admin_fee' => $item['admin_fee'] ?? 0,
            'nominal' => $item['nominal'] ?? null,
        ];

        if ($isAreaBased) {
            return $this->computeDbAreaItem($item, $product, $index, $itemType, $unit, $metadata);
        }

        // ---- Non-area (per piece / lembar / rim) — behavior lama dipertahankan ----
        $basePrice = $product->base_price;

        $providerCost = null;
        if ($itemType === 'ppob') {
            // Nominal adalah nilai pulsa customer. HPP PPOB adalah biaya
            // aktual yang dipotong provider dan dapat berubah setiap saat.
            $providerCost = $this->resolveProviderCost($item, $product);

            if ($providerCost <= 0) {
                throw ValidationException::withMessages([
                    "cart.{$index}.provider_cost" => 'Modal aktual provider harus lebih besar dari Rp0.',
                ]);
            }

            $basePrice = $providerCost;
            $metadata['provider_cost'] = $providerCost;
        } elseif ($itemType === 'jasa' && $product->base_price <= 0) {
            // Estimasi biaya vendor untuk jasa non-area tanpa HPP master
            $basePrice = $item['price'] / 1.3;
        }

        $sellingPrice = $itemType === 'ppob'
            ? (float) $item['price']
            : (float) $product->selling_price;
        $subtotalBase = $basePrice * $quantity;
        $subtotalPrice = $sellingPrice * $quantity;
        $profit = $subtotalPrice - $subtotalBase;

        $computedItem = $this->buildComputedItem(
            $item,
            $product,
            $itemType,
            $unit,
            $quantity,
            $basePrice,
            $sellingPrice,
            $subtotalBase,
            $subtotalPrice,
            $profit,
            $metadata,
            false
        );

        $computedItem['provider_cost'] = $providerCost;

        return $computedItem;
    }

    private function resolveProviderCost(array $item, Product $product): float
    {
        $requestedCost = $item['provider_cost'] ?? null;

        if ($requestedCost !== null && $requestedCost !== '') {
            return round(max(0, (float) $requestedCost), 2);
        }

        $nominal = round((float) ($item['nominal'] ?? 0), 2);
        $latestMatchingCost = TransactionItem::query()
            ->where('product_id', $product->id)
            ->where('type', 'ppob')
            ->whereNotNull('provider_cost')
            ->latest('id')
            ->get(['provider_cost', 'metadata'])
            ->first(function (TransactionItem $transactionItem) use ($nominal) {
                return abs((float) data_get($transactionItem->metadata, 'nominal') - $nominal) < 0.01;
            });

        return round((float) ($latestMatchingCost?->provider_cost ?? $nominal), 2);
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
            'length' => $length,
            'width' => $width,
            'area_per_piece' => $areaPerPiece,
            'total_area' => AreaPricingService::totalArea($areaPerPiece, $quantity),
            'pricing_unit' => 'm2',
            'selling_rate' => $sellingRate,
            'base_rate' => $baseRate,
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
            'product' => $product,
            'item_name' => $item['name'] ?? '',
            'item_type' => $itemType,
            'unit' => $unit,
            'quantity' => $quantity,
            'base_price' => $basePrice,
            'selling_price' => $sellingPrice,
            'subtotal_base' => $subtotalBase,
            'provider_cost' => null,
            'subtotal_price' => $subtotalPrice,
            'profit' => $profit,
            'metadata' => $metadata,
            'is_area_based' => $isAreaBased,
            'print_vendor_id' => $item['print_vendor_id'] ?? null,
            'digital_type' => $item['digital_type'] ?? 'phone',
            'account_number' => $item['account_number'] ?? null,
            'account_name' => $item['account_name'] ?? null,
            'nominal' => $item['nominal'] ?? $item['price'] ?? 0,
            'provider_cost' => $item['provider_cost'] ?? null,
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

        $accounts = CustomerDigitalAccount::where('type', $type)
            ->with('customer:id,name,phone')
            ->where(function ($builder) use ($query) {
                $builder->where('account_number', 'like', "%{$query}%")
                    ->orWhere('account_name', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

        return response()->json($accounts);
    }
}
