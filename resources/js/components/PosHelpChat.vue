<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowUpRight,
    LoaderCircle,
    MessageCircle,
    RotateCcw,
    Send,
    Sparkles,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Source = { title: string; href: string };
type Message = {
    role: 'user' | 'assistant';
    content: string;
    sources?: Source[];
};
type Conversation = { available: boolean; messages: Message[] };

const page = usePage();
const open = ref(false);
const loading = ref(false);
const sending = ref(false);
const resetting = ref(false);
const available = ref(true);
const messages = ref<Message[]>([]);
const question = ref('');
const pendingQuestion = ref('');
const error = ref('');
const transcript = ref<HTMLElement | null>(null);
const input = ref<HTMLTextAreaElement | null>(null);
const busy = computed(() => loading.value || sending.value || resetting.value);
const canSend = computed(
    () => available.value && !busy.value && question.value.trim().length > 0,
);
const currentPath = computed(() => page.url.split(/[?#]/)[0]);
const examples = [
    'Bagaimana cara membuat transaksi?',
    'Bagaimana menambah modal digital?',
    'Apa perbedaan omzet dan keuntungan?',
];
let abortController: AbortController | null = null;

const scrollToBottom = async () => {
    await nextTick();
    transcript.value?.scrollTo({ top: transcript.value.scrollHeight });
};

const errorMessage = (cause: unknown): string => {
    if (axios.isAxiosError(cause)) {
        if (cause.response?.status === 429) {
            return 'Batas pertanyaan sementara tercapai. Silakan coba lagi nanti.';
        }

        if ([401, 419].includes(cause.response?.status ?? 0)) {
            return 'Sesi Anda berakhir. Muat ulang halaman dan masuk kembali.';
        }

        if (cause.response?.status === 422) {
            return 'Tulis pertanyaan tentang POS maksimal 1.000 karakter.';
        }

        if (cause.response?.status === 503) {
            return 'Bantuan AI sedang tidak tersedia. Silakan coba lagi nanti.';
        }
    }

    return 'Percakapan belum berhasil diproses. Periksa koneksi dan coba lagi.';
};

const loadConversation = async () => {
    loading.value = true;
    error.value = '';
    abortController = new AbortController();

    try {
        const response = await axios.get<Conversation>('/help/chat', {
            headers: { Accept: 'application/json' },
            signal: abortController.signal,
            timeout: 10000,
        });
        messages.value = response.data.messages;
        available.value = response.data.available;
    } catch (cause) {
        if (!axios.isCancel(cause)) {
            error.value = errorMessage(cause);
        }
    } finally {
        loading.value = false;
        await scrollToBottom();

        if (open.value) {
            input.value?.focus();
        }
    }
};

const sendQuestion = async () => {
    if (!canSend.value) {
        return;
    }

    sending.value = true;
    error.value = '';
    pendingQuestion.value = question.value.trim();
    abortController = new AbortController();
    await scrollToBottom();

    try {
        const response = await axios.post<{ messages: Message[] }>(
            '/help/chat',
            { message: pendingQuestion.value, page: currentPath.value },
            {
                headers: { Accept: 'application/json' },
                signal: abortController.signal,
                timeout: 35000,
            },
        );
        messages.value = response.data.messages;
        question.value = '';
    } catch (cause) {
        if (!axios.isCancel(cause)) {
            error.value = errorMessage(cause);
        }
    } finally {
        pendingQuestion.value = '';
        sending.value = false;
        await scrollToBottom();
        await nextTick();

        if (open.value) {
            input.value?.focus();
        }
    }
};

const resetConversation = async () => {
    if (busy.value) {
        return;
    }

    resetting.value = true;
    error.value = '';
    abortController = new AbortController();

    try {
        await axios.delete('/help/chat', {
            headers: { Accept: 'application/json' },
            signal: abortController.signal,
            timeout: 10000,
        });
        messages.value = [];
        question.value = '';
    } catch (cause) {
        if (!axios.isCancel(cause)) {
            error.value = errorMessage(cause);
        }
    } finally {
        resetting.value = false;
    }
};

const useExample = (example: string) => {
    question.value = example;
    input.value?.focus();
};

const onInputKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        void sendQuestion();
    }
};

const onDialogKeydown = (event: KeyboardEvent) => {
    // Keep cashier shortcuts from acting on the POS behind this dialog.
    if (['F2', 'F4', 'Escape'].includes(event.key)) {
        event.stopPropagation();

        if (event.key === 'Escape') {
            open.value = false;
        } else {
            event.preventDefault();
        }
    }
};

watch(open, (isOpen) => {
    if (isOpen && !busy.value) {
        void loadConversation();
    }
});

onBeforeUnmount(() => abortController?.abort());
</script>

<template>
    <Button
        class="fixed right-4 bottom-24 z-40 h-12 gap-2 rounded-full bg-indigo-600 px-4 text-white shadow-lg shadow-indigo-950/20 hover:bg-indigo-500 md:right-6 md:bottom-6"
        aria-label="Buka bantuan AI POS"
        aria-haspopup="dialog"
        :aria-expanded="open"
        @click="open = true"
    >
        <MessageCircle class="size-5" aria-hidden="true" />
        <span class="text-sm font-semibold">Bantuan POS</span>
    </Button>

    <Dialog v-model:open="open">
        <DialogContent
            class="top-auto right-4 bottom-24 left-auto flex h-[min(640px,calc(100dvh-8rem))] w-[calc(100%-2rem)] max-w-md translate-x-0 translate-y-0 flex-col gap-0 overflow-hidden rounded-2xl border-border bg-background p-0 shadow-2xl md:right-6 md:bottom-6"
            @open-auto-focus.prevent="input?.focus()"
            @keydown="onDialogKeydown"
        >
            <DialogHeader
                class="shrink-0 border-b bg-muted/30 px-5 py-4 pr-12 text-left"
            >
                <div
                    class="mb-1 flex items-center gap-2 text-indigo-600 dark:text-indigo-400"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                    <DialogTitle class="text-base font-bold text-foreground"
                        >Asisten POS</DialogTitle
                    >
                </div>
                <DialogDescription class="text-xs leading-5">
                    Panduan fitur dan cara menggunakan Era Digital.
                </DialogDescription>
            </DialogHeader>

            <div
                ref="transcript"
                role="log"
                aria-label="Percakapan bantuan POS"
                aria-live="polite"
                aria-relevant="additions text"
                :aria-busy="sending || loading"
                class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain p-4"
            >
                <div
                    v-if="loading"
                    class="flex items-center gap-2 py-5 text-sm text-muted-foreground"
                    role="status"
                >
                    <LoaderCircle
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    Memuat percakapan…
                </div>
                <div v-else-if="messages.length === 0" class="space-y-4 py-3">
                    <div class="space-y-2">
                        <p class="text-lg font-semibold tracking-tight">
                            Ada yang membingungkan?
                        </p>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Tanyakan cara memakai menu, mencatat transaksi, atau
                            memahami istilah di POS.
                        </p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <button
                            v-for="example in examples"
                            :key="example"
                            type="button"
                            :disabled="busy || !available"
                            class="rounded-xl border border-border bg-muted/20 px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                            @click="useExample(example)"
                        >
                            {{ example }}
                        </button>
                    </div>
                </div>

                <div
                    v-for="(message, index) in messages"
                    :key="index"
                    class="flex flex-col gap-1.5"
                    :class="
                        message.role === 'user' ? 'items-end' : 'items-start'
                    "
                >
                    <span
                        class="px-1 text-[11px] font-medium text-muted-foreground"
                        >{{
                            message.role === 'user' ? 'Anda' : 'Asisten POS'
                        }}</span
                    >
                    <div
                        class="max-w-[95%] rounded-2xl px-3.5 py-3 text-sm leading-6 wrap-anywhere whitespace-pre-wrap"
                        :class="
                            message.role === 'user'
                                ? 'rounded-tr-sm bg-indigo-600 text-white'
                                : 'rounded-tl-sm bg-muted text-foreground'
                        "
                    >
                        {{ message.content }}
                    </div>
                    <div
                        v-if="message.sources?.length"
                        class="flex flex-wrap gap-1.5 pt-1"
                    >
                        <Link
                            v-for="source in message.sources"
                            :key="source.href + source.title"
                            :href="source.href"
                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium text-indigo-600 hover:bg-muted dark:text-indigo-400"
                            @click="open = false"
                        >
                            {{ source.title }}
                            <ArrowUpRight class="size-3" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

                <div v-if="sending" class="space-y-3">
                    <div
                        class="ml-auto max-w-[95%] rounded-2xl rounded-tr-sm bg-indigo-600 px-3.5 py-3 text-sm leading-6 wrap-anywhere whitespace-pre-wrap text-white"
                    >
                        {{ pendingQuestion }}
                    </div>
                    <div
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                        role="status"
                    >
                        <LoaderCircle
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        Menyiapkan panduan…
                    </div>
                </div>
            </div>

            <div class="shrink-0 space-y-3 border-t bg-background p-4">
                <p
                    v-if="!available"
                    role="status"
                    class="text-sm text-muted-foreground"
                >
                    Bantuan AI belum tersedia. Hubungi pengelola aplikasi.
                </p>
                <div
                    v-if="error"
                    role="alert"
                    class="flex items-start justify-between gap-2 rounded-lg bg-destructive/10 p-2.5 text-sm text-destructive"
                >
                    <span>{{ error }}</span>
                    <button
                        v-if="!messages.length && !sending && !loading"
                        type="button"
                        class="shrink-0 underline"
                        @click="loadConversation"
                    >
                        Coba lagi
                    </button>
                </div>

                <form class="space-y-2" @submit.prevent="sendQuestion">
                    <label for="pos-help-question" class="sr-only"
                        >Pertanyaan tentang aplikasi POS</label
                    >
                    <div
                        class="flex items-end gap-2 rounded-xl border border-input bg-muted/20 p-2 focus-within:ring-2 focus-within:ring-ring/40"
                    >
                        <textarea
                            id="pos-help-question"
                            ref="input"
                            v-model="question"
                            rows="2"
                            maxlength="1000"
                            :disabled="busy || !available"
                            placeholder="Tanya tentang fitur POS…"
                            class="max-h-28 min-h-12 flex-1 resize-none border-0 bg-transparent px-1 py-1 text-sm leading-6 outline-none placeholder:text-muted-foreground disabled:opacity-50"
                            @keydown="onInputKeydown"
                        ></textarea>
                        <Button
                            type="submit"
                            size="icon"
                            :disabled="!canSend"
                            aria-label="Kirim pertanyaan"
                            class="shrink-0 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500"
                        >
                            <LoaderCircle
                                v-if="sending"
                                class="size-4 animate-spin"
                                aria-hidden="true"
                            />
                            <Send v-else class="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                    <div
                        class="flex items-center justify-between gap-2 text-[11px] text-muted-foreground"
                    >
                        <button
                            type="button"
                            :disabled="busy || messages.length === 0"
                            class="inline-flex items-center gap-1 rounded py-1 hover:text-foreground disabled:opacity-40"
                            @click="resetConversation"
                        >
                            <RotateCcw
                                class="size-3"
                                :class="{ 'animate-spin': resetting }"
                                aria-hidden="true"
                            />
                            Percakapan baru
                        </button>
                        <span>{{ question.length }}/1.000</span>
                    </div>
                </form>
                <p class="text-[10px] leading-4 text-muted-foreground">
                    Khusus panduan POS. Jawaban AI dapat keliru; periksa kembali
                    langkahnya. Pesan diproses oleh OpenAI. Jangan kirim
                    password, OTP, atau API key.
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
