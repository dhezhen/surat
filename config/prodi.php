<?php

// Nilai bawaan. Super Admin dapat menggantinya di menu Pengaturan tanpa mengubah berkas ini.
return [
    'nama_aplikasi' => env('PRODI_NAMA_APLIKASI', 'SIP-TI'),
    'nama_unit' => env('PRODI_NAMA_UNIT', 'Program Studi Teknik Informatika'),

    'nama_universitas' => env('PRODI_UNIVERSITAS', 'UNIVERSITAS KUNINGAN'),
    'nama_fakultas' => env('PRODI_FAKULTAS', 'FAKULTAS ILMU KOMPUTER'),
    'nama_prodi' => env('PRODI_NAMA', 'PROGRAM STUDI TEKNIK INFORMATIKA S1'),
    // Satu baris per kampus; pisahkan dengan tanda | bila diatur lewat .env.
    'alamat' => array_values(array_filter(array_map('trim', explode('|', env(
        'PRODI_ALAMAT',
        'Kampus I : Jalan Cut Nyak Dhien Kelurahan Cijoho Kuningan|Kampus II : Jalan Pramuka Nomor 67 Kuningan 45512 Telp/Fax. (0232) 871982'
    ))))),
    'kota' => env('PRODI_KOTA', 'Kuningan'),

    'kode_fakultas' => env('PRODI_KODE_FAKULTAS', 'FKOM-UNIKU'),
    'kode_prodi' => env('PRODI_KODE', 'TI-S1'),

    // Token: {urut} {prodi} {fakultas} {bulan} {tahun}. Tingkat prodi tidak memakai kode klasifikasi surat.
    'format_nomor' => env('PRODI_FORMAT_NOMOR', '{urut}/{prodi}/{fakultas}/{bulan}/{tahun}'),
    // Jumlah digit nomor urut; 0 berarti tanpa nol di depan (86), 3 berarti 086.
    'nomor_pad' => (int) env('PRODI_NOMOR_PAD', 0),

    // Label nomor induk pada blok tanda tangan (NIK, NIDN, NIP).
    'label_id_penandatangan' => env('PRODI_LABEL_ID', 'NIK'),

    'kategori_surat_masuk' => ['Rektorat', 'Fakultas', 'Instansi Luar', 'Undangan', 'Lainnya'],
];
