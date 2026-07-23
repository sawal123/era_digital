<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

type SelectValue = string | number | null | undefined;
type SelectOption = Record<string, unknown>;

const props = withDefaults(defineProps<{
    modelValue: SelectValue;
    options: SelectOption[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
    optionValue?: string;
    optionLabel?: (option: SelectOption) => string;
    optionDescription?: (option: SelectOption) => string;
}>(), {
    placeholder: 'Pilih data...',
    searchPlaceholder: 'Cari...',
    emptyText: 'Data tidak ditemukan.',
    disabled: false,
    optionValue: 'id',
    optionLabel: (option: SelectOption) => String(option.label ?? option.name ?? option.id ?? ''),
    optionDescription: () => '',
});

const emit = defineEmits<{
    'update:modelValue': [value: SelectValue];
    change: [value: SelectValue];
}>();

const open = ref(false);
const search = ref('');
const root = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

const getValue = (option: SelectOption) => option[props.optionValue] as SelectValue;
const isSelected = (option: SelectOption) => String(getValue(option) ?? '') === String(props.modelValue ?? '');

const selectedOption = computed(() => props.options.find(isSelected));
const selectedLabel = computed(() => selectedOption.value ? props.optionLabel(selectedOption.value) : props.placeholder);

const filteredOptions = computed(() => {
    const query = search.value.toLowerCase().trim();

    if (!query) {
        return props.options;
    }

    return props.options.filter((option) => [
        props.optionLabel(option),
        props.optionDescription(option),
    ].join(' ').toLowerCase().includes(query));
});

const close = () => {
    open.value = false;
    search.value = '';
};

const toggle = async () => {
    if (props.disabled) {
        return;
    }

    open.value = !open.value;

    if (open.value) {
        await nextTick();
        searchInput.value?.focus();
    }
};

const selectOption = (option: SelectOption) => {
    const value = getValue(option);

    emit('update:modelValue', value);
    emit('change', value);
    close();
};

const onDocumentClick = (event: MouseEvent) => {
    if (!root.value?.contains(event.target as Node)) {
        close();
    }
};

document.addEventListener('click', onDocumentClick);

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            data-click-feedback="none"
            :disabled="disabled"
            class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-1 text-left text-sm text-foreground shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-input/30"
            @click.stop="toggle"
        >
            <span class="truncate" :class="selectedOption ? 'text-foreground' : 'text-muted-foreground'">
                {{ selectedLabel }}
            </span>
            <i class="fas fa-chevron-down ml-2 shrink-0 text-[10px] text-muted-foreground transition-transform" :class="{ 'rotate-180': open }"></i>
        </button>

        <div v-if="open" class="absolute left-0 right-0 z-[80] mt-1 overflow-hidden rounded-xl border border-border bg-card shadow-2xl">
            <div class="border-b border-border bg-card p-2">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground"></i>
                    <input
                        ref="searchInput"
                        v-model="search"
                        type="search"
                        :placeholder="searchPlaceholder"
                        class="h-9 w-full rounded-lg border border-input bg-background px-3 pl-8 text-sm text-foreground outline-none focus:ring-2 focus:ring-ring/50"
                        @click.stop
                    />
                </div>
            </div>

            <div class="max-h-60 overflow-y-auto p-1 custom-scroll">
                <button
                    v-for="option in filteredOptions"
                    :key="String(getValue(option))"
                    type="button"
                    data-click-feedback="none"
                    class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-muted"
                    :class="isSelected(option) ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-foreground'"
                    @click.stop="selectOption(option)"
                >
                    <span class="block truncate">{{ optionLabel(option) }}</span>
                    <span v-if="optionDescription(option)" class="block truncate text-[11px] font-normal text-muted-foreground">
                        {{ optionDescription(option) }}
                    </span>
                </button>

                <div v-if="filteredOptions.length === 0" class="px-3 py-6 text-center text-xs text-muted-foreground">
                    {{ emptyText }}
                </div>
            </div>
        </div>
    </div>
</template>
