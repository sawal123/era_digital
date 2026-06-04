<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, MonitorSmartphone, Layers, Package, BarChart3, Wallet, RefreshCw, Users, Settings, Receipt, CreditCard, Handshake } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import { computed } from 'vue';

const page = usePage();
const isDemoUser = computed(() => page.props.auth.user?.role === 'demo');

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'POS Kasir',
        href: '/pos',
        icon: MonitorSmartphone,
    },
    {
        title: 'Restock Barang',
        href: '/purchases',
        icon: RefreshCw,
    },
    {
        title: 'Laporan Penjualan',
        href: '/reports',
        icon: BarChart3,
    },
    {
        title: 'Catatan Piutang',
        href: '/receivables',
        icon: Receipt,
    },
    {
        title: 'Pengeluaran',
        href: '/expenses',
        icon: Wallet,
    },
    ...(isDemoUser.value
        ? []
        : [{
            title: 'Pengaturan Toko',
            href: '/settings/store',
            icon: Settings,
        }]),
]);

const masterNavItems: NavItem[] = [
    {
        title: 'Master Kategori',
        href: '/categories',
        icon: Layers,
    },
    {
        title: 'Master Produk',
        href: '/products',
        icon: Package,
    },
    {
        title: 'Data Customer',
        href: '/customers',
        icon: Users,
    },
    {
        title: 'Data Master Pembayaran',
        href: '/payment-methods',
        icon: CreditCard,
    },
    {
        title: 'Mitra Percetakan',
        href: '/print-vendors',
        icon: Handshake,
    },
];

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="Menu Utama" :items="mainNavItems" />
            <NavMain label="Data Master" :items="masterNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
