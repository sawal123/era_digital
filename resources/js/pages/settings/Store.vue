<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
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
    saldo_digital: props.profile.saldo_digital !== undefined ? parseFloat(props.profile.saldo_digital) : 350000,
    signature: null,
});

const imagePreview = ref(props.profile.signature_path || null);
const fileInputRef = ref(null);

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.signature = file;
        const reader = new FileReader();
        reader.onload = (event) => {
            imagePreview.value = event.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const handleSubmit = () => {
    form.post('/settings/store', {
        forceFormData: true,
        onSuccess: () => {
            // Berhasil
        }
    });
};
</script>

<template>
    <Head title="Pengaturan Toko" />

    <div class="flex flex-col space-y-6 font-inter">
        <Heading
            variant="small"
            title="Profil Toko & Bukti Sah Struk"
            description="Lengkapi identitas toko Anda untuk dicetak sebagai kop nota transaksi dan cap tanda tangan digital."
        />

        <form @submit.prevent="handleSubmit" class="space-y-6 max-w-xl">
            <!-- Nama Toko -->
            <div class="grid gap-2">
                <Label for="store_name">Nama Toko</Label>
                <Input
                    id="store_name"
                    class="mt-1 block w-full bg-background border-border text-foreground"
                    v-model="form.store_name"
                    required
                    placeholder="Contoh: Era Digital Print"
                />
                <p v-if="form.errors.store_name" class="text-xs text-red-500">{{ form.errors.store_name }}</p>
            </div>

            <!-- Nomor HP -->
            <div class="grid gap-2">
                <Label for="phone">Nomor HP / WhatsApp Toko</Label>
                <Input
                    id="phone"
                    class="mt-1 block w-full bg-background border-border text-foreground"
                    v-model="form.phone"
                    required
                    placeholder="Contoh: 0812-3456-7890"
                />
                <p v-if="form.errors.phone" class="text-xs text-red-500">{{ form.errors.phone }}</p>
            </div>

            <!-- Alamat Toko -->
            <div class="grid gap-2">
                <Label for="address">Alamat Lengkap Toko</Label>
                <textarea
                    id="address"
                    rows="3"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 text-foreground border-border"
                    v-model="form.address"
                    required
                    placeholder="Tulis alamat ruko atau cabang lengkap..."
                ></textarea>
                <p v-if="form.errors.address" class="text-xs text-red-500">{{ form.errors.address }}</p>
            </div>

            <!-- Saldo Digital Toko -->
            <div class="grid gap-2">
                <Label for="saldo_digital">Saldo Digital Toko (Rupiah)</Label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-muted-foreground text-xs font-bold font-mono">Rp</span>
                    <Input
                        id="saldo_digital"
                        type="number"
                        class="pl-9 block w-full bg-background border-border text-foreground rounded-xl"
                        v-model.number="form.saldo_digital"
                        required
                        placeholder="Contoh: 350000"
                    />
                </div>
                <p class="text-xs text-muted-foreground">Isi dengan sisa float deposit atau saldo server PPOB Anda saat ini. Saldo ini akan terpotong secara otomatis tiap ada transaksi digital sukses.</p>
                <p v-if="form.errors.saldo_digital" class="text-xs text-red-500">{{ form.errors.saldo_digital }}</p>
            </div>

            <!-- Upload Tanda Tangan Digital -->
            <div class="grid gap-3 p-4 bg-muted/20 border border-border/60 rounded-2xl">
                <Label for="signature" class="font-bold flex items-center gap-2">
                    <i class="fas fa-file-signature text-indigo-500 text-sm"></i>
                    Gambar Cap / Tanda Tangan Digital
                </Label>
                <p class="text-xs text-muted-foreground">Pilih berkas gambar tanda tangan berformat PNG transparan (maksimal 2MB). Ini akan tercetak otomatis di bagian paling bawah invoice.</p>
                
                <input
                    ref="fileInputRef"
                    id="signature"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="handleFileChange"
                />
                
                <div class="flex items-center gap-4 mt-2">
                    <!-- Trigger button -->
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-xl border-indigo-200 hover:border-indigo-400 bg-background text-indigo-600 dark:text-indigo-400 dark:border-indigo-900/50"
                        @click="() => fileInputRef.click()"
                    >
                        <i class="fas fa-upload mr-2 text-xs"></i>
                        Pilih Gambar
                    </Button>

                    <!-- Preview box -->
                    <div v-if="imagePreview" class="relative border border-border/80 rounded-xl bg-white p-2 h-20 w-32 flex items-center justify-center overflow-hidden">
                        <img :src="imagePreview" alt="Pratinjau Tanda Tangan" class="max-h-full max-w-full object-contain" />
                    </div>
                    <div v-else class="text-xs italic text-muted-foreground">Belum ada tanda tangan diunggah.</div>
                </div>
                <p v-if="form.errors.signature" class="text-xs text-red-500 mt-1">{{ form.errors.signature }}</p>
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                </Button>
            </div>
        </form>
    </div>
</template>
