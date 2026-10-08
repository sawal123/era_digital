<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
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
    MailOpen,
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

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
    <div
        v-if="currentPath !== '/pos'"
        class="fixed right-6 bottom-6 z-[90] md:hidden"
    >
        <button
            @click="toggleMobileMenu"
            class="group flex items-center gap-2 rounded-full border border-white/10 bg-indigo-600 px-5 py-3 font-bold text-white shadow-[0_10px_25px_-5px_rgba(79,70,229,0.5)] transition-all duration-300 hover:bg-indigo-700 active:scale-95"
        >
            <component
                :is="isMobileMenuOpen ? X : Menu"
                class="h-5 w-5 transition-transform duration-300 group-hover:rotate-90"
            />
            <span class="text-sm font-extrabold tracking-wider uppercase"
                >MENU</span
            >
        </button>
    </div>

    <!-- FLOATING CONTAINER FOR BOTH MENU AND CART (VERTICALLY STACKED) ON POS PAGE -->
    <div
        v-if="currentPath === '/pos'"
        class="fixed right-4 bottom-4 z-[90] flex flex-col items-end gap-2 md:hidden"
    >
        <!-- Tombol KERANJANG / BAYAR (Warna Hijau) -->
        <button
            v-if="mobileCart.hasItems"
            @click="openMobileCart"
            class="flex items-center gap-2.5 rounded-full border border-white/10 bg-gradient-to-r from-emerald-500 to-teal-600 px-5 py-3.5 font-bold text-white shadow-[0_10px_25px_-5px_rgba(16,185,129,0.4)] transition-all duration-300 active:scale-[0.98]"
        >
            <div class="flex items-center gap-1.5">
                <i class="fas fa-shopping-cart animate-pulse text-xs"></i>
                <span class="text-xs font-bold"
                    >{{ mobileCart.count }} Item</span
                >
            </div>
            <span class="h-3 w-px bg-white/20"></span>
            <span class="text-xs font-black"
                >Rp {{ formatRupiah(mobileCart.total) }}</span
            >
        </button>

        <!-- Tombol MENU (Warna Ungu) -->
        <button
            @click="toggleMobileMenu"
            class="group flex items-center gap-2 rounded-full border border-white/10 bg-purple-600 px-5 py-3 font-bold text-white shadow-[0_10px_25px_-5px_rgba(147,51,234,0.4)] transition-all duration-300 hover:bg-purple-700 active:scale-95"
        >
            <component
                :is="isMobileMenuOpen ? X : Menu"
                class="h-4 w-4 transition-transform duration-300 group-hover:rotate-90"
            />
            <span class="text-xs font-extrabold tracking-wider uppercase"
                >MENU</span
            >
        </button>
    </div>

    <!-- BACKDROP / OVERLAY WITH TRANSITION -->
    <div
        v-if="isMobileMenuOpen"
        @click="closeMobileMenu"
        class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-xs transition-all duration-300 md:hidden"
    ></div>

    <!-- BOTTOM SHEET PANEL WITH SLIDE UP ANIMATION -->
    <div
        class="fixed right-0 bottom-0 left-0 z-[101] flex max-h-[85vh] transform flex-col overflow-hidden rounded-t-[2.5rem] border-t border-border bg-card shadow-2xl transition-all duration-500 ease-out md:hidden"
        :class="isMobileMenuOpen ? 'translate-y-0' : 'translate-y-full'"
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
                <h3 class="text-base font-black tracking-tight text-foreground">
                    Menu Navigasi
                </h3>
                <p class="text-xs text-muted-foreground">
                    Pilih menu untuk berpindah halaman.
                </p>
            </div>
            <button
                @click="closeMobileMenu"
                class="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-muted-foreground transition hover:bg-accent hover:text-foreground"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <!-- Grid Menu Content -->
        <div class="overflow-y-auto px-5 py-6 pb-12">
            <p
                class="mb-2 text-[10px] font-black tracking-[0.18em] text-muted-foreground uppercase"
            >
                Menu Utama
            </p>
            <div class="grid grid-cols-3 gap-3.5">
                <Link
                    v-for="item in mainNavItems"
                    :key="item.href"
                    :href="item.href"
                    @click="closeMobileMenu"
                    class="group flex flex-col items-center justify-center rounded-2xl border p-4 text-center transition-all duration-200 select-none"
                    :class="
                        isItemActive(item.href)
                            ? 'border-indigo-500/30 bg-indigo-500/10 font-extrabold text-indigo-600 shadow-xs dark:bg-indigo-500/20 dark:text-indigo-400'
                            : 'border-border/80 bg-muted/30 text-muted-foreground hover:bg-muted/80 hover:text-foreground'
                    "
                >
                    <div
                        class="mb-2 flex h-10 w-10 items-center justify-center rounded-xl transition-transform duration-300 group-active:scale-90"
                        :class="
                            isItemActive(item.href)
                                ? 'bg-indigo-500 text-white shadow-sm'
                                : 'border border-border bg-background text-muted-foreground group-hover:text-foreground'
                        "
                    >
                        <component :is="item.icon" class="h-5 w-5" />
                    </div>
                    <span
                        class="block w-full truncate text-[10px] leading-snug font-bold tracking-wider uppercase"
                        >{{ item.title }}</span
                    >
                </Link>
            </div>

            <p
                class="mt-6 mb-2 text-[10px] font-black tracking-[0.18em] text-muted-foreground uppercase"
            >
                Data Master
            </p>
            <div class="grid grid-cols-3 gap-3.5">
                <Link
                    v-for="item in masterNavItems"
                    :key="item.href"
                    :href="item.href"
                    @click="closeMobileMenu"
                    class="group flex flex-col items-center justify-center rounded-2xl border p-4 text-center transition-all duration-200 select-none"
                    :class="
                        isItemActive(item.href)
                            ? 'border-indigo-500/30 bg-indigo-500/10 font-extrabold text-indigo-600 shadow-xs dark:bg-indigo-500/20 dark:text-indigo-400'
                            : 'border-border/80 bg-muted/30 text-muted-foreground hover:bg-muted/80 hover:text-foreground'
                    "
                >
                    <div
                        class="mb-2 flex h-10 w-10 items-center justify-center rounded-xl transition-transform duration-300 group-active:scale-90"
                        :class="
                            isItemActive(item.href)
                                ? 'bg-indigo-500 text-white shadow-sm'
                                : 'border border-border bg-background text-muted-foreground group-hover:text-foreground'
                        "
                    >
                        <component :is="item.icon" class="h-5 w-5" />
                    </div>
                    <span
                        class="block w-full truncate text-[10px] leading-snug font-bold tracking-wider uppercase"
                        >{{ item.title }}</span
                    >
                </Link>
            </div>
        </div>
    </div>
</template>
