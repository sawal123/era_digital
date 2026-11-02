# UI-011 — Akses API eksternal Undangan Cetak

## Gejala dan penyebab

Halaman `/undangan` sebelumnya selalu merender katalog, termasuk ketika key
belum tersedia atau layanan menolak akses. Kegagalan daftar hanya ditampilkan
sebagai banner; penolakan endpoint jenis undangan diabaikan. Akibatnya tabel
kosong dan tombol pengelolaan tetap terlihat, tanpa penjelasan kebutuhan akses.

`UndanganController@index` mengakses dua endpoint melalui Laravel HTTP client
dengan konfigurasi `services.undangan`. Data berasal dari layanan eksternal,
tanpa model atau tabel katalog undangan lokal. Route tetap memakai middleware
login/verifikasi existing.

## Perubahan

- Key kosong menghentikan pemanggilan API. HTTP 401/403 pada daftar, halaman
  lanjutan, atau jenis undangan mengganti katalog dengan `Undangan/ApiKeyRequired`.
  Data parsial dan body penolakan tidak dikirim ke halaman tersebut.
- Teks utama: **Halaman ini membutuhkan API KEY dari Eksternal**. Ilustrasi
  amplop terkunci dan kunci bergerak memakai SVG/CSS lokal, mendukung tema
  aplikasi dan `prefers-reduced-motion`.
- Tombol Coba Lagi meminta ulang `/undangan`; Kembali ke Dashboard membuka
  `/dashboard`; Tanya Asisten POS memakai event pembuka chatbot existing.
- Panduan chatbot menjelaskan katalog, akses eksternal, langkah pemulihan,
  perbedaan key undangan dan chatbot, serta batas pencatatan katalog vs POS.
  Chatbot tidak meminta key, memasang key, atau mengklaim memverifikasi akses.

## Reproduksi dan validasi

- Sebelum implementasi, delapan case akses dan satu case materi chatbot baru
  gagal; halaman masih `Undangan/Index` atau data parsial masih dikirim.
- Test memakai HTTP fake, key dummy, dan SQLite in-memory; API operasional tidak
  dipanggil. Key kosong/null/spasi, status 401/403 dari kedua endpoint, penolakan
  pada pagination, serta pembeda gangguan 500 tercakup.
- `php vendor/pestphp/pest/bin/pest tests/Feature/UndanganApiAccessTest.php tests/Feature/UndanganCetakTest.php tests/Feature/PosChatbotTest.php`:
  **65 test lulus, 428 assertion**. Alur daftar sukses dan mutasi existing ikut diuji.
- ESLint dan Prettier halaman baru lulus. Pint test/panduan lulus; controller
  memiliki empat jenis temuan format yang sama pada isi HEAD sebelum perubahan
  (`function_declaration`, `concat_space`, `unary_operator_spaces`, dan
  `not_operator_with_successor_space`), sehingga format existing dipertahankan.
- Build Vite lulus; artefak build dilacak repository dan ikut diperbarui.
- Pemeriksaan tipe melalui `node node_modules/vue-tsc/bin/vue-tsc.js --noEmit`
  melaporkan 81 error existing di `Undangan/Index.vue` yang tidak diubah; tidak
  ada diagnostic di halaman baru atau file lain. `git diff --check` lulus.
- Saat menyiapkan commit, pemeriksaan staged source dengan pengecualian
  `public/build` lulus. Pemeriksaan staged seluruh file memberi satu peringatan
  trailing whitespace pada template literal `renderMarkdown` hard break di
  bundle hasil build. Dua spasi Markdown tersebut dipertahankan sebagai isi
  string, bukan dihapus dari artefak generator. Manifest build lengkap tanpa
  referensi file atau entry yang hilang.

## Keterbatasan

HTTP 403 berarti akses ditolak dan tidak selalu berarti key salah. Penanganan
ini didasarkan pada key kosong dan status autentikasi/otorisasi HTTP 401/403;
belum diuji terhadap layanan eksternal asli atau penolakan dengan format/status
nonstandar. Pengujian chatbot memakai HTTP fake, bukan jawaban model asli.
Host akses browser tidak tersedia pada sesi ini, sehingga verifikasi visual
desktop/mobile dan interaksi tombol di browser belum dilakukan. Suite lengkap
backend tidak dijalankan; perubahan finansial tidak ada.
