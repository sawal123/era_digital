# Dokumentasi Fitur Website POS Era Digital

## 1. Ringkasan

Website ini adalah sistem Point of Sale (POS) untuk toko yang melayani penjualan barang fisik, jasa cetak, fotokopi, dan layanan digital/PPOB. Sistem membantu kasir mencatat transaksi, mengelola stok, menghitung pembayaran, mencetak invoice, memantau piutang, serta melihat laporan keuntungan.

Seluruh menu operasional hanya dapat diakses oleh pengguna yang sudah login dan terverifikasi.

## 2. Struktur Menu

### Menu Utama

- Dashboard
- POS Kasir
- Restock Barang
- Laporan Penjualan
- Catatan Piutang
- Pengeluaran
- Pengaturan Toko

### Data Master

- Master Kategori
- Master Produk
- Data Customer
- Data Master Pembayaran
- Mitra Percetakan

## 3. Dashboard

Dashboard menampilkan ringkasan kondisi usaha dan performa transaksi.

Fitur yang tersedia:

- Total omset hari ini.
- Total modal penjualan hari ini.
- Total pengeluaran hari ini.
- Keuntungan bersih hari ini setelah dikurangi pengeluaran.
- Keuntungan dari biaya admin PPOB.
- Grafik performa keuntungan selama 7 hari terakhir.
- Peringatan stok kritis untuk barang fisik yang stoknya sudah mencapai atau berada di bawah batas minimum.

## 4. POS Kasir

POS Kasir digunakan untuk membuat transaksi penjualan. Tampilan tersedia untuk desktop dan mobile.

### 4.1 Jenis Penjualan

POS memiliki tiga kelompok layanan:

1. **Barang Fisik & Fotokopi**
   - Mencari produk berdasarkan nama atau SKU.
   - Menambahkan barang fisik ke keranjang.
   - Menambahkan layanan fotokopi atau print instan berdasarkan jumlah lembar.

2. **Jasa Cetak (Vendor)**
   - Memilih layanan jasa cetak dari produk yang tersedia.
   - Menghitung harga berdasarkan panjang dan lebar untuk produk satuan meter.
   - Mendukung quantity pecahan hingga dua angka desimal, misalnya `0,5`, `1,25`, atau `3,3`.
   - Memilih mitra/vendor percetakan dari data master.
   - Vendor disimpan untuk pencatatan internal dan tidak dicetak pada invoice.
   - Keterangan item pada invoice hanya menampilkan ukuran, misalnya `Ukuran: 2,2x1,5 m`.

3. **Saldo Digital / PPOB**
   - Menjual token listrik, pulsa, dan layanan digital lainnya.
   - Mengisi nomor meter, nomor pelanggan, atau nomor operator.
   - Mengisi nama pemilik akun.
   - Mencari akun digital yang pernah digunakan melalui autocomplete.
   - Menghitung nominal pembelian dan biaya admin.
   - Memeriksa kecukupan saldo digital toko sebelum transaksi diproses.

### 4.2 Keranjang Belanja

Fitur keranjang:

- Menampilkan daftar item, quantity, catatan, dan subtotal item.
- Tombol tambah dan kurang quantity tanpa loading spinner.
- Barang yang sama otomatis digabung dan quantity dijumlahkan.
- Item digital tetap dipisah karena dapat memiliki nomor tujuan berbeda.
- Jasa cetak yang sama menampilkan pilihan:
  - Timpa jumlah lama.
  - Tetap masukkan sebagai baris baru.
- Quantity pecahan ditampilkan secara ringkas tanpa angka floating point panjang.
- Keranjang desktop dapat di-scroll.
- Detail keranjang mobile tampil sebagai bottom sheet.

### 4.3 Customer dan Penerima Invoice

Pilihan customer pada POS:

- Cash / Umum.
- Customer yang sudah tersimpan pada data master.
- Customer sekali beli / penerima invoice.

Customer sekali beli dapat mengisi nama dan nomor telepon tanpa harus disimpan ke master customer. Data tersebut hanya disimpan sebagai penerima invoice pada transaksi.

### 4.4 Metode Pembayaran

Metode pembayaran ditampilkan dalam bentuk kartu dan dapat dikelola melalui Data Master Pembayaran.

Fitur pembayaran:

- Mendukung tunai, QRIS, transfer, dan metode lain yang dibuat pada data master.
- Metode pembayaran dapat disembunyikan atau diaktifkan.
- Pilihan metode pembayaran menggunakan accordion agar tidak memakan banyak ruang.
- Pembayaran tunai membuka modal input uang diterima.
- Modal tunai memiliki keypad angka untuk input melalui layar.
- Sistem menghitung jumlah dibayar, sisa tagihan, dan kembalian.
- Metode non-tunai otomatis dianggap membayar sesuai total transaksi.

### 4.5 Invoice

Setelah transaksi disimpan:

- Sistem membuat nomor invoice otomatis.
- Invoice dapat dicetak atau dibuka pada tab baru.
- Invoice menampilkan identitas toko, customer, item, total, pembayaran, dan tanda tangan digital.
- Invoice dapat diunduh sebagai PDF atau gambar JPG.
- Vendor percetakan tidak ditampilkan pada invoice.

### 4.6 Pintasan Keyboard

- `F2`: Proses pembayaran.
- `F4`: Fokus ke pencarian produk fisik.
- `Esc`: Membersihkan pencarian.

## 5. Restock Barang

Menu Restock Barang digunakan untuk mencatat pembelian stok barang fisik.

Fitur yang tersedia:

- Memilih produk fisik yang akan direstock.
- Mengisi quantity, harga beli, tanggal pembelian, dan catatan.
- Menambah stok produk secara otomatis.
- Memperbarui harga modal produk berdasarkan harga beli terbaru.
- Membuat catatan pergerakan stok masuk.
- Membuat catatan pengeluaran kategori stok secara otomatis.
- Menampilkan riwayat restock.

## 6. Laporan Penjualan

Menu Laporan Penjualan digunakan untuk melihat riwayat transaksi dan keuntungan.

Fitur yang tersedia:

- Filter laporan harian, bulanan, dan tahunan.
- Ringkasan total omset, total modal, dan total keuntungan.
- Pencarian berdasarkan nomor invoice, nama customer, atau metode pembayaran.
- Pilihan jumlah data tabel: 10, 25, 50, atau 100 baris.
- Pagination tabel.
- Menyalin nomor invoice dengan toast singkat dan perubahan warna tombol.
- Melihat detail transaksi, item, pembayaran, dan keuntungan.
- Melihat mitra percetakan sebagai informasi internal pada detail transaksi.
- Mencetak ulang invoice.
- Mengubah penerima invoice untuk transaksi Cash / Umum yang sudah tersimpan.
- Menghapus transaksi dan mengembalikan stok barang fisik terkait.
- Export hasil filter dan pencarian ke PDF.
- Export hasil filter dan pencarian ke Excel.

## 7. Catatan Piutang

Menu Catatan Piutang menampilkan transaksi yang belum lunas atau baru dibayar sebagian.

Fitur yang tersedia:

- Menampilkan transaksi berstatus DP atau piutang.
- Pencarian berdasarkan invoice atau customer.
- Melihat jumlah dibayar dan sisa tagihan.
- Mencatat cicilan atau pelunasan.
- Memperbarui status transaksi menjadi lunas jika pembayaran mencukupi.
- Menyimpan riwayat pembayaran secara kronologis.

## 8. Pengeluaran

Menu Pengeluaran digunakan untuk mencatat biaya usaha.

Fitur yang tersedia:

- Mencatat tanggal, nama pengeluaran, nominal, kategori, dan catatan.
- Kategori pengeluaran:
  - Stok.
  - Operasional.
  - Lainnya.
- Menampilkan daftar pengeluaran.
- Menghapus catatan pengeluaran.
- Pengeluaran dari restock barang dibuat otomatis oleh sistem.

## 9. Pengaturan Toko

Menu Pengaturan Toko digunakan untuk mengatur identitas usaha.

Fitur yang tersedia:

- Mengubah nama toko.
- Mengubah nomor telepon atau WhatsApp toko.
- Mengubah alamat toko.
- Mengatur saldo digital toko untuk transaksi PPOB.
- Mengunggah logo toko.
- Logo dan nama toko tampil pada bagian atas sidebar.
- Mengunggah cap atau tanda tangan digital untuk invoice.

## 10. Master Kategori

Menu Master Kategori digunakan untuk mengelompokkan produk.

Tipe kategori:

- Barang fisik.
- Jasa.
- PPOB.

Fitur yang tersedia:

- Menambah kategori.
- Mengedit kategori.
- Menghapus kategori yang belum memiliki produk.
- Melihat jumlah produk pada setiap kategori.

## 11. Master Produk

Menu Master Produk digunakan untuk mengelola barang dan layanan yang dijual.

Fitur yang tersedia:

- Menambah, mengedit, dan menghapus produk.
- Mengatur kategori, SKU, nama, satuan, harga modal, dan harga jual.
- Mengatur biaya admin untuk produk PPOB.
- Mengatur stok untuk barang fisik.
- Mengaktifkan atau menonaktifkan produk.
- Mengunggah gambar produk.
- Produk yang sudah memiliki riwayat transaksi tidak dapat dihapus, tetapi dapat dinonaktifkan.

## 12. Data Customer

Menu Data Customer digunakan untuk menyimpan direktori pelanggan.

Fitur yang tersedia:

- Menambah, mengedit, mencari, dan menghapus customer.
- Menyimpan nama, nomor telepon, dan alamat.
- Menentukan tipe customer:
  - Umum.
  - Token Listrik.
  - Nomor Operator / Pulsa.
- Customer yang dibuat otomatis dari transaksi PPOB diberi tipe sesuai layanan.

Sistem juga menyimpan akun digital yang pernah digunakan untuk membantu autocomplete nomor token atau nomor operator pada transaksi berikutnya.

## 13. Data Master Pembayaran

Menu Data Master Pembayaran digunakan untuk mengatur pilihan pembayaran pada POS.

Fitur yang tersedia:

- Menambah, mengedit, dan menghapus metode pembayaran.
- Mengatur nama dan kode metode.
- Menentukan apakah metode merupakan pembayaran tunai.
- Mengaktifkan atau menonaktifkan metode.
- Mengatur urutan tampil pada POS.
- Melihat jumlah transaksi yang menggunakan metode tersebut.
- Metode yang sudah digunakan pada transaksi tidak dapat dihapus.
- Hanya satu metode yang dapat ditandai sebagai metode tunai.

## 14. Mitra Percetakan

Menu Mitra Percetakan digunakan untuk mengelola vendor jasa cetak.

Fitur yang tersedia:

- Menambah, mengedit, mencari, dan menghapus mitra.
- Menyimpan nama, nomor telepon, alamat, dan status aktif.
- Mitra aktif tampil sebagai pilihan pada POS Jasa Cetak.
- Melihat jumlah transaksi item yang menggunakan mitra.
- Mitra yang sudah digunakan pada transaksi tidak dapat dihapus.
- Nama mitra hanya untuk kebutuhan internal dan tidak dicetak pada invoice.

## 15. Fitur Antarmuka

- Sidebar dikelompokkan menjadi Menu Utama dan Data Master.
- Sidebar dapat diperkecil menjadi mode ikon.
- Menu mobile tersedia dalam bottom sheet.
- POS memiliki Mode Zen Kasir untuk memperluas area kerja.
- Scrollbar menggunakan gaya minimalis.
- Tombol aksi menampilkan loading spinner saat proses berjalan.
- Tombol navigasi hanya dinonaktifkan setelah diklik.
- Tombol input angka, tab POS, pagination, dan kontrol quantity tidak menggunakan spinner.
- Tombol aksi tabel menggunakan ikon agar tampilan lebih ringkas.
- Notifikasi menggunakan flash message, toast, atau modal sesuai kebutuhan.

## 16. Catatan Operasional

- Jalankan pengaturan data master sebelum menggunakan POS, terutama kategori, produk, metode pembayaran, dan mitra percetakan.
- Pastikan saldo digital toko cukup sebelum menjual layanan PPOB.
- Gunakan customer sekali beli jika pelanggan hanya membutuhkan invoice tanpa perlu masuk ke master customer.
- Gunakan fitur penerima invoice pada Laporan Penjualan jika transaksi umum perlu dicetak ulang atas nama pelanggan.
- Nonaktifkan produk, metode pembayaran, atau mitra yang sudah memiliki riwayat transaksi daripada menghapusnya.
