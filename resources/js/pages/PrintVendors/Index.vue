<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Mitra Percetakan', href: '/print-vendors' },
        ],
    },
});

const props = defineProps({ vendors: Array });
const searchQuery = ref('');
const formOpen = ref(false);
const isEditing = ref(false);
const selectedId = ref(null);

const form = useForm({
    name: '',
    phone: '',
    address: '',
    is_active: true,
});

const filteredVendors = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    if (!query) return props.vendors;
    return props.vendors.filter(vendor =>
        vendor.name.toLowerCase().includes(query) ||
        (vendor.phone && vendor.phone.toLowerCase().includes(query)) ||
        (vendor.address && vendor.address.toLowerCase().includes(query))
    );
});

const openCreateModal = () => {
    isEditing.value = false;
    selectedId.value = null;
    form.reset();
    form.is_active = true;
    form.clearErrors();
    formOpen.value = true;
};

const openEditModal = (vendor) => {
    isEditing.value = true;
    selectedId.value = vendor.id;
    form.name = vendor.name;
    form.phone = vendor.phone || '';
    form.address = vendor.address || '';
    form.is_active = vendor.is_active;
    form.clearErrors();
    formOpen.value = true;
};

const handleSubmit = () => {
    const options = { onSuccess: () => { formOpen.value = false; form.reset(); } };
    if (isEditing.value) form.put(`/print-vendors/${selectedId.value}`, options);
    else form.post('/print-vendors', options);
};

const deleteVendor = (vendor) => {
    if (confirm(`Hapus mitra "${vendor.name}"?`)) router.delete(`/print-vendors/${vendor.id}`);
};
</script>

<template>
    <Head title="Mitra Percetakan" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Data Master Mitra Percetakan</h1>
                <p class="text-sm text-muted-foreground">Kelola vendor yang digunakan untuk pekerjaan jasa cetak.</p>
            </div>
            <Button @click="openCreateModal" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">
                <i class="fas fa-plus text-xs"></i>
                Tambah Mitra
            </Button>
        </div>

        <div class="relative w-full sm:w-80">
            <i class="fas fa-search absolute left-3.5 top-3 text-muted-foreground text-sm"></i>
            <Input v-model="searchQuery" placeholder="Cari nama, telepon, atau alamat..." class="pl-10 rounded-xl bg-card" />
        </div>

        <div class="bg-card rounded-2xl border border-border shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border bg-muted/40 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            <th class="p-4 pl-6">Nama Mitra</th>
                            <th class="p-4">Telepon</th>
                            <th class="p-4">Alamat</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-center">Digunakan</th>
                            <th class="p-4 text-right pr-6">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        <tr v-if="filteredVendors.length === 0">
                            <td colspan="6" class="p-8 text-center text-muted-foreground">Tidak ada data mitra ditemukan.</td>
                        </tr>
                        <tr v-for="vendor in filteredVendors" :key="vendor.id" class="hover:bg-muted/30">
                            <td class="p-4 pl-6 font-semibold text-foreground">{{ vendor.name }}</td>
                            <td class="p-4 text-muted-foreground">{{ vendor.phone || '-' }}</td>
                            <td class="p-4 text-muted-foreground max-w-xs truncate" :title="vendor.address">{{ vendor.address || '-' }}</td>
                            <td class="p-4 text-center">
                                <span class="rounded-full border px-2.5 py-1 text-xs" :class="vendor.is_active ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-muted text-muted-foreground border-border'">
                                    {{ vendor.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="p-4 text-center text-muted-foreground">{{ vendor.transaction_items_count }}</td>
                            <td class="p-4 text-right pr-6 space-x-2">
                                <Button @click="openEditModal(vendor)" variant="ghost" size="icon-sm" title="Edit mitra" aria-label="Edit mitra" class="text-indigo-600"><i class="fas fa-edit"></i></Button>
                                <Button @click="deleteVendor(vendor)" variant="ghost" size="icon-sm" title="Hapus mitra" aria-label="Hapus mitra" class="text-red-600"><i class="fas fa-trash-alt"></i></Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog :open="formOpen" @update:open="formOpen = $event">
            <DialogContent class="sm:max-w-[460px] rounded-2xl bg-card border-border text-foreground">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Edit Mitra Percetakan' : 'Tambah Mitra Percetakan' }}</DialogTitle>
                    <DialogDescription>Mitra aktif akan tampil sebagai pilihan pada POS jasa cetak.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="handleSubmit" class="space-y-4 py-3">
                    <div class="space-y-2">
                        <Label for="vendor-name">Nama Mitra</Label>
                        <Input id="vendor-name" v-model="form.name" class="rounded-xl" required />
                        <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="vendor-phone">Nomor Telepon</Label>
                        <Input id="vendor-phone" v-model="form.phone" class="rounded-xl" />
                    </div>
                    <div class="space-y-2">
                        <Label for="vendor-address">Alamat</Label>
                        <textarea id="vendor-address" v-model="form.address" rows="3" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="space-y-2">
                        <Label for="vendor-status">Status</Label>
                        <select id="vendor-status" v-model="form.is_active" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm">
                            <option :value="true">Aktif</option>
                            <option :value="false">Nonaktif</option>
                        </select>
                    </div>
                    <DialogFooter class="pt-3 gap-2">
                        <DialogClose as-child><Button type="button" variant="secondary" class="rounded-xl">Batal</Button></DialogClose>
                        <Button type="submit" :disabled="form.processing" class="rounded-xl bg-indigo-600 text-white hover:bg-indigo-700">{{ form.processing ? 'Menyimpan...' : 'Simpan' }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
