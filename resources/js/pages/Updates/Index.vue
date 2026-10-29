<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, History, Sparkles } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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
};

defineProps<{
    latestUpdate: UpdateItem;
    updates: UpdateItem[];
}>();
</script>

<template>
    <Head title="Informasi Update" />

    <div class="font-inter flex flex-col gap-6 p-4 pb-8 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div class="mb-2 flex items-center gap-2 text-indigo-500">
                    <Sparkles class="size-5" />
                    <span class="text-xs font-bold tracking-[0.2em] uppercase"
                        >Product updates</span
                    >
                </div>
                <h1
                    class="text-2xl font-black tracking-tight text-foreground md:text-3xl"
                >
                    Informasi Update Aplikasi
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Ringkasan perubahan untuk membantu Anda menggunakan fitur
                    terbaru.
                </p>
            </div>
            <Link
                href="/dashboard"
                class="inline-flex h-9 items-center gap-2 self-start rounded-lg border border-border bg-background px-3 text-sm font-semibold text-foreground transition hover:bg-accent sm:self-auto"
            >
                <ArrowLeft class="size-4" />
                Kembali ke Dashboard
            </Link>
        </div>

        <Card
            class="overflow-hidden border-indigo-500/30 bg-indigo-500/[0.04] shadow-sm"
        >
            <CardHeader class="border-b border-indigo-500/15">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <div
                            class="mb-2 flex items-center gap-2 text-indigo-500"
                        >
                            <span
                                class="rounded-full bg-indigo-500 px-2.5 py-1 text-[11px] font-black text-white"
                            >
                                v{{ latestUpdate.version }}
                            </span>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >{{ latestUpdate.date }}</span
                            >
                        </div>
                        <CardTitle class="text-xl font-black">{{
                            latestUpdate.title
                        }}</CardTitle>
                    </div>
                    <span
                        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        <Sparkles class="size-3.5" />
                        Update terbaru
                    </span>
                </div>
            </CardHeader>
            <CardContent class="space-y-5 p-5 md:p-6">
                <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                    {{ latestUpdate.summary }}
                </p>
                <div class="grid gap-3 md:grid-cols-2">
                    <div
                        v-for="highlight in latestUpdate.highlights"
                        :key="highlight"
                        class="flex gap-3 rounded-xl border border-border/70 bg-background/70 p-4"
                    >
                        <CheckCircle2
                            class="mt-0.5 size-4 shrink-0 text-emerald-500"
                        />
                        <span class="text-sm leading-5 text-foreground">{{
                            highlight
                        }}</span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card class="border-border/60 bg-card shadow-sm">
            <CardHeader class="border-b border-border/60">
                <CardTitle class="flex items-center gap-2 text-base font-black">
                    <History class="size-5 text-indigo-500" />
                    Riwayat Update
                </CardTitle>
            </CardHeader>
            <CardContent class="divide-y divide-border/60 p-0">
                <article
                    v-for="update in updates"
                    :key="update.version"
                    class="grid gap-4 p-5 md:grid-cols-[150px_1fr] md:p-6"
                >
                    <div>
                        <p class="text-sm font-black text-foreground">
                            v{{ update.version }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ update.date }}
                        </p>
                    </div>
                    <div>
                        <h2 class="font-bold text-foreground">
                            {{ update.title }}
                        </h2>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground">
                            {{ update.summary }}
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="highlight in update.highlights"
                                :key="highlight"
                                class="flex gap-2 text-sm text-muted-foreground"
                            >
                                <span
                                    class="mt-2 size-1.5 shrink-0 rounded-full bg-indigo-500"
                                />
                                <span>{{ highlight }}</span>
                            </li>
                        </ul>
                    </div>
                </article>
            </CardContent>
        </Card>
    </div>
</template>
