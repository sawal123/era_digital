<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'POS Kasir',
                href: '/pos',
            },
        ],
    },
});

const props = defineProps({
    products: Array,
    customers: Array,
    paymentMethods: Array,
    printVendors: Array,
    profile: Object,
});

// ---------- CORE CART VARIABLES ----------
const cart = ref([]);
const isMobileCartOpen = ref(false);
const duplicateCetakDialogOpen = ref(false);
const pendingCetakItem = ref(null);
const duplicateCetakIndex = ref(-1);

// Qty Numeric Keypad State
const qtyKeypadOpen = ref(false);
const qtyKeypadItem = ref(null);
const qtyKeypadInput = ref('');
const qtyKeypadFresh = ref(true);

const normalizeQuantity = (value) =>
    Math.round((Number(value) + Number.EPSILON) * 100) / 100;
const formatQuantity = (value) =>
    new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(normalizeQuantity(value));

// ---------- AREA-BASED (per m²) PRINTING HELPERS ----------
// Area & harga dibulatkan dengan cara yang SAMA di frontend dan backend
// (AreaPricingService PHP), agar estimasi == nilai snapshot transaksi.
const roundMoney = (value) =>
    Math.round((Number(value) + Number.EPSILON) * 100) / 100;
const roundArea = (value) =>
    Math.round((Number(value) + Number.EPSILON) * 100) / 100;
const isAreaBasedProduct = (product) => product && product.unit === 'meter';
const areaPerPiece = (length, width) =>
    roundArea(Number(length) * Number(width));
const pricePerPiece = (rate, area) => roundMoney(Number(rate) * Number(area));
const cartItemCount = computed(() => {
    return normalizeQuantity(
        cart.value.reduce((sum, item) => sum + item.quantity, 0),
    );
});
const cartTotal = computed(() => {
    return cart.value.reduce((sum, i) => sum + i.price * i.quantity, 0);
});

// ---------- SIDEBAR ZEN MODE & HELPERS ----------
const sidebarVisible = ref(true);
const toggleZenMode = () => {
    sidebarVisible.value = !sidebarVisible.value;
};

// Help Dialog State
const helpDialogOpen = ref(false);

// Realtime Clock State
const currentTime = ref('');
let timer = null;
const updateTime = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

// Keyboard Shortcuts & Keypad Listener
const handleKeyDown = (e) => {
    if (qtyKeypadOpen.value) {
        if (e.key >= '0' && e.key <= '9') {
            e.preventDefault();
            appendQtyDigit(e.key);
        } else if (e.key === 'Backspace') {
            e.preventDefault();
            backspaceQtyInput();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closeQtyKeypad();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            saveQtyKeypad();
        } else if (e.key === 'Delete' || e.key === 'c' || e.key === 'C') {
            e.preventDefault();
            clearQtyInput();
        }

        return;
    }

    if (e.key === 'F2') {
        e.preventDefault();
        prosesBayar();
    } else if (e.key === 'F4') {
        e.preventDefault();
        const searchInput = document.getElementById('pos-search-input');

        if (searchInput) {
            activeTab.value = 0; // Beralih ke tab fisik jika ingin cari
            searchInput.focus();
        }
    } else if (e.key === 'Escape') {
        posSearchQuery.value = '';
    }
};

const dispatchCartSync = () => {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(
            new CustomEvent('cart-updated', {
                detail: {
                    count: cartItemCount.value,
                    total: cartTotal.value,
                    hasItems: cart.value.length > 0,
                },
            }),
        );
    }
};

watch(
    cart,
    () => {
        dispatchCartSync();
    },
    { deep: true },
);

let handleOpenMobileCart;
let handleRequestCartSync;

const jasaDropdownOpen = ref(false);
const jasaSearchQuery = ref('');
const vendorDropdownOpen = ref(false);
const vendorSearchQuery = ref('');
const digitalDropdownOpen = ref(false);
const digitalSearchQuery = ref('');
const customerDropdownOpen = ref(false);
const customerSearchQuery = ref('');
const paymentMethodsOpen = ref(false);
const closeJasaDropdown = (e) => {
    if (
        jasaDropdownOpen.value &&
        !e.target.closest('.jasa-dropdown-container')
    ) {
        jasaDropdownOpen.value = false;
    }

    if (
        customerDropdownOpen.value &&
        !e.target.closest('.customer-dropdown-container')
    ) {
        customerDropdownOpen.value = false;
    }

    if (
        vendorDropdownOpen.value &&
        !e.target.closest('.vendor-dropdown-container')
    ) {
        vendorDropdownOpen.value = false;
    }

    if (
        digitalDropdownOpen.value &&
        !e.target.closest('.digital-dropdown-container')
    ) {
        digitalDropdownOpen.value = false;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
    document.addEventListener('click', closeJasaDropdown);
    updateTime();
    timer = setInterval(updateTime, 1000);

    handleOpenMobileCart = () => {
        isMobileCartOpen.value = true;
    };
    handleRequestCartSync = () => {
        dispatchCartSync();
    };
    window.addEventListener('open-mobile-cart', handleOpenMobileCart);
    window.addEventListener('request-cart-sync', handleRequestCartSync);
    dispatchCartSync();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    document.removeEventListener('click', closeJasaDropdown);

    if (timer) {
        clearInterval(timer);
    }

    if (autocompleteTimer) {
        clearTimeout(autocompleteTimer);
    }

    if (handleOpenMobileCart) {
        window.removeEventListener('open-mobile-cart', handleOpenMobileCart);
    }

    if (handleRequestCartSync) {
        window.removeEventListener('request-cart-sync', handleRequestCartSync);
    }
});

// TAB LOGIC (0 = fisik, 1 = cetak, 2 = digital)
const activeTab = ref(0);
const isTabLoading = ref(false);
const handleTabChange = (idx) => {
    if (activeTab.value === idx) {
        return;
    }

    isTabLoading.value = true;
    setTimeout(() => {
        activeTab.value = idx;
        isTabLoading.value = false;
    }, 250);
};

const tabs = [
    { name: 'Barang Fisik & Fotokopi', icon: 'fas fa-box-open' },
    { name: 'Jasa Cetak (Vendor)', icon: 'fas fa-print' },
    { name: 'Saldo Digital', icon: 'fas fa-mobile-alt' },
];

const themeColor = computed(() => {
    if (activeTab.value === 0) {
        return '#10b981';
    } // Hijau segar

    if (activeTab.value === 1) {
        return '#f97316';
    } // Oranye enerjik

    return '#3b82f6'; // Biru elektrik
});

const selectJasaProduct = (product) => {
    activeJasaProductId.value = product.id;
    jasaDropdownOpen.value = false;
    jasaSearchQuery.value = '';
};

const getInitials = (name) => {
    if (!name) {
        return '??';
    }

    const words = name.trim().split(/\s+/);

    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase();
    }

    return words[0].substring(0, 2).toUpperCase();
};

// ---------- TAB FISIK & SEARCH ----------
const posSearchQuery = ref('');

const fisikItems = computed(() => {
    return props.products.filter(
        (p) =>
            p.category && p.category.type === 'fisik' && p.name !== 'Fotokopi',
    );
});

const filteredFisikItems = computed(() => {
    return fisikItems.value.filter((item) => {
        return (
            item.name
                .toLowerCase()
                .includes(posSearchQuery.value.toLowerCase()) ||
            (item.sku &&
                item.sku
                    .toLowerCase()
                    .includes(posSearchQuery.value.toLowerCase()))
        );
    });
});

// ---------- LAYANAN CETAK & FOTOKOPI INSTAN ----------
const instanServices = computed(() => {
    const skus = ['JSA-FTK-01', 'JSA-FTK-02', 'JSA-PRN-01', 'JSA-PRN-02'];
    const dbServices = props.products
        .filter((p) => skus.includes(p.sku))
        .map((p) => ({
            id: p.id,
            sku: p.sku,
            name: p.name,
            price: parseFloat(p.selling_price),
        }));

    return dbServices.sort((a, b) => skus.indexOf(a.sku) - skus.indexOf(b.sku));
});

const selectedServiceId = ref(null);
const instanQty = ref(1);

watch(
    instanServices,
    (newList) => {
        if (newList.length > 0 && selectedServiceId.value === null) {
            selectedServiceId.value = newList[0].id;
        }
    },
    { immediate: true },
);

const selectedService = computed(() => {
    const list = instanServices.value;

    return (
        list.find((s) => s.id === selectedServiceId.value) ||
        list[0] || { id: null, name: '', price: 0 }
    );
});

const addInstanToCart = () => {
    let qty = instanQty.value;

    if (qty < 1) {
        qty = 1;
    }

    const service = selectedService.value;

    if (!service || !service.id) {
        return;
    }

    addToCart({
        id: service.id,
        name: service.name,
        price: service.price,
        quantity: qty,
        type: 'fotokopi',
        detail: `${qty} lembar x Rp ${formatRupiah(service.price)}`,
    });
    instanQty.value = 1;
};

// ---------- TAB JASA CETAK (Vendor) ----------
const jasaCetakItems = computed(() => {
    const instantSkus = [
        'JSA-FTK-01',
        'JSA-FTK-02',
        'JSA-PRN-01',
        'JSA-PRN-02',
    ];

    return props.products.filter(
        (p) =>
            p.category &&
            p.category.type === 'jasa' &&
            !instantSkus.includes(p.sku),
    );
});

const filteredJasaCetakItems = computed(() => {
    const query = jasaSearchQuery.value.toLowerCase().trim();

    if (!query) {
        return jasaCetakItems.value;
    }

    return jasaCetakItems.value.filter(
        (product) =>
            product.name.toLowerCase().includes(query) ||
            (product.sku && product.sku.toLowerCase().includes(query)),
    );
});

const activeJasaProductId = ref('');

watch(
    jasaCetakItems,
    (newItems) => {
        if (newItems.length > 0 && !activeJasaProductId.value) {
            activeJasaProductId.value = newItems[0].id;
        }
    },
    { immediate: true },
);

const selectedJasaProduct = computed(() => {
    return jasaCetakItems.value.find((p) => p.id === activeJasaProductId.value);
});

const cetakQty = ref(1);
const cetakPanjang = ref(1);
const cetakLebar = ref(1);
const cetakVendorId = ref('');

watch(
    () => props.printVendors,
    (vendors) => {
        if (vendors?.length > 0 && !cetakVendorId.value) {
            cetakVendorId.value = vendors[0].id;
        }
    },
    { immediate: true },
);

const selectedPrintVendor = computed(() => {
    return (
        props.printVendors?.find(
            (vendor) => String(vendor.id) === String(cetakVendorId.value),
        ) || null
    );
});

const filteredPrintVendors = computed(() => {
    const query = vendorSearchQuery.value.toLowerCase().trim();

    if (!query) {
        return props.printVendors || [];
    }

    return props.printVendors.filter(
        (vendor) =>
            vendor.name.toLowerCase().includes(query) ||
            (vendor.phone && vendor.phone.toLowerCase().includes(query)) ||
            (vendor.address && vendor.address.toLowerCase().includes(query)),
    );
});

const selectPrintVendor = (vendor) => {
    cetakVendorId.value = vendor?.id || '';
    vendorDropdownOpen.value = false;
    vendorSearchQuery.value = '';
};

const hargaJasaCetak = computed(() => {
    const prod = selectedJasaProduct.value;

    if (!prod) {
        return 0;
    }

    if (isAreaBasedProduct(prod)) {
        const p = Number(cetakPanjang.value);
        const l = Number(cetakLebar.value);
        const qty = Number(cetakQty.value);

        // Estimasi menampilkan 0 selama input belum lengkap/valid
        // (jangan paksa nilai invalid menjadi 1).
        if (
            !Number.isFinite(p) ||
            p <= 0 ||
            !Number.isFinite(l) ||
            l <= 0 ||
            !Number.isFinite(qty) ||
            qty < 1
        ) {
            return 0;
        }

        const ap = areaPerPiece(p, l);

        // harga per pcs = rate × luas; total = harga per pcs × jumlah pcs
        return roundMoney(
            pricePerPiece(parseFloat(prod.selling_price), ap) * qty,
        );
    }

    const qty = Number(cetakQty.value);

    if (!Number.isFinite(qty) || qty < 1) {
        return 0;
    }

    return parseFloat(prod.selling_price) * qty;
});

const addCetakToCart = () => {
    const prod = selectedJasaProduct.value;

    if (!prod) {
        return;
    }

    let quantity = 1;
    let detail = '';

    if (isAreaBasedProduct(prod)) {
        const p = Number(cetakPanjang.value);
        const l = Number(cetakLebar.value);
        const qty = Number(cetakQty.value);

        // Validasi eksplisit — bukan parseFloat/parseInt || 1
        if (!Number.isFinite(p) || p <= 0) {
            showNotification(
                'Panjang harus lebih besar dari 0 (nol).',
                'Ukuran Tidak Valid',
                'warning',
            );

            return;
        }

        if (!Number.isFinite(l) || l <= 0) {
            showNotification(
                'Lebar harus lebih besar dari 0 (nol).',
                'Ukuran Tidak Valid',
                'warning',
            );

            return;
        }

        if (!Number.isInteger(qty) || qty < 1) {
            showNotification(
                'Jumlah pcs harus bilangan bulat minimal 1.',
                'Jumlah Tidak Valid',
                'warning',
            );

            return;
        }

        const ap = areaPerPiece(p, l); // luas per pcs (m²)
        const rate = parseFloat(prod.selling_price) || 0; // harga jual per m²
        const baseRate = parseFloat(prod.base_price) || 0; // HPP per m²
        const perPiece = pricePerPiece(rate, ap); // harga efektif per pcs

        quantity = qty; // jumlah pcs — BUKAN luas
        detail = `Ukuran: ${formatQuantity(p)} x ${formatQuantity(l)} m`;

        addToCart({
            id: prod.id,
            name: prod.name,
            price: perPiece,
            quantity: quantity,
            type: 'cetak',
            detail: detail,
            note: '',
            print_vendor_id: cetakVendorId.value || null,
            is_area_based: true,
            length: p,
            width: l,
            area_per_piece: ap,
            total_area: roundArea(ap * qty),
            selling_rate: rate,
            base_rate: baseRate,
        });
    } else {
        const qty = Number(cetakQty.value);

        if (!Number.isFinite(qty) || qty < 1) {
            showNotification(
                'Jumlah harus minimal 1.',
                'Jumlah Tidak Valid',
                'warning',
            );

            return;
        }

        quantity = qty;

        addToCart({
            id: prod.id,
            name: prod.name,
            price: parseFloat(prod.selling_price),
            quantity: quantity,
            type: 'cetak',
            detail: detail,
            print_vendor_id: cetakVendorId.value || null,
        });
    }
};

// ---------- TAB SALDO DIGITAL ----------
const saldoDigital = ref(
    props.profile ? parseFloat(props.profile.saldo_digital) : 350000,
);
const digitalItems = computed(() => {
    return props.products.filter(
        (p) => p.category && p.category.type === 'ppob',
    );
});

const filteredDigitalItems = computed(() => {
    const query = digitalSearchQuery.value.toLowerCase().trim();

    if (!query) {
        return digitalItems.value;
    }

    return digitalItems.value.filter(
        (product) =>
            product.name.toLowerCase().includes(query) ||
            (product.sku && product.sku.toLowerCase().includes(query)),
    );
});

const selectedLayanan = ref(null);
const nomorPelanggan = ref('');
const namaPelanggan = ref('');
const nominalManual = ref('');

const autocompleteResults = ref([]);
const showAutocomplete = ref(false);
const activeAutocompleteField = ref('');
let autocompleteTimer = null;

watch(
    digitalItems,
    (newItems) => {
        if (newItems.length > 0 && !selectedLayanan.value) {
            selectedLayanan.value = newItems[0];
        }
    },
    { immediate: true },
);

const totalBiaya = computed(() => {
    const admin = selectedLayanan.value ? selectedLayanan.value.admin_fee : 0;

    return Number(nominalManual.value) + Number(admin);
});

// Autocomplete and auto-fill feature
const searchDigitalAccounts = (value, field) => {
    activeAutocompleteField.value = field;
    clearTimeout(autocompleteTimer);

    const query = String(value || '').trim();

    if (query.length < 2) {
        autocompleteResults.value = [];
        showAutocomplete.value = false;

        return;
    }

    autocompleteTimer = setTimeout(async () => {
        const type =
            selectedLayanan.value?.name?.toLowerCase().includes('token') ||
            selectedLayanan.value?.name?.toLowerCase().includes('listrik') ||
            selectedLayanan.value?.sku?.toLowerCase().includes('tkn')
                ? 'token'
                : 'phone';

        try {
            const response = await axios.get('/api/digital-accounts/search', {
                params: {
                    query,
                    type,
                },
            });
            autocompleteResults.value = response.data;
            showAutocomplete.value = response.data.length > 0;
        } catch (error) {
            console.error(
                'Error fetching digital accounts autocomplete:',
                error,
            );
            autocompleteResults.value = [];
            showAutocomplete.value = false;
        }
    }, 250);
};

const hideDigitalAutocomplete = () => {
    setTimeout(() => {
        showAutocomplete.value = false;
    }, 150);
};

const selectAccount = (account) => {
    nomorPelanggan.value = account.account_number;
    namaPelanggan.value = account.account_name;
    showAutocomplete.value = false;
    activeAutocompleteField.value = '';
};

const selectDigitalService = (service) => {
    selectedLayanan.value = service;
    digitalDropdownOpen.value = false;
    digitalSearchQuery.value = '';
    autocompleteResults.value = [];
    showAutocomplete.value = false;
};

const beliSaldoDigital = () => {
    const prod = selectedLayanan.value;

    if (!prod) {
        showNotification(
            'Silakan pilih produk digital yang ingin Anda beli terlebih dahulu sebelum melanjutkan.',
            'Pilih Produk Digital',
            'warning',
        );

        return;
    }

    const nomor = nomorPelanggan.value.trim();

    if (!nomor) {
        showNotification(
            'Masukkan nomor meteran listrik atau nomor HP tujuan pembeli agar transaksi valid.',
            'Nomor HP / ID Kosong',
            'warning',
        );

        return;
    }

    const nama = namaPelanggan.value.trim();

    if (!nama) {
        showNotification(
            'Masukkan nama pemilik akun digital untuk memvalidasi transaksi.',
            'Nama Akun Kosong',
            'warning',
        );

        return;
    }

    const nominal = parseFloat(nominalManual.value) || 0;

    if (nominal <= 0) {
        showNotification(
            'Masukkan nominal pembelian yang valid (harus lebih besar dari 0).',
            'Nominal Tidak Valid',
            'warning',
        );

        return;
    }

    const adminFee = Number(prod.admin_fee) || 0;
    const totalHarga = nominal + adminFee;

    if (saldoDigital.value < totalHarga) {
        showNotification(
            `Saldo digital toko Anda saat ini tidak cukup untuk melakukan transaksi nominal ini. Tersedia: Rp ${formatRupiah(saldoDigital.value)}`,
            'Saldo Toko Kurang',
            'warning',
        );

        return;
    }

    // Saldo toko berkurang HANYA sebesar nominal (modal yang disetor ke distributor)
    // Admin fee tetap menjadi keuntungan toko, tidak dikeluarkan dari saldo
    saldoDigital.value -= nominal;

    const digitalType =
        prod.name?.toLowerCase().includes('token') ||
        prod.name?.toLowerCase().includes('listrik') ||
        prod.sku?.toLowerCase().includes('tkn')
            ? 'token'
            : 'phone';

    addToCart({
        id: prod.id,
        name: `${prod.name} - Rp ${formatRupiah(nominal)}`,
        price: totalHarga,
        quantity: 1,
        type: 'digital',
        detail: `No: ${nomor} | Nama: ${nama} (Admin: Rp ${formatRupiah(adminFee)})`,
        digital_type: digitalType,
        account_number: nomor,
        account_name: nama,
        admin_fee: adminFee,
        nominal: nominal,
    });

    nomorPelanggan.value = '';
    namaPelanggan.value = '';
    nominalManual.value = '';
};

// ---------- GLOBAL FUNCTION ----------
const addToCart = (item) => {
    const cartItem = {
        _cartKey: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        id: item.id || Date.now(),
        name: item.name,
        price: item.price,
        quantity: normalizeQuantity(item.quantity || item.qty || 1),
        detail: item.detail || '',
        note: item.note || '',
        type: item.type,
        ...item,
    };

    if (cartItem.type === 'cetak') {
        const existingIndex = cart.value.findIndex(
            (existing) =>
                existing.id === cartItem.id && existing.type === 'cetak',
        );

        if (existingIndex !== -1) {
            pendingCetakItem.value = cartItem;
            duplicateCetakIndex.value = existingIndex;
            duplicateCetakDialogOpen.value = true;

            return;
        }
    }

    if (cartItem.type !== 'digital') {
        const existingItem = cart.value.find(
            (existing) =>
                existing.id === cartItem.id && existing.type === cartItem.type,
        );

        if (existingItem) {
            existingItem.quantity = normalizeQuantity(
                existingItem.quantity + cartItem.quantity,
            );

            if (existingItem.type === 'fotokopi') {
                existingItem.detail = `${formatQuantity(existingItem.quantity)} lembar x Rp ${formatRupiah(existingItem.price)}`;
            }

            return;
        }
    }

    cart.value.push(cartItem);
};

const overwriteDuplicateCetak = () => {
    if (!pendingCetakItem.value || duplicateCetakIndex.value === -1) {
        return;
    }

    const existingKey = cart.value[duplicateCetakIndex.value]._cartKey;
    cart.value[duplicateCetakIndex.value] = {
        ...pendingCetakItem.value,
        _cartKey: existingKey,
    };
    duplicateCetakDialogOpen.value = false;
    pendingCetakItem.value = null;
    duplicateCetakIndex.value = -1;
};

const keepDuplicateCetak = () => {
    if (!pendingCetakItem.value) {
        return;
    }

    cart.value.push(pendingCetakItem.value);
    duplicateCetakDialogOpen.value = false;
    pendingCetakItem.value = null;
    duplicateCetakIndex.value = -1;
};

const MAX_QTY = 999999;

const updateQty = (item, delta) => {
    const idx = cart.value.findIndex((i) => i._cartKey === item._cartKey);

    if (idx === -1) {
        return;
    }

    const currentItem = cart.value[idx];

    if (currentItem.type === 'digital' || currentItem.type === 'ppob') {
        showNotification(
            'Kuantitas untuk produk digital/PPOB dikunci 1 per transaksi.',
            'Produk Digital',
            'info',
        );

        return;
    }

    const newQty = normalizeQuantity(currentItem.quantity + delta);

    if (newQty < 1) {
        // Penurunan Qty via minus dihentikan pada 1. Item HANYA dihapus via tombol hapus (removeFromCart).
        return;
    }

    if (newQty > MAX_QTY) {
        showNotification(
            `Kuantitas maksimal per item adalah ${formatQuantity(MAX_QTY)}.`,
            'Batas Kuantitas',
            'warning',
        );

        return;
    }

    currentItem.quantity = newQty;

    if (currentItem.is_area_based) {
        currentItem.total_area = roundArea(
            (currentItem.area_per_piece || 0) * newQty,
        );
    }

    if (currentItem.type === 'fotokopi') {
        currentItem.detail = `${formatQuantity(newQty)} lembar x Rp ${formatRupiah(currentItem.price)}`;
    }
};

const openQtyKeypad = (item) => {
    if (item.type === 'digital' || item.type === 'ppob') {
        showNotification(
            'Kuantitas untuk produk digital/PPOB dikunci 1 per transaksi.',
            'Produk Digital',
            'info',
        );

        return;
    }

    qtyKeypadItem.value = item;
    qtyKeypadInput.value = String(normalizeQuantity(item.quantity));
    qtyKeypadFresh.value = true;
    qtyKeypadOpen.value = true;
};

const appendQtyDigit = (digit) => {
    if (qtyKeypadFresh.value) {
        qtyKeypadInput.value = digit;
        qtyKeypadFresh.value = false;

        return;
    }

    const nextVal =
        qtyKeypadInput.value === '0'
            ? digit
            : qtyKeypadInput.value + digit;

    if (Number(nextVal) > MAX_QTY) {
        showNotification(
            `Kuantitas maksimal per item adalah ${formatQuantity(MAX_QTY)}.`,
            'Batas Kuantitas',
            'warning',
        );

        return;
    }

    qtyKeypadInput.value = nextVal;
};

const clearQtyInput = () => {
    qtyKeypadInput.value = '0';
    qtyKeypadFresh.value = false;
};

const backspaceQtyInput = () => {
    qtyKeypadFresh.value = false;

    if (qtyKeypadInput.value.length <= 1) {
        qtyKeypadInput.value = '0';
    } else {
        qtyKeypadInput.value = qtyKeypadInput.value.slice(0, -1);
    }
};

const closeQtyKeypad = () => {
    qtyKeypadOpen.value = false;
    qtyKeypadItem.value = null;
    qtyKeypadInput.value = '';
    qtyKeypadFresh.value = true;
};

const saveQtyKeypad = () => {
    if (!qtyKeypadItem.value) {
        return;
    }

    const rawVal = String(qtyKeypadInput.value).trim();
    const parsedVal = Number(rawVal);

    if (
        !rawVal ||
        isNaN(parsedVal) ||
        !Number.isFinite(parsedVal) ||
        parsedVal <= 0
    ) {
        showNotification(
            'Jumlah quantity harus berupa angka valid dan lebih besar dari 0 (nol).',
            'Quantity Tidak Valid',
            'warning',
        );

        return;
    }

    if (parsedVal > MAX_QTY) {
        showNotification(
            `Jumlah quantity tidak boleh melebihi ${formatQuantity(MAX_QTY)}.`,
            'Quantity Melebihi Batas',
            'warning',
        );

        return;
    }

    const item = qtyKeypadItem.value;
    const isAreaBased = item.is_area_based;
    const isDigital = item.type === 'digital' || item.type === 'ppob';

    if (isDigital) {
        showNotification(
            'Kuantitas untuk produk digital/PPOB dikunci 1 per transaksi.',
            'Produk Digital',
            'warning',
        );
        closeQtyKeypad();

        return;
    }

    if (isAreaBased) {
        if (!Number.isInteger(parsedVal)) {
            showNotification(
                'Jumlah produk cetak berbasis luas (pcs) harus berupa bilangan bulat minimal 1.',
                'Quantity Tidak Valid',
                'warning',
            );

            return;
        }
    }

    const idx = cart.value.findIndex((i) => i._cartKey === item._cartKey);

    if (idx !== -1) {
        const newQty = normalizeQuantity(parsedVal);
        cart.value[idx].quantity = newQty;

        if (cart.value[idx].is_area_based) {
            cart.value[idx].total_area = roundArea(
                (cart.value[idx].area_per_piece || 0) * newQty,
            );
        }

        if (cart.value[idx].type === 'fotokopi') {
            cart.value[idx].detail =
                `${formatQuantity(newQty)} lembar x Rp ${formatRupiah(cart.value[idx].price)}`;
        }
    }

    closeQtyKeypad();
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

// State Custom Alert Dialog Modal
const alertOpen = ref(false);
const alertTitle = ref('');
const alertMessage = ref('');
const alertType = ref('info'); // 'success', 'warning', 'error', 'info'

const showNotification = (message, title = 'Notifikasi', type = 'info') => {
    alertTitle.value = title;
    alertMessage.value = message;
    alertType.value = type;
    alertOpen.value = true;
};

const isProcessing = ref(false);
const customerId = ref('');
const useOneTimeCustomer = ref(false);
const invoiceCustomerName = ref('');
const invoiceCustomerPhone = ref('');
const keterangan = ref('');
const paymentMethodId = ref('');
const cashModalOpen = ref(false);
const cashPaymentConfirmed = ref(false);
const cashKeypadFresh = ref(true);

const selectedPaymentMethod = computed(() => {
    return (
        props.paymentMethods?.find(
            (method) => String(method.id) === String(paymentMethodId.value),
        ) || null
    );
});

const isCashPayment = computed(
    () => selectedPaymentMethod.value?.is_cash === true,
);

const selectedCustomer = computed(() => {
    return (
        props.customers?.find(
            (customer) => String(customer.id) === String(customerId.value),
        ) || null
    );
});

const selectedCustomerLabel = computed(() => {
    if (selectedCustomer.value) {
        return `${selectedCustomer.value.name} (${selectedCustomer.value.phone || '-'})`;
    }

    if (useOneTimeCustomer.value) {
        return invoiceCustomerName.value || 'Customer Sekali Beli / Invoice';
    }

    return '-- Cash / Umum --';
});

const filteredCustomers = computed(() => {
    const query = customerSearchQuery.value.toLowerCase().trim();

    if (!query) {
        return props.customers || [];
    }

    return props.customers.filter(
        (customer) =>
            customer.name.toLowerCase().includes(query) ||
            (customer.phone && customer.phone.toLowerCase().includes(query)),
    );
});

const selectCustomer = (customer) => {
    customerId.value = customer?.id || '';
    useOneTimeCustomer.value = false;
    invoiceCustomerName.value = '';
    invoiceCustomerPhone.value = '';
    customerDropdownOpen.value = false;
    customerSearchQuery.value = '';
};

const selectOneTimeCustomer = () => {
    customerId.value = '';
    useOneTimeCustomer.value = true;
    customerDropdownOpen.value = false;
    customerSearchQuery.value = '';
};

const getPaymentMethodIcon = (method) => {
    if (method.is_cash) {
        return 'fas fa-money-bill-wave';
    }

    if (method.code?.toLowerCase().includes('qris')) {
        return 'fas fa-qrcode';
    }

    if (method.code?.toLowerCase().includes('transfer')) {
        return 'fas fa-university';
    }

    return 'fas fa-credit-card';
};

const selectPaymentMethod = (method) => {
    paymentMethodId.value = method.id;
    paymentMethodsOpen.value = false;

    if (method.is_cash) {
        uangDiterimaInput.value = cartTotal.value;
        cashPaymentConfirmed.value = false;
        cashKeypadFresh.value = true;
        cashModalOpen.value = true;

        return;
    }

    uangDiterimaInput.value = null;
    cashPaymentConfirmed.value = true;
};

const successDialogOpen = ref(false);
const successTransaction = ref(null);

const resetPOSState = () => {
    cart.value = [];
    customerId.value = '';
    useOneTimeCustomer.value = false;
    invoiceCustomerName.value = '';
    invoiceCustomerPhone.value = '';
    keterangan.value = '';
    uangDiterimaInput.value = null;
    paymentMethodId.value = '';
    cashPaymentConfirmed.value = false;
    cashModalOpen.value = false;
    isMobileCartOpen.value = false;
    successDialogOpen.value = false;
    successTransaction.value = null;
    duplicateCetakDialogOpen.value = false;
    pendingCetakItem.value = null;
    duplicateCetakIndex.value = -1;
};

const uangDiterimaInput = ref(null);
const uangDiterima = computed({
    get: () => {
        if (uangDiterimaInput.value === null) {
            return cartTotal.value;
        }

        return uangDiterimaInput.value;
    },
    set: (val) => {
        uangDiterimaInput.value = val;
    },
});

watch(cartTotal, () => {
    uangDiterimaInput.value = null;

    if (isCashPayment.value) {
        cashPaymentConfirmed.value = false;
    }
});

watch(paymentMethodId, () => {
    if (!isCashPayment.value) {
        uangDiterimaInput.value = null;
    }
});

const jumlahDibayar = computed(() => {
    if (!isCashPayment.value) {
        return cartTotal.value;
    }

    return Math.min(Number(uangDiterima.value) || 0, cartTotal.value);
});

const sisaTagihan = computed(() => {
    return Math.max(0, cartTotal.value - jumlahDibayar.value);
});

const kembalian = computed(() => {
    if (!isCashPayment.value) {
        return 0;
    }

    return Math.max(0, (Number(uangDiterima.value) || 0) - cartTotal.value);
});

const appendCashDigit = (digit) => {
    if (cashKeypadFresh.value) {
        uangDiterimaInput.value = Number(digit);
        cashKeypadFresh.value = false;

        return;
    }

    const current = String(Math.max(0, Number(uangDiterimaInput.value) || 0));
    const next = current === '0' ? digit : `${current}${digit}`;
    uangDiterimaInput.value = Number(next);
};

const removeCashDigit = () => {
    cashKeypadFresh.value = false;
    const current = String(Math.max(0, Number(uangDiterimaInput.value) || 0));
    uangDiterimaInput.value = Number(current.slice(0, -1)) || 0;
};

const setExactCash = () => {
    uangDiterimaInput.value = cartTotal.value;
    cashKeypadFresh.value = true;
};

const confirmCashPayment = () => {
    cashPaymentConfirmed.value = true;
    cashModalOpen.value = false;
};

const prosesBayar = () => {
    if (cart.value.length === 0) {
        showNotification(
            'Keranjang belanja kasir Anda saat ini masih kosong. Silakan tambahkan beberapa produk atau layanan cetak terlebih dahulu sebelum memproses pembayaran.',
            'Keranjang Kosong',
            'warning',
        );

        return;
    }

    if (!paymentMethodId.value) {
        showNotification(
            'Pilih metode pembayaran sebelum menyimpan transaksi.',
            'Metode Pembayaran Kosong',
            'warning',
        );

        return;
    }

    if (useOneTimeCustomer.value && !invoiceCustomerName.value.trim()) {
        showNotification(
            'Isi nama penerima invoice untuk customer sekali beli.',
            'Nama Penerima Invoice Kosong',
            'warning',
        );

        return;
    }

    isMobileCartOpen.value = false;

    if (isCashPayment.value && !cashPaymentConfirmed.value) {
        cashModalOpen.value = true;

        return;
    }

    isProcessing.value = true;

    router.post(
        '/pos',
        {
            cart: cart.value,
            total: cartTotal.value,
            payment_method_id: paymentMethodId.value,
            uang_diterima: isCashPayment.value
                ? uangDiterima.value
                : cartTotal.value,
            customer_id: customerId.value || null,
            invoice_customer_name: useOneTimeCustomer.value
                ? invoiceCustomerName.value.trim()
                : null,
            invoice_customer_phone: useOneTimeCustomer.value
                ? invoiceCustomerPhone.value.trim() || null
                : null,
            keterangan: keterangan.value || null,
        },
        {
            onSuccess: () => {
                const flashError = usePage().props.flash?.error;

                if (flashError) {
                    showNotification(flashError, 'Simpan Gagal', 'error');
                    isProcessing.value = false;

                    return;
                }

                const recentTrx = usePage().props.flash?.recent_transaction;

                if (recentTrx) {
                    successTransaction.value = recentTrx;
                    successDialogOpen.value = true;
                } else {
                    showNotification(
                        `Pembayaran nota kasir telah sukses diterima sebesar Rp ${formatRupiah(jumlahDibayar.value)}.`,
                        'Pembayaran Berhasil',
                        'success',
                    );
                    resetPOSState();
                }

                isProcessing.value = false;
            },
            onError: () => {
                showNotification(
                    'Ada kesalahan tak terduga saat menyimpan transaksi ke server. Mohon periksa jaringan internet Anda dan coba lagi.',
                    'Simpan Gagal',
                    'error',
                );
                isProcessing.value = false;
            },
        },
    );
};

const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID').format(angka);
};
</script>

<template>
    <Head>
        <title>Kasir Ultima | Smart POS System</title>
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
        />
    </Head>

    <div
        class="font-inter flex flex-1 flex-col bg-transparent pb-6 text-foreground transition-all duration-300 lg:pb-0"
        :class="{
            'fixed inset-0 z-[100] h-screen overflow-hidden bg-background p-6':
                !sidebarVisible,
            'h-auto p-4 md:p-6 lg:absolute lg:inset-0 lg:top-16 lg:overflow-hidden':
                sidebarVisible,
        }"
    >
        <!-- Header Mode Zen Premium -->
        <div
            v-if="!sidebarVisible"
            class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-border pb-4"
        >
            <div class="flex items-center gap-3">
                <span class="relative flex h-3 w-3">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                    ></span>
                    <span
                        class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"
                    ></span>
                </span>
                <span
                    class="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-lg font-bold tracking-wider text-transparent"
                    >KASIR ZEN MODE</span
                >
                <Badge
                    variant="outline"
                    class="border-emerald-500/30 bg-emerald-500/5 font-mono text-xs text-emerald-500"
                    >{{ currentTime }}</Badge
                >
            </div>

            <div class="flex items-center gap-2">
                <!-- Button Shortcut Helper -->
                <Button
                    @click="helpDialogOpen = true"
                    variant="outline"
                    size="sm"
                    class="flex items-center gap-1.5 rounded-xl border-border/80 bg-card text-xs"
                >
                    <i class="fas fa-keyboard text-indigo-500"></i>
                    Pintasan (F2/F4)
                </Button>
                <!-- Button Clear Cart -->
                <Button
                    @click="cart = []"
                    variant="outline"
                    size="sm"
                    class="flex items-center gap-1.5 rounded-xl border-border/80 bg-card text-xs text-red-500 hover:text-red-600"
                >
                    <i class="fas fa-trash-alt"></i>
                    Kosongkan
                </Button>
                <!-- Exit Zen Button -->
                <Button
                    @click="toggleZenMode"
                    variant="default"
                    size="sm"
                    class="flex items-center gap-1.5 rounded-xl bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                >
                    <i class="fas fa-compress-alt"></i>
                    Keluar Zen
                </Button>
            </div>
        </div>

        <!-- Header Biasa -->
        <div
            v-else
            class="mb-4 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center"
        >
            <div class="flex w-full gap-3 sm:w-auto">
                <Button
                    @click="toggleZenMode"
                    variant="outline"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-border bg-card/80 px-4 py-2 text-sm font-medium text-foreground shadow-sm backdrop-blur-sm transition-all hover:bg-muted sm:w-auto"
                >
                    <i
                        class="fas fa-expand-alt text-indigo-600 dark:text-indigo-400"
                    ></i>
                    <span>Mode Zen Kasir</span>
                </Button>
            </div>
            <div class="flex w-full justify-between sm:w-auto sm:text-right">
                <div class="text-xs text-muted-foreground sm:mb-1">
                    👋 Selamat bekerja
                </div>
                <div class="text-sm font-semibold text-foreground">
                    Medan | Hari ini
                </div>
            </div>
        </div>

        <!-- KARTU TRANSAKSI -->
        <div
            class="relative flex flex-1 flex-col overflow-hidden rounded-3xl border border-border bg-card text-card-foreground shadow-2xl transition-all duration-500 lg:flex-row"
            :style="{ borderTop: `4px solid ${themeColor}` }"
        >
            <!-- Loading overlay saat proses submit -->
            <div
                v-if="isProcessing"
                class="absolute inset-0 z-50 flex flex-col items-center justify-center bg-background/50 backdrop-blur-sm"
            >
                <i
                    class="fas fa-circle-notch fa-spin mb-3 text-4xl"
                    :style="{ color: themeColor }"
                ></i>
                <p class="font-bold text-foreground">Memproses Pembayaran...</p>
            </div>

            <!-- Area Kiri Dinamis (2/3) -->
            <div
                class="relative flex h-[calc(100vh-14rem)] w-full flex-col p-4 md:p-6 lg:h-full lg:w-2/3"
            >
                <!-- TAB BUTTONS -->
                <div
                    class="mb-6 grid w-full max-w-xl grid-cols-3 gap-1 rounded-2xl border border-border/40 bg-muted/40 p-1 dark:bg-muted/10"
                >
                    <button
                        v-for="(tab, idx) in tabs"
                        :key="idx"
                        @click="handleTabChange(idx)"
                        data-click-feedback="none"
                        class="flex min-h-11 min-w-0 items-center justify-center gap-1 rounded-xl px-1 py-2 text-center text-[9px] leading-tight font-bold tracking-wide uppercase transition-all duration-300 sm:h-9 sm:gap-2 sm:px-4 sm:text-xs sm:tracking-wider"
                        :class="
                            activeTab === idx
                                ? 'text-white shadow-sm'
                                : 'text-muted-foreground hover:bg-muted/30 hover:text-foreground'
                        "
                        :style="
                            activeTab === idx
                                ? { backgroundColor: themeColor }
                                : {}
                        "
                    >
                        <i
                            :class="tab.icon"
                            class="shrink-0 text-[10px] sm:text-xs"
                        ></i>
                        <span class="break-words whitespace-normal">{{
                            tab.name
                        }}</span>
                    </button>
                </div>

                <!-- KONTEN -->
                <div class="custom-scroll relative flex-1 overflow-y-auto pr-2">
                    <!-- Tab Loading Overlay -->
                    <div
                        v-if="isTabLoading"
                        class="absolute inset-0 z-30 flex items-center justify-center bg-background/60 backdrop-blur-xs transition-all duration-200"
                    >
                        <div class="flex flex-col items-center gap-2">
                            <i
                                class="fas fa-circle-notch fa-spin text-2xl"
                                :style="{ color: themeColor }"
                            ></i>
                            <span
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Memuat Produk...</span
                            >
                        </div>
                    </div>

                    <transition name="fade" mode="out-in">
                        <div
                            v-if="!isTabLoading"
                            :key="activeTab"
                            class="w-full"
                        >
                            <!-- TAB 0 : Barang Fisik -->
                            <div v-if="activeTab === 0" class="space-y-6">
                                <!-- Search Input Dinamis -->
                                <div class="relative w-full">
                                    <i
                                        class="fas fa-search absolute top-3 left-3.5 text-sm text-muted-foreground"
                                    ></i>
                                    <Input
                                        id="pos-search-input"
                                        type="text"
                                        v-model="posSearchQuery"
                                        placeholder="Cari nama barang atau SKU... (Tekan F4)"
                                        class="h-10 w-full rounded-xl border-border/80 bg-background pl-10 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-emerald-500"
                                    />
                                    <Button
                                        v-if="posSearchQuery"
                                        @click="posSearchQuery = ''"
                                        variant="ghost"
                                        size="sm"
                                        class="absolute top-1.5 right-2 h-7 w-7 rounded-full p-0 text-muted-foreground hover:bg-muted"
                                    >
                                        <i class="fas fa-times text-xs"></i>
                                    </Button>
                                </div>

                                <div
                                    v-if="filteredFisikItems.length === 0"
                                    class="rounded-2xl border border-dashed border-border bg-muted/5 py-10 text-center text-muted-foreground"
                                >
                                    <i
                                        class="fas fa-search-minus mb-2 text-4xl opacity-30"
                                    ></i>
                                    <p class="text-sm font-medium">
                                        Produk tidak ditemukan.
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Coba kata kunci lain atau bersihkan
                                        pencarian.
                                    </p>
                                </div>

                                <div
                                    v-else
                                    class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3"
                                >
                                    <div
                                        v-for="item in filteredFisikItems"
                                        :key="item.id"
                                        @click="
                                            addToCart({
                                                id: item.id,
                                                name: item.name,
                                                price: item.selling_price,
                                                qty: 1,
                                                type: 'fisik',
                                            })
                                        "
                                        class="group relative flex cursor-pointer items-center gap-3 overflow-hidden rounded-xl border border-border/50 bg-card p-3 shadow-sm transition duration-200 select-none hover:border-emerald-500/40 hover:bg-muted/10 hover:shadow-md"
                                    >
                                        <!-- Small image or initial box -->
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border/30 bg-muted/40"
                                        >
                                            <img
                                                v-if="item.image_path"
                                                :src="item.image_path"
                                                class="h-full w-full object-cover"
                                            />
                                            <span
                                                v-else
                                                class="flex h-full w-full items-center justify-center bg-emerald-500/10 text-xs font-black text-emerald-600 dark:text-emerald-400"
                                            >
                                                {{ getInitials(item.name) }}
                                            </span>
                                        </div>

                                        <div class="min-w-0 flex-1 text-left">
                                            <p
                                                class="line-clamp-2 text-xs leading-snug font-bold text-foreground"
                                                :title="item.name"
                                            >
                                                {{ item.name }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-[10px] font-medium text-muted-foreground"
                                            >
                                                Stok:
                                                {{ parseFloat(item.stock) }}
                                                {{ item.unit }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-xs font-black text-indigo-600 dark:text-indigo-400"
                                            >
                                                Rp
                                                {{
                                                    formatRupiah(
                                                        item.selling_price,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Layanan Cetak & Fotokopi Instan (Komponen Terintegrasi) -->
                                <Card
                                    class="rounded-2xl border-indigo-200/20 bg-indigo-50/5 p-5 shadow-sm dark:border-indigo-900/40 dark:bg-indigo-950/20"
                                >
                                    <CardHeader class="mb-4 p-0">
                                        <CardTitle
                                            class="flex items-center gap-3 font-bold text-foreground"
                                        >
                                            <i
                                                class="fas fa-print text-xl text-indigo-500 dark:text-indigo-400"
                                            ></i>
                                            Layanan Cetak & Fotokopi Instan
                                        </CardTitle>
                                        <CardDescription
                                            class="text-xs text-muted-foreground"
                                        >
                                            Layanan cetak fotokopi dan print
                                            langsung per lembar secara instan.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="space-y-4 p-0">
                                        <!-- Pilihan Layanan Langsung di Card (Tanpa Dropdown) -->
                                        <div
                                            class="grid w-full grid-cols-2 gap-3 md:grid-cols-4"
                                        >
                                            <div
                                                v-for="service in instanServices"
                                                :key="service.id"
                                                @click="
                                                    selectedServiceId =
                                                        service.id
                                                "
                                                class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border p-4 text-center transition-all duration-200 select-none"
                                                :class="
                                                    selectedServiceId ===
                                                    service.id
                                                        ? 'border-indigo-500 bg-indigo-500/10 font-bold text-indigo-600 shadow-sm ring-2 ring-indigo-500/20 dark:border-indigo-400 dark:text-indigo-400'
                                                        : 'border-border bg-background text-foreground hover:bg-muted/30'
                                                "
                                            >
                                                <div class="mb-1.5 text-xl">
                                                    <i
                                                        v-if="
                                                            String(
                                                                service.id,
                                                            ).includes(
                                                                'fotokopi',
                                                            ) ||
                                                            (service.sku &&
                                                                service.sku
                                                                    .toLowerCase()
                                                                    .includes(
                                                                        'ftk',
                                                                    ))
                                                        "
                                                        class="fas fa-copy"
                                                    ></i>
                                                    <i
                                                        v-else
                                                        class="fas fa-print"
                                                    ></i>
                                                </div>
                                                <p
                                                    class="text-xs leading-tight font-black"
                                                >
                                                    {{ service.name }}
                                                </p>
                                                <p
                                                    class="mt-1 font-mono text-[11px] font-bold text-muted-foreground"
                                                >
                                                    Rp
                                                    {{
                                                        formatRupiah(
                                                            service.price,
                                                        )
                                                    }}/lbr
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="flex flex-wrap items-end justify-between gap-4 pt-2"
                                        >
                                            <!-- Jumlah Lembar -->
                                            <div class="space-y-1.5">
                                                <Label
                                                    for="pos-instan-qty"
                                                    class="text-xs font-bold text-muted-foreground"
                                                    >Jumlah Lembar</Label
                                                >
                                                <Input
                                                    id="pos-instan-qty"
                                                    type="number"
                                                    v-model.number="instanQty"
                                                    min="1"
                                                    class="w-36 rounded-xl border border-input bg-background text-sm font-bold text-foreground"
                                                />
                                            </div>

                                            <div
                                                class="mt-2 flex items-center gap-5 lg:mt-0"
                                            >
                                                <div
                                                    class="self-center text-sm font-medium text-foreground"
                                                >
                                                    Total:
                                                    <span
                                                        class="text-lg font-black text-indigo-600 dark:text-indigo-400"
                                                        >Rp
                                                        {{
                                                            formatRupiah(
                                                                instanQty *
                                                                    (selectedService?.price ||
                                                                        0),
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                                <Button
                                                    @click="addInstanToCart"
                                                    class="rounded-xl bg-indigo-600 px-5 font-semibold text-white shadow-sm hover:bg-indigo-700"
                                                    >+ Tambah</Button
                                                >
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <!-- TAB 1 : Jasa Cetak (Dinamis dari Database) -->
                            <div v-if="activeTab === 1" class="space-y-6">
                                <Card
                                    class="rounded-2xl border-border/40 bg-muted/10 p-5"
                                >
                                    <CardHeader class="mb-4 p-0">
                                        <CardTitle
                                            class="flex items-center gap-3 font-bold text-foreground"
                                        >
                                            <i
                                                class="fas fa-print text-xl text-orange-500 dark:text-orange-400"
                                            ></i>
                                            Layanan Cetak Jasa & Spanduk
                                        </CardTitle>
                                        <CardDescription
                                            class="text-xs text-muted-foreground"
                                        >
                                            Kalkulasi dinamis berdasarkan satuan
                                            meter persegi (spanduk) atau
                                            lembaran (jasa cetak/jilid).
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent
                                        class="grid grid-cols-1 gap-5 p-0"
                                    >
                                        <div
                                            class="jasa-dropdown-container relative space-y-1.5"
                                        >
                                            <Label
                                                class="text-sm font-semibold text-foreground"
                                                >Pilih Layanan Jasa</Label
                                            >

                                            <!-- Elegant custom dropdown select -->
                                            <div class="relative">
                                                <button
                                                    type="button"
                                                    @click="
                                                        jasaDropdownOpen =
                                                            !jasaDropdownOpen
                                                    "
                                                    data-click-feedback="none"
                                                    class="flex h-11 w-full items-center justify-between rounded-xl border border-input bg-background px-4 py-2 text-sm text-foreground shadow-sm transition-all outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
                                                >
                                                    <span
                                                        class="font-semibold text-foreground"
                                                    >
                                                        {{
                                                            selectedJasaProduct
                                                                ? `${selectedJasaProduct.name} (Rp ${formatRupiah(selectedJasaProduct.selling_price)} / ${selectedJasaProduct.unit})`
                                                                : 'Pilih Layanan Jasa...'
                                                        }}
                                                    </span>
                                                    <i
                                                        class="fas fa-chevron-down text-muted-foreground transition-transform duration-200"
                                                        :class="{
                                                            'rotate-180':
                                                                jasaDropdownOpen,
                                                        }"
                                                    ></i>
                                                </button>
                                                <div
                                                    v-if="jasaDropdownOpen"
                                                    class="custom-scroll absolute right-0 left-0 z-50 mt-1 max-h-60 divide-y divide-border/40 overflow-y-auto rounded-xl border border-border bg-card p-1.5 shadow-xl"
                                                >
                                                    <div
                                                        class="sticky top-0 z-10 bg-card p-1.5"
                                                    >
                                                        <div class="relative">
                                                            <i
                                                                class="fas fa-search absolute top-2.5 left-3 text-xs text-muted-foreground"
                                                            ></i>
                                                            <Input
                                                                v-model="
                                                                    jasaSearchQuery
                                                                "
                                                                type="text"
                                                                placeholder="Cari nama atau SKU jasa..."
                                                                class="h-8 rounded-lg bg-background pl-8 text-xs"
                                                                @click.stop
                                                            />
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-for="j in filteredJasaCetakItems"
                                                        :key="j.id"
                                                        @click="
                                                            selectJasaProduct(j)
                                                        "
                                                        class="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2.5 text-xs transition hover:bg-muted/50"
                                                        :class="{
                                                            'bg-orange-500/10 font-bold text-orange-600 dark:text-orange-400':
                                                                activeJasaProductId ===
                                                                j.id,
                                                        }"
                                                    >
                                                        <span>{{
                                                            j.name
                                                        }}</span>
                                                        <span
                                                            class="font-mono text-muted-foreground"
                                                            >Rp
                                                            {{
                                                                formatRupiah(
                                                                    j.selling_price,
                                                                )
                                                            }}
                                                            / {{ j.unit }}</span
                                                        >
                                                    </div>
                                                    <div
                                                        v-if="
                                                            filteredJasaCetakItems.length ===
                                                            0
                                                        "
                                                        class="px-3 py-4 text-center text-xs text-muted-foreground"
                                                    >
                                                        Layanan jasa tidak
                                                        ditemukan.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Kalkulator Dinamis jika unit meter (pricing per m²) -->
                                        <div
                                            class="flex gap-4"
                                            v-if="
                                                isAreaBasedProduct(
                                                    selectedJasaProduct,
                                                )
                                            "
                                        >
                                            <div class="w-1/3 space-y-1.5">
                                                <Label
                                                    class="text-xs font-semibold text-foreground"
                                                    >Panjang (meter)</Label
                                                >
                                                <Input
                                                    type="number"
                                                    step="0.1"
                                                    min="0.1"
                                                    v-model.number="
                                                        cetakPanjang
                                                    "
                                                    class="w-full border-border bg-background text-foreground"
                                                />
                                            </div>
                                            <div class="w-1/3 space-y-1.5">
                                                <Label
                                                    class="text-xs font-semibold text-foreground"
                                                    >Lebar (meter)</Label
                                                >
                                                <Input
                                                    type="number"
                                                    step="0.1"
                                                    min="0.1"
                                                    v-model.number="cetakLebar"
                                                    class="w-full border-border bg-background text-foreground"
                                                />
                                            </div>
                                            <div class="w-1/3 space-y-1.5">
                                                <Label
                                                    class="text-xs font-semibold text-foreground"
                                                    >Jumlah (pcs)</Label
                                                >
                                                <Input
                                                    type="number"
                                                    step="1"
                                                    min="1"
                                                    v-model.number="cetakQty"
                                                    class="w-full border-border bg-background text-foreground"
                                                />
                                            </div>
                                        </div>
                                        <!-- Input Quantity biasa jika unit rim / lembar / pcs dll -->
                                        <div v-else class="space-y-1.5">
                                            <Label
                                                class="text-xs font-semibold text-foreground"
                                                >Jumlah ({{
                                                    selectedJasaProduct?.unit
                                                }})</Label
                                            >
                                            <Input
                                                type="number"
                                                step="1"
                                                min="1"
                                                v-model.number="cetakQty"
                                                class="w-full border-border bg-background text-foreground"
                                            />
                                        </div>

                                        <div
                                            class="vendor-dropdown-container relative space-y-1.5"
                                        >
                                            <Label
                                                class="text-xs font-semibold text-foreground"
                                                >Mitra / Vendor
                                                Percetakan</Label
                                            >
                                            <button
                                                type="button"
                                                data-click-feedback="none"
                                                @click="
                                                    vendorDropdownOpen =
                                                        !vendorDropdownOpen
                                                "
                                                class="flex h-10 w-full items-center justify-between rounded-xl border border-input bg-background px-3 text-sm text-foreground transition outline-none focus:ring-2 focus:ring-orange-500"
                                            >
                                                <span class="truncate">{{
                                                    selectedPrintVendor?.name ||
                                                    '-- Tanpa Mitra --'
                                                }}</span>
                                                <i
                                                    class="fas fa-chevron-down text-xs text-muted-foreground transition-transform"
                                                    :class="{
                                                        'rotate-180':
                                                            vendorDropdownOpen,
                                                    }"
                                                ></i>
                                            </button>
                                            <div
                                                v-if="vendorDropdownOpen"
                                                class="custom-scroll absolute right-0 left-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-xl border border-border bg-card p-1.5 shadow-xl"
                                            >
                                                <div
                                                    class="sticky top-0 z-10 bg-card p-1"
                                                >
                                                    <div class="relative">
                                                        <i
                                                            class="fas fa-search absolute top-2.5 left-3 text-xs text-muted-foreground"
                                                        ></i>
                                                        <Input
                                                            v-model="
                                                                vendorSearchQuery
                                                            "
                                                            type="text"
                                                            placeholder="Cari nama, telepon, atau alamat..."
                                                            class="h-8 rounded-lg bg-background pl-8 text-xs"
                                                            @click.stop
                                                        />
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    data-click-feedback="none"
                                                    @click="
                                                        selectPrintVendor(null)
                                                    "
                                                    class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold hover:bg-muted/50"
                                                >
                                                    -- Tanpa Mitra --
                                                </button>
                                                <button
                                                    v-for="vendor in filteredPrintVendors"
                                                    :key="vendor.id"
                                                    type="button"
                                                    data-click-feedback="none"
                                                    @click="
                                                        selectPrintVendor(
                                                            vendor,
                                                        )
                                                    "
                                                    class="w-full rounded-lg px-3 py-2 text-left text-xs hover:bg-muted/50"
                                                    :class="{
                                                        'bg-orange-500/10 font-bold text-orange-600 dark:text-orange-400':
                                                            String(
                                                                cetakVendorId,
                                                            ) ===
                                                            String(vendor.id),
                                                    }"
                                                >
                                                    <span
                                                        class="block font-semibold"
                                                        >{{ vendor.name }}</span
                                                    >
                                                    <span
                                                        class="block text-[10px] text-muted-foreground"
                                                        >{{
                                                            vendor.phone ||
                                                            vendor.address ||
                                                            '-'
                                                        }}</span
                                                    >
                                                </button>
                                                <div
                                                    v-if="
                                                        filteredPrintVendors.length ===
                                                        0
                                                    "
                                                    class="px-3 py-4 text-center text-xs text-muted-foreground"
                                                >
                                                    Mitra tidak ditemukan.
                                                </div>
                                            </div>
                                            <p
                                                class="text-[10px] text-muted-foreground"
                                            >
                                                Nama mitra hanya untuk
                                                pencatatan internal dan tidak
                                                dicetak pada invoice.
                                            </p>
                                        </div>

                                        <div
                                            class="mt-2 rounded-xl border border-border bg-muted/50 p-4 shadow-inner"
                                        >
                                            <div
                                                class="flex flex-col gap-1.5"
                                                v-if="
                                                    isAreaBasedProduct(
                                                        selectedJasaProduct,
                                                    )
                                                "
                                            >
                                                <div
                                                    class="flex justify-between text-xs text-muted-foreground"
                                                >
                                                    <span
                                                        ><i
                                                            class="fas fa-calculator mr-1"
                                                        ></i>
                                                        Luas / pcs</span
                                                    >
                                                    <span
                                                        class="font-mono font-semibold text-foreground"
                                                        >{{
                                                            formatQuantity(
                                                                areaPerPiece(
                                                                    cetakPanjang,
                                                                    cetakLebar,
                                                                ),
                                                            )
                                                        }}
                                                        m²</span
                                                    >
                                                </div>
                                                <div
                                                    class="flex justify-between text-xs text-muted-foreground"
                                                >
                                                    <span>Harga / pcs</span>
                                                    <span
                                                        class="font-mono font-semibold text-foreground"
                                                        >Rp
                                                        {{
                                                            formatRupiah(
                                                                pricePerPiece(
                                                                    selectedJasaProduct.selling_price,
                                                                    areaPerPiece(
                                                                        cetakPanjang,
                                                                        cetakLebar,
                                                                    ),
                                                                ),
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                                <div
                                                    class="flex justify-between text-xs text-muted-foreground"
                                                    v-if="
                                                        parseInt(cetakQty) > 1
                                                    "
                                                >
                                                    <span
                                                        >Total luas ({{
                                                            parseInt(cetakQty)
                                                        }}
                                                        pcs)</span
                                                    >
                                                    <span
                                                        class="font-mono font-semibold text-foreground"
                                                        >{{
                                                            formatQuantity(
                                                                roundArea(
                                                                    areaPerPiece(
                                                                        cetakPanjang,
                                                                        cetakLebar,
                                                                    ) *
                                                                        (parseInt(
                                                                            cetakQty,
                                                                        ) || 1),
                                                                ),
                                                            )
                                                        }}
                                                        m²</span
                                                    >
                                                </div>
                                                <div
                                                    class="mt-0.5 flex items-center justify-between border-t border-border/60 pt-1.5"
                                                >
                                                    <span
                                                        class="text-sm font-medium text-muted-foreground"
                                                        ><i
                                                            class="fas fa-calculator mr-1"
                                                        ></i>
                                                        Estimasi Total</span
                                                    >
                                                    <span
                                                        class="text-lg font-bold text-foreground"
                                                        >Rp
                                                        {{
                                                            formatRupiah(
                                                                hargaJasaCetak,
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                v-else
                                                class="flex items-center justify-between"
                                            >
                                                <span
                                                    class="text-sm font-medium text-muted-foreground"
                                                    ><i
                                                        class="fas fa-calculator mr-1"
                                                    ></i>
                                                    Estimasi Harga</span
                                                >
                                                <span
                                                    class="text-lg font-bold text-foreground"
                                                    >Rp
                                                    {{
                                                        formatRupiah(
                                                            hargaJasaCetak,
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </div>

                                        <Button
                                            @click="addCetakToCart"
                                            data-loading-mode="spinner-only"
                                            class="mt-2 w-full rounded-xl bg-orange-600 py-6 text-sm font-bold text-white shadow-md transition-all hover:bg-orange-700 hover:opacity-90"
                                        >
                                            <i
                                                class="fas fa-cart-plus mr-2 text-xs"
                                            ></i>
                                            Tambah ke Keranjang
                                        </Button>
                                    </CardContent>
                                </Card>
                            </div>

                            <!-- TAB 2 : Saldo Digital (Dinamis dari Database) -->
                            <div v-if="activeTab === 2" class="space-y-6">
                                <Card
                                    class="rounded-2xl border-border/40 bg-muted/10 p-5"
                                >
                                    <CardHeader class="mb-4 p-0">
                                        <CardTitle
                                            class="flex items-center gap-3 font-bold text-foreground"
                                        >
                                            <i
                                                class="fas fa-bolt animate-pulse text-xl text-blue-500 dark:text-blue-400"
                                            ></i>
                                            Layanan Pembayaran Digital & PPOB
                                        </CardTitle>
                                        <CardDescription
                                            class="text-xs text-muted-foreground"
                                        >
                                            Pembelian Pulsa, Kuota, Token
                                            Listrik PLN Pintar, dan Saldo
                                            E-Wallet secara cepat.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent
                                        class="grid grid-cols-1 gap-5 p-0"
                                    >
                                        <!-- Layanan Selector -->
                                        <div
                                            class="digital-dropdown-container relative space-y-1.5"
                                        >
                                            <Label
                                                class="text-sm font-semibold text-foreground"
                                                >Pilih Layanan Saldo /
                                                PPOB</Label
                                            >
                                            <button
                                                type="button"
                                                data-click-feedback="none"
                                                @click="
                                                    digitalDropdownOpen =
                                                        !digitalDropdownOpen
                                                "
                                                class="flex h-10 w-full items-center justify-between rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs transition-all outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                            >
                                                <span
                                                    class="truncate font-semibold"
                                                    >{{
                                                        selectedLayanan?.name ||
                                                        'Pilih layanan digital...'
                                                    }}</span
                                                >
                                                <i
                                                    class="fas fa-chevron-down text-xs text-muted-foreground transition-transform"
                                                    :class="{
                                                        'rotate-180':
                                                            digitalDropdownOpen,
                                                    }"
                                                ></i>
                                            </button>
                                            <div
                                                v-if="digitalDropdownOpen"
                                                class="custom-scroll absolute right-0 left-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-xl border border-border bg-card p-1.5 shadow-xl"
                                            >
                                                <div
                                                    class="sticky top-0 z-10 bg-card p-1"
                                                >
                                                    <div class="relative">
                                                        <i
                                                            class="fas fa-search absolute top-2.5 left-3 text-xs text-muted-foreground"
                                                        ></i>
                                                        <Input
                                                            v-model="
                                                                digitalSearchQuery
                                                            "
                                                            type="text"
                                                            placeholder="Cari nama atau SKU layanan..."
                                                            class="h-8 rounded-lg bg-background pl-8 text-xs"
                                                            @click.stop
                                                        />
                                                    </div>
                                                </div>
                                                <button
                                                    v-for="service in filteredDigitalItems"
                                                    :key="service.id"
                                                    type="button"
                                                    data-click-feedback="none"
                                                    @click="
                                                        selectDigitalService(
                                                            service,
                                                        )
                                                    "
                                                    class="w-full rounded-lg px-3 py-2 text-left text-xs hover:bg-muted/50"
                                                    :class="{
                                                        'bg-blue-500/10 font-bold text-blue-600 dark:text-blue-400':
                                                            selectedLayanan?.id ===
                                                            service.id,
                                                    }"
                                                >
                                                    <span
                                                        class="block font-semibold"
                                                        >{{
                                                            service.name
                                                        }}</span
                                                    >
                                                    <span
                                                        class="block font-mono text-[10px] text-muted-foreground"
                                                        >{{
                                                            service.sku || '-'
                                                        }}</span
                                                    >
                                                </button>
                                                <div
                                                    v-if="
                                                        filteredDigitalItems.length ===
                                                        0
                                                    "
                                                    class="px-3 py-4 text-center text-xs text-muted-foreground"
                                                >
                                                    Layanan digital tidak
                                                    ditemukan.
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Nomor Pelanggan with Autocomplete -->
                                        <div class="relative space-y-1.5">
                                            <Label
                                                class="text-xs font-semibold text-foreground"
                                            >
                                                {{
                                                    selectedLayanan?.name
                                                        ?.toLowerCase()
                                                        .includes('token') ||
                                                    selectedLayanan?.name
                                                        ?.toLowerCase()
                                                        .includes('listrik')
                                                        ? 'Nomor Meter / ID Pelanggan'
                                                        : 'Nomor HP Tujuan / ID'
                                                }}
                                            </Label>
                                            <div class="relative">
                                                <span
                                                    class="absolute top-3 left-3 text-xs text-muted-foreground"
                                                >
                                                    <i
                                                        v-if="
                                                            selectedLayanan?.name
                                                                ?.toLowerCase()
                                                                .includes(
                                                                    'token',
                                                                ) ||
                                                            selectedLayanan?.name
                                                                ?.toLowerCase()
                                                                .includes(
                                                                    'listrik',
                                                                )
                                                        "
                                                        class="fas fa-plug text-blue-500"
                                                    ></i>
                                                    <i
                                                        v-else
                                                        class="fas fa-mobile-alt text-blue-500"
                                                    ></i>
                                                </span>
                                                <Input
                                                    type="text"
                                                    v-model="nomorPelanggan"
                                                    placeholder="Ketik nomor pelanggan..."
                                                    class="h-10 w-full rounded-xl border-border bg-background pl-9 text-foreground"
                                                    @input="
                                                        searchDigitalAccounts(
                                                            nomorPelanggan,
                                                            'number',
                                                        )
                                                    "
                                                    @focus="
                                                        searchDigitalAccounts(
                                                            nomorPelanggan,
                                                            'number',
                                                        )
                                                    "
                                                    @blur="
                                                        hideDigitalAutocomplete
                                                    "
                                                />
                                            </div>

                                            <!-- Autocomplete List -->
                                            <div
                                                v-if="
                                                    showAutocomplete &&
                                                    activeAutocompleteField ===
                                                        'number' &&
                                                    autocompleteResults.length >
                                                        0
                                                "
                                                class="absolute right-0 left-0 z-50 mt-1 max-h-48 divide-y divide-border/60 overflow-y-auto rounded-2xl border border-border bg-card shadow-2xl"
                                            >
                                                <div
                                                    v-for="account in autocompleteResults"
                                                    :key="account.id"
                                                    @mousedown.prevent="
                                                        selectAccount(account)
                                                    "
                                                    class="flex cursor-pointer items-center justify-between px-4 py-3 text-xs transition-colors hover:bg-blue-500/10"
                                                >
                                                    <div>
                                                        <p
                                                            class="font-bold text-foreground"
                                                        >
                                                            {{
                                                                account.account_number
                                                            }}
                                                        </p>
                                                        <p
                                                            class="text-[10px] text-muted-foreground"
                                                        >
                                                            {{
                                                                account.account_name
                                                            }}
                                                        </p>
                                                    </div>
                                                    <Badge
                                                        variant="outline"
                                                        class="border-blue-500/30 bg-blue-500/5 text-[9px] text-blue-500 uppercase"
                                                        >{{
                                                            account.type
                                                        }}</Badge
                                                    >
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Nama Pelanggan -->
                                        <div class="relative space-y-1.5">
                                            <Label
                                                class="text-xs font-semibold text-foreground"
                                                >Nama Pemilik Akun /
                                                Pelanggan</Label
                                            >
                                            <div class="relative">
                                                <span
                                                    class="absolute top-3 left-3 text-xs text-muted-foreground"
                                                >
                                                    <i
                                                        class="fas fa-user-circle text-blue-500"
                                                    ></i>
                                                </span>
                                                <Input
                                                    type="text"
                                                    v-model="namaPelanggan"
                                                    placeholder="Ketik nama pemilik akun..."
                                                    class="h-10 w-full rounded-xl border-border bg-background pl-9 text-foreground"
                                                    @input="
                                                        searchDigitalAccounts(
                                                            namaPelanggan,
                                                            'name',
                                                        )
                                                    "
                                                    @focus="
                                                        searchDigitalAccounts(
                                                            namaPelanggan,
                                                            'name',
                                                        )
                                                    "
                                                    @blur="
                                                        hideDigitalAutocomplete
                                                    "
                                                />
                                            </div>

                                            <div
                                                v-if="
                                                    showAutocomplete &&
                                                    activeAutocompleteField ===
                                                        'name' &&
                                                    autocompleteResults.length >
                                                        0
                                                "
                                                class="absolute right-0 left-0 z-50 mt-1 max-h-48 divide-y divide-border/60 overflow-y-auto rounded-2xl border border-border bg-card shadow-2xl"
                                            >
                                                <div
                                                    v-for="account in autocompleteResults"
                                                    :key="account.id"
                                                    @mousedown.prevent="
                                                        selectAccount(account)
                                                    "
                                                    class="flex cursor-pointer items-center justify-between px-4 py-3 text-xs transition-colors hover:bg-blue-500/10"
                                                >
                                                    <div>
                                                        <p
                                                            class="font-bold text-foreground"
                                                        >
                                                            {{
                                                                account.account_name
                                                            }}
                                                        </p>
                                                        <p
                                                            class="text-[10px] text-muted-foreground"
                                                        >
                                                            {{
                                                                account.account_number
                                                            }}
                                                        </p>
                                                    </div>
                                                    <Badge
                                                        variant="outline"
                                                        class="border-blue-500/30 bg-blue-500/5 text-[9px] text-blue-500 uppercase"
                                                        >{{
                                                            account.type
                                                        }}</Badge
                                                    >
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Nominal Manual -->
                                        <div class="space-y-1.5">
                                            <Label
                                                class="text-xs font-semibold text-foreground"
                                                >Nominal Pembelian
                                                (Rupiah)</Label
                                            >
                                            <div class="relative">
                                                <span
                                                    class="absolute top-3 left-3 font-mono text-xs font-bold text-muted-foreground"
                                                    >Rp</span
                                                >
                                                <Input
                                                    type="number"
                                                    v-model.number="
                                                        nominalManual
                                                    "
                                                    placeholder="Masukkan nominal (ex: 50000)"
                                                    class="h-10 w-full rounded-xl border-border bg-background pl-9 font-bold text-foreground"
                                                />
                                            </div>
                                        </div>

                                        <!-- Realtime Total Breakdown -->
                                        <div
                                            class="mt-2 grid grid-cols-2 gap-4 rounded-2xl border border-blue-200/20 bg-blue-50/5 p-4 shadow-inner dark:border-blue-900/40 dark:bg-blue-950/20"
                                        >
                                            <div
                                                class="space-y-1 text-xs text-muted-foreground"
                                            >
                                                <p>
                                                    Nominal:
                                                    <span
                                                        class="font-mono font-semibold text-foreground"
                                                        >Rp
                                                        {{
                                                            formatRupiah(
                                                                nominalManual ||
                                                                    0,
                                                            )
                                                        }}</span
                                                    >
                                                </p>
                                                <p>
                                                    Biaya Admin:
                                                    <span
                                                        class="font-mono font-semibold text-foreground"
                                                        >Rp
                                                        {{
                                                            formatRupiah(
                                                                selectedLayanan
                                                                    ? selectedLayanan.admin_fee
                                                                    : 0,
                                                            )
                                                        }}</span
                                                    >
                                                </p>
                                            </div>
                                            <div
                                                class="flex flex-col justify-center text-right"
                                            >
                                                <span
                                                    class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                                                    >Total Biaya</span
                                                >
                                                <span
                                                    class="font-mono text-lg font-black text-blue-600 dark:text-blue-400"
                                                    >Rp
                                                    {{
                                                        formatRupiah(totalBiaya)
                                                    }}</span
                                                >
                                            </div>
                                        </div>

                                        <div
                                            class="flex items-center justify-between rounded-xl border border-blue-200/40 bg-blue-50/5 p-3.5 shadow-inner dark:border-blue-900/40 dark:bg-blue-950/20"
                                        >
                                            <span
                                                class="flex items-center gap-1.5 text-sm text-foreground"
                                                ><i
                                                    class="fas fa-wallet text-blue-500 dark:text-blue-400"
                                                ></i>
                                                Saldo Digital Toko:</span
                                            >
                                            <span
                                                class="font-black text-blue-600 dark:text-blue-400"
                                                >Rp
                                                {{
                                                    formatRupiah(saldoDigital)
                                                }}</span
                                            >
                                        </div>

                                        <Button
                                            @click="beliSaldoDigital"
                                            class="mt-2 w-full rounded-xl bg-blue-600 py-6 text-sm font-bold text-white shadow-md transition-all hover:bg-blue-700 hover:opacity-90"
                                        >
                                            <i
                                                class="fas fa-cart-plus mr-2 text-xs"
                                            ></i>
                                            Beli & Tambah ke Keranjang
                                        </Button>
                                        <p
                                            class="mt-1 text-center text-[10px] text-muted-foreground"
                                        >
                                            *Saldo akan terpotong dari saldo
                                            digital toko saat transaksi
                                            dimasukkan
                                        </p>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>

            <!-- SISI KANAN KARTU: STRUK VIRTUAL -->
            <div
                class="custom-scroll relative z-10 hidden h-[50vh] w-full flex-col overflow-y-auto border-t border-border bg-muted/30 p-4 shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.1)] md:p-6 lg:flex lg:h-full lg:w-1/3 lg:border-t-0 lg:border-l lg:bg-muted/50 lg:shadow-none"
            >
                <div
                    class="mb-4 flex items-center justify-between border-b border-border pb-2"
                >
                    <h2
                        class="flex items-center gap-2 text-lg font-bold text-foreground"
                    >
                        <i
                            class="fas fa-receipt text-indigo-500 dark:text-indigo-400"
                        ></i>
                        Keranjang
                    </h2>
                    <span
                        class="rounded-full border border-border bg-background px-2 py-1 text-xs text-foreground shadow-sm"
                        >{{ cart.length }} item</span
                    >
                </div>
                <div
                    class="cart-scroll min-h-[180px] flex-1 space-y-2 overflow-y-auto pr-1"
                >
                    <div
                        v-if="cart.length === 0"
                        class="mt-10 text-center text-muted-foreground"
                    >
                        <i
                            class="fas fa-shopping-cart mb-2 text-4xl opacity-30"
                        ></i>
                        <p>Keranjang kosong</p>
                    </div>
                    <div
                        v-for="(item, idx) in cart"
                        :key="item._cartKey"
                        class="group relative rounded-xl border border-border bg-background p-2.5 shadow-sm"
                    >
                        <div class="flex justify-between">
                            <div class="w-[70%]">
                                <p
                                    class="truncate text-xs leading-tight font-bold text-foreground"
                                    :title="item.name"
                                >
                                    {{ item.name }}
                                </p>
                                <div class="mt-1">
                                    <input
                                        type="text"
                                        v-model="item.note"
                                        placeholder="Catatan item..."
                                        class="w-full rounded-md border-none bg-muted/40 px-1.5 py-0.5 text-[10px] font-medium text-foreground outline-none placeholder:text-muted-foreground/60 focus:ring-1 focus:ring-indigo-500/50 dark:bg-muted/20"
                                    />
                                </div>
                                <template v-if="item.is_area_based">
                                    <p
                                        class="mt-1 text-[10px] font-medium text-muted-foreground"
                                    >
                                        Luas/pcs:
                                        {{
                                            formatQuantity(item.area_per_piece)
                                        }}
                                        m² &middot; Total luas:
                                        {{ formatQuantity(item.total_area) }} m²
                                    </p>
                                </template>
                                <div class="mt-1.5 flex items-center gap-1.5">
                                    <Button
                                        @click="updateQty(item, -1)"
                                        variant="ghost"
                                        size="sm"
                                        data-click-feedback="none"
                                        class="flex h-5 w-5 items-center justify-center rounded-full bg-muted p-0 text-[10px] text-foreground transition hover:bg-accent"
                                        >-</Button
                                    >
                                    <button
                                        type="button"
                                        @click="openQtyKeypad(item)"
                                        data-click-feedback="none"
                                        title="Ubah Qty"
                                        class="min-w-8 cursor-pointer rounded-lg border border-border bg-muted/50 px-2 py-0.5 text-center text-xs font-bold text-foreground transition hover:border-indigo-500/50 hover:bg-muted"
                                    >
                                        {{ formatQuantity(item.quantity) }}
                                    </button>
                                    <Button
                                        @click="updateQty(item, 1)"
                                        variant="ghost"
                                        size="sm"
                                        data-click-feedback="none"
                                        class="flex h-5 w-5 items-center justify-center rounded-full bg-muted p-0 text-[10px] text-foreground transition hover:bg-accent"
                                        >+</Button
                                    >
                                </div>
                            </div>
                            <div
                                class="flex w-[28%] flex-col justify-between text-right"
                            >
                                <p
                                    class="text-xs font-black text-indigo-700 dark:text-indigo-400"
                                >
                                    Rp
                                    {{
                                        formatRupiah(item.price * item.quantity)
                                    }}
                                </p>
                                <Button
                                    @click="removeFromCart(idx)"
                                    variant="ghost"
                                    size="sm"
                                    class="mt-1 flex h-5 w-5 items-center justify-center self-end rounded-full bg-red-50 p-0 text-[10px] text-red-400 opacity-0 transition group-hover:opacity-100 hover:text-red-600 dark:bg-red-900/30 dark:hover:text-red-400"
                                    ><i class="fas fa-trash-alt text-[9px]"></i
                                ></Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CUSTOMER SELECT & INVOICE NOTES -->
                <div
                    class="mt-4 space-y-3.5 rounded-2xl border border-t border-border border-border/50 bg-muted/20 p-3.5 pt-4"
                >
                    <!-- Customer Dropdown -->
                    <div
                        class="customer-dropdown-container relative space-y-1.5"
                    >
                        <Label
                            class="flex items-center gap-1.5 text-[10px] font-extrabold tracking-wider text-muted-foreground uppercase"
                        >
                            <i
                                class="fas fa-user-circle text-[10px] text-indigo-500"
                            ></i>
                            Pilih Customer
                        </Label>
                        <button
                            type="button"
                            @click="
                                customerDropdownOpen = !customerDropdownOpen
                            "
                            data-click-feedback="none"
                            class="flex h-9 w-full items-center justify-between rounded-xl border border-input bg-background px-3 py-1 text-xs text-foreground shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <span class="truncate">{{
                                selectedCustomerLabel
                            }}</span>
                            <i
                                class="fas fa-chevron-down text-[10px] text-muted-foreground transition-transform"
                                :class="{ 'rotate-180': customerDropdownOpen }"
                            ></i>
                        </button>
                        <div
                            v-if="customerDropdownOpen"
                            class="custom-scroll absolute right-0 left-0 z-50 mt-1 max-h-56 overflow-y-auto rounded-xl border border-border bg-card p-1.5 shadow-xl"
                        >
                            <div class="sticky top-0 z-10 bg-card p-1">
                                <div class="relative">
                                    <i
                                        class="fas fa-search absolute top-2.5 left-3 text-xs text-muted-foreground"
                                    ></i>
                                    <Input
                                        v-model="customerSearchQuery"
                                        type="text"
                                        placeholder="Cari nama atau telepon..."
                                        class="h-8 rounded-lg bg-background pl-8 text-xs"
                                        @click.stop
                                    />
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="selectCustomer(null)"
                                data-click-feedback="none"
                                class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold hover:bg-muted/50"
                            >
                                -- Cash / Umum --
                            </button>
                            <button
                                type="button"
                                @click="selectOneTimeCustomer"
                                data-click-feedback="none"
                                class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-indigo-600 hover:bg-indigo-500/10 dark:text-indigo-400"
                            >
                                <span class="block"
                                    >Customer Sekali Beli / Invoice</span
                                >
                                <span
                                    class="block text-[10px] font-normal text-muted-foreground"
                                    >Tidak disimpan ke master pelanggan</span
                                >
                            </button>
                            <button
                                v-for="cust in filteredCustomers"
                                :key="cust.id"
                                type="button"
                                @click="selectCustomer(cust)"
                                data-click-feedback="none"
                                class="w-full rounded-lg px-3 py-2 text-left text-xs hover:bg-muted/50"
                                :class="{
                                    'bg-indigo-500/10 font-bold text-indigo-600 dark:text-indigo-400':
                                        String(customerId) === String(cust.id),
                                }"
                            >
                                <span class="block font-semibold">{{
                                    cust.name
                                }}</span>
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >{{ cust.phone || '-' }}</span
                                >
                            </button>
                            <div
                                v-if="filteredCustomers.length === 0"
                                class="px-3 py-4 text-center text-xs text-muted-foreground"
                            >
                                Customer tidak ditemukan.
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="useOneTimeCustomer"
                        class="grid grid-cols-1 gap-2 rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-3"
                    >
                        <Input
                            v-model="invoiceCustomerName"
                            type="text"
                            placeholder="Nama penerima invoice *"
                            class="h-8 rounded-lg bg-background text-xs"
                        />
                        <Input
                            v-model="invoiceCustomerPhone"
                            type="text"
                            placeholder="Nomor telepon (opsional)"
                            class="h-8 rounded-lg bg-background text-xs"
                        />
                    </div>

                    <!-- Keterangan Invoice -->
                    <div class="space-y-1.5">
                        <Label
                            for="pos-keterangan"
                            class="flex items-center gap-1.5 text-[10px] font-extrabold tracking-wider text-muted-foreground uppercase"
                        >
                            <i
                                class="fas fa-sticky-note text-[10px] text-indigo-500"
                            ></i>
                            Catatan / Keterangan Invoice
                        </Label>
                        <textarea
                            id="pos-keterangan"
                            v-model="keterangan"
                            rows="2"
                            placeholder="Keterangan transaksi..."
                            class="flex min-h-[45px] w-full rounded-xl border border-border border-input bg-background px-3 py-1.5 text-xs text-foreground shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        ></textarea>
                    </div>
                </div>

                <!-- RINGKASAN PEMBAYARAN -->
                <div
                    class="mt-4 space-y-3 rounded-2xl border border-t border-border border-border/50 bg-muted/40 p-4 pt-4 dark:bg-muted/10"
                >
                    <!-- Subtotal -->
                    <div
                        class="flex items-center justify-between text-xs font-semibold text-muted-foreground"
                    >
                        <span>Subtotal Belanja</span>
                        <span class="font-mono text-foreground"
                            >Rp {{ formatRupiah(cartTotal) }}</span
                        >
                    </div>

                    <!-- Metode Pembayaran -->
                    <div class="space-y-1.5">
                        <button
                            type="button"
                            @click="paymentMethodsOpen = !paymentMethodsOpen"
                            data-click-feedback="none"
                            class="flex w-full items-center justify-between rounded-xl border border-border bg-background px-3 py-2 text-left transition hover:bg-muted/30"
                        >
                            <span>
                                <span
                                    class="block text-[10px] font-extrabold tracking-wider text-muted-foreground uppercase"
                                    >Metode Pembayaran</span
                                >
                                <span
                                    class="block text-xs font-bold text-foreground"
                                    >{{
                                        selectedPaymentMethod?.name ||
                                        'Pilih metode pembayaran'
                                    }}</span
                                >
                            </span>
                            <i
                                class="fas fa-chevron-down text-xs text-muted-foreground transition-transform"
                                :class="{ 'rotate-180': paymentMethodsOpen }"
                            ></i>
                        </button>
                        <div
                            v-if="paymentMethodsOpen"
                            class="grid grid-cols-2 gap-2 rounded-xl border border-border/60 bg-muted/20 p-2"
                        >
                            <button
                                v-for="method in paymentMethods"
                                :key="method.id"
                                type="button"
                                @click="selectPaymentMethod(method)"
                                class="rounded-xl border p-2.5 text-left transition-all"
                                :class="
                                    String(paymentMethodId) ===
                                    String(method.id)
                                        ? 'border-indigo-500 bg-indigo-500/10 ring-1 ring-indigo-500/30'
                                        : 'border-border bg-background hover:border-indigo-500/40 hover:bg-muted/40'
                                "
                            >
                                <span class="flex items-center gap-2">
                                    <i
                                        :class="getPaymentMethodIcon(method)"
                                        class="text-xs text-indigo-500"
                                    ></i>
                                    <span
                                        class="text-[11px] leading-tight font-bold text-foreground"
                                        >{{ method.name }}</span
                                    >
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Kembalian / Sisa Tagihan -->
                    <div
                        v-if="paymentMethodId"
                        class="flex items-center justify-between pt-1 text-xs font-bold"
                    >
                        <span class="text-muted-foreground">{{
                            isCashPayment && kembalian > 0
                                ? 'Kembalian:'
                                : 'Sisa Tagihan:'
                        }}</span>
                        <span
                            :class="
                                sisaTagihan > 0
                                    ? 'animate-pulse text-red-500'
                                    : 'text-emerald-500'
                            "
                            class="font-mono"
                        >
                            Rp
                            {{
                                formatRupiah(
                                    isCashPayment && kembalian > 0
                                        ? kembalian
                                        : sisaTagihan,
                                )
                            }}
                        </span>
                    </div>

                    <!-- Total Block (Kontras Lembut) -->
                    <div
                        class="mt-1 flex items-center justify-between rounded-xl border border-indigo-500/20 bg-indigo-500/10 p-3 shadow-sm dark:bg-indigo-500/20"
                    >
                        <span
                            class="text-xs font-extrabold tracking-wider text-foreground uppercase"
                            >Total Akhir</span
                        >
                        <span
                            class="font-mono text-xl font-black text-indigo-600 dark:text-indigo-400"
                            >Rp {{ formatRupiah(cartTotal) }}</span
                        >
                    </div>

                    <!-- Action Button -->
                    <Button
                        @click="prosesBayar"
                        :disabled="isProcessing"
                        class="flex h-11 w-full items-center justify-center rounded-xl py-5 text-xs font-extrabold tracking-wider text-white uppercase shadow-md transition-all hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50"
                        :style="{ backgroundColor: themeColor }"
                    >
                        <i
                            class="fas fa-credit-card mr-2"
                            v-if="!isProcessing"
                        ></i>
                        {{
                            isProcessing
                                ? 'Memproses...'
                                : 'Bayar Sekarang (F2)'
                        }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- ==================== MOBILE FLOATING CART & BOTTOM SHEET ==================== -->

        <!-- BACKDROP OVERLAY -->
        <div
            v-if="isMobileCartOpen"
            @click="isMobileCartOpen = false"
            class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-all duration-300 lg:hidden"
            :class="cashModalOpen ? 'z-40' : 'z-[100]'"
        ></div>

        <!-- BOTTOM SHEET DETAIL KERANJANG -->
        <div
            class="fixed right-0 bottom-0 left-0 flex max-h-[85vh] transform flex-col overflow-hidden rounded-t-[2.5rem] border-t border-border bg-card shadow-2xl transition-all duration-500 ease-out lg:hidden"
            :class="[
                isMobileCartOpen ? 'translate-y-0' : 'translate-y-full',
                cashModalOpen ? 'z-40' : 'z-[101]',
            ]"
        >
            <!-- Drag Handle Indicator -->
            <div
                class="mx-auto my-3.5 h-1.5 w-12 rounded-full bg-muted opacity-60"
            ></div>

            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-border/60 px-6 pb-3"
            >
                <div>
                    <h3
                        class="flex items-center gap-2 text-base font-black tracking-tight text-foreground"
                    >
                        <i class="fas fa-receipt text-indigo-500"></i> Detail
                        Keranjang
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Periksa kembali pesanan pelanggan Anda.
                    </p>
                </div>
                <button
                    @click="isMobileCartOpen = false"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-muted-foreground transition hover:bg-accent hover:text-foreground"
                >
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Scrollable List -->
            <div
                class="custom-scroll flex-1 space-y-3 overflow-y-auto px-5 py-4"
            >
                <div
                    v-for="(item, idx) in cart"
                    :key="item._cartKey"
                    class="flex items-center justify-between rounded-xl border border-border/50 bg-muted/35 p-2.5 dark:bg-muted/10"
                >
                    <div class="w-[70%]">
                        <p
                            class="truncate text-xs leading-tight font-bold text-foreground"
                            :title="item.name"
                        >
                            {{ item.name }}
                        </p>
                        <div class="mt-1">
                            <input
                                type="text"
                                v-model="item.note"
                                placeholder="Catatan item..."
                                class="w-full rounded-md border-none bg-background px-1.5 py-0.5 text-[10px] font-medium text-foreground outline-none placeholder:text-muted-foreground/60 focus:ring-1 focus:ring-indigo-500/50"
                            />
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <Button
                                @click="updateQty(item, -1)"
                                variant="ghost"
                                size="sm"
                                data-click-feedback="none"
                                class="flex h-5 w-5 items-center justify-center rounded-full border border-border bg-background p-0 text-xs text-foreground transition hover:bg-muted"
                                >-</Button
                            >
                            <button
                                type="button"
                                @click="openQtyKeypad(item)"
                                data-click-feedback="none"
                                title="Ubah Qty"
                                class="min-w-8 cursor-pointer rounded-lg border border-border bg-background px-2.5 py-0.5 text-center text-xs font-bold text-foreground transition hover:border-indigo-500/50 hover:bg-muted"
                            >
                                {{ formatQuantity(item.quantity) }}
                            </button>
                            <Button
                                @click="updateQty(item, 1)"
                                variant="ghost"
                                size="sm"
                                data-click-feedback="none"
                                class="flex h-5 w-5 items-center justify-center rounded-full border border-border bg-background p-0 text-xs text-foreground transition hover:bg-muted"
                                >+</Button
                            >
                        </div>
                    </div>
                    <div
                        class="flex min-h-[55px] w-[28%] flex-col items-end justify-between text-right"
                    >
                        <p
                            class="text-xs font-black text-indigo-600 dark:text-indigo-400"
                        >
                            Rp {{ formatRupiah(item.price * item.quantity) }}
                        </p>
                        <Button
                            @click="removeFromCart(idx)"
                            variant="ghost"
                            size="sm"
                            class="mt-1 flex h-5 w-5 items-center justify-center rounded-full bg-rose-50 p-0 text-rose-500 transition hover:text-rose-600 dark:bg-rose-950/30"
                        >
                            <i class="fas fa-trash-alt text-xs"></i>
                        </Button>
                    </div>
                </div>

                <!-- Customer Select & Invoice Notes (Mobile Mode) -->
                <div
                    class="mt-4 space-y-3.5 rounded-2xl border border-border/50 bg-muted/20 p-4"
                >
                    <!-- Customer Dropdown -->
                    <div
                        class="customer-dropdown-container relative space-y-1.5"
                    >
                        <Label
                            class="flex items-center gap-1.5 text-xs font-bold text-muted-foreground"
                        >
                            <i
                                class="fas fa-user-circle text-[11px] text-indigo-500"
                            ></i>
                            Pilih Customer (Opsional)
                        </Label>
                        <button
                            type="button"
                            @click="
                                customerDropdownOpen = !customerDropdownOpen
                            "
                            data-click-feedback="none"
                            class="flex h-9 w-full items-center justify-between rounded-xl border border-input bg-background px-3 py-1 text-xs text-foreground shadow-xs outline-none"
                        >
                            <span class="truncate">{{
                                selectedCustomerLabel
                            }}</span>
                            <i
                                class="fas fa-chevron-down text-[10px] text-muted-foreground transition-transform"
                                :class="{ 'rotate-180': customerDropdownOpen }"
                            ></i>
                        </button>
                        <div
                            v-if="customerDropdownOpen"
                            class="custom-scroll absolute right-0 left-0 z-50 mt-1 max-h-56 overflow-y-auto rounded-xl border border-border bg-card p-1.5 shadow-xl"
                        >
                            <div class="sticky top-0 z-10 bg-card p-1">
                                <div class="relative">
                                    <i
                                        class="fas fa-search absolute top-2.5 left-3 text-xs text-muted-foreground"
                                    ></i>
                                    <Input
                                        v-model="customerSearchQuery"
                                        type="text"
                                        placeholder="Cari nama atau telepon..."
                                        class="h-8 rounded-lg bg-background pl-8 text-xs"
                                        @click.stop
                                    />
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="selectCustomer(null)"
                                data-click-feedback="none"
                                class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold hover:bg-muted/50"
                            >
                                -- Cash / Umum --
                            </button>
                            <button
                                type="button"
                                @click="selectOneTimeCustomer"
                                data-click-feedback="none"
                                class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-indigo-600 hover:bg-indigo-500/10 dark:text-indigo-400"
                            >
                                <span class="block"
                                    >Customer Sekali Beli / Invoice</span
                                >
                                <span
                                    class="block text-[10px] font-normal text-muted-foreground"
                                    >Tidak disimpan ke master pelanggan</span
                                >
                            </button>
                            <button
                                v-for="cust in filteredCustomers"
                                :key="cust.id"
                                type="button"
                                @click="selectCustomer(cust)"
                                data-click-feedback="none"
                                class="w-full rounded-lg px-3 py-2 text-left text-xs hover:bg-muted/50"
                                :class="{
                                    'bg-indigo-500/10 font-bold text-indigo-600 dark:text-indigo-400':
                                        String(customerId) === String(cust.id),
                                }"
                            >
                                <span class="block font-semibold">{{
                                    cust.name
                                }}</span>
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >{{ cust.phone || '-' }}</span
                                >
                            </button>
                            <div
                                v-if="filteredCustomers.length === 0"
                                class="px-3 py-4 text-center text-xs text-muted-foreground"
                            >
                                Customer tidak ditemukan.
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="useOneTimeCustomer"
                        class="grid grid-cols-1 gap-2 rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-3"
                    >
                        <Input
                            v-model="invoiceCustomerName"
                            type="text"
                            placeholder="Nama penerima invoice *"
                            class="h-8 rounded-lg bg-background text-xs"
                        />
                        <Input
                            v-model="invoiceCustomerPhone"
                            type="text"
                            placeholder="Nomor telepon (opsional)"
                            class="h-8 rounded-lg bg-background text-xs"
                        />
                    </div>

                    <!-- Keterangan Invoice -->
                    <div class="space-y-1.5">
                        <Label
                            for="pos-keterangan-mobile"
                            class="flex items-center gap-1.5 text-xs font-bold text-muted-foreground"
                        >
                            <i
                                class="fas fa-sticky-note text-[11px] text-indigo-500"
                            ></i>
                            Catatan / Keterangan Invoice
                        </Label>
                        <textarea
                            id="pos-keterangan-mobile"
                            v-model="keterangan"
                            rows="2"
                            placeholder="Misal: Spanduk 3x1 meter, DP Rp 50.000..."
                            class="flex min-h-[50px] w-full rounded-xl border border-border border-input bg-background px-3 py-2 text-xs text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        ></textarea>
                    </div>

                    <!-- Jumlah Uang Dibayar (Mobile) -->
                    <div class="mt-3 space-y-1.5">
                        <button
                            type="button"
                            @click="paymentMethodsOpen = !paymentMethodsOpen"
                            data-click-feedback="none"
                            class="flex w-full items-center justify-between rounded-xl border border-border bg-background px-3 py-2 text-left"
                        >
                            <span>
                                <span
                                    class="block text-[10px] font-bold text-muted-foreground"
                                    >Metode Pembayaran</span
                                >
                                <span
                                    class="block text-xs font-bold text-foreground"
                                    >{{
                                        selectedPaymentMethod?.name ||
                                        'Pilih metode pembayaran'
                                    }}</span
                                >
                            </span>
                            <i
                                class="fas fa-chevron-down text-xs text-muted-foreground transition-transform"
                                :class="{ 'rotate-180': paymentMethodsOpen }"
                            ></i>
                        </button>
                        <div
                            v-if="paymentMethodsOpen"
                            class="grid grid-cols-2 gap-2 rounded-xl border border-border/60 bg-muted/20 p-2"
                        >
                            <button
                                v-for="method in paymentMethods"
                                :key="method.id"
                                type="button"
                                @click="selectPaymentMethod(method)"
                                class="rounded-xl border p-3 text-left transition-all"
                                :class="
                                    String(paymentMethodId) ===
                                    String(method.id)
                                        ? 'border-indigo-500 bg-indigo-500/10 ring-1 ring-indigo-500/30'
                                        : 'border-border bg-background hover:border-indigo-500/40'
                                "
                            >
                                <span class="flex items-center gap-2">
                                    <i
                                        :class="getPaymentMethodIcon(method)"
                                        class="text-sm text-indigo-500"
                                    ></i>
                                    <span
                                        class="text-xs font-bold text-foreground"
                                        >{{ method.name }}</span
                                    >
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-if="paymentMethodId" class="mt-3 space-y-1.5">
                        <div
                            class="mt-1 flex items-center justify-between px-1 text-[10px] font-bold"
                        >
                            <span class="text-muted-foreground">{{
                                kembalian > 0 ? 'Kembalian:' : 'Sisa Tagihan:'
                            }}</span>
                            <span
                                :class="
                                    sisaTagihan > 0
                                        ? 'animate-pulse text-red-500'
                                        : 'text-emerald-500'
                                "
                            >
                                Rp
                                {{
                                    formatRupiah(
                                        kembalian > 0 ? kembalian : sisaTagihan,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Sticky Footer -->
            <div
                class="sticky bottom-0 space-y-3 border-t border-border bg-card p-5 pb-8"
            >
                <div
                    class="flex items-center justify-between text-base font-bold text-foreground"
                >
                    <span>Total Pembayaran</span>
                    <span
                        class="text-base font-black text-indigo-600 dark:text-indigo-400"
                        >Rp {{ formatRupiah(cartTotal) }}</span
                    >
                </div>
                <Button
                    @click="prosesBayar"
                    :disabled="isProcessing"
                    class="flex w-full items-center justify-center rounded-xl py-6 font-bold text-white shadow-lg transition-all hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    :style="{ backgroundColor: themeColor }"
                >
                    <i class="fas fa-credit-card mr-2" v-if="!isProcessing"></i>
                    {{ isProcessing ? 'MEMPROSES...' : 'BAYAR SEKARANG' }}
                </Button>
            </div>
        </div>

        <!-- DIALOG INPUT UANG TUNAI -->
        <Dialog :open="cashModalOpen" @update:open="cashModalOpen = $event">
            <DialogContent
                class="rounded-3xl border-border bg-card p-6 text-foreground sm:max-w-[420px]"
            >
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-money-bill-wave text-emerald-500"></i>
                        Input Uang Diterima
                    </DialogTitle>
                    <DialogDescription>
                        Masukkan nominal tunai melalui keyboard atau tombol
                        angka di layar.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div
                        class="space-y-2 rounded-2xl border border-border bg-muted/30 p-4"
                    >
                        <div
                            class="flex justify-between text-xs text-muted-foreground"
                        >
                            <span>Total Belanja</span>
                            <span class="font-mono font-bold text-foreground"
                                >Rp {{ formatRupiah(cartTotal) }}</span
                            >
                        </div>
                        <div class="relative">
                            <span
                                class="absolute top-3.5 left-4 text-sm font-bold text-muted-foreground"
                                >Rp</span
                            >
                            <Input
                                id="cash-received-input"
                                v-model.number="uangDiterima"
                                type="number"
                                min="0"
                                class="h-12 rounded-xl border-border bg-background pl-11 text-right font-mono text-xl font-black text-foreground"
                                autofocus
                                @input="cashKeypadFresh = false"
                            />
                        </div>
                        <div
                            class="flex justify-between pt-1 text-sm font-bold"
                        >
                            <span class="text-muted-foreground">{{
                                kembalian > 0 ? 'Kembalian' : 'Sisa Tagihan'
                            }}</span>
                            <span
                                :class="
                                    sisaTagihan > 0
                                        ? 'text-red-500'
                                        : 'text-emerald-500'
                                "
                                class="font-mono"
                            >
                                Rp
                                {{
                                    formatRupiah(
                                        kembalian > 0 ? kembalian : sisaTagihan,
                                    )
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="digit in [
                                '1',
                                '2',
                                '3',
                                '4',
                                '5',
                                '6',
                                '7',
                                '8',
                                '9',
                            ]"
                            :key="digit"
                            type="button"
                            @click="appendCashDigit(digit)"
                            data-click-feedback="none"
                            class="h-12 rounded-xl border border-border bg-background text-lg font-black text-foreground transition hover:bg-muted"
                        >
                            {{ digit }}
                        </button>
                        <button
                            type="button"
                            @click="
                                uangDiterimaInput = 0;
                                cashKeypadFresh = false;
                            "
                            data-click-feedback="none"
                            class="h-12 rounded-xl border border-red-500/20 bg-red-500/5 text-sm font-black text-red-500 transition hover:bg-red-500/10"
                        >
                            C
                        </button>
                        <button
                            type="button"
                            @click="appendCashDigit('0')"
                            data-click-feedback="none"
                            class="h-12 rounded-xl border border-border bg-background text-lg font-black text-foreground transition hover:bg-muted"
                        >
                            0
                        </button>
                        <button
                            type="button"
                            @click="removeCashDigit"
                            data-click-feedback="none"
                            class="h-12 rounded-xl border border-border bg-background text-foreground transition hover:bg-muted"
                        >
                            <i class="fas fa-backspace"></i>
                        </button>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        @click="setExactCash"
                        class="w-full rounded-xl border-emerald-500/30 font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        <i class="fas fa-check mr-2"></i> Uang Pas
                    </Button>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                            >Batal</Button
                        >
                    </DialogClose>
                    <Button
                        type="button"
                        @click="confirmCashPayment"
                        data-loading-mode="spinner-only"
                        class="rounded-xl bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                    >
                        Gunakan Nominal
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG NUMERIC KEYPAD QUANTITY -->
        <Dialog
            :open="qtyKeypadOpen"
            @update:open="
                (val) => (val ? (qtyKeypadOpen = true) : closeQtyKeypad())
            "
        >
            <DialogContent
                class="z-[9999] rounded-3xl border-border bg-card p-6 text-foreground sm:max-w-[400px]"
            >
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-foreground"
                    >
                        <i class="fas fa-calculator text-indigo-500"></i>
                        Numeric Keypad Quantity
                    </DialogTitle>
                    <DialogDescription
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ qtyKeypadItem?.name || 'Ubah kuantitas item' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div
                        class="space-y-2 rounded-2xl border border-border bg-muted/30 p-4"
                    >
                        <div
                            class="flex justify-between text-xs text-muted-foreground"
                        >
                            <span>Harga Satuan / Pcs</span>
                            <span class="font-mono font-bold text-foreground"
                                >Rp
                                {{
                                    formatRupiah(qtyKeypadItem?.price || 0)
                                }}</span
                            >
                        </div>
                        <div
                            class="flex min-h-[52px] items-center justify-end rounded-xl border border-border bg-background p-3 text-right font-mono text-2xl font-black tracking-wider text-foreground shadow-inner select-none"
                        >
                            {{ qtyKeypadInput || '0' }}
                        </div>
                        <div
                            class="flex justify-between pt-1 text-xs font-bold"
                        >
                            <span class="text-muted-foreground"
                                >Subtotal Item</span
                            >
                            <span
                                class="font-mono text-indigo-600 dark:text-indigo-400"
                            >
                                Rp
                                {{
                                    formatRupiah(
                                        (qtyKeypadItem?.price || 0) *
                                            (Number(qtyKeypadInput) || 0),
                                    )
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="digit in [
                                '1',
                                '2',
                                '3',
                                '4',
                                '5',
                                '6',
                                '7',
                                '8',
                                '9',
                            ]"
                            :key="digit"
                            type="button"
                            @click="appendQtyDigit(digit)"
                            data-click-feedback="none"
                            class="h-12 rounded-xl border border-border bg-background text-lg font-black text-foreground transition hover:bg-muted active:scale-95"
                        >
                            {{ digit }}
                        </button>
                        <button
                            type="button"
                            @click="clearQtyInput"
                            data-click-feedback="none"
                            class="h-12 rounded-xl border border-red-500/20 bg-red-500/5 text-xs font-black text-red-500 transition hover:bg-red-500/10 active:scale-95"
                        >
                            Clear
                        </button>
                        <button
                            type="button"
                            @click="appendQtyDigit('0')"
                            data-click-feedback="none"
                            class="h-12 rounded-xl border border-border bg-background text-lg font-black text-foreground transition hover:bg-muted active:scale-95"
                        >
                            0
                        </button>
                        <button
                            type="button"
                            @click="backspaceQtyInput"
                            data-click-feedback="none"
                            class="flex h-12 items-center justify-center rounded-xl border border-border bg-background text-foreground transition hover:bg-muted active:scale-95"
                        >
                            <i class="fas fa-backspace"></i>
                        </button>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:grid sm:grid-cols-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="closeQtyKeypad"
                        class="rounded-xl"
                        >Batal</Button
                    >
                    <Button
                        type="button"
                        @click="saveQtyKeypad"
                        class="rounded-xl bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                        >Simpan</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG DUPLIKAT JASA CETAK -->
        <Dialog
            :open="duplicateCetakDialogOpen"
            @update:open="duplicateCetakDialogOpen = $event"
        >
            <DialogContent
                class="rounded-3xl border-border bg-card p-6 text-foreground sm:max-w-[440px]"
            >
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-print text-orange-500"></i>
                        Jasa Cetak Sudah Ada
                    </DialogTitle>
                    <DialogDescription>
                        Produk jasa cetak yang sama sudah ada di keranjang.
                        Pilih cara memasukkan data terbaru.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="pendingCetakItem && duplicateCetakIndex !== -1"
                    class="space-y-3 py-2"
                >
                    <div
                        class="rounded-2xl border border-border bg-muted/30 p-4"
                    >
                        <p class="text-sm font-bold text-foreground">
                            {{ pendingCetakItem.name }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ pendingCetakItem.detail }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div
                            class="rounded-xl border border-border bg-background p-3"
                        >
                            <span class="block text-muted-foreground"
                                >Jumlah di keranjang</span
                            >
                            <span
                                class="mt-1 block text-lg font-black text-foreground"
                                >{{
                                    formatQuantity(
                                        cart[duplicateCetakIndex]?.quantity,
                                    )
                                }}</span
                            >
                        </div>
                        <div
                            class="rounded-xl border border-orange-500/20 bg-orange-500/5 p-3"
                        >
                            <span class="block text-muted-foreground"
                                >Jumlah input baru</span
                            >
                            <span
                                class="mt-1 block text-lg font-black text-orange-600 dark:text-orange-400"
                                >{{
                                    formatQuantity(pendingCetakItem.quantity)
                                }}</span
                            >
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:grid sm:grid-cols-2">
                    <Button
                        type="button"
                        variant="outline"
                        data-click-feedback="none"
                        class="rounded-xl"
                        @click="keepDuplicateCetak"
                    >
                        <i class="fas fa-plus text-xs"></i>
                        Tetap Masukkan Baru
                    </Button>
                    <Button
                        type="button"
                        data-click-feedback="none"
                        class="rounded-xl bg-orange-600 text-white hover:bg-orange-700"
                        @click="overwriteDuplicateCetak"
                    >
                        <i class="fas fa-pen text-xs"></i>
                        Timpa Jumlah Lama
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG SHORTCUT HELPER -->
        <Dialog :open="helpDialogOpen" @update:open="helpDialogOpen = $event">
            <DialogContent
                class="rounded-2xl border-border bg-card text-foreground sm:max-w-[450px]"
            >
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-keyboard text-emerald-500"></i>
                        Pintasan Keyboard POS Kasir
                    </DialogTitle>
                    <DialogDescription>
                        Gunakan tombol pintasan berikut untuk mempercepat proses
                        transaksi di kasir:
                    </DialogDescription>
                </DialogHeader>

                <div class="my-2 space-y-4">
                    <div
                        class="flex items-center justify-between rounded-xl border border-border/50 bg-muted/40 p-2.5"
                    >
                        <span
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <i
                                class="fas fa-search text-xs text-muted-foreground"
                            ></i>
                            Cari Produk Fisik
                        </span>
                        <kbd
                            class="rounded-lg border border-border bg-background px-2.5 py-1 font-mono text-xs font-bold text-foreground shadow-sm"
                            >F4</kbd
                        >
                    </div>

                    <div
                        class="flex items-center justify-between rounded-xl border border-border/50 bg-muted/40 p-2.5"
                    >
                        <span
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <i
                                class="fas fa-credit-card text-xs text-muted-foreground"
                            ></i>
                            Bayar Sekarang
                        </span>
                        <kbd
                            class="rounded-lg border border-border bg-background px-2.5 py-1 font-mono text-xs font-bold text-foreground shadow-sm"
                            >F2</kbd
                        >
                    </div>

                    <div
                        class="flex items-center justify-between rounded-xl border border-border/50 bg-muted/40 p-2.5"
                    >
                        <span
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <i
                                class="fas fa-times-circle text-xs text-muted-foreground"
                            ></i>
                            Bersihkan Kolom Cari
                        </span>
                        <kbd
                            class="rounded-lg border border-border bg-background px-2.5 py-1 font-mono text-xs font-bold text-foreground shadow-sm"
                            >ESC</kbd
                        >
                    </div>
                </div>

                <DialogFooter class="sm:justify-end">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                        >
                            Tutup
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG SUCCESS TRANSACTION SUMMARY (PREVIEW INVOICE) -->
        <Dialog
            :open="successDialogOpen"
            @update:open="successDialogOpen = $event"
        >
            <DialogContent
                class="max-h-[85vh] overflow-y-auto rounded-3xl border-border bg-card p-6 text-foreground shadow-2xl sm:max-w-[500px]"
            >
                <div
                    class="flex flex-col items-center border-b border-border pb-4 text-center"
                >
                    <div
                        class="mb-3 flex h-16 w-16 animate-bounce items-center justify-center rounded-full bg-emerald-500/10 text-3xl text-emerald-500 dark:bg-emerald-500/20"
                    >
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <DialogTitle class="text-xl font-black text-foreground"
                        >Transaksi Berhasil Disimpan!</DialogTitle
                    >
                    <DialogDescription
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Nota belanja telah dicatat dalam database pos kasir
                        secara permanen.
                    </DialogDescription>
                </div>

                <div v-if="successTransaction" class="my-2 space-y-4 text-sm">
                    <!-- Detail Ringkas Transaksi -->
                    <div
                        class="space-y-2.5 rounded-2xl border border-border bg-muted/40 p-4"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground"
                                >Nomor Invoice:</span
                            >
                            <span
                                class="font-mono font-bold text-indigo-600 dark:text-indigo-400"
                                >{{ successTransaction.invoice_number }}</span
                            >
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground"
                                >Pelanggan:</span
                            >
                            <span class="font-bold">{{
                                successTransaction.customer_name ||
                                'Cash / Umum'
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground"
                                >Status Pembayaran:</span
                            >
                            <span
                                class="rounded px-2 py-0.5 text-[10px] font-black tracking-wider uppercase"
                                :class="
                                    successTransaction.status_bayar === 'lunas'
                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                        : successTransaction.status_bayar ===
                                            'dp'
                                          ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                          : 'bg-red-500/10 text-red-600 dark:text-red-400'
                                "
                            >
                                {{ successTransaction.status_bayar }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground"
                                >Metode Pembayaran:</span
                            >
                            <span class="font-bold">{{
                                successTransaction.payment_method_master
                                    ?.name || successTransaction.payment_method
                            }}</span>
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-border/60 pt-2 text-xs font-bold"
                        >
                            <span class="text-muted-foreground"
                                >Total Belanja:</span
                            >
                            <span class="font-mono text-foreground"
                                >Rp
                                {{
                                    formatRupiah(successTransaction.total_price)
                                }}</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between text-xs font-bold"
                        >
                            <span class="text-emerald-600 dark:text-emerald-400"
                                >Jumlah Uang Dibayar:</span
                            >
                            <span
                                class="font-mono text-emerald-600 dark:text-emerald-400"
                                >Rp
                                {{
                                    formatRupiah(
                                        successTransaction.jumlah_dibayar,
                                    )
                                }}</span
                            >
                        </div>
                        <div
                            v-if="successTransaction.kembalian > 0"
                            class="flex items-center justify-between text-xs font-bold text-indigo-600 dark:text-indigo-400"
                        >
                            <span>Kembalian:</span>
                            <span class="font-mono"
                                >Rp
                                {{
                                    formatRupiah(successTransaction.kembalian)
                                }}</span
                            >
                        </div>
                        <div
                            v-if="successTransaction.sisa_tagihan > 0"
                            class="flex items-center justify-between text-xs font-bold text-red-500"
                        >
                            <span>Sisa Kurang (Piutang):</span>
                            <span class="font-mono"
                                >Rp
                                {{
                                    formatRupiah(
                                        successTransaction.sisa_tagihan,
                                    )
                                }}</span
                            >
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-1.5">
                        <Label
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Item Yang Dibeli</Label
                        >
                        <div
                            class="custom-scroll max-h-[150px] divide-y divide-border overflow-hidden overflow-y-auto rounded-2xl border border-border bg-background"
                        >
                            <div
                                v-for="item in successTransaction.items"
                                :key="item.id"
                                class="flex items-center justify-between p-3 text-xs"
                            >
                                <div>
                                    <p class="font-bold text-foreground">
                                        {{ item.item_name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-muted-foreground"
                                    >
                                        {{ parseFloat(item.quantity) }}
                                        {{ item.unit || 'pcs' }} &times; Rp
                                        {{ formatRupiah(item.selling_price) }}
                                    </p>
                                </div>
                                <span
                                    class="font-mono font-bold text-foreground"
                                    >Rp
                                    {{
                                        formatRupiah(item.subtotal_price)
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter
                    class="grid grid-cols-2 gap-3 border-t border-border pt-4"
                >
                    <Button
                        @click="resetPOSState"
                        variant="outline"
                        class="flex h-10 w-full items-center justify-center gap-1.5 rounded-xl border-border py-5 text-xs font-bold text-foreground hover:bg-muted"
                    >
                        <i class="fas fa-arrow-left"></i>
                        Kembali ke Kasir
                    </Button>
                    <a
                        v-if="successTransaction"
                        :href="`/pos/print/${successTransaction.invoice_number}`"
                        target="_blank"
                        class="inline-flex h-10 w-full cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2.5 text-center text-xs font-bold text-white shadow-sm hover:bg-indigo-700"
                    >
                        <i class="fas fa-print"></i>
                        Cetak Invoice
                    </a>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG ALERT CUSTOM (SUCCESS / WARNING / ERROR / INFO) -->
        <Dialog :open="alertOpen" @update:open="alertOpen = $event">
            <DialogContent
                class="z-[9999] overflow-hidden rounded-3xl border border-border bg-card p-6 text-foreground shadow-2xl sm:max-w-[400px]"
            >
                <div class="flex flex-col items-center space-y-4 text-center">
                    <!-- Icon based on type -->
                    <div
                        v-if="alertType === 'success'"
                        class="flex h-16 w-16 animate-bounce items-center justify-center rounded-full bg-emerald-500/10 text-3xl text-emerald-500 dark:bg-emerald-500/20"
                    >
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div
                        v-else-if="alertType === 'warning'"
                        class="flex h-16 w-16 animate-pulse items-center justify-center rounded-full bg-amber-500/10 text-3xl text-amber-500 dark:bg-amber-500/20"
                    >
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div
                        v-else-if="alertType === 'error'"
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-red-500/10 text-3xl text-red-500 dark:bg-red-500/20"
                    >
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div
                        v-else
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-500/10 text-3xl text-indigo-500 dark:bg-indigo-500/20"
                    >
                        <i class="fas fa-info-circle"></i>
                    </div>

                    <div class="w-full space-y-1.5">
                        <DialogTitle
                            class="text-lg font-black text-foreground"
                            >{{ alertTitle }}</DialogTitle
                        >
                        <DialogDescription
                            class="px-2 text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ alertMessage }}
                        </DialogDescription>
                    </div>
                </div>

                <DialogFooter class="mt-5 w-full">
                    <Button
                        @click="alertOpen = false"
                        class="h-10 w-full rounded-xl border-none py-5 text-xs font-bold text-white shadow-sm transition-all hover:opacity-90"
                        :class="{
                            'bg-emerald-600 hover:bg-emerald-700':
                                alertType === 'success',
                            'bg-amber-500 hover:bg-amber-600':
                                alertType === 'warning',
                            'bg-red-600 hover:bg-red-700':
                                alertType === 'error',
                            'bg-indigo-600 hover:bg-indigo-700':
                                alertType === 'info',
                        }"
                    >
                        Mengerti
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style>
/* CSS dari template.html dipindah ke sini */
.font-inter {
    font-family: 'Inter', sans-serif;
}
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.sidebar-transition {
    transition: all 0.3s ease-in-out;
}
.btn-zen-hover:hover {
    transform: scale(0.97);
    transition: all 0.2s;
}
.card-hover {
    transition:
        box-shadow 0.2s,
        transform 0.2s;
}
.card-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -12px rgba(0, 0, 0, 0.15);
}
</style>
