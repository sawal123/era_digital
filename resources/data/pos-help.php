<?php

// Panduan pengguna yang dikirim ke AI. Perbarui bersama perubahan fitur terkait.
// Jangan masukkan kredensial, data operasional, atau isi .env dalam panduan ini.
return [
    'overview' => [
        'title' => 'Navigasi aplikasi POS',
        'path' => '/dashboard',
        'content' => 'Era Digital melayani barang fisik, fotokopi/print instan, jasa cetak vendor, dan layanan digital/PPOB. Menu utama: Dashboard, POS Kasir, Restock, Laporan, Ledger Modal, Piutang, Pengeluaran, Undangan Cetak, Pengaturan Toko, Update Aplikasi. Data Master: Kategori, Produk, Customer, Pembayaran, Mitra Percetakan. Sidebar desktop bisa diperkecil. Di ponsel gunakan menu navigasi bawah. Semua menu operasional memerlukan login. Chatbot hanya menjelaskan panduan, tidak membuka data usaha, menjalankan transaksi, atau mengubah pengaturan.',
    ],
    'dashboard' => [
        'title' => 'Dashboard',
        'path' => '/dashboard',
        'content' => 'Dashboard menampilkan ringkasan penjualan, modal/HPP, keuntungan, pengeluaran, laba bersih, grafik performa, dan stok kritis. Omzet adalah total nilai penjualan; keuntungan kotor adalah penjualan dikurangi HPP. Laba bersih mengurangi HPP tambahan pesanan dan biaya operasional. Arus kas keluar berbeda dari pengurang laba: pembelian stok, aset/peralatan, dan penarikan pribadi tidak otomatis diperlakukan sebagai biaya operasional. Saldo digital berbeda dari omzet dan keuntungan. Untuk rincian periode/transaksi gunakan Laporan. Chatbot tidak dapat membaca nominal dashboard pengguna.',
    ],
    'pos' => [
        'title' => 'POS Kasir dan pembayaran',
        'path' => '/pos',
        'content' => 'Buka POS Kasir, pilih kelompok barang/layanan, cari produk berdasarkan nama atau SKU, tambahkan item dan kuantitas ke keranjang, tentukan customer bila diperlukan, lalu proses pembayaran. Barang sama dapat digabung; item digital dipisah untuk nomor tujuan berbeda. Periksa item, kuantitas, subtotal, dan catatan sebelum menyimpan. Pilih metode pembayaran yang aktif. Pada tunai, isi uang diterima; kelebihan menjadi kembalian, pembayaran sebagian menjadi DP, dan tanpa pembayaran menjadi piutang. Transaksi belum lunas diteruskan ke Catatan Piutang. Total dihitung ulang server dari produk, dimensi, dan biaya provider, sehingga tidak cukup mengubah angka di tampilan. F2 membuka pembayaran, F4 fokus pencarian barang, Esc membersihkan pencarian. Mode Zen Kasir memperluas area kerja. Penjualan barang fisik ditolak jika stok tidak cukup; tambahkan stok melalui Restock.',
    ],
    'printing' => [
        'title' => 'Jasa cetak, fotokopi, dan ukuran',
        'path' => '/pos',
        'content' => 'Fotokopi/print instan ditambahkan melalui kelompok Barang Fisik & Fotokopi dengan jumlah lembar. Untuk Jasa Cetak (Vendor), pilih produk cetak, isi panjang dan lebar pada produk satuan meter, isi kuantitas, lalu pilih mitra percetakan jika diperlukan. Harga produk meter dihitung dari luas panjang × lebar dan tarif per meter persegi; luas dan uang dibulatkan dua desimal oleh AreaPricingService. Kuantitas pecahan didukung. Bila jasa sama sudah ada, UI menawarkan timpa jumlah lama atau masukkan sebagai baris baru. Vendor untuk catatan internal, tidak ditampilkan pada invoice; ukuran item ditampilkan. Jika salah tarif, periksa Master Produk sebelum transaksi baru.',
    ],
    'digital' => [
        'title' => 'Layanan digital / PPOB',
        'path' => '/pos',
        'content' => 'Di POS pilih Saldo Digital/PPOB, pilih layanan, isi nomor meter/HP/pelanggan dan nama pemilik, lalu nominal, biaya aktual provider, serta biaya admin. Gunakan autocomplete akun digital yang pernah tersimpan untuk memilih akun dan customer terkait. Nominal yang diterima customer terpisah dari biaya aktual provider; biaya aktual provider menjadi HPP dan pengurang saldo toko. Keuntungan adalah harga jual termasuk admin dikurangi HPP efektif. Sistem dapat menyarankan biaya provider dari transaksi terakhir untuk produk dan nominal sama, tetapi periksa biaya aktual. Biaya provider harus lebih dari nol. Saldo toko habis atau lebih kecil dari total biaya provider membuat transaksi ditolak. Saldo pas boleh dihabiskan hingga nol. Periksa Ledger Modal dan Pengaturan Toko untuk modal/saldo; catat tambahan modal sebelum menaikkan saldo di atas modal disetor. Aplikasi mencatat transaksi dan saldo secara internal; panduan ini tidak membuktikan pengiriman pulsa atau pembayaran tagihan ke provider.',
    ],
    'invoice' => [
        'title' => 'Invoice dan cetak ulang',
        'path' => '/reports',
        'content' => 'Setelah transaksi tersimpan, invoice dapat dibuka/dicetak. Untuk cetak ulang, buka Laporan, cari nomor invoice atau customer, buka detail lalu gunakan cetak invoice. Invoice memuat identitas toko, penerima, item, ukuran jasa, pembayaran, sisa tagihan, dan tanda tangan bila diatur. Vendor percetakan hanya informasi internal. Halaman invoice menyediakan cetak dan unduh PDF/JPG. Logo, identitas, dan tanda tangan diatur di Pengaturan Toko. Penerima invoice Cash/Umum dapat diperbarui pada Laporan; perubahan penerima berbeda dari mengubah item atau nilai penjualan.',
    ],
    'purchases' => [
        'title' => 'Restock Barang',
        'path' => '/purchases',
        'content' => 'Buka Restock, tambahkan catatan pembelian, pilih produk fisik, isi kuantitas, harga beli per satuan, tanggal, dan catatan, kemudian simpan. Stok bertambah, harga modal produk diperbarui ke harga beli terbaru, pergerakan stok masuk dicatat, pengeluaran Pembelian Stok dibuat otomatis, dan ledger persediaan bertambah. Jangan mencatat pembelian sama dua kali sebagai pengeluaran operasional. Restock adalah modal persediaan; HPP barang baru menjadi biaya ketika terjual. Restock dapat diedit/dihapus dengan penyesuaian stok dan pengeluaran terkait, tetapi ditolak bila pengurangan membuat stok tidak mencukupi karena sudah terjual. Produk jasa/PPOB bukan objek restock barang fisik.',
    ],
    'reports' => [
        'title' => 'Laporan Penjualan dan keuntungan',
        'path' => '/reports',
        'content' => 'Buka Laporan, pilih filter harian/bulanan/tahunan atau rentang yang tersedia, cari nomor invoice/customer/metode pembayaran, lalu buka detail. Tabel mendukung pagination dan pilihan jumlah baris. Detail menampilkan item, pembayaran, HPP, keuntungan, serta vendor internal. Hasil filter/pencarian dapat diekspor ke PDF dan Excel. HPP/profit PPOB memakai biaya aktual provider bila tersedia; transaksi lama tanpa biaya provider memakai snapshot HPP lama. Laba bersih = keuntungan transaksi - HPP tambahan yang belum tercermin pada transaksi - biaya operasional rutin. Pembelian stok dipisahkan sebagai modal persediaan. Penghapusan transaksi membalik stok dan saldo digital terkait, tetapi ditolak jika terikat alokasi pembayaran vendor atau jika pengembalian saldo digital melampaui modal disetor. Lepaskan alokasi yang terkait atau sesuaikan saldo terlebih dahulu sesuai pesan validasi. Chatbot tidak mengetahui nilai transaksi atau keuntungan aktual pengguna.',
    ],
    'capital' => [
        'title' => 'Ledger Modal',
        'path' => '/capital',
        'content' => 'Ledger Modal memisahkan Modal Disetor, Saldo Digital Tersedia, Keuntungan Digital, Total Saldo Terpakai, dan modal persediaan kategori fisik. Modal disetor adalah dana pemilik yang tercatat; saldo digital adalah sisa saldo yang bisa dipakai; keuntungan adalah akumulasi margin transaksi digital; total pemakaian adalah akumulasi HPP/biaya provider, sehingga pemakaian kumulatif dapat melebihi modal awal karena perputaran. Untuk tambah modal: buka Ledger Modal, klik Tambah Catatan Modal, pilih akun Saldo Digital, arah kredit/modal masuk, nominal, tanggal, dan keterangan, lalu simpan. Tambahan manual menaikkan modal disetor dan saldo digital. Debit menarik modal dan saldo; tidak boleh melebihi saldo tersedia. Tombol Tarik semua mengisi nominal saldo akun terpilih. Penjualan mengurangi saldo berjalan tanpa menambah modal disetor. Penyesuaian dari Pengaturan Toko tidak menambah modal. Saldo digital tersedia tidak boleh lebih dari modal disetor. Saldo lama yang melampaui modal dikoreksi melalui entry debit Penyesuaian dengan histori lama tetap ada. Modal barang fisik berasal dari nilai stok per kategori. Riwayat bisa dicari/difilter berdasarkan akun, jenis, arah, dan rentang tanggal. Entry otomatis tidak dapat dihapus dari UI; entry manual dapat dihapus dengan pembalikan saldo. Jangan menganggap saldo sama dengan laba.',
    ],
    'receivables' => [
        'title' => 'Catatan Piutang dan pelunasan',
        'path' => '/receivables',
        'content' => 'Buka Catatan Piutang untuk transaksi berstatus DP atau piutang. Cari invoice/customer, periksa sudah dibayar dan sisa tagihan, pilih pembayaran/cicilan, isi nominal, lalu simpan. Nominal minimal Rp1 dan maksimal sisa tagihan. Pembayaran sebagian menyisakan DP; pembayaran sebesar sisa tagihan mengubah transaksi menjadi lunas. Riwayat pembayaran disimpan berurutan. Transaksi lunas tidak dapat dibayar lagi. Jangan membuat penjualan baru hanya untuk mencatat pelunasan transaksi lama.',
    ],
    'expenses' => [
        'title' => 'Pengeluaran dan alokasi HPP',
        'path' => '/expenses',
        'content' => 'Buka Pengeluaran, tambah catatan, isi tanggal, nama, nominal, kategori, dan catatan. Kategori backend: HPP Pesanan, Pembelian Stok, Operasional Rutin, Aset/Peralatan, dan Pribadi Pemilik. Form manual tidak menawarkan Pembelian Stok untuk catatan baru karena kategori ini dibuat melalui Restock dan wajib memiliki referensi pembelian. Operasional Rutin mengurangi laba bersih. HPP Pesanan dapat dialokasikan ke transaksi/item dan ditandai sudah atau belum masuk HPP; biaya yang belum tercermin menjadi HPP tambahan. Total alokasi lintas pengeluaran tidak boleh melampaui HPP transaksi/item yang tersedia atau nominal pengeluaran. Aset/Peralatan dan Pribadi Pemilik dicatat sebagai kas keluar terpisah dari biaya operasional rutin. Edit/hapus catatan sesuai aksi yang tersedia, dan periksa alokasi vendor sebelum menghapus transaksi terkait.',
    ],
    'categories' => [
        'title' => 'Master Kategori',
        'path' => '/categories',
        'content' => 'Buka Master Kategori untuk menambah atau mengedit kelompok produk. Pilih tipe Barang Fisik, Jasa, atau PPOB sesuai produk. Kategori menentukan alur stok dan jenis layanan POS. Kategori fisik memiliki akun modal persediaan. Daftar menampilkan jumlah produk; kategori yang masih memiliki produk tidak dapat dihapus. Pindahkan produk dengan memperhatikan histori dan aturan aplikasi.',
    ],
    'products' => [
        'title' => 'Master Produk',
        'path' => '/products',
        'content' => 'Buka Master Produk, tambah produk, isi kategori, SKU bila diperlukan, nama, satuan, harga modal dan jual, status aktif, serta gambar bila diperlukan. Produk fisik memiliki stok dan batas stok minimum; PPOB memiliki biaya admin; produk cetak satuan meter memakai tarif per meter persegi. Produk aktif muncul di POS. Produk berhistori transaksi tidak dapat dihapus; nonaktifkan bila tidak lagi dijual. Tabel menampilkan total terjual kumulatif. Perubahan stok manual saat edit dicatat sebagai adjustment pergerakan stok; gunakan Restock untuk pembelian masuk agar modal, pengeluaran, dan histori pembelian ikut tercatat.',
    ],
    'customers' => [
        'title' => 'Customer dan akun digital',
        'path' => '/customers',
        'content' => 'Data Customer menyimpan nama, telepon, alamat, dan tipe Umum, Token Listrik, atau Operator/Pulsa. Tambah/edit/cari customer melalui menu ini; daftar mendukung pengurutan dan ringkasan transaksi/keuntungan. Satu customer dapat memiliki banyak akun digital seperti nomor meter dan nomor HP. Tambah/edit/hapus akun digital pada detail customer. Nomor akun digital dinormalisasi dan kombinasi tipe/nomor harus unik. POS dapat memilih customer otomatis dari akun digital yang dipilih. Kelompok nama serupa ditampilkan sebagai kandidat duplikat; gunakan gabung customer setelah memastikan identitas orang sama. Nama sama tidak otomatis berarti orang sama. Customer yang sudah memiliki transaksi tidak dapat dihapus dari aksi tabel.',
    ],
    'payment_methods' => [
        'title' => 'Master Pembayaran',
        'path' => '/payment-methods',
        'content' => 'Master Pembayaran mengelola nama, kode, status aktif, urutan, dan penanda tunai metode pembayaran. Tambah/edit metode untuk menawarkannya di POS; metode aktif ditampilkan sesuai urutan. Hanya satu metode ditandai sebagai tunai. Metode yang sudah dipakai transaksi tidak dapat dihapus; nonaktifkan bila tidak lagi dipakai. Pilihan non-tunai dicatat lunas sesuai total server; aplikasi tidak otomatis memverifikasi transfer bank hanya karena metode dipilih.',
    ],
    'print_vendors' => [
        'title' => 'Mitra Percetakan',
        'path' => '/print-vendors',
        'content' => 'Mitra Percetakan menyimpan nama, telepon, alamat, dan status aktif vendor jasa cetak. Tambah/edit/cari mitra di menu ini. Mitra aktif dapat dipilih pada POS Jasa Cetak. Vendor terkait item terlihat dalam detail internal Laporan dan tidak dicetak pada invoice. Vendor yang sudah dipakai tidak dapat dihapus. Pembayaran biaya vendor dicatat melalui Pengeluaran/HPP Pesanan dengan alokasi yang sesuai.',
    ],
    'invitations' => [
        'title' => 'Undangan Cetak',
        'path' => '/undangan',
        'content' => 'Menu Undangan Cetak mengambil daftar undangan dan jenis undangan dari layanan eksternal yang dikonfigurasi administrator. Daftar dapat dicari/disaring/diurutkan dan dipaginasi, dengan pilihan tampilan yang tersedia. Form tambah/edit meliputi nama, jenis, stok, harga jual, harga modal, jumlah terjual, ukuran OPP, promo, favorite, deskripsi, dan gambar. Jenis dipilih dari daftar jenis undangan. Gambar baru bisa ditambah; pada edit, gambar lama dapat ditandai dihapus atau dikembalikan sebelum disimpan. Favorite dan data promo mengikuti form/katalog; jangan menganggap perubahan data katalog otomatis mencatat transaksi POS. Jika gagal memuat atau menyimpan, periksa pesan aplikasi dan minta administrator memeriksa koneksi serta konfigurasi integrasi. Chatbot tidak mengakses API eksternal tersebut dan tidak memiliki kredensialnya.',
    ],
    'store_settings' => [
        'title' => 'Pengaturan Toko',
        'path' => '/settings/store',
        'content' => 'Buka Pengaturan Toko, ubah nama, telepon/WhatsApp, alamat, saldo digital, logo, atau cap/tanda tangan digital, lalu simpan. Identitas/logo dipakai di sidebar dan invoice; tanda tangan pada invoice. Logo dan tanda tangan berupa gambar maksimal 2 MB masing-masing. Saldo digital diisi dengan sisa saldo provider, harus tidak negatif dan tidak melebihi modal disetor di Ledger Modal. Untuk tambahan dana baru, catat kredit/tambahan modal di Ledger Modal terlebih dahulu. Perubahan saldo di sini membuat entry Penyesuaian, tidak menambah modal disetor. Akun demo tidak dapat menyimpan perubahan pengaturan.',
    ],
    'profile' => [
        'title' => 'Profil akun',
        'path' => '/settings/profile',
        'content' => 'Pengaturan Profil memungkinkan pengguna mengubah nama dan email akun sesuai form. Verifikasi email mengikuti fitur autentikasi yang diaktifkan aplikasi. Halaman profil memiliki aksi penghapusan akun dengan konfirmasi password; pastikan memahami dampaknya sebelum menggunakan aksi itu. Akun demo tidak dapat mengubah atau menghapus profil. Chatbot tidak meminta, mengetahui, atau memverifikasi password pengguna.',
    ],
    'security' => [
        'title' => 'Keamanan akun',
        'path' => '/settings/security',
        'content' => 'Pengaturan Keamanan menyediakan perubahan password dengan password saat ini dan konfirmasi password baru. Jika fitur autentikasi dua faktor tersedia, pengguna dapat mengaktifkan/menonaktifkan 2FA dan mengelola recovery code sesuai langkah konfirmasi pada layar. Simpan recovery code secara pribadi. Akun demo tidak dapat mengubah pengaturan keamanan. Jangan kirim password, OTP, recovery code, atau API key ke chatbot. Jika lupa password, gunakan alur Lupa Password pada halaman login bila tersedia.',
    ],
    'appearance' => [
        'title' => 'Tampilan aplikasi',
        'path' => '/settings/appearance',
        'content' => 'Pengaturan Tampilan menyediakan tema terang, gelap, atau mengikuti sistem. Pilih sesuai kenyamanan; perubahan tema tidak mengubah transaksi atau angka keuangan. Pada ponsel gunakan menu navigasi bawah; POS menyediakan Mode Zen Kasir untuk area kerja lebih luas.',
    ],
    'updates' => [
        'title' => 'Update Aplikasi',
        'path' => '/updates',
        'content' => 'Menu Update Aplikasi menampilkan informasi versi, ringkasan perubahan, dan sorotan fitur dari konfigurasi update aplikasi. Modal informasi update tampil sekali per versi pada browser; tombol detail membuka halaman Update Aplikasi. Halaman ini memberi informasi perubahan, bukan tombol memasang software secara otomatis. Informasi versi aktual dibaca pada halaman tersebut; chatbot tidak boleh menebak nomor versi atau menjanjikan fitur yang belum ada di panduan.',
    ],
];
