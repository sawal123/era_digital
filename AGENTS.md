Baca dan pelajari struktur repository ini, termasuk arsitektur, alur data, dependency, testing, dan konvensi coding yang sudah digunakan.

Saya ingin menerapkan workflow AI coding agent yang terstruktur dan mudah dilacak.

**Tugas:**

1. Buat `AGENTS.md` pada root repository sebagai instruksi permanen bagi AI coding agent.
2. Buat `docs/TASKS.md` untuk tracking pekerjaan dengan ID, status, prioritas, dan referensi commit/PR.
3. Buat direktori `docs/bugs/` sebagai tempat dokumentasi bug yang signifikan.
4. Dokumentasikan arsitektur inti pada `docs/ARCHITECTURE.md` berdasarkan kode yang benar-benar ditemukan.

**Aturan kerja agent:**

- Sebelum memperbaiki bug, investigasi penyebabnya melalui source code.
- Jangan langsung mengubah kode berdasarkan asumsi.
- Upayakan mereproduksi bug dan membuat regression test.
- Gunakan solusi paling sederhana yang sesuai arsitektur existing.
- Hindari refactor di luar lingkup tugas.
- Jangan mengubah business logic yang tidak terkait.
- Jalankan pengujian yang relevan setelah perubahan.
- Catat pekerjaan signifikan beserta root cause, perubahan, dan hasil pengujian.
- Jika pengujian tidak dapat dilakukan, jelaskan alasannya.
- Jangan membuat klaim bahwa bug sudah selesai tanpa bukti validasi.
- Jangan melakukan commit atau push tanpa instruksi saya.

Status tracking:
TODO → IN PROGRESS → BLOCKED / REVIEW → DONE

Jangan membuat dokumentasi berlebihan untuk perubahan kecil.

Untuk tugas awal ini, hanya buat dokumentasi dan aturan kerja. Jangan mengubah implementasi aplikasi.
