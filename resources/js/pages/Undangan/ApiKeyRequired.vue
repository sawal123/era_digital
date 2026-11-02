<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, KeyRound, MessageCircle, RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Undangan Cetak', href: '/undangan' },
        ],
    },
});

const retrying = ref(false);

const retry = () => {
    retrying.value = true;
    router.get(
        '/undangan',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                retrying.value = false;
            },
        },
    );
};

const openHelp = () => {
    window.dispatchEvent(new CustomEvent('open-pos-help'));
};
</script>

<template>
    <Head title="Undangan Cetak — Akses Eksternal" />

    <div
        class="flex flex-1 items-center justify-center p-4 pb-28 sm:p-6 sm:pb-28 lg:p-10"
    >
        <section
            aria-labelledby="api-key-title"
            class="relative w-full max-w-3xl overflow-hidden rounded-3xl border border-border bg-card px-5 py-10 text-center shadow-sm sm:px-12 sm:py-12"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-32 left-1/2 size-96 -translate-x-1/2 rounded-full bg-indigo-500/10 blur-3xl"
            ></div>

            <div
                aria-hidden="true"
                class="relative mx-auto mb-7 h-56 w-full max-w-80 select-none"
            >
                <div
                    class="absolute inset-x-12 top-0 aspect-square rounded-full border border-dashed border-indigo-300/60 dark:border-indigo-500/30"
                ></div>
                <span
                    class="absolute inset-x-0 top-6 text-[100px] leading-none font-black tracking-widest text-indigo-500/[0.07] dark:text-indigo-400/10"
                    >API</span
                >

                <svg
                    class="invitation-float absolute inset-x-0 top-10 mx-auto h-44 w-64 text-indigo-600 dark:text-indigo-400"
                    viewBox="0 0 280 190"
                    fill="none"
                >
                    <rect
                        x="54"
                        y="34"
                        width="172"
                        height="122"
                        rx="18"
                        fill="currentColor"
                        opacity="0.12"
                    />
                    <rect
                        x="66"
                        y="20"
                        width="148"
                        height="124"
                        rx="12"
                        class="fill-card"
                        stroke="currentColor"
                        stroke-width="2"
                    />
                    <path
                        d="M92 48H154M92 61H177M92 74H143"
                        stroke="currentColor"
                        stroke-width="5"
                        stroke-linecap="round"
                        opacity="0.2"
                    />
                    <path
                        d="M54 78L130 127C136 131 144 131 150 127L226 78V155C226 165 218 173 208 173H72C62 173 54 165 54 155V78Z"
                        class="fill-card"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linejoin="round"
                    />
                    <path
                        d="M57 166L112 125M223 166L168 125"
                        stroke="currentColor"
                        stroke-width="2"
                        opacity="0.25"
                    />
                    <circle
                        cx="200"
                        cy="127"
                        r="32"
                        class="fill-card"
                        stroke="currentColor"
                        stroke-width="2"
                    />
                    <rect
                        x="187"
                        y="122"
                        width="26"
                        height="21"
                        rx="5"
                        fill="currentColor"
                    />
                    <path
                        d="M192 122V116C192 105 208 105 208 116V122"
                        stroke="currentColor"
                        stroke-width="3"
                        stroke-linecap="round"
                    />
                    <path
                        d="M200 131V135"
                        class="stroke-card"
                        stroke-width="3"
                        stroke-linecap="round"
                    />
                </svg>

                <span
                    class="key-float absolute top-12 right-4 flex size-14 -rotate-12 items-center justify-center rounded-2xl border border-amber-300/70 bg-amber-50 text-amber-600 shadow-sm dark:border-amber-500/30 dark:bg-amber-950 dark:text-amber-400"
                >
                    <KeyRound class="size-7" />
                </span>
                <span
                    class="absolute bottom-6 left-7 size-3 rounded-full bg-indigo-400/40"
                ></span>
                <span
                    class="absolute top-6 left-8 size-2 rounded-full bg-amber-400/60"
                ></span>
                <span
                    class="absolute right-7 bottom-5 size-2 rounded-full bg-indigo-400/30"
                ></span>
            </div>

            <div class="relative">
                <p
                    class="mb-3 text-xs font-semibold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-400"
                >
                    Undangan Cetak · Akses eksternal
                </p>
                <h1
                    id="api-key-title"
                    class="mx-auto max-w-xl text-2xl leading-tight font-bold tracking-tight text-foreground sm:text-3xl"
                >
                    Halaman ini membutuhkan API KEY dari Eksternal
                </h1>
                <p
                    class="mx-auto mt-4 max-w-lg text-sm leading-7 text-muted-foreground"
                >
                    Katalog undangan terhubung dengan layanan eksternal. API key
                    belum tersedia atau aksesnya belum diterima oleh layanan
                    tersebut. Hubungi pengelola aplikasi untuk memeriksa akses,
                    lalu coba lagi.
                </p>

                <div
                    class="mt-7 flex flex-col justify-center gap-3 sm:flex-row"
                >
                    <Button
                        :disabled="retrying"
                        class="rounded-xl bg-indigo-600 text-white hover:bg-indigo-500"
                        @click="retry"
                    >
                        <RefreshCw
                            class="size-4"
                            :class="{
                                'animate-spin motion-reduce:animate-none':
                                    retrying,
                            }"
                            aria-hidden="true"
                        />
                        {{ retrying ? 'Memeriksa akses…' : 'Coba Lagi' }}
                    </Button>
                    <Button variant="outline" class="rounded-xl" as-child>
                        <Link href="/dashboard">
                            <ArrowLeft class="size-4" aria-hidden="true" />
                            Kembali ke Dashboard
                        </Link>
                    </Button>
                </div>

                <button
                    type="button"
                    class="mx-auto mt-6 inline-flex items-center gap-2 rounded-md px-2 py-1 text-sm font-medium text-muted-foreground transition-colors hover:text-indigo-600 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:hover:text-indigo-400"
                    @click="openHelp"
                >
                    <MessageCircle class="size-4" aria-hidden="true" />
                    Tanya Asisten POS
                </button>
            </div>
        </section>
    </div>
</template>

<style scoped>
.invitation-float {
    animation: invitation-float 5s ease-in-out infinite;
}

.key-float {
    animation: key-float 4s ease-in-out infinite;
}

@keyframes invitation-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}

@keyframes key-float {
    0%,
    100% {
        transform: translateY(0) rotate(-12deg);
    }
    50% {
        transform: translateY(9px) rotate(0deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .invitation-float,
    .key-float {
        animation: none;
    }
}
</style>
