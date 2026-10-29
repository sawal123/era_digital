<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh items-center justify-center overflow-hidden bg-background p-4 sm:p-6"
    >
        <div
            class="pointer-events-none absolute -top-32 -left-32 size-80 rounded-full bg-indigo-500/10 blur-3xl"
        ></div>
        <div
            class="pointer-events-none absolute -right-32 -bottom-32 size-80 rounded-full bg-violet-500/10 blur-3xl"
        ></div>

        <div class="relative w-full max-w-md">
            <div
                class="overflow-hidden rounded-3xl border border-border/80 bg-card/95 shadow-2xl shadow-indigo-950/10 backdrop-blur"
            >
                <div
                    class="border-b border-border/70 px-6 pt-8 pb-6 text-center sm:px-10"
                >
                    <Link
                        :href="home()"
                        class="group inline-flex flex-col items-center gap-3"
                    >
                        <span
                            class="flex size-16 items-center justify-center rounded-2xl border border-indigo-500/20 bg-indigo-500/10 shadow-lg shadow-indigo-500/10 transition group-hover:scale-105"
                        >
                            <AppLogoIcon
                                class="size-12 fill-current text-indigo-600 dark:text-indigo-300"
                            />
                        </span>
                        <span
                            class="text-base font-bold tracking-tight text-foreground"
                        >
                            {{
                                $page.props.storeProfile?.store_name ||
                                $page.props.name
                            }}
                        </span>
                    </Link>
                    <div class="mt-6 space-y-2">
                        <h1
                            class="text-2xl font-black tracking-tight text-foreground"
                        >
                            {{ title }}
                        </h1>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <div class="px-6 py-7 sm:px-10 sm:py-8">
                    <slot />
                </div>
            </div>
            <p class="mt-6 text-center text-xs text-muted-foreground">
                Sistem kasir dan manajemen usaha Era Digital
            </p>
        </div>
    </div>
</template>
