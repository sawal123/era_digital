<?php

$chatbotUpdate = [
    'version' => '2026.11.01',
    'date' => '1 November 2026',
    'title' => 'Asisten POS: bantuan AI langsung di aplikasi',
    'summary' => 'Bingung memakai suatu fitur? Sekarang Anda bisa bertanya langsung di aplikasi dan mendapatkan panduan tanpa meninggalkan halaman kerja.',
    'highlights' => [
        'Tanyakan cara menggunakan transaksi, stok, piutang, laporan, dan Ledger Modal.',
        'Lanjutkan pertanyaan sesuai halaman dan percakapan yang sedang dibuka.',
        'Buka menu terkait langsung dari tautan yang tersedia pada jawaban.',
        'Asisten berfokus pada aplikasi POS dan mengarahkan kembali pertanyaan di luar cakupan.',
    ],
    'action' => [
        'type' => 'open-chat',
        'label' => 'Coba Bantuan POS',
    ],
    'getting_started' => [
        'Klik Bantuan POS di pojok kanan bawah, atau gunakan tombol di halaman ini.',
        'Tulis pertanyaan tentang menu atau langkah yang ingin Anda pahami.',
        'Ikuti panduan dan buka tautan menu yang disarankan bila diperlukan.',
    ],
];

return [
    'latest' => $chatbotUpdate,

    'history' => [
        $chatbotUpdate,
        [
            'version' => '2026.10.29',
            'date' => '29 Oktober 2026',
            'title' => 'Pembaruan pengelolaan modal dan transaksi',
            'summary' => 'Peningkatan pencatatan modal, saldo digital, customer, laporan, dan pengalaman penggunaan aplikasi.',
            'highlights' => [
                'Ledger modal dan saldo digital yang lebih transparan.',
                'Perhitungan provider cost dan laba PPOB lebih akurat.',
                'Filter riwayat ledger dengan rentang tanggal.',
                'Pengelolaan customer dan akun digital yang lebih terstruktur.',
            ],
        ],
    ],
];
