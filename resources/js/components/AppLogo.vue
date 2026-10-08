<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const storeProfile = computed(() => page.props.storeProfile);
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <img
            v-if="storeProfile?.logo_path"
            :src="storeProfile.logo_path"
            :alt="storeProfile.store_name"
            class="size-full rounded-md bg-white object-contain p-0.5"
        />
        <AppLogoIcon
            v-else
            class="size-5 fill-current text-white dark:text-black"
        />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            storeProfile?.store_name || page.props.name
        }}</span>
    </div>
</template>
