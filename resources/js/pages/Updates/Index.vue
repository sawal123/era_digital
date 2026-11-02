<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpRight,
    CalendarDays,
    Check,
    History,
    MessageCircle,
    Sparkles,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Informasi Update', href: '/updates' },
        ],
    },
});

type UpdateItem = {
    version: string;
    date: string;
    title: string;
    summary: string;
    highlights: string[];
    action?: { type: 'open-chat'; label: string };
    getting_started?: string[];
};

const props = defineProps<{
    latestUpdate: UpdateItem;
    updates: UpdateItem[];
}>();

const previousUpdates = computed(() =>
    props.updates.filter(
        (update) => update.version !== props.latestUpdate.version,
    ),
);

const openHelp = () => {
    window.dispatchEvent(new CustomEvent('open-pos-help'));
};
</script>

<template>
    <Head title="Informasi Update" />

    <div
        class="font-inter mx-auto w-full max-w-6xl space-y-7 p-4 pb-32 sm:p-6 sm:pb-32 lg:p-8 lg:pb-24"
    >
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-widest text-indigo-600 uppercase dark:text-indigo-400"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                    Informasi pembaruan
                </div>
                <h1
                    class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                >
                    Yang baru di Era Digital
                </h1>
                <p
                    class="mt-2 max-w-xl text-sm leading-6 text-muted-foreground"
                >
                    Kenali fitur terbaru dan temukan kembali pembaruan yang
                    sudah hadir.
                </p>
            </div>
            <Link
                href="/dashboard"
                class="inline-flex h-10 items-center gap-2 self-start rounded-xl border border-border px-3.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:self-auto"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Dashboard
            </Link>
        </header>

        <section
            aria-labelledby="latest-update-title"
            class="overflow-hidden rounded-2xl border border-indigo-500/20 bg-gradient-to-br from-indigo-500/[0.07] via-card to-card shadow-sm"
        >
            <div
                class="grid gap-7 p-5 sm:p-7 lg:grid-cols-[1.2fr_1fr] lg:gap-10"
            >
                <div class="min-w-0">
                    <div
                        class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs"
                    >
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-indigo-500/10 px-2.5 py-1.5 font-semibold text-indigo-700 dark:text-indigo-300"
                        >
                            <Sparkles class="size-3.5" aria-hidden="true" />
                            Pembaruan terbaru
                        </span>
                        <span
                            class="font-mono font-medium text-muted-foreground"
                            >v{{ latestUpdate.version }}</span
                        >
                    </div>
                    <div
                        class="mb-3 flex size-11 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm shadow-indigo-500/20"
                    >
                        <MessageCircle
                            v-if="latestUpdate.action?.type === 'open-chat'"
                            class="size-6"
                            aria-hidden="true"
                        />
                        <Sparkles v-else class="size-6" aria-hidden="true" />
                    </div>
                    <h2
                        id="latest-update-title"
                        class="max-w-xl text-2xl leading-tight font-semibold tracking-tight text-foreground sm:text-3xl"
                    >
                        {{ latestUpdate.title }}
                    </h2>
                    <p
                        class="mt-3 max-w-xl text-sm leading-7 text-muted-foreground"
                    >
                        {{ latestUpdate.summary }}
                    </p>
                    <div class="mt-5 flex flex-wrap items-center gap-4">
                        <Button
                            v-if="latestUpdate.action?.type === 'open-chat'"
                            type="button"
                            aria-haspopup="dialog"
                            class="h-10 rounded-xl bg-indigo-600 px-4 text-white hover:bg-indigo-500"
                            @click="openHelp"
                        >
                            {{ latestUpdate.action.label }}
                            <ArrowUpRight class="size-4" aria-hidden="true" />
                        </Button>
                        <span
                            class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <CalendarDays class="size-3.5" aria-hidden="true" />
                            {{ latestUpdate.date }}
                        </span>
                    </div>
                </div>

                <aside
                    v-if="latestUpdate.getting_started?.length"
                    aria-labelledby="getting-started-title"
                    class="self-center rounded-2xl border border-border/70 bg-background/80 p-5 sm:p-6"
                >
                    <p
                        class="mb-1 text-xs font-semibold tracking-wider text-indigo-600 uppercase dark:text-indigo-400"
                    >
                        Panduan singkat
                    </p>
                    <h3
                        id="getting-started-title"
                        class="text-lg font-semibold tracking-tight"
                    >
                        Mulai dalam
                        {{ latestUpdate.getting_started.length }} langkah
                    </h3>
                    <ol class="mt-5 space-y-4">
                        <li
                            v-for="(
                                step, index
                            ) in latestUpdate.getting_started"
                            :key="step"
                            class="flex gap-3"
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-indigo-500/10 text-xs font-semibold text-indigo-600 dark:text-indigo-400"
                                >{{ index + 1 }}</span
                            >
                            <span
                                class="text-sm leading-6 text-muted-foreground"
                                >{{ step }}</span
                            >
                        </li>
                    </ol>
                </aside>
            </div>

            <div
                class="border-t border-indigo-500/10 bg-background/40 px-5 py-5 sm:px-7"
            >
                <h3 class="mb-4 text-sm font-semibold text-foreground">
                    Yang bisa Anda lakukan
                </h3>
                <ul class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    <li
                        v-for="highlight in latestUpdate.highlights"
                        :key="highlight"
                        class="flex items-start gap-2.5 text-sm leading-6 text-muted-foreground"
                    >
                        <Check
                            class="mt-1 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                            aria-hidden="true"
                        />
                        <span>{{ highlight }}</span>
                    </li>
                </ul>
            </div>
        </section>

        <section
            v-if="previousUpdates.length"
            aria-labelledby="update-history-title"
            class="space-y-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2
                    id="update-history-title"
                    class="flex items-center gap-2 text-lg font-semibold tracking-tight text-foreground"
                >
                    <History
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    Riwayat pembaruan
                </h2>
                <span class="text-xs text-muted-foreground"
                    >{{ previousUpdates.length }} pembaruan sebelumnya</span
                >
            </div>
            <div
                class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card"
            >
                <article
                    v-for="update in previousUpdates"
                    :key="update.version"
                    class="grid gap-4 p-5 sm:p-6 md:grid-cols-[150px_1fr] md:gap-7"
                >
                    <div class="flex items-center gap-3 md:block">
                        <p
                            class="font-mono text-xs font-semibold text-foreground"
                        >
                            v{{ update.version }}
                        </p>
                        <p class="text-xs text-muted-foreground md:mt-2">
                            {{ update.date }}
                        </p>
                    </div>
                    <div class="min-w-0">
                        <h3
                            class="text-base leading-6 font-semibold text-foreground"
                        >
                            {{ update.title }}
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ update.summary }}
                        </p>
                        <ul class="mt-4 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                            <li
                                v-for="highlight in update.highlights"
                                :key="highlight"
                                class="flex items-start gap-2 text-sm leading-6 text-muted-foreground"
                            >
                                <span
                                    class="mt-2.5 size-1 shrink-0 rounded-full bg-indigo-400"
                                    aria-hidden="true"
                                ></span>
                                <span>{{ highlight }}</span>
                            </li>
                        </ul>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
