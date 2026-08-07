<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';
import { 
    LayoutGrid, 
    MonitorSmartphone, 
    Layers, 
    Package, 
    RefreshCw, 
    Users, 
    BarChart3, 
    Wallet, 
    Settings, 
    Menu, 
    X,
    Receipt,
    CreditCard,
    Handshake,
    MailOpen
} from 'lucide-vue-next';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const isMobileMenuOpen = ref(false);

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

const closeMobileMenu = () => {
    isMobileMenuOpen.value = false;
};

const page = usePage();
const currentPath = computed(() => page.url);

const mainNavItems = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'POS Kasir',
        href: '/pos',
        icon: MonitorSmartphone,
    },
    {
        title: 'Restock',
        href: '/purchases',
        icon: RefreshCw,
    },
    {
        title: 'Laporan',
        href: '/reports',
        icon: BarChart3,
    },
    {
        title: 'Piutang',
        href: '/receivables',
        icon: Receipt,
    },
    {
        title: 'Pengeluaran',
        href: '/expenses',
        icon: Wallet,
    },
    {
        title: 'Pengaturan',
        href: '/settings/store',
        icon: Settings,
    },
];

const masterNavItems = [
    {
        title: 'Kategori',
        href: '/categories',
        icon: Layers,
    },
    {
        title: 'Produk',
        href: '/products',
        icon: Package,
    },
    {
        title: 'Customer',
        href: '/customers',
        icon: Users,
    },
    {
        title: 'Pembayaran',
        href: '/payment-methods',
        icon: CreditCard,
    },
    {
        title: 'Mitra Cetak',
        href: '/print-vendors',
        icon: Handshake,
    },
    {
        title: 'Undangan',
        href: '/undangan',
        icon: MailOpen,
    },
];

const isItemActive = (href: string) => {
    if (href === '/dashboard') {
        return currentPath.value === '/dashboard';
    }
    return currentPath.value.startsWith(href);
};

// Mobile Cart syncing logic
const mobileCart = ref({ count: 0, total: 0, hasItems: false });
const openMobileCart = () => {
    window.dispatchEvent(new CustomEvent('open-mobile-cart'));
};
const formatRupiah = (angka: number) => {
    return new Intl.NumberFormat('id-ID').format(angka);
};

let handleCartUpdate: (e: any) => void;

onMounted(() => {
    handleCartUpdate = (e: any) => {
        mobileCart.value = e.detail;
    };
    window.addEventListener('cart-updated', handleCartUpdate);
    // Request initial cart state on load
    window.dispatchEvent(new CustomEvent('request-cart-sync'));
});

onUnmounted(() => {
    if (handleCartUpdate) {
        window.removeEventListener('cart-updated', handleCartUpdate);
    }
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>

    <!-- FLOATING ACTION BUTTON (KHUSUS MOBILE - DEFAULT) -->
    <div v-if="currentPath !== '/pos'" class="fixed bottom-6 right-6 z-[90] md:hidden">
        <button
            @click="toggleMobileMenu"
            class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-3 rounded-full shadow-[0_10px_25px_-5px_rgba(79,70,229,0.5)] active:scale-95 transition-all duration-300 border border-white/10 group"
        >
            <component
                :is="isMobileMenuOpen ? X : Menu"
                class="w-5 h-5 transition-transform duration-300 group-hover:rotate-90"
            />
            <span class="text-sm tracking-wider uppercase font-extrabold">MENU</span>
        </button>
    </div>

    <!-- FLOATING CONTAINER FOR BOTH MENU AND CART (VERTICALLY STACKED) ON POS PAGE -->
    <div v-if="currentPath === '/pos'" class="fixed bottom-4 right-4 z-[90] md:hidden flex flex-col items-end gap-2">
        <!-- Tombol KERANJANG / BAYAR (Warna Hijau) -->
        <button 
            v-if="mobileCart.hasItems"
            @click="openMobileCart"
            class="flex items-center gap-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-bold px-5 py-3.5 rounded-full shadow-[0_10px_25px_-5px_rgba(16,185,129,0.4)] active:scale-[0.98] transition-all duration-300 border border-white/10"
        >
            <div class="flex items-center gap-1.5">
                <i class="fas fa-shopping-cart text-xs animate-pulse"></i>
                <span class="text-xs font-bold">{{ mobileCart.count }} Item</span>
            </div>
            <span class="h-3 w-px bg-white/20"></span>
            <span class="text-xs font-black">Rp {{ formatRupiah(mobileCart.total) }}</span>
        </button>

        <!-- Tombol MENU (Warna Ungu) -->
        <button
            @click="toggleMobileMenu"
            class="flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white font-bold px-5 py-3 rounded-full shadow-[0_10px_25px_-5px_rgba(147,51,234,0.4)] active:scale-95 transition-all duration-300 border border-white/10 group"
        >
            <component
                :is="isMobileMenuOpen ? X : Menu"
                class="w-4 h-4 transition-transform duration-300 group-hover:rotate-90"
            />
            <span class="text-xs tracking-wider uppercase font-extrabold">MENU</span>
        </button>
    </div>

    <!-- BACKDROP / OVERLAY WITH TRANSITION -->
    <div 
        v-if="isMobileMenuOpen"
        @click="closeMobileMenu"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs z-[100] md:hidden transition-all duration-300"
    ></div>

    <!-- BOTTOM SHEET PANEL WITH SLIDE UP ANIMATION -->
    <div 
        class="fixed bottom-0 left-0 right-0 max-h-[85vh] bg-card border-t border-border rounded-t-[2.5rem] z-[101] md:hidden flex flex-col transition-all duration-500 ease-out shadow-2xl overflow-hidden transform"
        :class="isMobileMenuOpen ? 'translate-y-0' : 'translate-y-full'"
    >
        <!-- Drag Handle Indicator -->
        <div class="w-12 h-1.5 bg-muted rounded-full mx-auto my-3.5 opacity-60"></div>

        <!-- Header -->
        <div class="flex justify-between items-center px-6 pb-3 border-b border-border/60">
            <div>
                <h3 class="text-base font-black tracking-tight text-foreground">Menu Navigasi</h3>
                <p class="text-xs text-muted-foreground">Pilih menu untuk berpindah halaman.</p>
            </div>
            <button 
                @click="closeMobileMenu"
                class="w-8 h-8 rounded-full bg-muted flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-accent transition"
            >
                <X class="w-4 h-4" />
            </button>
        </div>

        <!-- Grid Menu Content -->
        <div class="overflow-y-auto px-5 py-6 pb-12">
            <p class="mb-2 text-[10px] font-black uppercase tracking-[0.18em] text-muted-foreground">Menu Utama</p>
            <div class="grid grid-cols-3 gap-3.5">
                <Link 
                    v-for="item in mainNavItems" 
                    :key="item.href"
                    :href="item.href"
                    @click="closeMobileMenu"
                    class="flex flex-col items-center justify-center p-4 rounded-2xl border text-center transition-all duration-200 select-none group"
                    :class="isItemActive(item.href) 
                        ? 'bg-indigo-500/10 text-indigo-600 border-indigo-500/30 dark:bg-indigo-500/20 dark:text-indigo-400 font-extrabold shadow-xs' 
                        : 'bg-muted/30 text-muted-foreground hover:text-foreground hover:bg-muted/80 border-border/80'"
                >
                    <div 
                        class="w-10 h-10 rounded-xl flex items-center justify-center mb-2 transition-transform duration-300 group-active:scale-90"
                        :class="isItemActive(item.href) 
                            ? 'bg-indigo-500 text-white shadow-sm' 
                            : 'bg-background text-muted-foreground group-hover:text-foreground border border-border'"
                    >
                        <component :is="item.icon" class="w-5 h-5" />
                    </div>
                    <span class="text-[10px] leading-snug font-bold uppercase tracking-wider block truncate w-full">{{ item.title }}</span>
                </Link>
            </div>

            <p class="mb-2 mt-6 text-[10px] font-black uppercase tracking-[0.18em] text-muted-foreground">Data Master</p>
            <div class="grid grid-cols-3 gap-3.5">
                <Link
                    v-for="item in masterNavItems"
                    :key="item.href"
                    :href="item.href"
                    @click="closeMobileMenu"
                    class="flex flex-col items-center justify-center p-4 rounded-2xl border text-center transition-all duration-200 select-none group"
                    :class="isItemActive(item.href)
                        ? 'bg-indigo-500/10 text-indigo-600 border-indigo-500/30 dark:bg-indigo-500/20 dark:text-indigo-400 font-extrabold shadow-xs'
                        : 'bg-muted/30 text-muted-foreground hover:text-foreground hover:bg-muted/80 border-border/80'"
                >
                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center mb-2 transition-transform duration-300 group-active:scale-90"
                        :class="isItemActive(item.href)
                            ? 'bg-indigo-500 text-white shadow-sm'
                            : 'bg-background text-muted-foreground group-hover:text-foreground border border-border'"
                    >
                        <component :is="item.icon" class="w-5 h-5" />
                    </div>
                    <span class="text-[10px] leading-snug font-bold uppercase tracking-wider block truncate w-full">{{ item.title }}</span>
                </Link>
            </div>
        </div>
    </div>
</template>
