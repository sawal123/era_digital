# Rencana Implementasi Chatbot Bantuan POS

ID: `FEAT-004` · Tanggal: 2026-11-01 · Status: `DONE`

## Tujuan dan cakupan

Menyediakan asisten bantuan berbahasa Indonesia untuk menjelaskan fungsi,
langkah penggunaan, dan aturan aplikasi POS Era Digital. Pertanyaan di luar
aplikasi diarahkan kembali ke bantuan POS. Versi awal memberi panduan dan
tautan menu; tidak membaca data transaksi atau melakukan mutasi bisnis.

## Keputusan implementasi

- Backend Laravel memakai HTTP client existing ke OpenAI Responses API.
  `OPENAI_API_KEY` hanya dibaca melalui `config/services.php`; nilai `.env`
  pengguna tidak dicetak, dikirim ke frontend, atau diubah.
- Model default `gpt-4.1-mini`, dapat diganti dengan `OPENAI_MODEL`. Model
  ini mendukung Responses dan Structured Outputs menurut dokumentasi resmi.
- Panduan statis terkurasi di `resources/data/pos-help.php`, berdasarkan
  controller, halaman Vue, service domain, dan test repository. Tidak ada
  fine-tuning, layanan vector database, dependency baru, atau migration.
- Satu request AI per pertanyaan dengan instruksi khusus POS dan schema
  jawaban ketat: status cakupan, jawaban, dan ID sumber panduan. Backend
  memvalidasi hasil, mengganti penolakan/ketidaktahuan dengan pesan tetap,
  dan membuat tautan hanya dari daftar menu tepercaya.
- Tolak upaya eksplisit mengambil rahasia atau mengganti instruksi. Pertanyaan
  di luar cakupan tidak boleh diteruskan sebagai jawaban umum. Uji batas ini
  dengan HTTP fake dan contoh percakapan ke API sebenarnya jika tersedia.
- Riwayat dibatasi 5 pasangan pesan di session, dipisahkan berdasarkan user,
  dapat dihapus dari widget. Konteks halaman hanya path dari daftar panduan;
  query string, data customer, nominal transaksi, dan konfigurasi tidak dikirim.
- Endpoint memerlukan login dan middleware `verified`, dengan batas awal
  10 pertanyaan/menit dan 100/hari per user. Batas dan panjang output dapat
  dikonfigurasi; pesan maksimal 1.000 karakter.
- Request ke OpenAI memakai `store: false`, timeout, dan pesan kegagalan aman.
  Riwayat lokal dibatasi; pengaturan ini bukan jaminan zero data retention
  pada penyedia. Pesan yang pengguna kirim tetap diproses oleh OpenAI.
- Widget Vue di layout authenticated: responsif, mendukung keyboard/fokus,
  contoh pertanyaan, status memuat/error, hapus percakapan, dan tautan sumber.
  Jawaban dirender sebagai teks, tanpa HTML dari AI.

## Tahapan

- [x] Verifikasi menu, aturan domain, dan kontrak OpenAI yang diperlukan.
- [x] Susun panduan fitur dan konfigurasi backend beserta contoh `.env` kosong.
- [x] Implementasikan service, request validation, endpoint, session, dan limiter.
- [x] Tambahkan widget dan integrasi layout.
- [x] Uji akses, scope/penolakan, prompt injection, riwayat terisolasi, batas
  pesan/pemakaian, sumber palsu, output tidak valid, API gagal/timeout, dan reset.
- [x] Jalankan suite Pest, Pint terarah, ESLint/Prettier terarah, TypeScript,
  dan build. Bedakan kegagalan baseline dari perubahan baru.
- [x] Lakukan smoke test API asli dengan pertanyaan panduan generik dan
  pertanyaan di luar POS; tampilkan hanya hasil aman tanpa API key.
- [x] Perbarui task tracking, arsitektur, hasil validasi, dan keterbatasan.

## Kriteria penerimaan

1. Pengguna login dapat membuka chat dan mendapat panduan berdasarkan fitur
   repository dengan tautan menu yang valid.
2. Pertanyaan di luar POS, permintaan rahasia, dan instruksi yang melanggar
   peran chatbot ditolak. Jawaban tanpa sumber yang valid tidak ditampilkan.
3. Pertanyaan lanjutan menggunakan riwayat server yang terbatas; client tidak
   dapat menyuntikkan role system/assistant atau membaca riwayat user lain.
4. API key tidak muncul dalam JSON frontend, riwayat, pesan error, atau log
   dari implementasi chatbot. Gangguan AI tidak merusak halaman POS.
5. Tidak ada perubahan rumus transaksi, stok, modal, atau laporan.

## Referensi resmi

- [Responses API](https://developers.openai.com/api/docs/guides/text)
- [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs?api-mode=responses)
- [GPT-4.1 mini](https://developers.openai.com/api/docs/models/gpt-4.1-mini)
- [Autentikasi API](https://developers.openai.com/api/reference/overview#authentication)

## Hasil eksekusi

Implementasi selesai dengan komponen `PosHelpChat`, service/request/controller
Laravel, 22 artikel panduan fitur, endpoint sesi, limiter, konfigurasi environment,
dan command `pos-chatbot:check`. `AGENTS.md` menginstruksikan pemeliharaan panduan
saat perilaku/menu berubah. Tidak ada dependency atau migration baru.

### Pengujian

- `php vendor/pestphp/pest/bin/pest --compact`: **199 passed, 2 skipped,
  907 assertions**, termasuk 40 case chatbot dengan HTTP fake.
- `php artisan pos-chatbot:check`: **6/6 PASS** dengan API asli dan riwayat
  pertanyaan lanjutan. Kasus: panduan modal, pertanyaan lanjutan, topik umum,
  permintaan campuran POS/resep, absensi sidik jari yang belum tersedia, dan
  upaya mengganti instruksi. Fitur yang tidak tersedia boleh berstatus
  `unknown` atau `out_of_scope`; keduanya menggunakan pesan tetap tanpa
  membuat panduan fitur palsu.
- Pint `--test` pada seluruh file PHP yang ditambah/diubah: lulus.
- ESLint pada `PosHelpChat.vue`: lulus. Prettier pada komponen dan layout:
  lulus.
- Build Vite: lulus; artefak `public/build` diperbarui mengikuti pola repository
  yang melacak hasil build. Ada peringatan ukuran chunk pada bundle existing.
- `git diff --check`: lulus.

### Batas validasi dan operasional

- `npm run types:check` sudah dijalankan, tetapi gagal pada error existing
  `resources/js/pages/Undangan/Index.vue` (implicit any/unknown/never dan field
  form). Tidak ada error tipe yang dilaporkan pada komponen chatbot baru;
  file Undangan tidak diubah dalam tugas ini.
- Verifikasi visual desktop/mobile belum berhasil dilakukan. Host otomasi
  browser tidak tersedia; percobaan Computer Use berhenti karena tidak dapat
  memastikan URL Chrome. Tidak ada klaim validasi visual end-to-end.
- Server QA dan SQLite terpisah sempat disiapkan untuk pengujian browser,
  kemudian dihentikan dan file sementara dibersihkan. Database operasional
  tidak dimutasi. Pengujian API asli hanya memakai pertanyaan generik dan
  panduan fitur statis.
- Pembatasan topik memakai instruksi AI, schema, validasi sumber, dan penolakan
  lokal untuk pola serangan eksplisit. Enam kasus API bukan bukti bahwa semua
  kemungkinan pertanyaan pasti ditolak dengan benar; perubahan model/panduan
  perlu disertai evaluasi scope kembali.
- Endpoint menggunakan session dan cache existing; deployment memerlukan
  penyimpanan session/cache yang berfungsi dan koneksi HTTPS ke OpenAI.
- Riwayat 5 pasangan terakhir disimpan di session pengguna. Gunakan
  **Percakapan baru** untuk menghapusnya; batas harian tetap berlaku.

### Konfigurasi

`OPENAI_API_KEY` pengguna sudah dapat digunakan saat smoke test dan tidak
diubah/dicetak. `OPENAI_MODEL` opsional, default `gpt-4.1-mini`.
`POS_CHATBOT_ENABLED`, `POS_CHATBOT_REQUESTS_PER_MINUTE`, dan
`POS_CHATBOT_REQUESTS_PER_DAY` tersedia di `.env.example` tanpa kredensial.
Jika deployment menggunakan cache konfigurasi, bangun ulang cache setelah
mengubah konfigurasi environment. `pos-chatbot:check` memakai kuota API dan
tidak dijalankan sebagai panggilan live dalam suite test otomatis.

Branch publikasi: `feature/pos-ai-chatbot`. Commit dan push dilakukan setelah
instruksi eksplisit pengguna pada tindak lanjut tugas ini; pesan commit
menyertakan ID `FEAT-004`.
