<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps({
    profile: Object,
});

const form = useForm({
    store_name: props.profile.store_name || '',
    phone: props.profile.phone || '',
    address: props.profile.address || '',
    saldo_digital:
        props.profile.saldo_digital !== undefined
            ? parseFloat(props.profile.saldo_digital)
            : 350000,
    logo: null,
    signature: null,
});

const logoPreview = ref(props.profile.logo_path || null);
const signaturePreview = ref(props.profile.signature_path || null);
const logoInputRef = ref(null);
const signatureInputRef = ref(null);

const handleLogoChange = (e) => {
    const file = e.target.files[0];

    if (file) {
        form.logo = file;
        const reader = new FileReader();
        reader.onload = (event) => {
            logoPreview.value = event.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const handleSignatureChange = (e) => {
    const file = e.target.files[0];

    if (file) {
        form.signature = file;
        const reader = new FileReader();
        reader.onload = (event) => {
            signaturePreview.value = event.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const handleSubmit = () => {
    form.post('/settings/store', {
        forceFormData: true,
        onSuccess: () => {
            // Berhasil
        },
    });
};
</script>

<template>
    <Head title="Pengaturan Toko" />

    <div class="font-inter flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profil Toko & Bukti Sah Struk"
            description="Lengkapi identitas toko Anda untuk dicetak sebagai kop nota transaksi dan cap tanda tangan digital."
        />

        <form @submit.prevent="handleSubmit" class="max-w-xl space-y-6">
            <!-- Nama Toko -->
            <div class="grid gap-2">
                <Label for="store_name">Nama Toko</Label>
                <Input
                    id="store_name"
                    class="mt-1 block w-full border-border bg-background text-foreground"
                    v-model="form.store_name"
                    required
                    placeholder="Contoh: Era Digital Print"
                />
                <p v-if="form.errors.store_name" class="text-xs text-red-500">
                    {{ form.errors.store_name }}
                </p>
            </div>

            <!-- Nomor HP -->
            <div class="grid gap-2">
                <Label for="phone">Nomor HP / WhatsApp Toko</Label>
                <Input
                    id="phone"
                    class="mt-1 block w-full border-border bg-background text-foreground"
                    v-model="form.phone"
                    required
                    placeholder="Contoh: 0812-3456-7890"
                />
                <p v-if="form.errors.phone" class="text-xs text-red-500">
                    {{ form.errors.phone }}
                </p>
            </div>

            <!-- Alamat Toko -->
            <div class="grid gap-2">
                <Label for="address">Alamat Lengkap Toko</Label>
                <textarea
                    id="address"
                    rows="3"
                    class="flex min-h-[80px] w-full rounded-md border border-border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    v-model="form.address"
                    required
                    placeholder="Tulis alamat ruko atau cabang lengkap..."
                ></textarea>
                <p v-if="form.errors.address" class="text-xs text-red-500">
                    {{ form.errors.address }}
                </p>
            </div>

            <!-- Saldo Digital Toko -->
            <div class="grid gap-2">
                <Label for="saldo_digital">Saldo Digital Toko (Rupiah)</Label>
                <div class="relative">
                    <span
                        class="absolute top-3 left-3 font-mono text-xs font-bold text-muted-foreground"
                        >Rp</span
                    >
                    <Input
                        id="saldo_digital"
                        type="number"
                        class="block w-full rounded-xl border-border bg-background pl-9 text-foreground"
                        v-model.number="form.saldo_digital"
                        required
                        placeholder="Contoh: 350000"
                    />
                </div>
                <p class="text-xs text-muted-foreground">
                    Isi dengan sisa float deposit atau saldo server PPOB Anda
                    saat ini. Saldo ini akan terpotong secara otomatis tiap ada
                    transaksi digital sukses.
                </p>
                <p
                    v-if="form.errors.saldo_digital"
                    class="text-xs text-red-500"
                >
                    {{ form.errors.saldo_digital }}
                </p>
            </div>

            <!-- Upload Logo Toko -->
            <div
                class="grid gap-3 rounded-2xl border border-border/60 bg-muted/20 p-4"
            >
                <Label for="logo" class="flex items-center gap-2 font-bold">
                    <i class="fas fa-store text-sm text-indigo-500"></i>
                    Logo Toko
                </Label>
                <p class="text-xs text-muted-foreground">
                    Logo akan ditampilkan pada bagian atas sidebar aplikasi.
                    Gunakan gambar persegi PNG atau JPG maksimal 2MB.
                </p>

                <input
                    ref="logoInputRef"
                    id="logo"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="handleLogoChange"
                />

                <div class="mt-2 flex items-center gap-4">
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-xl border-indigo-200 bg-background text-indigo-600 hover:border-indigo-400 dark:border-indigo-900/50 dark:text-indigo-400"
                        @click="() => logoInputRef.click()"
                    >
                        <i class="fas fa-upload mr-2 text-xs"></i>
                        Pilih Logo
                    </Button>

                    <div
                        v-if="logoPreview"
                        class="relative flex h-20 w-20 items-center justify-center overflow-hidden rounded-xl border border-border/80 bg-white p-2"
                    >
                        <img
                            :src="logoPreview"
                            alt="Pratinjau Logo Toko"
                            class="max-h-full max-w-full object-contain"
                        />
                    </div>
                    <div v-else class="text-xs text-muted-foreground italic">
                        Belum ada logo toko diunggah.
                    </div>
                </div>
                <p v-if="form.errors.logo" class="mt-1 text-xs text-red-500">
                    {{ form.errors.logo }}
                </p>
            </div>

            <!-- Upload Tanda Tangan Digital -->
            <div
                class="grid gap-3 rounded-2xl border border-border/60 bg-muted/20 p-4"
            >
                <Label
                    for="signature"
                    class="flex items-center gap-2 font-bold"
                >
                    <i
                        class="fas fa-file-signature text-sm text-indigo-500"
                    ></i>
                    Gambar Cap / Tanda Tangan Digital
                </Label>
                <p class="text-xs text-muted-foreground">
                    Pilih berkas gambar tanda tangan berformat PNG transparan
                    (maksimal 2MB). Ini akan tercetak otomatis di bagian paling
                    bawah invoice.
                </p>

                <input
                    ref="signatureInputRef"
                    id="signature"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="handleSignatureChange"
                />

                <div class="mt-2 flex items-center gap-4">
                    <!-- Trigger button -->
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-xl border-indigo-200 bg-background text-indigo-600 hover:border-indigo-400 dark:border-indigo-900/50 dark:text-indigo-400"
                        @click="() => signatureInputRef.click()"
                    >
                        <i class="fas fa-upload mr-2 text-xs"></i>
                        Pilih Gambar
                    </Button>

                    <!-- Preview box -->
                    <div
                        v-if="signaturePreview"
                        class="relative flex h-20 w-32 items-center justify-center overflow-hidden rounded-xl border border-border/80 bg-white p-2"
                    >
                        <img
                            :src="signaturePreview"
                            alt="Pratinjau Tanda Tangan"
                            class="max-h-full max-w-full object-contain"
                        />
                    </div>
                    <div v-else class="text-xs text-muted-foreground italic">
                        Belum ada tanda tangan diunggah.
                    </div>
                </div>
                <p
                    v-if="form.errors.signature"
                    class="mt-1 text-xs text-red-500"
                >
                    {{ form.errors.signature }}
                </p>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-xl bg-indigo-600 text-white hover:bg-indigo-700"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                </Button>
            </div>
        </form>
    </div>
</template>
