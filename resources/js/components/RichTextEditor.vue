<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Link from '@tiptap/extension-link';
import {
    Undo2,
    Redo2,
    Bold,
    Italic,
    Strikethrough,
    Heading1,
    Heading2,
    Heading3,
    List,
    ListOrdered,
    Quote,
    Code,
    Link as LinkIcon,
    RemoveFormatting,
} from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Tulis deskripsi...' },
    minHeight: { type: String, default: '160px' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit,
        Placeholder.configure({ placeholder: props.placeholder }),
        Link.configure({ openOnClick: false, autolink: true }),
    ],
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
    editorProps: {
        attributes: {
            class: 'focus:outline-none',
        },
    },
});

// Sinkronkan bila nilai berubah dari luar (mis. saat membuka modal edit)
watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && value !== editor.value.getHTML()) {
            editor.value.commands.setContent(value || '', false);
        }
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const isActive = (type, attrs) => editor.value?.isActive(type, attrs) ?? false;

const setLink = () => {
    const previousUrl = editor.value?.getAttributes('link').href ?? '';
    const url = window.prompt('Masukkan URL:', previousUrl || 'https://');
    if (url === null) return;
    if (url === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};
</script>

<template>
    <div class="rounded-xl border border-input bg-background overflow-hidden focus-within:ring-2 focus-within:ring-ring">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-0.5 border-b border-border bg-muted/30 px-2 py-1.5">
            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center text-muted-foreground hover:bg-accent hover:text-foreground transition disabled:opacity-40"
                title="Batal (undo)"
                :disabled="!editor?.can().chain().focus().undo().run()"
                @click="editor?.chain().focus().undo().run()"
            >
                <Undo2 class="size-4" />
            </button>
            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center text-muted-foreground hover:bg-accent hover:text-foreground transition disabled:opacity-40"
                title="Ulang (redo)"
                :disabled="!editor?.can().chain().focus().redo().run()"
                @click="editor?.chain().focus().redo().run()"
            >
                <Redo2 class="size-4" />
            </button>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>

            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center transition"
                :class="isActive('bold') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Tebal"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <Bold class="size-4" />
            </button>
            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center transition"
                :class="isActive('italic') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Miring"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                <Italic class="size-4" />
            </button>
            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center transition"
                :class="isActive('strike') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Coret"
                @click="editor?.chain().focus().toggleStrike().run()"
            >
                <Strikethrough class="size-4" />
            </button>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>

            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center transition"
                :class="isActive('heading', { level: 1 }) ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Heading 1"
                @click="editor?.chain().focus().toggleHeading({ level: 1 }).run()"
            >
                <Heading1 class="size-4" />
            </button>
            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center transition"
                :class="isActive('heading', { level: 2 }) ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Heading 2"
                @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                <Heading2 class="size-4" />
            </button>
            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center transition"
                :class="isActive('heading', { level: 3 }) ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Heading 3"
                @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
            >
                <Heading3 class="size-4" />
            </button>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>

            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center transition"
                :class="isActive('bulletList') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Daftar poin"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <List class="size-4" />
            </button>
            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center transition"
                :class="isActive('orderedList') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Daftar nomor"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="size-4" />
            </button>
            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center transition"
                :class="isActive('blockquote') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Kutipan"
                @click="editor?.chain().focus().toggleBlockquote().run()"
            >
                <Quote class="size-4" />
            </button>
            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center transition"
                :class="isActive('code') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Kode"
                @click="editor?.chain().focus().toggleCode().run()"
            >
                <Code class="size-4" />
            </button>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>

            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center transition"
                :class="isActive('link') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Tautan"
                @click="setLink"
            >
                <LinkIcon class="size-4" />
            </button>
            <button
                type="button"
                class="h-7 w-7 rounded-md flex items-center justify-center text-muted-foreground hover:bg-accent hover:text-foreground transition"
                title="Bersihkan format"
                @click="editor?.chain().focus().clearNodes().unsetAllMarks().run()"
            >
                <RemoveFormatting class="size-4" />
            </button>
        </div>

        <!-- Body -->
        <div class="editor-content bg-background text-sm text-foreground">
            <EditorContent :editor="editor" />
        </div>
    </div>
</template>

<style scoped>
.editor-content :deep(.ProseMirror) {
    min-height: v-bind('props.minHeight');
    padding: 0.75rem 1rem;
    outline: none;
    line-height: 1.6;
}

.editor-content :deep(.ProseMirror > *:first-child) {
    margin-top: 0;
}

.editor-content :deep(.ProseMirror p) {
    margin: 0 0 0.5rem;
}

.editor-content :deep(.ProseMirror h1) {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0.75rem 0 0.5rem;
    line-height: 1.3;
}

.editor-content :deep(.ProseMirror h2) {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0.75rem 0 0.5rem;
    line-height: 1.3;
}

.editor-content :deep(.ProseMirror h3) {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0.5rem 0;
    line-height: 1.3;
}

.editor-content :deep(.ProseMirror ul) {
    list-style: disc;
    padding-left: 1.25rem;
    margin: 0 0 0.5rem;
}

.editor-content :deep(.ProseMirror ol) {
    list-style: decimal;
    padding-left: 1.25rem;
    margin: 0 0 0.5rem;
}

.editor-content :deep(.ProseMirror li) {
    margin: 0.15rem 0;
}

.editor-content :deep(.ProseMirror blockquote) {
    border-left: 3px solid var(--border);
    padding-left: 0.75rem;
    margin: 0 0 0.5rem;
    color: var(--muted-foreground);
    font-style: italic;
}

.editor-content :deep(.ProseMirror code) {
    background: var(--muted);
    border-radius: 0.375rem;
    padding: 0.1rem 0.35rem;
    font-size: 0.85em;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.editor-content :deep(.ProseMirror pre) {
    background: var(--muted);
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    margin: 0 0 0.5rem;
    overflow-x: auto;
}

.editor-content :deep(.ProseMirror pre code) {
    background: transparent;
    padding: 0;
}

.editor-content :deep(.ProseMirror a) {
    color: var(--primary);
    text-decoration: underline;
    cursor: pointer;
}

.editor-content :deep(.ProseMirror p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    color: var(--muted-foreground);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
