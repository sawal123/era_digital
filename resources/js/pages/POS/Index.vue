<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
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
    profile: Object,
});

// ---------- CORE CART VARIABLES ----------
const cart = ref([]);
const isMobileCartOpen = ref(false);
const cartItemCount = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.quantity, 0);
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

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
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
    if (timer) clearInterval(timer);
    if (handleOpenMobileCart) {
        window.removeEventListener('open-mobile-cart', handleOpenMobileCart);
    }
    if (handleRequestCartSync) {
        window.removeEventListener('request-cart-sync', handleRequestCartSync);
    }
});

// TAB LOGIC (0 = fisik, 1 = cetak, 2 = digital)
const activeTab = ref(0);
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
        name: `${service.name} - ${qty} Lembar`,
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
const cetakVendor = ref('MITRA PRINT');

const hargaJasaCetak = computed(() => {
    const prod = selectedJasaProduct.value;
    if (!prod) return 0;

    if (prod.unit === 'meter') {
        let p = parseFloat(cetakPanjang.value) || 1;
        let l = parseFloat(cetakLebar.value) || 1;
        return parseFloat(prod.selling_price) * p * l;
    } else {
        let qty = parseInt(cetakQty.value) || 1;
        return parseFloat(prod.selling_price) * qty;
    }
});

const addCetakToCart = () => {
    const prod = selectedJasaProduct.value;
    if (!prod) return;

    let quantity = 1;
    let detail = `Vendor: ${cetakVendor.value || '-'}`;

    if (prod.unit === 'meter') {
        let p = parseFloat(cetakPanjang.value) || 1;
        let l = parseFloat(cetakLebar.value) || 1;
        quantity = p * l;
        detail += ` | Ukuran: ${p}x${l} m`;
    } else {
        quantity = parseInt(cetakQty.value) || 1;
        detail += ` | Jumlah: ${quantity} ${prod.unit}`;
    }

    addToCart({
        id: prod.id,
        name: prod.name,
        price: parseFloat(prod.selling_price),
        quantity: quantity,
        type: 'cetak',
        detail: detail
    });
};

// ---------- TAB SALDO DIGITAL ----------
const saldoDigital = ref(props.profile ? parseFloat(props.profile.saldo_digital) : 350000); 
const digitalItems = computed(() => {
    return props.products.filter(p => p.category && p.category.type === 'ppob');
});

const selectedLayanan = ref(null);
const nomorPelanggan = ref('');
const namaPelanggan = ref('');
const nominalManual = ref('');

const autocompleteResults = ref([]);
const showAutocomplete = ref(false);

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
watch(nomorPelanggan, async (newVal) => {
    if (!newVal || newVal.length <= 3) {
        autocompleteResults.value = [];
        showAutocomplete.value = false;
        return;
    }

    const type = (selectedLayanan.value?.name?.toLowerCase().includes('token') || 
                  selectedLayanan.value?.name?.toLowerCase().includes('listrik') || 
                  selectedLayanan.value?.sku?.toLowerCase().includes('tkn')) 
                  ? 'token' 
                  : 'phone';

    try {
        const response = await axios.get('/api/digital-accounts/search', {
            params: {
                number: newVal,
                type: type
            }
        });
        autocompleteResults.value = response.data;
        showAutocomplete.value = response.data.length > 0;
    } catch (error) {
        console.error('Error fetching digital accounts autocomplete:', error);
    }
});

const selectAccount = (account) => {
    nomorPelanggan.value = account.account_number;
    namaPelanggan.value = account.account_name;
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
    cart.value.push({
        id: item.id || Date.now(),
        name: item.name,
        price: item.price,
        quantity: item.quantity || item.qty || 1,
        detail: item.detail || '',
        type: item.type,
        ...item
    });
};

const updateQty = (item, delta) => {
    const idx = cart.value.findIndex(i => i.id === item.id && i.detail === item.detail);
    if (idx === -1) return;
    
    const newQty = cart.value[idx].quantity + delta;
    if (newQty <= 0) {
        cart.value.splice(idx, 1);
    } else {
        cart.value[idx].quantity = newQty;
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
const keterangan = ref('');

const successDialogOpen = ref(false);
const successTransaction = ref(null);

const resetPOSState = () => {
    cart.value = [];
    customerId.value = '';
    keterangan.value = '';
    jumlahDibayarInput.value = null;
    isMobileCartOpen.value = false;
    successDialogOpen.value = false;
    successTransaction.value = null;
};

const jumlahDibayarInput = ref(null);
const jumlahDibayar = computed({
    get: () => {
        if (jumlahDibayarInput.value === null) {
            return cartTotal.value;
        }
        return jumlahDibayarInput.value;
    },
    set: (val) => {
        jumlahDibayarInput.value = val;
    }
});

watch(cartTotal, () => {
    jumlahDibayarInput.value = null;
});

const sisaTagihan = computed(() => {
    return Math.max(0, cartTotal.value - jumlahDibayar.value);
});

const prosesBayar = () => {
    if (cart.value.length === 0) {
        showNotification("Keranjang belanja kasir Anda saat ini masih kosong. Silakan tambahkan beberapa produk atau layanan cetak terlebih dahulu sebelum memproses pembayaran.", "Keranjang Kosong", "warning");
        return;
    }
    
    isProcessing.value = true;
    
    router.post('/pos', {
        cart: cart.value,
        total: cartTotal.value,
        payment_method: 'cash',
        jumlah_dibayar: jumlahDibayar.value,
        customer_id: customerId.value || null,
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

const alertFeature = (fitur) => {
    showNotification(`Fitur "${fitur}" sedang dalam tahap finalisasi dan akan segera hadir pada pembaharuan sistem versi berikutnya.`, "Fitur Segera Hadir", "info");
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
                <div class="flex gap-2 border-b border-border pb-3 mb-6 overflow-x-auto custom-scroll">
                    <Button 
                        v-for="(tab, idx) in tabs" 
                        :key="idx"
                        @click="activeTab = idx"
                        :variant="activeTab === idx ? 'default' : 'outline'"
                        class="px-5 py-2.5 rounded-xl font-semibold transition-all duration-200 text-sm flex items-center gap-2 whitespace-nowrap h-10 border-border/60"
                        :style="activeTab === idx ? { backgroundColor: themeColor, borderColor: themeColor, color: '#fff' } : {}"
                    >
                        <i :class="tab.icon"></i>
                        {{ tab.name }}
                    </Button>
                </div>

                <!-- KONTEN -->
                <div class="flex-1 overflow-y-auto pr-2 custom-scroll">
                    <transition name="flip" mode="out-in">
                        <div :key="activeTab" class="w-full">
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

                                <div v-else class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                                    <Card 
                                        v-for="item in filteredFisikItems" 
                                        :key="item.id" 
                                        @click="addToCart({ id: item.id, name: item.name, price: item.selling_price, qty: 1, type: 'fisik' })" 
                                        class="rounded-2xl p-4 text-center cursor-pointer hover:shadow-md transition hover:border-border/60 flex flex-col items-center justify-between min-h-[165px] bg-muted/20 border-border/40 select-none shadow-sm relative group overflow-hidden"
                                    >
                                        <div class="w-full flex-1 flex items-center justify-center overflow-hidden mb-2 rounded-xl bg-background border border-border/30">
                                            <img v-if="item.image_path" :src="item.image_path" class="w-full h-20 object-cover rounded-xl" />
                                            <!-- Simple icon detection based on name if no image -->
                                            <span v-else class="text-3xl text-foreground">
                                                <i v-if="item.name.toLowerCase().includes('pulpen')" class="fas fa-pen text-indigo-500"></i>
                                                <i v-else-if="item.name.toLowerCase().includes('buku')" class="fas fa-book text-indigo-500"></i>
                                                <i v-else-if="item.name.toLowerCase().includes('kertas')" class="fas fa-copy text-indigo-500"></i>
                                                <i v-else-if="item.name.toLowerCase().includes('kalkulator')" class="fas fa-calculator text-indigo-500"></i>
                                                <i v-else class="fas fa-box text-indigo-500"></i>
                                            </span>
                                        </div>
                                        
                                        <p class="font-bold text-foreground text-xs line-clamp-1 w-full" :title="item.name">{{ item.name }}</p>
                                        <p class="text-indigo-600 dark:text-indigo-400 text-xs font-semibold mt-1">Rp {{ formatRupiah(item.selling_price) }}</p>
                                        <Badge variant="secondary" class="text-[9px] px-1.5 py-0 mt-1 font-normal border-border/30">Stok: {{ parseFloat(item.stock) }} {{ item.unit }}</Badge>
                                    </Card>
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
                                        <div class="space-y-1.5">
                                            <Label class="text-sm font-semibold text-foreground">Pilih Layanan Jasa</Label>
                                            <select v-model="activeJasaProductId" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30 text-foreground">
                                                <option v-for="j in jasaCetakItems" :key="j.id" :value="j.id">
                                                    {{ j.name }} (Rp {{ formatRupiah(j.selling_price) }} / {{ j.unit }})
                                                </option>
                                            </select>
                                        </div>
                                        
                                        <!-- Kalkulator Dinamis jika unit meter -->
                                        <div class="flex gap-4" v-if="selectedJasaProduct?.unit === 'meter'">
                                            <div class="w-1/2 space-y-1.5">
                                                <Label class="text-xs font-semibold text-foreground">Panjang (meter)</Label>
                                                <Input type="number" step="0.1" v-model.number="cetakPanjang" class="w-full bg-background border-border text-foreground" />
                                            </div>
                                            <div class="w-1/2 space-y-1.5">
                                                <Label class="text-xs font-semibold text-foreground">Lebar (meter)</Label>
                                                <Input type="number" step="0.1" v-model.number="cetakLebar" class="w-full bg-background border-border text-foreground" />
                                            </div>
                                        </div>
                                        <!-- Input Quantity biasa jika unit rim / lembar / pcs dll -->
                                        <div v-else class="space-y-1.5">
                                            <Label class="text-xs font-semibold text-foreground">Jumlah ({{ selectedJasaProduct?.unit }})</Label>
                                            <Input type="number" step="1" min="1" v-model.number="cetakQty" class="w-full bg-background border-border text-foreground" />
                                        </div>

                                        <div class="space-y-1.5">
                                            <Label class="text-xs font-semibold text-foreground">Nama Vendor / Mitra (Opsional)</Label>
                                            <Input type="text" v-model="cetakVendor" placeholder="ex: CV. Grafika Jaya" class="w-full bg-background border-border text-foreground" />
                                        </div>
                                        
                                        <div class="bg-muted/50 p-4 rounded-xl flex justify-between items-center border border-border shadow-inner mt-2">
                                            <span class="text-sm text-muted-foreground font-medium"><i class="fas fa-calculator mr-1"></i> Estimasi Harga</span>
                                            <span class="font-bold text-lg text-foreground">Rp {{ formatRupiah(hargaJasaCetak) }}</span>
                                        </div>
                                        
                                        <Button @click="addCetakToCart" class="w-full py-6 rounded-xl text-white font-bold shadow-md text-sm transition-all hover:opacity-90 mt-2 bg-orange-600 hover:bg-orange-700">
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
                                        <div class="space-y-1.5">
                                            <Label class="text-sm font-semibold text-foreground">Pilih Layanan Saldo / PPOB</Label>
                                            <select v-model="selectedLayanan" class="flex h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm shadow-xs transition-all outline-none focus-visible:ring-2 focus-visible:ring-blue-500 text-foreground">
                                                <option v-for="d in digitalItems" :key="d.id" :value="d">
                                                    {{ d.name }}
                                                </option>
                                            </select>
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
                                                <Input type="text" v-model="nomorPelanggan" placeholder="Masukkan nomor pelanggan..." class="w-full bg-background border-border text-foreground rounded-xl pl-9 h-10" />
                                            </div>

                                            <!-- Autocomplete List -->
                                            <div v-if="showAutocomplete && autocompleteResults.length > 0" class="absolute left-0 right-0 z-50 mt-1 bg-card border border-border rounded-2xl shadow-2xl max-h-48 overflow-y-auto divide-y divide-border/60">
                                                <div 
                                                    v-for="account in autocompleteResults" 
                                                    :key="account.id"
                                                    @click="selectAccount(account)"
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
                                        <div class="space-y-1.5">
                                            <Label class="text-xs font-semibold text-foreground">Nama Pemilik Akun / Pelanggan</Label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-3 text-muted-foreground text-xs">
                                                    <i class="fas fa-user-circle text-blue-500"></i>
                                                </span>
                                                <Input type="text" v-model="namaPelanggan" placeholder="Masukkan nama pemilik akun..." class="w-full bg-background border-border text-foreground rounded-xl pl-9 h-10" />
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
            <div class="hidden lg:flex w-full lg:w-1/3 bg-muted/30 lg:bg-muted/50 border-t lg:border-t-0 lg:border-l border-border flex-col p-4 md:p-6 h-[50vh] lg:h-full relative shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.1)] lg:shadow-none z-10">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-border">
                    <h2 class="font-bold text-lg flex gap-2 items-center text-foreground"><i class="fas fa-receipt text-indigo-500 dark:text-indigo-400"></i> Keranjang</h2>
                    <span class="text-xs bg-background text-foreground border border-border px-2 py-1 rounded-full shadow-sm">{{ cart.length }} item</span>
                </div>
                <div class="flex-1 overflow-y-auto cart-scroll space-y-3 pr-1">
                    <div v-if="cart.length === 0" class="text-center text-muted-foreground mt-10">
                        <i class="fas fa-shopping-cart text-4xl mb-2 opacity-30"></i>
                        <p>Keranjang kosong</p>
                    </div>
                    <div v-for="(item, idx) in cart" :key="idx" class="bg-background rounded-xl p-3 shadow-sm border border-border relative group">
                        <div class="flex justify-between">
                            <div class="w-2/3">
                                <p class="font-semibold text-sm leading-tight truncate text-foreground" :title="item.name">{{ item.name }}</p>
                                <p class="text-[11px] text-muted-foreground mt-0.5 line-clamp-2" :title="item.detail">{{ item.detail || '' }}</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <Button @click="updateQty(item, -1)" variant="ghost" size="sm" class="w-6 h-6 p-0 flex items-center justify-center rounded-full bg-muted hover:bg-accent text-foreground text-xs transition">-</Button>
                                    <span class="text-sm font-medium w-4 text-center text-foreground">{{ item.quantity }}</span>
                                    <Button @click="updateQty(item, 1)" variant="ghost" size="sm" class="w-6 h-6 p-0 flex items-center justify-center rounded-full bg-muted hover:bg-accent text-foreground text-xs transition">+</Button>
                                </div>
                            </div>
                            <div class="text-right flex flex-col justify-between">
                                <p class="font-bold text-indigo-700 dark:text-indigo-400 text-sm">Rp {{ formatRupiah(item.price * item.quantity) }}</p>
                                <Button @click="removeFromCart(idx)" variant="ghost" size="sm" class="text-red-400 text-xs mt-1 self-end hover:text-red-600 dark:hover:text-red-400 bg-red-50 dark:bg-red-900/30 w-6 h-6 p-0 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition"><i class="fas fa-trash-alt"></i></Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CUSTOMER SELECT & INVOICE NOTES -->
                <div class="mt-4 pt-4 border-t border-border space-y-3.5 bg-muted/20 p-3 rounded-2xl border border-border/50">
                    <!-- Customer Dropdown -->
                    <div class="space-y-1.5">
                        <Label for="pos-customer" class="text-xs font-bold text-muted-foreground flex items-center gap-1.5">
                            <i class="fas fa-user-circle text-indigo-500 text-[11px]"></i>
                            Pilih Customer (Opsional)
                        </Label>
                        <select id="pos-customer" v-model="customerId" class="flex h-9 w-full rounded-xl border border-input bg-background px-3 py-1 text-xs shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] text-foreground">
                            <option value="">-- Cash / Umum --</option>
                            <option v-for="cust in customers" :key="cust.id" :value="cust.id">
                                {{ cust.name }} ({{ cust.phone || '-' }})
                            </option>
                        </select>
                    </div>

                    <!-- Keterangan Invoice -->
                    <div class="space-y-1.5">
                        <Label for="pos-keterangan" class="text-xs font-bold text-muted-foreground flex items-center gap-1.5">
                            <i class="fas fa-sticky-note text-indigo-500 text-[11px]"></i>
                            Catatan / Keterangan Invoice
                        </Label>
                        <textarea id="pos-keterangan" v-model="keterangan" rows="2" placeholder="Misal: Spanduk 3x1 meter, DP Rp 50.000..." class="flex min-h-[50px] w-full rounded-xl border border-input bg-background px-3 py-2 text-xs shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 text-foreground border-border"></textarea>
                    </div>

                    <!-- Jumlah Uang Dibayar (LUNAS / DP / PIUTANG) -->
                    <div class="space-y-1.5 mt-3">
                        <Label for="pos-jumlah-dibayar" class="text-xs font-bold text-muted-foreground flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <i class="fas fa-coins text-emerald-500 text-[11px]"></i>
                                Jumlah Uang Dibayar
                            </span>
                            <span class="text-[10px] text-muted-foreground bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">Default: Lunas</span>
                        </Label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-xs text-muted-foreground font-bold">Rp</span>
                            <Input id="pos-jumlah-dibayar" type="number" v-model.number="jumlahDibayar" placeholder="Masukkan jumlah bayar..." min="0" class="pl-8 bg-background border border-input rounded-xl text-xs text-foreground font-bold" />
                        </div>
                        <div class="flex justify-between items-center text-[10px] font-bold mt-1 px-1">
                            <span class="text-muted-foreground">Sisa Tagihan:</span>
                            <span :class="sisaTagihan > 0 ? 'text-red-500 animate-pulse' : 'text-emerald-500'">
                                Rp {{ formatRupiah(sisaTagihan) }}
                             </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-border space-y-3">
                    <div class="flex justify-between font-bold text-lg text-foreground">
                        <span>Total</span>
                        <span :style="{ color: themeColor }">Rp {{ formatRupiah(cartTotal) }}</span>
                    </div>
                    <Button @click="prosesBayar" :disabled="isProcessing" class="w-full py-6 rounded-xl text-white font-bold shadow-lg transition-all hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center" :style="{ backgroundColor: themeColor }">
                        <i class="fas fa-credit-card mr-2" v-if="!isProcessing"></i> 
                        {{ isProcessing ? 'MEMPROSES...' : 'BAYAR SEKARANG' }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- ==================== MOBILE FLOATING CART & BOTTOM SHEET ==================== -->

        <!-- BACKDROP OVERLAY -->
        <div 
            v-if="isMobileCartOpen"
            @click="isMobileCartOpen = false"
            class="fixed inset-0 bg-black/60 backdrop-blur-xs z-[100] lg:hidden transition-all duration-300"
        ></div>

        <!-- BOTTOM SHEET DETAIL KERANJANG -->
        <div 
            class="fixed bottom-0 left-0 right-0 max-h-[85vh] bg-card border-t border-border rounded-t-[2.5rem] z-[101] lg:hidden flex flex-col transition-all duration-500 ease-out shadow-2xl overflow-hidden transform"
            :class="isMobileCartOpen ? 'translate-y-0' : 'translate-y-full'"
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
                <div v-for="(item, idx) in cart" :key="idx" class="bg-muted/35 dark:bg-muted/10 rounded-xl p-3 border border-border/50 flex justify-between items-center">
                    <div class="w-2/3">
                        <p class="font-bold text-sm leading-tight truncate text-foreground" :title="item.name">{{ item.name }}</p>
                        <p class="text-[10px] text-muted-foreground mt-0.5 truncate" :title="item.detail">{{ item.detail || '' }}</p>
                        <div class="flex items-center gap-2 mt-2">
                            <Button @click="updateQty(item, -1)" variant="ghost" size="sm" class="w-6 h-6 p-0 flex items-center justify-center rounded-full bg-background hover:bg-muted text-foreground text-xs transition border border-border">-</Button>
                            <span class="text-xs font-bold w-4 text-center text-foreground">{{ item.quantity }}</span>
                            <Button @click="updateQty(item, 1)" variant="ghost" size="sm" class="w-6 h-6 p-0 flex items-center justify-center rounded-full bg-background hover:bg-muted text-foreground text-xs transition border border-border">+</Button>
                        </div>
                    </div>
                    <div class="text-right flex flex-col items-end justify-between min-h-[55px]">
                        <p class="font-black text-indigo-600 dark:text-indigo-400 text-sm">Rp {{ formatRupiah(item.price * item.quantity) }}</p>
                        <Button @click="removeFromCart(idx)" variant="ghost" size="sm" class="text-rose-500 hover:text-rose-600 bg-rose-50 dark:bg-rose-950/30 w-6 h-6 p-0 rounded-full flex items-center justify-center transition mt-1">
                            <i class="fas fa-trash-alt text-xs"></i>
                        </Button>
                    </div>
                </div>

                <!-- Customer Select & Invoice Notes (Mobile Mode) -->
                <div class="space-y-3.5 bg-muted/20 p-4 rounded-2xl border border-border/50 mt-4">
                    <!-- Customer Dropdown -->
                    <div class="space-y-1.5">
                        <Label for="pos-customer-mobile" class="text-xs font-bold text-muted-foreground flex items-center gap-1.5">
                            <i class="fas fa-user-circle text-indigo-500 text-[11px]"></i>
                            Pilih Customer (Opsional)
                        </Label>
                        <select id="pos-customer-mobile" v-model="customerId" class="flex h-9 w-full rounded-xl border border-input bg-background px-3 py-1 text-xs shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] text-foreground">
                            <option value="">-- Cash / Umum --</option>
                            <option v-for="cust in customers" :key="cust.id" :value="cust.id">
                                {{ cust.name }} ({{ cust.phone || '-' }})
                            </option>
                        </select>
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
                        <Label for="pos-jumlah-dibayar-mobile" class="text-xs font-bold text-muted-foreground flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <i class="fas fa-coins text-emerald-500 text-[11px]"></i>
                                Jumlah Uang Dibayar
                            </span>
                            <span class="text-[10px] text-muted-foreground bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">Default: Lunas</span>
                        </Label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-xs text-muted-foreground font-bold">Rp</span>
                            <Input id="pos-jumlah-dibayar-mobile" type="number" v-model.number="jumlahDibayar" placeholder="Masukkan jumlah bayar..." min="0" class="pl-8 bg-background border border-input rounded-xl text-xs text-foreground font-bold" />
                        </div>
                        <div class="flex justify-between items-center text-[10px] font-bold mt-1 px-1">
                            <span class="text-muted-foreground">Sisa Tagihan:</span>
                            <span :class="sisaTagihan > 0 ? 'text-red-500 animate-pulse' : 'text-emerald-500'">
                                Rp {{ formatRupiah(sisaTagihan) }}
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
                        <div class="flex justify-between items-center border-t border-border/60 pt-2 font-bold text-xs">
                            <span class="text-muted-foreground">Total Belanja:</span>
                            <span class="font-mono text-foreground">Rp {{ formatRupiah(successTransaction.total_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center font-bold text-xs">
                            <span class="text-emerald-600 dark:text-emerald-400">Jumlah Uang Dibayar:</span>
                            <span class="font-mono text-emerald-600 dark:text-emerald-400">Rp {{ formatRupiah(successTransaction.jumlah_dibayar) }}</span>
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
.flip-enter-active {
    animation: flip-in 0.35s cubic-bezier(0.23, 1, 0.32, 1);
}
.flip-leave-active {
    animation: flip-out 0.25s cubic-bezier(0.23, 1, 0.32, 1);
}
@keyframes flip-in {
    0% { transform: rotateY(90deg); opacity: 0; }
    100% { transform: rotateY(0deg); opacity: 1; }
}
@keyframes flip-out {
    0% { transform: rotateY(0deg); opacity: 1; }
    100% { transform: rotateY(-90deg); opacity: 0; }
}
.sidebar-transition {
    transition: all 0.3s ease-in-out;
}
.cart-scroll::-webkit-scrollbar, .custom-scroll::-webkit-scrollbar {
    width: 4px;
}
.cart-scroll::-webkit-scrollbar-track, .custom-scroll::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
.cart-scroll::-webkit-scrollbar-thumb, .custom-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
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
