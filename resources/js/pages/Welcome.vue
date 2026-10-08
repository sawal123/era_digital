<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';

defineProps<{
    canRegister?: boolean;
}>();
</script>

<template>
    <Head title="Selamat Datang - Era Digital">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin="anonymous"
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap"
            rel="stylesheet"
        />
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        />
    </Head>

    <div
        class="font-outfit relative flex min-h-screen w-full flex-col items-center justify-center overflow-hidden bg-neutral-950 text-neutral-100 selection:bg-indigo-600/30 selection:text-white"
    >
        <!-- Glowing Ambient Lighting Orb Backdrops -->
        <div
            class="pointer-events-none absolute -top-40 -left-40 h-96 w-96 animate-pulse rounded-full bg-indigo-600/20 blur-[120px] duration-[6000ms]"
        ></div>
        <div
            class="pointer-events-none absolute -right-40 -bottom-40 h-96 w-96 animate-pulse rounded-full bg-purple-600/20 blur-[120px] duration-[8000ms]"
        ></div>
        <div
            class="pointer-events-none absolute top-1/2 left-1/2 h-[500px] w-[500px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-600/10 blur-[160px]"
        ></div>

        <!-- Geometric Mesh / Sleek Grid Backdrop -->
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,_rgba(255,255,255,0.025)_1px,_transparent_1px)] bg-[size:32px_32px]"
        ></div>

        <!-- Premium Glassmorphism Card Container -->
        <div
            class="relative z-10 w-full max-w-md translate-y-4 scale-95 animate-[fadeInUp_0.8s_ease-out_forwards] rounded-[32px] border border-neutral-800/80 bg-neutral-900/40 px-8 py-14 text-center opacity-0 shadow-[0_20px_50px_rgba(0,0,0,0.5)] backdrop-blur-xl transition-all duration-700"
        >
            <!-- Store Brand Neon Logo Icon -->
            <div
                class="mb-8 inline-flex h-20 w-20 transform items-center justify-center rounded-[24px] border border-indigo-400/20 bg-gradient-to-tr from-indigo-600 via-indigo-500 to-purple-600 shadow-[0_8px_30px_rgba(79,70,229,0.4)] transition-transform duration-300 hover:rotate-6"
            >
                <i class="fas fa-store text-3xl text-white"></i>
            </div>

            <!-- Welcoming Messaging -->
            <div class="mb-10 space-y-4">
                <h1
                    class="bg-gradient-to-r from-white via-neutral-100 to-neutral-400 bg-clip-text text-4xl leading-none font-black tracking-tight text-transparent"
                >
                    Selamat Datang Kembali
                </h1>
                <p
                    class="mx-auto max-w-[280px] text-sm leading-relaxed text-neutral-400"
                >
                    Sistem POS & Layanan Pembayaran Digital PPOB Terintegrasi
                    Era Digital. Silakan masuk untuk mengelola transaksi.
                </p>
            </div>

            <!-- Premium Action Button Area -->
            <div class="space-y-4 px-2">
                <template v-if="$page.props.auth.user">
                    <Link
                        :href="dashboard()"
                        class="group relative inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 text-sm font-bold text-white shadow-[0_4px_25px_rgba(79,70,229,0.3)] transition-all duration-300 hover:-translate-y-0.5 hover:from-indigo-500 hover:to-purple-500 hover:shadow-[0_4px_30px_rgba(79,70,229,0.5)]"
                    >
                        <span>Masuk ke Dashboard</span>
                        <i
                            class="fas fa-arrow-right text-xs transition-transform group-hover:translate-x-1"
                        ></i>
                    </Link>

                    <div
                        class="mt-2 flex items-center justify-center gap-1.5 text-xs text-neutral-500"
                    >
                        <span
                            class="inline-block h-1.5 w-1.5 animate-ping rounded-full bg-emerald-500"
                        ></span>
                        <span
                            >Aktif sebagai
                            <span class="font-semibold text-indigo-400">{{
                                $page.props.auth.user.name
                            }}</span></span
                        >
                    </div>
                </template>

                <template v-else>
                    <Link
                        :href="login()"
                        class="group relative inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 text-sm font-bold text-white shadow-[0_4px_25px_rgba(79,70,229,0.3)] transition-all duration-300 hover:-translate-y-0.5 hover:from-indigo-500 hover:to-purple-500 hover:shadow-[0_4px_30px_rgba(79,70,229,0.5)]"
                    >
                        <i
                            class="fas fa-sign-in-alt mr-0.5 text-xs transition-transform group-hover:translate-x-0.5"
                        ></i>
                        <span>Login Kasir & Admin</span>
                    </Link>
                </template>
            </div>

            <!-- Footer branding details -->
            <div
                class="mt-16 text-center font-mono text-[10px] tracking-widest text-neutral-600 uppercase"
            >
                Era Digital &bull; Platform POS Kasir
            </div>
        </div>
    </div>
</template>

<style>
/* Custom entrance animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(16px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.font-outfit {
    font-family: 'Outfit', sans-serif !important;
}
</style>
