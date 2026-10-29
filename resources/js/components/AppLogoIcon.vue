<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();

const page = usePage();
const storeProfile = computed(() => page.props.storeProfile);
</script>

<template>
    <img
        :src="storeProfile?.logo_path || '/favicon.svg'"
        :alt="storeProfile?.store_name || page.props.name"
        :class="[className, 'rounded-md bg-white object-contain p-1']"
        v-bind="$attrs"
    />
</template>
