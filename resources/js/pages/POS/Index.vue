<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

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
const normalizeQuantity = (value) => Math.round((Number(value) + Number.EPSILON) * 100) / 100;
const formatQuantity = (value) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
}).format(normalizeQuantity(value));

// ---------- AREA-BASED (per m²) PRINTING HELPERS ----------
// Area & harga dibulatkan dengan cara yang SAMA di frontend dan backend
// (AreaPricingService PHP), agar estimasi == nilai snapshot transaksi.
const roundMoney = (value) => Math.round((Number(value) + Number.EPSILON) * 100) / 100;
const roundArea = (value) => Math.round((Number(value) + Number.EPSILON) * 100) / 100;
const isAreaBasedProduct = (product) => product && product.unit === 'meter';
const areaPerPiece = (length, width) => roundArea(Number(length) * Number(width));
const pricePerPiece = (rate, area) => roundMoney(Number(rate) * Number(area));
const cartItemCount = computed(() => {
    return normalizeQuantity(cart.value.reduce((sum, item) => sum + item.quantity, 0));
});
const cartTotal = computed(() => {
    return cart.value.reduce((sum, i) => sum + (i.price * i.quantity), 0);
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
    currentTime.value = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

// Keyboard Shortcuts Listener
const handleKeyDown = (e) => {
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
        window.dispatchEvent(new CustomEvent('cart-updated', { detail: { 
            count: cartItemCount.value, 
            total: cartTotal.value, 
            hasItems: cart.value.length > 0 
        }}));
    }
};

watch(cart, () => {
    dispatchCartSync();
}, { deep: true });


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
    if (jasaDropdownOpen.value && !e.target.closest('.jasa-dropdown-container')) {
        jasaDropdownOpen.value = false;
    }
    if (customerDropdownOpen.value && !e.target.closest('.customer-dropdown-container')) {
        customerDropdownOpen.value = false;
    }
    if (vendorDropdownOpen.value && !e.target.closest('.vendor-dropdown-container')) {
        vendorDropdownOpen.value = false;
    }
    if (digitalDropdownOpen.value && !e.target.closest('.digital-dropdown-container')) {
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
    if (timer) clearInterval(timer);
    if (autocompleteTimer) clearTimeout(autocompleteTimer);
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
    if (activeTab.value === idx) return;
    isTabLoading.value = true;
    setTimeout(() => {
        activeTab.value = idx;
        isTabLoading.value = false;
    }, 250);
};

const tabs = [
    { name: "Barang Fisik & Fotokopi", icon: "fas fa-box-open" },
    { name: "Jasa Cetak (Vendor)", icon: "fas fa-print" },
    { name: "Saldo Digital", icon: "fas fa-mobile-alt" }
];

const themeColor = computed(() => {
    if (activeTab.value === 0) return "#10b981"; // Hijau segar
    if (activeTab.value === 1) return "#f97316"; // Oranye enerjik
    return "#3b82f6"; // Biru elektrik
});

const selectJasaProduct = (product) => {
    activeJasaProductId.value = product.id;
    jasaDropdownOpen.value = false;
    jasaSearchQuery.value = '';
};

const getInitials = (name) => {
    if (!name) return '??';
    const words = name.trim().split(/\s+/);
    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase();
    }
    return words[0].substring(0, 2).toUpperCase();
};



// ---------- TAB FISIK & SEARCH ----------
const posSearchQuery = ref('');

const fisikItems = computed(() => {
    return props.products.filter(p => p.category && p.category.type === 'fisik' && p.name !== 'Fotokopi');
});

const filteredFisikItems = computed(() => {
    return fisikItems.value.filter(item => {
        return item.name.toLowerCase().includes(posSearchQuery.value.toLowerCase()) ||
               (item.sku && item.sku.toLowerCase().includes(posSearchQuery.value.toLowerCase()));
    });
});

// ---------- LAYANAN CETAK & FOTOKOPI INSTAN ----------
const instanServices = computed(() => {
    const skus = ['JSA-FTK-01', 'JSA-FTK-02', 'JSA-PRN-01', 'JSA-PRN-02'];
    const dbServices = props.products
        .filter(p => skus.includes(p.sku))
        .map(p => ({
            id: p.id,
            sku: p.sku,
            name: p.name,
            price: parseFloat(p.selling_price)
        }));
        
    return dbServices.sort((a, b) => skus.indexOf(a.sku) - skus.indexOf(b.sku));
});

const selectedServiceId = ref(null);
const instanQty = ref(1);

watch(instanServices, (newList) => {
    if (newList.length > 0 && selectedServiceId.value === null) {
        selectedServiceId.value = newList[0].id;
    }
}, { immediate: true });

const selectedService = computed(() => {
    const list = instanServices.value;
    return list.find(s => s.id === selectedServiceId.value) || list[0] || { id: null, name: '', price: 0 };
});

const addInstanToCart = () => {
    let qty = instanQty.value;
    if (qty < 1) qty = 1;
    
    const service = selectedService.value;
    if (!service || !service.id) return;
    
    addToCart({
        id: service.id,
        name: service.name,
        price: service.price,
        quantity: qty,
        type: 'fotokopi',
        detail: `${qty} lembar x Rp ${formatRupiah(service.price)}`
    });
    instanQty.value = 1;
};

// ---------- TAB JASA CETAK (Vendor) ----------
const jasaCetakItems = computed(() => {
    const instantSkus = ['JSA-FTK-01', 'JSA-FTK-02', 'JSA-PRN-01', 'JSA-PRN-02'];
    return props.products.filter(p => p.category && p.category.type === 'jasa' && !instantSkus.includes(p.sku));
});

const filteredJasaCetakItems = computed(() => {
    const query = jasaSearchQuery.value.toLowerCase().trim();
    if (!query) return jasaCetakItems.value;

    return jasaCetakItems.value.filter(product =>
        product.name.toLowerCase().includes(query) ||
        (product.sku && product.sku.toLowerCase().includes(query))
    );
});

const activeJasaProductId = ref('');

watch(jasaCetakItems, (newItems) => {
    if (newItems.length > 0 && !activeJasaProductId.value) {
        activeJasaProductId.value = newItems[0].id;
    }
}, { immediate: true });

const selectedJasaProduct = computed(() => {
    return jasaCetakItems.value.find(p => p.id === activeJasaProductId.value);
});

const cetakQty = ref(1);
const cetakPanjang = ref(1);
const cetakLebar = ref(1);
const cetakVendorId = ref('');

watch(() => props.printVendors, (vendors) => {
    if (vendors?.length > 0 && !cetakVendorId.value) {
        cetakVendorId.value = vendors[0].id;
    }
}, { immediate: true });

const selectedPrintVendor = computed(() => {
    return props.printVendors?.find(vendor => String(vendor.id) === String(cetakVendorId.value)) || null;
});

const filteredPrintVendors = computed(() => {
    const query = vendorSearchQuery.value.toLowerCase().trim();
    if (!query) return props.printVendors || [];

    return props.printVendors.filter(vendor =>
        vendor.name.toLowerCase().includes(query) ||
        (vendor.phone && vendor.phone.toLowerCase().includes(query)) ||
        (vendor.address && vendor.address.toLowerCase().includes(query))
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
        if (!Number.isFinite(p) || p <= 0 || !Number.isFinite(l) || l <= 0 || !Number.isFinite(qty) || qty < 1) {
            return 0;
        }

        const ap = areaPerPiece(p, l);

        // harga per pcs = rate × luas; total = harga per pcs × jumlah pcs
        return roundMoney(pricePerPiece(parseFloat(prod.selling_price), ap) * qty);
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
            showNotification("Panjang harus lebih besar dari 0 (nol).", "Ukuran Tidak Valid", "warning");

            return;
        }

        if (!Number.isFinite(l) || l <= 0) {
            showNotification("Lebar harus lebih besar dari 0 (nol).", "Ukuran Tidak Valid", "warning");

            return;
        }

        if (!Number.isInteger(qty) || qty < 1) {
            showNotification("Jumlah pcs harus bilangan bulat minimal 1.", "Jumlah Tidak Valid", "warning");

            return;
        }

        const ap = areaPerPiece(p, l);       // luas per pcs (m²)
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
            showNotification("Jumlah harus minimal 1.", "Jumlah Tidak Valid", "warning");

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
const saldoDigital = ref(props.profile ? parseFloat(props.profile.saldo_digital) : 350000); 
const digitalItems = computed(() => {
    return props.products.filter(p => p.category && p.category.type === 'ppob');
});

const filteredDigitalItems = computed(() => {
    const query = digitalSearchQuery.value.toLowerCase().trim();
    if (!query) return digitalItems.value;

    return digitalItems.value.filter(product =>
        product.name.toLowerCase().includes(query) ||
        (product.sku && product.sku.toLowerCase().includes(query))
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

watch(digitalItems, (newItems) => {
    if (newItems.length > 0 && !selectedLayanan.value) {
        selectedLayanan.value = newItems[0];
    }
}, { immediate: true });

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
        const type = (selectedLayanan.value?.name?.toLowerCase().includes('token') ||
                      selectedLayanan.value?.name?.toLowerCase().includes('listrik') ||
                      selectedLayanan.value?.sku?.toLowerCase().includes('tkn'))
                      ? 'token'
                      : 'phone';

        try {
            const response = await axios.get('/api/digital-accounts/search', {
                params: {
                    query,
                    type,
                }
            });
            autocompleteResults.value = response.data;
            showAutocomplete.value = response.data.length > 0;
        } catch (error) {
            console.error('Error fetching digital accounts autocomplete:', error);
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
        showNotification("Silakan pilih produk digital yang ingin Anda beli terlebih dahulu sebelum melanjutkan.", "Pilih Produk Digital", "warning");
        return;
    }
    let nomor = nomorPelanggan.value.trim();
    if (!nomor) {
        showNotification("Masukkan nomor meteran listrik atau nomor HP tujuan pembeli agar transaksi valid.", "Nomor HP / ID Kosong", "warning");
        return;
    }
    let nama = namaPelanggan.value.trim();
    if (!nama) {
        showNotification("Masukkan nama pemilik akun digital untuk memvalidasi transaksi.", "Nama Akun Kosong", "warning");
        return;
    }
    let nominal = parseFloat(nominalManual.value) || 0;
    if (nominal <= 0) {
        showNotification("Masukkan nominal pembelian yang valid (harus lebih besar dari 0).", "Nominal Tidak Valid", "warning");
        return;
    }
    
    const adminFee = Number(prod.admin_fee) || 0;
    const totalHarga = nominal + adminFee;

    if (saldoDigital.value < totalHarga) {
        showNotification(`Saldo digital toko Anda saat ini tidak cukup untuk melakukan transaksi nominal ini. Tersedia: Rp ${formatRupiah(saldoDigital.value)}`, "Saldo Toko Kurang", "warning");
        return;
    }
    
    // Saldo toko berkurang HANYA sebesar nominal (modal yang disetor ke distributor)
    // Admin fee tetap menjadi keuntungan toko, tidak dikeluarkan dari saldo
    saldoDigital.value -= nominal;
    
    const digitalType = (prod.name?.toLowerCase().includes('token') || 
                         prod.name?.toLowerCase().includes('listrik') || 
                         prod.sku?.toLowerCase().includes('tkn')) 
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
        nominal: nominal
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
        ...item
    };

    if (cartItem.type === 'cetak') {
        const existingIndex = cart.value.findIndex(existing => existing.id === cartItem.id && existing.type === 'cetak');
        if (existingIndex !== -1) {
            pendingCetakItem.value = cartItem;
            duplicateCetakIndex.value = existingIndex;
            duplicateCetakDialogOpen.value = true;
            return;
        }
    }

    if (cartItem.type !== 'digital') {
        const existingItem = cart.value.find(existing => existing.id === cartItem.id && existing.type === cartItem.type);
        if (existingItem) {
            existingItem.quantity = normalizeQuantity(existingItem.quantity + cartItem.quantity);
            if (existingItem.type === 'fotokopi') {
                existingItem.detail = `${formatQuantity(existingItem.quantity)} lembar x Rp ${formatRupiah(existingItem.price)}`;
            }
            return;
        }
    }

    cart.value.push(cartItem);
};

const overwriteDuplicateCetak = () => {
    if (!pendingCetakItem.value || duplicateCetakIndex.value === -1) return;

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
    if (!pendingCetakItem.value) return;

    cart.value.push(pendingCetakItem.value);
    duplicateCetakDialogOpen.value = false;
    pendingCetakItem.value = null;
    duplicateCetakIndex.value = -1;
};

const updateQty = (item, delta) => {
    const idx = cart.value.findIndex(i => i._cartKey === item._cartKey);
    if (idx === -1) return;
    
    const newQty = normalizeQuantity(cart.value[idx].quantity + delta);
    if (newQty <= 0) {
        cart.value.splice(idx, 1);
    } else {
        cart.value[idx].quantity = newQty;
        if (cart.value[idx].is_area_based) {
            // Ukuran & luas per pcs TETAP; hanya jumlah pcs yang berubah.
            cart.value[idx].total_area = roundArea((cart.value[idx].area_per_piece || 0) * newQty);
        }
    }
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
    return props.paymentMethods?.find(method => String(method.id) === String(paymentMethodId.value)) || null;
});

const isCashPayment = computed(() => selectedPaymentMethod.value?.is_cash === true);

const selectedCustomer = computed(() => {
    return props.customers?.find(customer => String(customer.id) === String(customerId.value)) || null;
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
    if (!query) return props.customers || [];

    return props.customers.filter(customer =>
        customer.name.toLowerCase().includes(query) ||
        (customer.phone && customer.phone.toLowerCase().includes(query))
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
    if (method.is_cash) return 'fas fa-money-bill-wave';
    if (method.code?.toLowerCase().includes('qris')) return 'fas fa-qrcode';
    if (method.code?.toLowerCase().includes('transfer')) return 'fas fa-university';
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
    }
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
    if (!isCashPayment.value) return cartTotal.value;
    return Math.min(Number(uangDiterima.value) || 0, cartTotal.value);
});

const sisaTagihan = computed(() => {
    return Math.max(0, cartTotal.value - jumlahDibayar.value);
});

const kembalian = computed(() => {
    if (!isCashPayment.value) return 0;
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
        showNotification("Keranjang belanja kasir Anda saat ini masih kosong. Silakan tambahkan beberapa produk atau layanan cetak terlebih dahulu sebelum memproses pembayaran.", "Keranjang Kosong", "warning");
        return;
    }

    if (!paymentMethodId.value) {
        showNotification("Pilih metode pembayaran sebelum menyimpan transaksi.", "Metode Pembayaran Kosong", "warning");
        return;
    }

    if (useOneTimeCustomer.value && !invoiceCustomerName.value.trim()) {
        showNotification("Isi nama penerima invoice untuk customer sekali beli.", "Nama Penerima Invoice Kosong", "warning");
        return;
    }

    isMobileCartOpen.value = false;

    if (isCashPayment.value && !cashPaymentConfirmed.value) {
        cashModalOpen.value = true;
        return;
    }
    
    isProcessing.value = true;
    
    router.post('/pos', {
        cart: cart.value,
        total: cartTotal.value,
        payment_method_id: paymentMethodId.value,
        uang_diterima: isCashPayment.value ? uangDiterima.value : cartTotal.value,
        customer_id: customerId.value || null,
        invoice_customer_name: useOneTimeCustomer.value ? invoiceCustomerName.value.trim() : null,
        invoice_customer_phone: useOneTimeCustomer.value ? invoiceCustomerPhone.value.trim() || null : null,
        keterangan: keterangan.value || null,
    }, {
        onSuccess: () => {
            const flashError = usePage().props.flash?.error;
            if (flashError) {
                showNotification(flashError, "Simpan Gagal", "error");
                isProcessing.value = false;
                return;
            }

            const recentTrx = usePage().props.flash?.recent_transaction;
            if (recentTrx) {
                successTransaction.value = recentTrx;
                successDialogOpen.value = true;
            } else {
                showNotification(`Pembayaran nota kasir telah sukses diterima sebesar Rp ${formatRupiah(jumlahDibayar.value)}.`, "Pembayaran Berhasil", "success");
                resetPOSState();
            }
            isProcessing.value = false;
        },
        onError: () => {
            showNotification("Ada kesalahan tak terduga saat menyimpan transaksi ke server. Mohon periksa jaringan internet Anda dan coba lagi.", "Simpan Gagal", "error");
            isProcessing.value = false;
        }
    });
};

const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID').format(angka);
};
</script>

<template>
    <Head>
        <title>Kasir Ultima | Smart POS System</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    </Head>

    <div 
        class="flex flex-col flex-1 bg-transparent font-inter text-foreground pb-6 lg:pb-0 transition-all duration-300"
        :class="{ 'fixed inset-0 z-[100] bg-background p-6 overflow-hidden h-screen': !sidebarVisible, 'h-auto lg:absolute lg:inset-0 lg:top-16 lg:overflow-hidden p-4 md:p-6': sidebarVisible }"
    >
        <!-- Header Mode Zen Premium -->
        <div v-if="!sidebarVisible" class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-border mb-6">
            <div class="flex items-center gap-3">
                <span class="flex h-3 w-3 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </span>
                <span class="font-bold text-lg tracking-wider bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">KASIR ZEN MODE</span>
                <Badge variant="outline" class="font-mono text-xs border-emerald-500/30 text-emerald-500 bg-emerald-500/5">{{ currentTime }}</Badge>
            </div>
            
            <div class="flex items-center gap-2">
                <!-- Button Shortcut Helper -->
                <Button @click="helpDialogOpen = true" variant="outline" size="sm" class="rounded-xl flex items-center gap-1.5 border-border/80 text-xs bg-card">
                    <i class="fas fa-keyboard text-indigo-500"></i>
                    Pintasan (F2/F4)
                </Button>
                <!-- Button Clear Cart -->
                <Button @click="cart = []" variant="outline" size="sm" class="rounded-xl flex items-center gap-1.5 border-border/80 text-xs text-red-500 hover:text-red-600 bg-card">
                    <i class="fas fa-trash-alt"></i>
                    Kosongkan
                </Button>
                <!-- Exit Zen Button -->
                <Button @click="toggleZenMode" variant="default" size="sm" class="rounded-xl flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs">
                    <i class="fas fa-compress-alt"></i>
                    Keluar Zen
                </Button>
            </div>
        </div>

        <!-- Header Biasa -->
        <div v-else class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
            <div class="flex gap-3 w-full sm:w-auto">
                <Button @click="toggleZenMode" variant="outline" class="w-full sm:w-auto justify-center bg-card/80 backdrop-blur-sm px-4 py-2 rounded-xl shadow-sm text-foreground hover:bg-muted transition-all flex items-center gap-2 text-sm font-medium border border-border">
                    <i class="fas fa-expand-alt text-indigo-600 dark:text-indigo-400"></i>
                    <span>Mode Zen Kasir</span>
                </Button>
            </div>
            <div class="flex justify-between w-full sm:w-auto sm:text-right">
                <div class="text-xs text-muted-foreground sm:mb-1">👋 Selamat bekerja</div>
                <div class="text-sm font-semibold text-foreground">Medan | Hari ini</div>
            </div>
        </div>

        <!-- KARTU TRANSAKSI -->
        <div 
            class="bg-card text-card-foreground rounded-3xl shadow-2xl flex flex-col lg:flex-row flex-1 overflow-hidden transition-all duration-500 relative border border-border"
            :style="{ borderTop: `4px solid ${themeColor}` }"
        >
            <!-- Loading overlay saat proses submit -->
            <div v-if="isProcessing" class="absolute inset-0 bg-background/50 backdrop-blur-sm z-50 flex flex-col items-center justify-center">
                <i class="fas fa-circle-notch fa-spin text-4xl mb-3" :style="{ color: themeColor }"></i>
                <p class="font-bold text-foreground">Memproses Pembayaran...</p>
            </div>

            <!-- Area Kiri Dinamis (2/3) -->
            <div class="w-full lg:w-2/3 flex flex-col p-4 md:p-6 relative h-[calc(100vh-14rem)] lg:h-full">
                <!-- TAB BUTTONS -->
                <div class="grid grid-cols-3 p-1 bg-muted/40 dark:bg-muted/10 rounded-2xl gap-1 mb-6 border border-border/40 w-full max-w-xl">
                    <button 
                        v-for="(tab, idx) in tabs" 
                        :key="idx"
                        @click="handleTabChange(idx)"
                        data-click-feedback="none"
                        class="min-w-0 py-2 px-1 sm:px-4 rounded-xl font-bold text-[9px] sm:text-xs uppercase tracking-wide sm:tracking-wider transition-all duration-300 flex items-center justify-center gap-1 sm:gap-2 leading-tight text-center min-h-11 sm:h-9"
                        :class="activeTab === idx 
                            ? 'shadow-sm text-white' 
                            : 'text-muted-foreground hover:text-foreground hover:bg-muted/30'"
                        :style="activeTab === idx ? { backgroundColor: themeColor } : {}"
                    >
                        <i :class="tab.icon" class="text-[10px] sm:text-xs shrink-0"></i>
                        <span class="whitespace-normal break-words">{{ tab.name }}</span>
                    </button>
                </div>

                <!-- KONTEN -->
                <div class="flex-1 overflow-y-auto pr-2 custom-scroll relative">
                    <!-- Tab Loading Overlay -->
                    <div v-if="isTabLoading" class="absolute inset-0 bg-background/60 backdrop-blur-xs z-30 flex items-center justify-center transition-all duration-200">
                        <div class="flex flex-col items-center gap-2">
                            <i class="fas fa-circle-notch fa-spin text-2xl" :style="{ color: themeColor }"></i>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Memuat Produk...</span>
                        </div>
                    </div>

                    <transition name="fade" mode="out-in">
                        <div v-if="!isTabLoading" :key="activeTab" class="w-full">
                            <!-- TAB 0 : Barang Fisik -->
                            <div v-if="activeTab === 0" class="space-y-6">
                                <!-- Search Input Dinamis -->
                                <div class="relative w-full">
                                    <i class="fas fa-search absolute left-3.5 top-3 text-muted-foreground text-sm"></i>
                                    <Input 
                                        id="pos-search-input"
                                        type="text" 
                                        v-model="posSearchQuery" 
                                        placeholder="Cari nama barang atau SKU... (Tekan F4)" 
                                        class="pl-10 h-10 w-full rounded-xl bg-background border-border/80 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-emerald-500" 
                                    />
                                    <Button 
                                        v-if="posSearchQuery" 
                                        @click="posSearchQuery = ''" 
                                        variant="ghost" 
                                        size="sm" 
                                        class="absolute right-2 top-1.5 h-7 w-7 p-0 rounded-full hover:bg-muted text-muted-foreground"
                                    >
                                        <i class="fas fa-times text-xs"></i>
                                    </Button>
                                </div>

                                <div v-if="filteredFisikItems.length === 0" class="text-center py-10 text-muted-foreground border border-dashed border-border rounded-2xl bg-muted/5">
                                    <i class="fas fa-search-minus text-4xl mb-2 opacity-30"></i>
                                    <p class="text-sm font-medium">Produk tidak ditemukan.</p>
                                    <p class="text-xs text-muted-foreground mt-1">Coba kata kunci lain atau bersihkan pencarian.</p>
                                </div>

                                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <div 
                                        v-for="item in filteredFisikItems" 
                                        :key="item.id" 
                                        @click="addToCart({ id: item.id, name: item.name, price: item.selling_price, qty: 1, type: 'fisik' })" 
                                        class="rounded-xl p-3 cursor-pointer hover:shadow-md transition duration-200 border border-border/50 hover:border-emerald-500/40 flex items-center gap-3 bg-card hover:bg-muted/10 select-none shadow-sm relative group overflow-hidden"
                                    >
                                        <!-- Small image or initial box -->
                                        <div class="w-10 h-10 rounded-lg overflow-hidden bg-muted/40 flex items-center justify-center shrink-0 border border-border/30">
                                            <img v-if="item.image_path" :src="item.image_path" class="w-full h-full object-cover" />
                                            <span v-else class="text-xs font-black text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 w-full h-full flex items-center justify-center">
                                                {{ getInitials(item.name) }}
                                            </span>
                                        </div>
                                        
                                        <div class="flex-1 min-w-0 text-left">
                                            <p class="font-bold text-foreground text-xs leading-snug line-clamp-2" :title="item.name">{{ item.name }}</p>
                                            <p class="text-[10px] text-muted-foreground mt-0.5 font-medium">Stok: {{ parseFloat(item.stock) }} {{ item.unit }}</p>
                                            <p class="text-indigo-600 dark:text-indigo-400 text-xs font-black mt-0.5">Rp {{ formatRupiah(item.selling_price) }}</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Layanan Cetak & Fotokopi Instan (Komponen Terintegrasi) -->
                                <Card class="bg-indigo-50/5 dark:bg-indigo-950/20 rounded-2xl p-5 border-indigo-200/20 dark:border-indigo-900/40 shadow-sm">
                                    <CardHeader class="p-0 mb-4">
                                        <CardTitle class="flex items-center gap-3 text-foreground font-bold">
                                            <i class="fas fa-print text-indigo-500 dark:text-indigo-400 text-xl"></i>
                                            Layanan Cetak & Fotokopi Instan
                                        </CardTitle>
                                        <CardDescription class="text-xs text-muted-foreground">
                                            Layanan cetak fotokopi dan print langsung per lembar secara instan.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="p-0 space-y-4">
                                        <!-- Pilihan Layanan Langsung di Card (Tanpa Dropdown) -->
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 w-full">
                                            <div 
                                                v-for="service in instanServices" 
                                                :key="service.id"
                                                @click="selectedServiceId = service.id"
                                                class="rounded-2xl p-4 border cursor-pointer flex flex-col items-center justify-center text-center select-none transition-all duration-200"
                                                :class="selectedServiceId === service.id 
                                                    ? 'bg-indigo-500/10 border-indigo-500 dark:border-indigo-400 shadow-sm text-indigo-600 dark:text-indigo-400 ring-2 ring-indigo-500/20 font-bold' 
                                                    : 'bg-background hover:bg-muted/30 border-border text-foreground'"
                                            >
                                                <div class="text-xl mb-1.5">
                                                    <i v-if="String(service.id).includes('fotokopi') || (service.sku && service.sku.toLowerCase().includes('ftk'))" class="fas fa-copy"></i>
                                                    <i v-else class="fas fa-print"></i>
                                                </div>
                                                <p class="text-xs font-black leading-tight">{{ service.name }}</p>
                                                <p class="text-[11px] text-muted-foreground mt-1 font-mono font-bold">Rp {{ formatRupiah(service.price) }}/lbr</p>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-4 items-end justify-between pt-2">
                                            <!-- Jumlah Lembar -->
                                            <div class="space-y-1.5">
                                                <Label for="pos-instan-qty" class="text-xs text-muted-foreground font-bold">Jumlah Lembar</Label>
                                                <Input id="pos-instan-qty" type="number" v-model.number="instanQty" min="1" class="w-36 bg-background border border-input rounded-xl text-sm text-foreground font-bold" />
                                            </div>

                                            <div class="flex items-center gap-5 mt-2 lg:mt-0">
                                                <div class="font-medium text-sm text-foreground self-center">
                                                    Total: <span class="text-lg font-black text-indigo-600 dark:text-indigo-400">Rp {{ formatRupiah(instanQty * (selectedService?.price || 0)) }}</span>
                                                </div>
                                                <Button @click="addInstanToCart" class="px-5 font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm">+ Tambah</Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <!-- TAB 1 : Jasa Cetak (Dinamis dari Database) -->
                            <div v-if="activeTab === 1" class="space-y-6">
                                <Card class="border-border/40 p-5 rounded-2xl bg-muted/10">
                                    <CardHeader class="p-0 mb-4">
                                        <CardTitle class="flex items-center gap-3 text-foreground font-bold">
                                            <i class="fas fa-print text-orange-500 dark:text-orange-400 text-xl"></i>
                                            Layanan Cetak Jasa & Spanduk
                                        </CardTitle>
                                        <CardDescription class="text-xs text-muted-foreground">
                                            Kalkulasi dinamis berdasarkan satuan meter persegi (spanduk) atau lembaran (jasa cetak/jilid).
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="p-0 grid grid-cols-1 gap-5">
                                        <div class="space-y-1.5 jasa-dropdown-container relative">
                                            <Label class="text-sm font-semibold text-foreground">Pilih Layanan Jasa</Label>
                                            
                                            <!-- Elegant custom dropdown select -->
                                            <div class="relative">
                                                <button 
                                                    type="button" 
                                                    @click="jasaDropdownOpen = !jasaDropdownOpen"
                                                    data-click-feedback="none"
                                                    class="flex h-11 w-full items-center justify-between rounded-xl border border-input bg-background px-4 py-2 text-sm shadow-sm transition-all outline-none focus-visible:ring-2 focus-visible:ring-orange-500 text-foreground"
                                                >
                                                    <span class="font-semibold text-foreground">
                                                        {{ selectedJasaProduct ? `${selectedJasaProduct.name} (Rp ${formatRupiah(selectedJasaProduct.selling_price)} / ${selectedJasaProduct.unit})` : 'Pilih Layanan Jasa...' }}
                                                    </span>
                                                    <i class="fas fa-chevron-down text-muted-foreground transition-transform duration-200" :class="{ 'rotate-180': jasaDropdownOpen }"></i>
                                                </button>
                                                <div 
                                                    v-if="jasaDropdownOpen" 
                                                    class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-xl border border-border bg-card p-1.5 shadow-xl custom-scroll divide-y divide-border/40"
                                                >
                                                    <div class="sticky top-0 z-10 bg-card p-1.5">
                                                        <div class="relative">
                                                            <i class="fas fa-search absolute left-3 top-2.5 text-xs text-muted-foreground"></i>
                                                            <Input v-model="jasaSearchQuery" type="text" placeholder="Cari nama atau SKU jasa..." class="h-8 pl-8 rounded-lg bg-background text-xs" @click.stop />
                                                        </div>
                                                    </div>
                                                    <div 
                                                        v-for="j in filteredJasaCetakItems"
                                                        :key="j.id"
                                                        @click="selectJasaProduct(j)"
                                                        class="flex items-center justify-between px-3 py-2.5 rounded-lg hover:bg-muted/50 cursor-pointer transition text-xs"
                                                        :class="{ 'bg-orange-500/10 font-bold text-orange-600 dark:text-orange-400': activeJasaProductId === j.id }"
                                                    >
                                                        <span>{{ j.name }}</span>
                                                        <span class="font-mono text-muted-foreground">Rp {{ formatRupiah(j.selling_price) }} / {{ j.unit }}</span>
                                                    </div>
                                                    <div v-if="filteredJasaCetakItems.length === 0" class="px-3 py-4 text-center text-xs text-muted-foreground">
                                                        Layanan jasa tidak ditemukan.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Kalkulator Dinamis jika unit meter (pricing per m²) -->
                                        <div class="flex gap-4" v-if="isAreaBasedProduct(selectedJasaProduct)">
                                            <div class="w-1/3 space-y-1.5">
                                                <Label class="text-xs font-semibold text-foreground">Panjang (meter)</Label>
                                                <Input type="number" step="0.1" min="0.1" v-model.number="cetakPanjang" class="w-full bg-background border-border text-foreground" />
                                            </div>
                                            <div class="w-1/3 space-y-1.5">
                                                <Label class="text-xs font-semibold text-foreground">Lebar (meter)</Label>
                                                <Input type="number" step="0.1" min="0.1" v-model.number="cetakLebar" class="w-full bg-background border-border text-foreground" />
                                            </div>
                                            <div class="w-1/3 space-y-1.5">
                                                <Label class="text-xs font-semibold text-foreground">Jumlah (pcs)</Label>
                                                <Input type="number" step="1" min="1" v-model.number="cetakQty" class="w-full bg-background border-border text-foreground" />
                                            </div>
                                        </div>
                                        <!-- Input Quantity biasa jika unit rim / lembar / pcs dll -->
                                        <div v-else class="space-y-1.5">
                                            <Label class="text-xs font-semibold text-foreground">Jumlah ({{ selectedJasaProduct?.unit }})</Label>
                                            <Input type="number" step="1" min="1" v-model.number="cetakQty" class="w-full bg-background border-border text-foreground" />
                                        </div>

                                        <div class="space-y-1.5 vendor-dropdown-container relative">
                                            <Label class="text-xs font-semibold text-foreground">Mitra / Vendor Percetakan</Label>
                                            <button
                                                type="button"
                                                data-click-feedback="none"
                                                @click="vendorDropdownOpen = !vendorDropdownOpen"
                                                class="flex h-10 w-full items-center justify-between rounded-xl border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:ring-2 focus:ring-orange-500"
                                            >
                                                <span class="truncate">{{ selectedPrintVendor?.name || '-- Tanpa Mitra --' }}</span>
                                                <i class="fas fa-chevron-down text-xs text-muted-foreground transition-transform" :class="{ 'rotate-180': vendorDropdownOpen }"></i>
                                            </button>
                                            <div v-if="vendorDropdownOpen" class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-xl border border-border bg-card p-1.5 shadow-xl custom-scroll">
                                                <div class="sticky top-0 z-10 bg-card p-1">
                                                    <div class="relative">
                                                        <i class="fas fa-search absolute left-3 top-2.5 text-xs text-muted-foreground"></i>
                                                        <Input v-model="vendorSearchQuery" type="text" placeholder="Cari nama, telepon, atau alamat..." class="h-8 pl-8 rounded-lg bg-background text-xs" @click.stop />
                                                    </div>
                                                </div>
                                                <button type="button" data-click-feedback="none" @click="selectPrintVendor(null)" class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold hover:bg-muted/50">
                                                    -- Tanpa Mitra --
                                                </button>
                                                <button
                                                    v-for="vendor in filteredPrintVendors"
                                                    :key="vendor.id"
                                                    type="button"
                                                    data-click-feedback="none"
                                                    @click="selectPrintVendor(vendor)"
                                                    class="w-full rounded-lg px-3 py-2 text-left text-xs hover:bg-muted/50"
                                                    :class="{ 'bg-orange-500/10 font-bold text-orange-600 dark:text-orange-400': String(cetakVendorId) === String(vendor.id) }"
                                                >
                                                    <span class="block font-semibold">{{ vendor.name }}</span>
                                                    <span class="block text-[10px] text-muted-foreground">{{ vendor.phone || vendor.address || '-' }}</span>
                                                </button>
                                                <div v-if="filteredPrintVendors.length === 0" class="px-3 py-4 text-center text-xs text-muted-foreground">Mitra tidak ditemukan.</div>
                                            </div>
                                            <p class="text-[10px] text-muted-foreground">Nama mitra hanya untuk pencatatan internal dan tidak dicetak pada invoice.</p>
                                        </div>
                                        
                                        <div class="bg-muted/50 p-4 rounded-xl border border-border shadow-inner mt-2">
                                            <div class="flex flex-col gap-1.5" v-if="isAreaBasedProduct(selectedJasaProduct)">
                                                <div class="flex justify-between text-xs text-muted-foreground">
                                                    <span><i class="fas fa-calculator mr-1"></i> Luas / pcs</span>
                                                    <span class="font-mono font-semibold text-foreground">{{ formatQuantity(areaPerPiece(cetakPanjang, cetakLebar)) }} m²</span>
                                                </div>
                                                <div class="flex justify-between text-xs text-muted-foreground">
                                                    <span>Harga / pcs</span>
                                                    <span class="font-mono font-semibold text-foreground">Rp {{ formatRupiah(pricePerPiece(selectedJasaProduct.selling_price, areaPerPiece(cetakPanjang, cetakLebar))) }}</span>
                                                </div>
                                                <div class="flex justify-between text-xs text-muted-foreground" v-if="parseInt(cetakQty) > 1">
                                                    <span>Total luas ({{ parseInt(cetakQty) }} pcs)</span>
                                                    <span class="font-mono font-semibold text-foreground">{{ formatQuantity(roundArea(areaPerPiece(cetakPanjang, cetakLebar) * (parseInt(cetakQty) || 1))) }} m²</span>
                                                </div>
                                                <div class="border-t border-border/60 pt-1.5 mt-0.5 flex justify-between items-center">
                                                    <span class="text-sm text-muted-foreground font-medium"><i class="fas fa-calculator mr-1"></i> Estimasi Total</span>
                                                    <span class="font-bold text-lg text-foreground">Rp {{ formatRupiah(hargaJasaCetak) }}</span>
                                                </div>
                                            </div>
                                            <div v-else class="flex justify-between items-center">
                                                <span class="text-sm text-muted-foreground font-medium"><i class="fas fa-calculator mr-1"></i> Estimasi Harga</span>
                                                <span class="font-bold text-lg text-foreground">Rp {{ formatRupiah(hargaJasaCetak) }}</span>
                                            </div>
                                        </div>
                                        
                                        <Button @click="addCetakToCart" data-loading-mode="spinner-only" class="w-full py-6 rounded-xl text-white font-bold shadow-md text-sm transition-all hover:opacity-90 mt-2 bg-orange-600 hover:bg-orange-700">
                                            <i class="fas fa-cart-plus mr-2 text-xs"></i> Tambah ke Keranjang
                                        </Button>
                                    </CardContent>
                                </Card>
                            </div>

                            <!-- TAB 2 : Saldo Digital (Dinamis dari Database) -->
                            <div v-if="activeTab === 2" class="space-y-6">
                                <Card class="border-border/40 p-5 rounded-2xl bg-muted/10">
                                    <CardHeader class="p-0 mb-4">
                                        <CardTitle class="flex items-center gap-3 text-foreground font-bold">
                                            <i class="fas fa-bolt text-blue-500 dark:text-blue-400 text-xl animate-pulse"></i>
                                            Layanan Pembayaran Digital & PPOB
                                        </CardTitle>
                                        <CardDescription class="text-xs text-muted-foreground">
                                            Pembelian Pulsa, Kuota, Token Listrik PLN Pintar, dan Saldo E-Wallet secara cepat.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="p-0 grid grid-cols-1 gap-5">
                                        <!-- Layanan Selector -->
                                        <div class="space-y-1.5 digital-dropdown-container relative">
                                            <Label class="text-sm font-semibold text-foreground">Pilih Layanan Saldo / PPOB</Label>
                                            <button
                                                type="button"
                                                data-click-feedback="none"
                                                @click="digitalDropdownOpen = !digitalDropdownOpen"
                                                class="flex h-10 w-full items-center justify-between rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs outline-none transition-all focus-visible:ring-2 focus-visible:ring-blue-500"
                                            >
                                                <span class="truncate font-semibold">{{ selectedLayanan?.name || 'Pilih layanan digital...' }}</span>
                                                <i class="fas fa-chevron-down text-xs text-muted-foreground transition-transform" :class="{ 'rotate-180': digitalDropdownOpen }"></i>
                                            </button>
                                            <div v-if="digitalDropdownOpen" class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-xl border border-border bg-card p-1.5 shadow-xl custom-scroll">
                                                <div class="sticky top-0 z-10 bg-card p-1">
                                                    <div class="relative">
                                                        <i class="fas fa-search absolute left-3 top-2.5 text-xs text-muted-foreground"></i>
                                                        <Input v-model="digitalSearchQuery" type="text" placeholder="Cari nama atau SKU layanan..." class="h-8 pl-8 rounded-lg bg-background text-xs" @click.stop />
                                                    </div>
                                                </div>
                                                <button
                                                    v-for="service in filteredDigitalItems"
                                                    :key="service.id"
                                                    type="button"
                                                    data-click-feedback="none"
                                                    @click="selectDigitalService(service)"
                                                    class="w-full rounded-lg px-3 py-2 text-left text-xs hover:bg-muted/50"
                                                    :class="{ 'bg-blue-500/10 font-bold text-blue-600 dark:text-blue-400': selectedLayanan?.id === service.id }"
                                                >
                                                    <span class="block font-semibold">{{ service.name }}</span>
                                                    <span class="block text-[10px] font-mono text-muted-foreground">{{ service.sku || '-' }}</span>
                                                </button>
                                                <div v-if="filteredDigitalItems.length === 0" class="px-3 py-4 text-center text-xs text-muted-foreground">Layanan digital tidak ditemukan.</div>
                                            </div>
                                        </div>

                                        <!-- Nomor Pelanggan with Autocomplete -->
                                        <div class="space-y-1.5 relative">
                                            <Label class="text-xs font-semibold text-foreground">
                                                {{ selectedLayanan?.name?.toLowerCase().includes('token') || selectedLayanan?.name?.toLowerCase().includes('listrik') ? 'Nomor Meter / ID Pelanggan' : 'Nomor HP Tujuan / ID' }}
                                            </Label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-3 text-muted-foreground text-xs">
                                                    <i v-if="selectedLayanan?.name?.toLowerCase().includes('token') || selectedLayanan?.name?.toLowerCase().includes('listrik')" class="fas fa-plug text-blue-500"></i>
                                                    <i v-else class="fas fa-mobile-alt text-blue-500"></i>
                                                </span>
                                                <Input
                                                    type="text"
                                                    v-model="nomorPelanggan"
                                                    placeholder="Ketik nomor pelanggan..."
                                                    class="w-full bg-background border-border text-foreground rounded-xl pl-9 h-10"
                                                    @input="searchDigitalAccounts(nomorPelanggan, 'number')"
                                                    @focus="searchDigitalAccounts(nomorPelanggan, 'number')"
                                                    @blur="hideDigitalAutocomplete"
                                                />
                                            </div>

                                            <!-- Autocomplete List -->
                                            <div v-if="showAutocomplete && activeAutocompleteField === 'number' && autocompleteResults.length > 0" class="absolute left-0 right-0 z-50 mt-1 bg-card border border-border rounded-2xl shadow-2xl max-h-48 overflow-y-auto divide-y divide-border/60">
                                                <div 
                                                    v-for="account in autocompleteResults" 
                                                    :key="account.id"
                                                    @mousedown.prevent="selectAccount(account)"
                                                    class="px-4 py-3 hover:bg-blue-500/10 cursor-pointer text-xs transition-colors flex justify-between items-center"
                                                >
                                                    <div>
                                                        <p class="font-bold text-foreground">{{ account.account_number }}</p>
                                                        <p class="text-[10px] text-muted-foreground">{{ account.account_name }}</p>
                                                    </div>
                                                    <Badge variant="outline" class="text-[9px] uppercase border-blue-500/30 text-blue-500 bg-blue-500/5">{{ account.type }}</Badge>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Nama Pelanggan -->
                                        <div class="space-y-1.5 relative">
                                            <Label class="text-xs font-semibold text-foreground">Nama Pemilik Akun / Pelanggan</Label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-3 text-muted-foreground text-xs">
                                                    <i class="fas fa-user-circle text-blue-500"></i>
                                                </span>
                                                <Input
                                                    type="text"
                                                    v-model="namaPelanggan"
                                                    placeholder="Ketik nama pemilik akun..."
                                                    class="w-full bg-background border-border text-foreground rounded-xl pl-9 h-10"
                                                    @input="searchDigitalAccounts(namaPelanggan, 'name')"
                                                    @focus="searchDigitalAccounts(namaPelanggan, 'name')"
                                                    @blur="hideDigitalAutocomplete"
                                                />
                                            </div>

                                            <div v-if="showAutocomplete && activeAutocompleteField === 'name' && autocompleteResults.length > 0" class="absolute left-0 right-0 z-50 mt-1 bg-card border border-border rounded-2xl shadow-2xl max-h-48 overflow-y-auto divide-y divide-border/60">
                                                <div
                                                    v-for="account in autocompleteResults"
                                                    :key="account.id"
                                                    @mousedown.prevent="selectAccount(account)"
                                                    class="px-4 py-3 hover:bg-blue-500/10 cursor-pointer text-xs transition-colors flex justify-between items-center"
                                                >
                                                    <div>
                                                        <p class="font-bold text-foreground">{{ account.account_name }}</p>
                                                        <p class="text-[10px] text-muted-foreground">{{ account.account_number }}</p>
                                                    </div>
                                                    <Badge variant="outline" class="text-[9px] uppercase border-blue-500/30 text-blue-500 bg-blue-500/5">{{ account.type }}</Badge>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Nominal Manual -->
                                        <div class="space-y-1.5">
                                            <Label class="text-xs font-semibold text-foreground">Nominal Pembelian (Rupiah)</Label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-3 text-muted-foreground text-xs font-bold font-mono">Rp</span>
                                                <Input type="number" v-model.number="nominalManual" placeholder="Masukkan nominal (ex: 50000)" class="w-full bg-background border-border text-foreground rounded-xl pl-9 h-10 font-bold" />
                                            </div>
                                        </div>

                                        <!-- Realtime Total Breakdown -->
                                        <div class="grid grid-cols-2 gap-4 mt-2 bg-blue-50/5 dark:bg-blue-950/20 border border-blue-200/20 dark:border-blue-900/40 p-4 rounded-2xl shadow-inner">
                                            <div class="text-xs space-y-1 text-muted-foreground">
                                                <p>Nominal: <span class="font-mono text-foreground font-semibold">Rp {{ formatRupiah(nominalManual || 0) }}</span></p>
                                                <p>Biaya Admin: <span class="font-mono text-foreground font-semibold">Rp {{ formatRupiah(selectedLayanan ? selectedLayanan.admin_fee : 0) }}</span></p>
                                            </div>
                                            <div class="text-right flex flex-col justify-center">
                                                <span class="text-[9px] text-muted-foreground font-black uppercase tracking-wider">Total Biaya</span>
                                                <span class="font-black text-lg text-blue-600 dark:text-blue-400 font-mono">Rp {{ formatRupiah(totalBiaya) }}</span>
                                            </div>
                                        </div>

                                        <div class="bg-blue-50/5 dark:bg-blue-950/20 border border-blue-200/40 dark:border-blue-900/40 p-3.5 rounded-xl flex justify-between items-center shadow-inner">
                                            <span class="text-sm text-foreground flex items-center gap-1.5"><i class="fas fa-wallet text-blue-500 dark:text-blue-400"></i> Saldo Digital Toko:</span>
                                            <span class="font-black text-blue-600 dark:text-blue-400">Rp {{ formatRupiah(saldoDigital) }}</span>
                                        </div>

                                        <Button @click="beliSaldoDigital" class="w-full py-6 rounded-xl text-white font-bold shadow-md text-sm transition-all hover:opacity-90 mt-2 bg-blue-600 hover:bg-blue-700">
                                            <i class="fas fa-cart-plus mr-2 text-xs"></i> Beli & Tambah ke Keranjang
                                        </Button>
                                        <p class="text-[10px] text-muted-foreground text-center mt-1">*Saldo akan terpotong dari saldo digital toko saat transaksi dimasukkan</p>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>

            <!-- SISI KANAN KARTU: STRUK VIRTUAL -->
            <div class="hidden lg:flex w-full lg:w-1/3 bg-muted/30 lg:bg-muted/50 border-t lg:border-t-0 lg:border-l border-border flex-col p-4 md:p-6 h-[50vh] lg:h-full relative shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.1)] lg:shadow-none z-10 overflow-y-auto custom-scroll">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-border">
                    <h2 class="font-bold text-lg flex gap-2 items-center text-foreground"><i class="fas fa-receipt text-indigo-500 dark:text-indigo-400"></i> Keranjang</h2>
                    <span class="text-xs bg-background text-foreground border border-border px-2 py-1 rounded-full shadow-sm">{{ cart.length }} item</span>
                </div>
                <div class="flex-1 min-h-[180px] overflow-y-auto cart-scroll space-y-2 pr-1">
                    <div v-if="cart.length === 0" class="text-center text-muted-foreground mt-10">
                        <i class="fas fa-shopping-cart text-4xl mb-2 opacity-30"></i>
                        <p>Keranjang kosong</p>
                    </div>
                    <div v-for="(item, idx) in cart" :key="item._cartKey" class="bg-background rounded-xl p-2.5 shadow-sm border border-border relative group">
                        <div class="flex justify-between">
                            <div class="w-[70%]">
                                <p class="font-bold text-xs leading-tight text-foreground truncate" :title="item.name">{{ item.name }}</p>
                                <div class="mt-1">
                                    <input 
                                        type="text" 
                                        v-model="item.note" 
                                        placeholder="Catatan item..." 
                                        class="w-full bg-muted/40 dark:bg-muted/20 border-none rounded-md px-1.5 py-0.5 text-[10px] font-medium text-foreground focus:ring-1 focus:ring-indigo-500/50 outline-none placeholder:text-muted-foreground/60"
                                    />
                                </div>
                                <template v-if="item.is_area_based">
                                    <p class="text-[10px] text-muted-foreground font-medium mt-1">
                                        Luas/pcs: {{ formatQuantity(item.area_per_piece) }} m² &middot; Total luas: {{ formatQuantity(item.total_area) }} m²
                                    </p>
                                </template>
                                <div class="flex items-center gap-1.5 mt-1.5">
                                    <Button @click="updateQty(item, -1)" variant="ghost" size="sm" data-click-feedback="none" class="w-5 h-5 p-0 flex items-center justify-center rounded-full bg-muted hover:bg-accent text-foreground text-[10px] transition">-</Button>
                                    <span class="text-xs font-bold min-w-4 text-center text-foreground">{{ formatQuantity(item.quantity) }}</span>
                                    <Button @click="updateQty(item, 1)" variant="ghost" size="sm" data-click-feedback="none" class="w-5 h-5 p-0 flex items-center justify-center rounded-full bg-muted hover:bg-accent text-foreground text-[10px] transition">+</Button>
                                </div>
                            </div>
                            <div class="text-right flex flex-col justify-between w-[28%]">
                                <p class="font-black text-indigo-700 dark:text-indigo-400 text-xs">Rp {{ formatRupiah(item.price * item.quantity) }}</p>
                                <Button @click="removeFromCart(idx)" variant="ghost" size="sm" class="text-red-400 text-[10px] mt-1 self-end hover:text-red-600 dark:hover:text-red-400 bg-red-50 dark:bg-red-900/30 w-5 h-5 p-0 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition"><i class="fas fa-trash-alt text-[9px]"></i></Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CUSTOMER SELECT & INVOICE NOTES -->
                <div class="mt-4 pt-4 border-t border-border space-y-3.5 bg-muted/20 p-3.5 rounded-2xl border border-border/50">
                    <!-- Customer Dropdown -->
                    <div class="space-y-1.5 customer-dropdown-container relative">
                        <Label class="text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <i class="fas fa-user-circle text-indigo-500 text-[10px]"></i>
                            Pilih Customer
                        </Label>
                        <button type="button" @click="customerDropdownOpen = !customerDropdownOpen" data-click-feedback="none" class="flex h-9 w-full items-center justify-between rounded-xl border border-input bg-background px-3 py-1 text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] text-foreground">
                            <span class="truncate">{{ selectedCustomerLabel }}</span>
                            <i class="fas fa-chevron-down text-[10px] text-muted-foreground transition-transform" :class="{ 'rotate-180': customerDropdownOpen }"></i>
                        </button>
                        <div v-if="customerDropdownOpen" class="absolute left-0 right-0 z-50 mt-1 max-h-56 overflow-y-auto rounded-xl border border-border bg-card p-1.5 shadow-xl custom-scroll">
                            <div class="sticky top-0 z-10 bg-card p-1">
                                <div class="relative">
                                    <i class="fas fa-search absolute left-3 top-2.5 text-xs text-muted-foreground"></i>
                                    <Input v-model="customerSearchQuery" type="text" placeholder="Cari nama atau telepon..." class="h-8 pl-8 rounded-lg bg-background text-xs" @click.stop />
                                </div>
                            </div>
                            <button type="button" @click="selectCustomer(null)" data-click-feedback="none" class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold hover:bg-muted/50">
                                -- Cash / Umum --
                            </button>
                            <button type="button" @click="selectOneTimeCustomer" data-click-feedback="none" class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-indigo-600 hover:bg-indigo-500/10 dark:text-indigo-400">
                                <span class="block">Customer Sekali Beli / Invoice</span>
                                <span class="block text-[10px] font-normal text-muted-foreground">Tidak disimpan ke master pelanggan</span>
                            </button>
                            <button v-for="cust in filteredCustomers" :key="cust.id" type="button" @click="selectCustomer(cust)" data-click-feedback="none" class="w-full rounded-lg px-3 py-2 text-left text-xs hover:bg-muted/50" :class="{ 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-bold': String(customerId) === String(cust.id) }">
                                <span class="block font-semibold">{{ cust.name }}</span>
                                <span class="block text-[10px] text-muted-foreground">{{ cust.phone || '-' }}</span>
                            </button>
                            <div v-if="filteredCustomers.length === 0" class="px-3 py-4 text-center text-xs text-muted-foreground">Customer tidak ditemukan.</div>
                        </div>
                    </div>

                    <div v-if="useOneTimeCustomer" class="grid grid-cols-1 gap-2 rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-3">
                        <Input v-model="invoiceCustomerName" type="text" placeholder="Nama penerima invoice *" class="h-8 rounded-lg bg-background text-xs" />
                        <Input v-model="invoiceCustomerPhone" type="text" placeholder="Nomor telepon (opsional)" class="h-8 rounded-lg bg-background text-xs" />
                    </div>

                    <!-- Keterangan Invoice -->
                    <div class="space-y-1.5">
                        <Label for="pos-keterangan" class="text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <i class="fas fa-sticky-note text-indigo-500 text-[10px]"></i>
                            Catatan / Keterangan Invoice
                        </Label>
                        <textarea id="pos-keterangan" v-model="keterangan" rows="2" placeholder="Keterangan transaksi..." class="flex min-h-[45px] w-full rounded-xl border border-input bg-background px-3 py-1.5 text-xs shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 text-foreground border-border"></textarea>
                    </div>
                </div>

                <!-- RINGKASAN PEMBAYARAN -->
                <div class="mt-4 pt-4 border-t border-border space-y-3 bg-muted/40 dark:bg-muted/10 p-4 rounded-2xl border border-border/50">
                    <!-- Subtotal -->
                    <div class="flex justify-between items-center text-xs font-semibold text-muted-foreground">
                        <span>Subtotal Belanja</span>
                        <span class="font-mono text-foreground">Rp {{ formatRupiah(cartTotal) }}</span>
                    </div>

                    <!-- Metode Pembayaran -->
                    <div class="space-y-1.5">
                        <button type="button" @click="paymentMethodsOpen = !paymentMethodsOpen" data-click-feedback="none" class="flex w-full items-center justify-between rounded-xl border border-border bg-background px-3 py-2 text-left transition hover:bg-muted/30">
                            <span>
                                <span class="block text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground">Metode Pembayaran</span>
                                <span class="block text-xs font-bold text-foreground">{{ selectedPaymentMethod?.name || 'Pilih metode pembayaran' }}</span>
                            </span>
                            <i class="fas fa-chevron-down text-xs text-muted-foreground transition-transform" :class="{ 'rotate-180': paymentMethodsOpen }"></i>
                        </button>
                        <div v-if="paymentMethodsOpen" class="grid grid-cols-2 gap-2 rounded-xl border border-border/60 bg-muted/20 p-2">
                            <button
                                v-for="method in paymentMethods"
                                :key="method.id"
                                type="button"
                                @click="selectPaymentMethod(method)"
                                class="rounded-xl border p-2.5 text-left transition-all"
                                :class="String(paymentMethodId) === String(method.id) ? 'border-indigo-500 bg-indigo-500/10 ring-1 ring-indigo-500/30' : 'border-border bg-background hover:border-indigo-500/40 hover:bg-muted/40'"
                            >
                                <span class="flex items-center gap-2">
                                    <i :class="getPaymentMethodIcon(method)" class="text-indigo-500 text-xs"></i>
                                    <span class="text-[11px] font-bold text-foreground leading-tight">{{ method.name }}</span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Kembalian / Sisa Tagihan -->
                    <div v-if="paymentMethodId" class="flex justify-between items-center text-xs font-bold pt-1">
                        <span class="text-muted-foreground">{{ isCashPayment && kembalian > 0 ? 'Kembalian:' : 'Sisa Tagihan:' }}</span>
                        <span :class="sisaTagihan > 0 ? 'text-red-500 animate-pulse' : 'text-emerald-500'" class="font-mono">
                            Rp {{ formatRupiah(isCashPayment && kembalian > 0 ? kembalian : sisaTagihan) }}
                        </span>
                    </div>

                    <!-- Total Block (Kontras Lembut) -->
                    <div class="bg-indigo-500/10 dark:bg-indigo-500/20 p-3 rounded-xl flex justify-between items-center border border-indigo-500/20 shadow-sm mt-1">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-foreground">Total Akhir</span>
                        <span class="font-black text-xl text-indigo-600 dark:text-indigo-400 font-mono">Rp {{ formatRupiah(cartTotal) }}</span>
                    </div>

                    <!-- Action Button -->
                    <Button 
                        @click="prosesBayar" 
                        :disabled="isProcessing" 
                        class="w-full py-5 rounded-xl text-white font-extrabold shadow-md transition-all hover:opacity-95 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center text-xs uppercase tracking-wider h-11" 
                        :style="{ backgroundColor: themeColor }"
                    >
                        <i class="fas fa-credit-card mr-2" v-if="!isProcessing"></i> 
                        {{ isProcessing ? 'Memproses...' : 'Bayar Sekarang (F2)' }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- ==================== MOBILE FLOATING CART & BOTTOM SHEET ==================== -->

        <!-- BACKDROP OVERLAY -->
        <div 
            v-if="isMobileCartOpen"
            @click="isMobileCartOpen = false"
            class="fixed inset-0 bg-black/60 backdrop-blur-xs lg:hidden transition-all duration-300"
            :class="cashModalOpen ? 'z-40' : 'z-[100]'"
        ></div>

        <!-- BOTTOM SHEET DETAIL KERANJANG -->
        <div 
            class="fixed bottom-0 left-0 right-0 max-h-[85vh] bg-card border-t border-border rounded-t-[2.5rem] lg:hidden flex flex-col transition-all duration-500 ease-out shadow-2xl overflow-hidden transform"
            :class="[
                isMobileCartOpen ? 'translate-y-0' : 'translate-y-full',
                cashModalOpen ? 'z-40' : 'z-[101]',
            ]"
        >
            <!-- Drag Handle Indicator -->
            <div class="w-12 h-1.5 bg-muted rounded-full mx-auto my-3.5 opacity-60"></div>

            <!-- Header -->
            <div class="flex justify-between items-center px-6 pb-3 border-b border-border/60">
                <div>
                    <h3 class="text-base font-black tracking-tight text-foreground flex items-center gap-2">
                        <i class="fas fa-receipt text-indigo-500"></i> Detail Keranjang
                    </h3>
                    <p class="text-xs text-muted-foreground">Periksa kembali pesanan pelanggan Anda.</p>
                </div>
                <button 
                    @click="isMobileCartOpen = false"
                    class="w-8 h-8 rounded-full bg-muted flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-accent transition"
                >
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Scrollable List -->
            <div class="overflow-y-auto px-5 py-4 flex-1 space-y-3 custom-scroll">
                <div v-for="(item, idx) in cart" :key="item._cartKey" class="bg-muted/35 dark:bg-muted/10 rounded-xl p-2.5 border border-border/50 flex justify-between items-center">
                    <div class="w-[70%]">
                        <p class="font-bold text-xs leading-tight truncate text-foreground" :title="item.name">{{ item.name }}</p>
                        <div class="mt-1">
                            <input 
                                type="text" 
                                v-model="item.note" 
                                placeholder="Catatan item..." 
                                class="w-full bg-background border-none rounded-md px-1.5 py-0.5 text-[10px] font-medium text-foreground focus:ring-1 focus:ring-indigo-500/50 outline-none placeholder:text-muted-foreground/60"
                            />
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            <Button @click="updateQty(item, -1)" variant="ghost" size="sm" data-click-feedback="none" class="w-5 h-5 p-0 flex items-center justify-center rounded-full bg-background hover:bg-muted text-foreground text-xs transition border border-border">-</Button>
                            <span class="text-xs font-bold min-w-4 text-center text-foreground">{{ formatQuantity(item.quantity) }}</span>
                            <Button @click="updateQty(item, 1)" variant="ghost" size="sm" data-click-feedback="none" class="w-5 h-5 p-0 flex items-center justify-center rounded-full bg-background hover:bg-muted text-foreground text-xs transition border border-border">+</Button>
                        </div>
                    </div>
                    <div class="text-right flex flex-col items-end justify-between min-h-[55px] w-[28%]">
                        <p class="font-black text-indigo-600 dark:text-indigo-400 text-xs">Rp {{ formatRupiah(item.price * item.quantity) }}</p>
                        <Button @click="removeFromCart(idx)" variant="ghost" size="sm" class="text-rose-500 hover:text-rose-600 bg-rose-50 dark:bg-rose-950/30 w-5 h-5 p-0 rounded-full flex items-center justify-center transition mt-1">
                            <i class="fas fa-trash-alt text-xs"></i>
                        </Button>
                    </div>
                </div>

                <!-- Customer Select & Invoice Notes (Mobile Mode) -->
                <div class="space-y-3.5 bg-muted/20 p-4 rounded-2xl border border-border/50 mt-4">
                    <!-- Customer Dropdown -->
                    <div class="space-y-1.5 customer-dropdown-container relative">
                        <Label class="text-xs font-bold text-muted-foreground flex items-center gap-1.5">
                            <i class="fas fa-user-circle text-indigo-500 text-[11px]"></i>
                            Pilih Customer (Opsional)
                        </Label>
                        <button type="button" @click="customerDropdownOpen = !customerDropdownOpen" data-click-feedback="none" class="flex h-9 w-full items-center justify-between rounded-xl border border-input bg-background px-3 py-1 text-xs shadow-xs outline-none text-foreground">
                            <span class="truncate">{{ selectedCustomerLabel }}</span>
                            <i class="fas fa-chevron-down text-[10px] text-muted-foreground transition-transform" :class="{ 'rotate-180': customerDropdownOpen }"></i>
                        </button>
                        <div v-if="customerDropdownOpen" class="absolute left-0 right-0 z-50 mt-1 max-h-56 overflow-y-auto rounded-xl border border-border bg-card p-1.5 shadow-xl custom-scroll">
                            <div class="sticky top-0 z-10 bg-card p-1">
                                <div class="relative">
                                    <i class="fas fa-search absolute left-3 top-2.5 text-xs text-muted-foreground"></i>
                                    <Input v-model="customerSearchQuery" type="text" placeholder="Cari nama atau telepon..." class="h-8 pl-8 rounded-lg bg-background text-xs" @click.stop />
                                </div>
                            </div>
                            <button type="button" @click="selectCustomer(null)" data-click-feedback="none" class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold hover:bg-muted/50">-- Cash / Umum --</button>
                            <button type="button" @click="selectOneTimeCustomer" data-click-feedback="none" class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-indigo-600 hover:bg-indigo-500/10 dark:text-indigo-400">
                                <span class="block">Customer Sekali Beli / Invoice</span>
                                <span class="block text-[10px] font-normal text-muted-foreground">Tidak disimpan ke master pelanggan</span>
                            </button>
                            <button v-for="cust in filteredCustomers" :key="cust.id" type="button" @click="selectCustomer(cust)" data-click-feedback="none" class="w-full rounded-lg px-3 py-2 text-left text-xs hover:bg-muted/50" :class="{ 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-bold': String(customerId) === String(cust.id) }">
                                <span class="block font-semibold">{{ cust.name }}</span>
                                <span class="block text-[10px] text-muted-foreground">{{ cust.phone || '-' }}</span>
                            </button>
                            <div v-if="filteredCustomers.length === 0" class="px-3 py-4 text-center text-xs text-muted-foreground">Customer tidak ditemukan.</div>
                        </div>
                    </div>

                    <div v-if="useOneTimeCustomer" class="grid grid-cols-1 gap-2 rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-3">
                        <Input v-model="invoiceCustomerName" type="text" placeholder="Nama penerima invoice *" class="h-8 rounded-lg bg-background text-xs" />
                        <Input v-model="invoiceCustomerPhone" type="text" placeholder="Nomor telepon (opsional)" class="h-8 rounded-lg bg-background text-xs" />
                    </div>

                    <!-- Keterangan Invoice -->
                    <div class="space-y-1.5">
                        <Label for="pos-keterangan-mobile" class="text-xs font-bold text-muted-foreground flex items-center gap-1.5">
                            <i class="fas fa-sticky-note text-indigo-500 text-[11px]"></i>
                            Catatan / Keterangan Invoice
                        </Label>
                        <textarea id="pos-keterangan-mobile" v-model="keterangan" rows="2" placeholder="Misal: Spanduk 3x1 meter, DP Rp 50.000..." class="flex min-h-[50px] w-full rounded-xl border border-input bg-background px-3 py-2 text-xs shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 text-foreground border-border"></textarea>
                    </div>

                    <!-- Jumlah Uang Dibayar (Mobile) -->
                    <div class="space-y-1.5 mt-3">
                        <button type="button" @click="paymentMethodsOpen = !paymentMethodsOpen" data-click-feedback="none" class="flex w-full items-center justify-between rounded-xl border border-border bg-background px-3 py-2 text-left">
                            <span>
                                <span class="block text-[10px] font-bold text-muted-foreground">Metode Pembayaran</span>
                                <span class="block text-xs font-bold text-foreground">{{ selectedPaymentMethod?.name || 'Pilih metode pembayaran' }}</span>
                            </span>
                            <i class="fas fa-chevron-down text-xs text-muted-foreground transition-transform" :class="{ 'rotate-180': paymentMethodsOpen }"></i>
                        </button>
                        <div v-if="paymentMethodsOpen" class="grid grid-cols-2 gap-2 rounded-xl border border-border/60 bg-muted/20 p-2">
                            <button
                                v-for="method in paymentMethods"
                                :key="method.id"
                                type="button"
                                @click="selectPaymentMethod(method)"
                                class="rounded-xl border p-3 text-left transition-all"
                                :class="String(paymentMethodId) === String(method.id) ? 'border-indigo-500 bg-indigo-500/10 ring-1 ring-indigo-500/30' : 'border-border bg-background hover:border-indigo-500/40'"
                            >
                                <span class="flex items-center gap-2">
                                    <i :class="getPaymentMethodIcon(method)" class="text-indigo-500 text-sm"></i>
                                    <span class="text-xs font-bold text-foreground">{{ method.name }}</span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-if="paymentMethodId" class="space-y-1.5 mt-3">
                        <div class="flex justify-between items-center text-[10px] font-bold mt-1 px-1">
                            <span class="text-muted-foreground">{{ kembalian > 0 ? 'Kembalian:' : 'Sisa Tagihan:' }}</span>
                            <span :class="sisaTagihan > 0 ? 'text-red-500 animate-pulse' : 'text-emerald-500'">
                                Rp {{ formatRupiah(kembalian > 0 ? kembalian : sisaTagihan) }}
                             </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Sticky Footer -->
            <div class="border-t border-border p-5 bg-card sticky bottom-0 space-y-3 pb-8">
                <div class="flex justify-between items-center font-bold text-base text-foreground">
                    <span>Total Pembayaran</span>
                    <span class="text-base font-black text-indigo-600 dark:text-indigo-400">Rp {{ formatRupiah(cartTotal) }}</span>
                </div>
                <Button @click="prosesBayar" :disabled="isProcessing" class="w-full py-6 rounded-xl text-white font-bold shadow-lg transition-all hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center" :style="{ backgroundColor: themeColor }">
                    <i class="fas fa-credit-card mr-2" v-if="!isProcessing"></i> 
                    {{ isProcessing ? 'MEMPROSES...' : 'BAYAR SEKARANG' }}
                </Button>
            </div>
        </div>

        <!-- DIALOG INPUT UANG TUNAI -->
        <Dialog :open="cashModalOpen" @update:open="cashModalOpen = $event">
            <DialogContent class="sm:max-w-[420px] rounded-3xl bg-card border-border text-foreground p-6">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-money-bill-wave text-emerald-500"></i>
                        Input Uang Diterima
                    </DialogTitle>
                    <DialogDescription>
                        Masukkan nominal tunai melalui keyboard atau tombol angka di layar.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div class="rounded-2xl border border-border bg-muted/30 p-4 space-y-2">
                        <div class="flex justify-between text-xs text-muted-foreground">
                            <span>Total Belanja</span>
                            <span class="font-mono font-bold text-foreground">Rp {{ formatRupiah(cartTotal) }}</span>
                        </div>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-sm text-muted-foreground font-bold">Rp</span>
                            <Input
                                id="cash-received-input"
                                v-model.number="uangDiterima"
                                type="number"
                                min="0"
                                class="h-12 pl-11 rounded-xl bg-background border-border text-right text-xl font-black font-mono text-foreground"
                                autofocus
                                @input="cashKeypadFresh = false"
                            />
                        </div>
                        <div class="flex justify-between text-sm font-bold pt-1">
                            <span class="text-muted-foreground">{{ kembalian > 0 ? 'Kembalian' : 'Sisa Tagihan' }}</span>
                            <span :class="sisaTagihan > 0 ? 'text-red-500' : 'text-emerald-500'" class="font-mono">
                                Rp {{ formatRupiah(kembalian > 0 ? kembalian : sisaTagihan) }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <button v-for="digit in ['1', '2', '3', '4', '5', '6', '7', '8', '9']" :key="digit" type="button" @click="appendCashDigit(digit)" data-click-feedback="none" class="h-12 rounded-xl border border-border bg-background hover:bg-muted text-lg font-black text-foreground transition">
                            {{ digit }}
                        </button>
                        <button type="button" @click="uangDiterimaInput = 0; cashKeypadFresh = false" data-click-feedback="none" class="h-12 rounded-xl border border-red-500/20 bg-red-500/5 hover:bg-red-500/10 text-sm font-black text-red-500 transition">C</button>
                        <button type="button" @click="appendCashDigit('0')" data-click-feedback="none" class="h-12 rounded-xl border border-border bg-background hover:bg-muted text-lg font-black text-foreground transition">0</button>
                        <button type="button" @click="removeCashDigit" data-click-feedback="none" class="h-12 rounded-xl border border-border bg-background hover:bg-muted text-foreground transition">
                            <i class="fas fa-backspace"></i>
                        </button>
                    </div>

                    <Button type="button" variant="outline" @click="setExactCash" class="w-full rounded-xl border-emerald-500/30 text-emerald-600 dark:text-emerald-400 font-bold">
                        <i class="fas fa-check mr-2"></i> Uang Pas
                    </Button>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary" class="rounded-xl">Batal</Button>
                    </DialogClose>
                    <Button type="button" @click="confirmCashPayment" data-loading-mode="spinner-only" class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">
                        Gunakan Nominal
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG DUPLIKAT JASA CETAK -->
        <Dialog :open="duplicateCetakDialogOpen" @update:open="duplicateCetakDialogOpen = $event">
            <DialogContent class="sm:max-w-[440px] rounded-3xl bg-card border-border text-foreground p-6">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-print text-orange-500"></i>
                        Jasa Cetak Sudah Ada
                    </DialogTitle>
                    <DialogDescription>
                        Produk jasa cetak yang sama sudah ada di keranjang. Pilih cara memasukkan data terbaru.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="pendingCetakItem && duplicateCetakIndex !== -1" class="space-y-3 py-2">
                    <div class="rounded-2xl border border-border bg-muted/30 p-4">
                        <p class="text-sm font-bold text-foreground">{{ pendingCetakItem.name }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ pendingCetakItem.detail }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="rounded-xl border border-border bg-background p-3">
                            <span class="block text-muted-foreground">Jumlah di keranjang</span>
                            <span class="mt-1 block text-lg font-black text-foreground">{{ formatQuantity(cart[duplicateCetakIndex]?.quantity) }}</span>
                        </div>
                        <div class="rounded-xl border border-orange-500/20 bg-orange-500/5 p-3">
                            <span class="block text-muted-foreground">Jumlah input baru</span>
                            <span class="mt-1 block text-lg font-black text-orange-600 dark:text-orange-400">{{ formatQuantity(pendingCetakItem.quantity) }}</span>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:grid sm:grid-cols-2">
                    <Button type="button" variant="outline" data-click-feedback="none" class="rounded-xl" @click="keepDuplicateCetak">
                        <i class="fas fa-plus text-xs"></i>
                        Tetap Masukkan Baru
                    </Button>
                    <Button type="button" data-click-feedback="none" class="rounded-xl bg-orange-600 text-white hover:bg-orange-700" @click="overwriteDuplicateCetak">
                        <i class="fas fa-pen text-xs"></i>
                        Timpa Jumlah Lama
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG SHORTCUT HELPER -->
        <Dialog :open="helpDialogOpen" @update:open="helpDialogOpen = $event">
            <DialogContent class="sm:max-w-[450px] rounded-2xl bg-card border-border text-foreground">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-keyboard text-emerald-500"></i>
                        Pintasan Keyboard POS Kasir
                    </DialogTitle>
                    <DialogDescription>
                        Gunakan tombol pintasan berikut untuk mempercepat proses transaksi di kasir:
                    </DialogDescription>
                </DialogHeader>
                
                <div class="space-y-4 my-2">
                    <div class="flex justify-between items-center p-2.5 rounded-xl bg-muted/40 border border-border/50">
                        <span class="text-sm font-semibold flex items-center gap-2">
                            <i class="fas fa-search text-muted-foreground text-xs"></i> Cari Produk Fisik
                        </span>
                        <kbd class="px-2.5 py-1 text-xs font-mono bg-background text-foreground border border-border rounded-lg shadow-sm font-bold">F4</kbd>
                    </div>
                    
                    <div class="flex justify-between items-center p-2.5 rounded-xl bg-muted/40 border border-border/50">
                        <span class="text-sm font-semibold flex items-center gap-2">
                            <i class="fas fa-credit-card text-muted-foreground text-xs"></i> Bayar Sekarang
                        </span>
                        <kbd class="px-2.5 py-1 text-xs font-mono bg-background text-foreground border border-border rounded-lg shadow-sm font-bold">F2</kbd>
                    </div>
                    
                    <div class="flex justify-between items-center p-2.5 rounded-xl bg-muted/40 border border-border/50">
                        <span class="text-sm font-semibold flex items-center gap-2">
                            <i class="fas fa-times-circle text-muted-foreground text-xs"></i> Bersihkan Kolom Cari
                        </span>
                        <kbd class="px-2.5 py-1 text-xs font-mono bg-background text-foreground border border-border rounded-lg shadow-sm font-bold">ESC</kbd>
                    </div>
                </div>

                <DialogFooter class="sm:justify-end">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary" class="rounded-xl">
                            Tutup
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG SUCCESS TRANSACTION SUMMARY (PREVIEW INVOICE) -->
        <Dialog :open="successDialogOpen" @update:open="successDialogOpen = $event">
            <DialogContent class="sm:max-w-[500px] rounded-3xl bg-card border-border text-foreground overflow-y-auto max-h-[85vh] shadow-2xl p-6">
                <div class="flex flex-col items-center text-center pb-4 border-b border-border">
                    <div class="w-16 h-16 rounded-full bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-500 flex items-center justify-center text-3xl mb-3 animate-bounce">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <DialogTitle class="text-xl font-black text-foreground">Transaksi Berhasil Disimpan!</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground mt-1">
                        Nota belanja telah dicatat dalam database pos kasir secara permanen.
                    </DialogDescription>
                </div>

                <div v-if="successTransaction" class="space-y-4 my-2 text-sm">
                    <!-- Detail Ringkas Transaksi -->
                    <div class="bg-muted/40 p-4 rounded-2xl border border-border space-y-2.5">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-muted-foreground">Nomor Invoice:</span>
                            <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ successTransaction.invoice_number }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-muted-foreground">Pelanggan:</span>
                            <span class="font-bold">{{ successTransaction.customer_name || 'Cash / Umum' }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-muted-foreground">Status Pembayaran:</span>
                            <span 
                                class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                                :class="successTransaction.status_bayar === 'lunas' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : (successTransaction.status_bayar === 'dp' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-red-500/10 text-red-600 dark:text-red-400')"
                            >
                                {{ successTransaction.status_bayar }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-muted-foreground">Metode Pembayaran:</span>
                            <span class="font-bold">{{ successTransaction.payment_method_master?.name || successTransaction.payment_method }}</span>
                        </div>
                        <div class="flex justify-between items-center border-t border-border/60 pt-2 font-bold text-xs">
                            <span class="text-muted-foreground">Total Belanja:</span>
                            <span class="font-mono text-foreground">Rp {{ formatRupiah(successTransaction.total_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center font-bold text-xs">
                            <span class="text-emerald-600 dark:text-emerald-400">Jumlah Uang Dibayar:</span>
                            <span class="font-mono text-emerald-600 dark:text-emerald-400">Rp {{ formatRupiah(successTransaction.jumlah_dibayar) }}</span>
                        </div>
                        <div v-if="successTransaction.kembalian > 0" class="flex justify-between items-center font-bold text-xs text-indigo-600 dark:text-indigo-400">
                            <span>Kembalian:</span>
                            <span class="font-mono">Rp {{ formatRupiah(successTransaction.kembalian) }}</span>
                        </div>
                        <div v-if="successTransaction.sisa_tagihan > 0" class="flex justify-between items-center font-bold text-xs text-red-500">
                            <span>Sisa Kurang (Piutang):</span>
                            <span class="font-mono">Rp {{ formatRupiah(successTransaction.sisa_tagihan) }}</span>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Item Yang Dibeli</Label>
                        <div class="divide-y divide-border border border-border rounded-2xl overflow-hidden bg-background max-h-[150px] overflow-y-auto custom-scroll">
                            <div v-for="item in successTransaction.items" :key="item.id" class="p-3 flex justify-between items-center text-xs">
                                <div>
                                    <p class="font-bold text-foreground">{{ item.item_name }}</p>
                                    <p class="text-[10px] text-muted-foreground mt-0.5">
                                        {{ parseFloat(item.quantity) }} {{ item.unit || 'pcs' }} &times; Rp {{ formatRupiah(item.selling_price) }}
                                    </p>
                                </div>
                                <span class="font-bold text-foreground font-mono">Rp {{ formatRupiah(item.subtotal_price) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="grid grid-cols-2 gap-3 pt-4 border-t border-border">
                    <Button 
                        @click="resetPOSState" 
                        variant="outline" 
                        class="rounded-xl font-bold py-5 border-border hover:bg-muted text-xs text-foreground flex items-center justify-center gap-1.5 w-full h-10"
                    >
                        <i class="fas fa-arrow-left"></i>
                        Kembali ke Kasir
                    </Button>
                    <a 
                        v-if="successTransaction"
                        :href="`/pos/print/${successTransaction.invoice_number}`" 
                        target="_blank" 
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs py-2.5 px-4 shadow-sm w-full gap-1.5 cursor-pointer text-center h-10"
                    >
                        <i class="fas fa-print"></i>
                        Cetak Invoice
                    </a>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG ALERT CUSTOM (SUCCESS / WARNING / ERROR / INFO) -->
        <Dialog :open="alertOpen" @update:open="alertOpen = $event">
            <DialogContent class="sm:max-w-[400px] rounded-3xl bg-card border border-border text-foreground shadow-2xl p-6 overflow-hidden z-[9999]">
                <div class="flex flex-col items-center text-center space-y-4">
                    <!-- Icon based on type -->
                    <div 
                        v-if="alertType === 'success'" 
                        class="w-16 h-16 rounded-full bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-500 flex items-center justify-center text-3xl animate-bounce"
                    >
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div 
                        v-else-if="alertType === 'warning'" 
                        class="w-16 h-16 rounded-full bg-amber-500/10 dark:bg-amber-500/20 text-amber-500 flex items-center justify-center text-3xl animate-pulse"
                    >
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div 
                        v-else-if="alertType === 'error'" 
                        class="w-16 h-16 rounded-full bg-red-500/10 dark:bg-red-500/20 text-red-500 flex items-center justify-center text-3xl"
                    >
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div 
                        v-else 
                        class="w-16 h-16 rounded-full bg-indigo-500/10 dark:bg-indigo-500/20 text-indigo-500 flex items-center justify-center text-3xl"
                    >
                        <i class="fas fa-info-circle"></i>
                    </div>

                    <div class="space-y-1.5 w-full">
                        <DialogTitle class="text-lg font-black text-foreground">{{ alertTitle }}</DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground leading-relaxed px-2">
                            {{ alertMessage }}
                        </DialogDescription>
                    </div>
                </div>

                <DialogFooter class="mt-5 w-full">
                    <Button 
                        @click="alertOpen = false" 
                        class="w-full rounded-xl font-bold py-5 hover:opacity-90 shadow-sm text-xs text-white h-10 border-none transition-all"
                        :class="{
                            'bg-emerald-600 hover:bg-emerald-700': alertType === 'success',
                            'bg-amber-500 hover:bg-amber-600': alertType === 'warning',
                            'bg-red-600 hover:bg-red-700': alertType === 'error',
                            'bg-indigo-600 hover:bg-indigo-700': alertType === 'info'
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
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.15s ease;
}
.fade-enter-from, .fade-leave-to {
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
    transition: box-shadow 0.2s, transform 0.2s;
}
.card-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -12px rgba(0, 0, 0, 0.15);
}
</style>
