<?php

namespace App\Http\Controllers;

use App\Models\StoreProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class StoreProfileController extends Controller
{
    /**
     * Show the store settings form.
     */
    public function edit()
    {
        $profile = StoreProfile::firstOrCreate([], [
            'store_name' => 'Era Digital',
            'address' => 'Jl. Raya Utama No. 45, Kebayoran Baru, Jakarta Selatan',
            'phone' => '0812-3456-7890',
            'signature_path' => null,
            'saldo_digital' => 350000.00,
        ]);

        return Inertia::render('settings/Store', [
            'profile' => $profile
        ]);
    }

    /**
     * Update the store settings.
     */
    public function update(Request $request)
    {
        $request->validate([
            'store_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'address' => 'required|string',
            'saldo_digital' => 'required|numeric|min:0',
            'signature' => 'nullable|image|max:2048',
        ]);

        $profile = StoreProfile::first();
        if (!$profile) {
            $profile = new StoreProfile();
        }

        $data = [
            'store_name' => $request->store_name,
            'phone' => $request->phone,
            'address' => $request->address,
            'saldo_digital' => $request->saldo_digital,
        ];

        if ($request->hasFile('signature')) {
            // Hapus gambar tanda tangan lama jika ada
            if ($profile->signature_path) {
                $oldPath = str_replace('/storage/', '', $profile->signature_path);
                Storage::disk('public')->delete($oldPath);
            }

            // Simpan gambar baru
            $path = $request->file('signature')->store('signatures', 'public');
            $data['signature_path'] = '/storage/' . $path;
        }

        $profile->fill($data)->save();

        return redirect()->back()->with('success', 'Pengaturan Toko berhasil diperbarui!');
    }
}
