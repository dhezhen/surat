<?php

namespace Database\Seeders;

use App\Models\JenisSurat;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'staff@prodi.ac.id'], [
            'name' => 'Sekretaris Prodi, S.Kom.',
            'jabatan' => 'Sekretaris Program Studi Teknik Informatika',
            'role' => User::ROLE_STAFF,
            'password' => 'password',
        ]);

        User::updateOrCreate(['email' => 'kaprodi@prodi.ac.id'], [
            'name' => 'Iwan Lesmana, M.Kom',
            'jabatan' => 'Kepala Program Studi Teknik Informatika',
            'nidn' => '41038091288',
            'role' => User::ROLE_KAPRODI,
            'password' => 'password',
        ]);

        $this->buatSuperAdmin();

        $lampiran = ['name' => 'lampiran', 'label' => 'Lampiran', 'type' => 'text', 'required' => false, 'default' => '-'];

        // Blok Nomor/Lampiran/Perihal dan alamat tujuan mengikuti tata letak surat dinas prodi.
        $kepala = <<<'HTML'
<table style="width:100%; margin-bottom:12px">
<tr><td style="width:2.4cm">Nomor</td><td style="width:0.4cm">:</td><td>{{nomor_surat}}</td></tr>
<tr><td>Lampiran</td><td>:</td><td>{{lampiran}}</td></tr>
<tr><td>Perihal</td><td>:</td><td>{{perihal}}</td></tr>
</table>
<p>Kepada Yth.<br>{{tujuan_surat}}<br>Di tempat.</p>
HTML;

        // Halaman lampiran opsional: dicetak setelah tanda tangan, tanpa kop, hanya bila judul atau tabelnya diisi.
        $lampiranSchema = [
            ['name' => 'judul_lampiran', 'label' => 'Judul halaman lampiran (opsional)', 'type' => 'text', 'required' => false],
            ['name' => 'tabel_lampiran', 'label' => 'Tabel lampiran (opsional)', 'type' => 'tabel', 'required' => false, 'kolom' => ['Nama', 'Keterangan']],
        ];
        $lampiranHalaman = '{{halaman_baru}}<p style="text-align:center"><strong>{{judul_lampiran}}</strong></p>{{tabel_lampiran}}';

        // Isi surat di bawah ini disusun dari pola surat-surat nyata prodi.
        $undangan = <<<'HTML'
<p>Assalamu'alaikum Warahmatullahi Wabarakatuh.</p>
<p>Dengan hormat,</p>
<p class="indent">{{pembuka}}</p>
<table style="margin-left:1.2cm; margin-bottom:8px"><tbody>
<tr><td style="width:3cm">Hari, tanggal</td><td>: {{hari_tanggal_lengkap}}</td></tr>
<tr><td>Waktu</td><td>: {{waktu}}</td></tr>
<tr><td>Tempat</td><td>: {{tempat}}</td></tr>
</tbody></table>
<p class="indent">Mengingat pentingnya kegiatan tersebut, kami mengharapkan kehadiran Bapak/Ibu tepat pada waktunya.</p>
<p class="indent">Demikian surat undangan ini kami sampaikan. Atas perhatian, kehadiran, dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>
<p>Wassalamu'alaikum Warahmatullahi Wabarakatuh.</p>
HTML;

        $spj = <<<'HTML'
<p>Dengan hormat,</p>
<p class="indent">Sehubungan dengan telah terlaksananya kegiatan {{kegiatan}}, {{keterangan_pelaksanaan}} bersama ini kami menyampaikan {{jenis_laporan}} atas pelaksanaan kegiatan dan penggunaan anggaran yang telah dialokasikan. {{kelengkapan}}</p>
<p class="indent">Demikian {{jenis_laporan}} ini kami sampaikan sebagai bentuk transparansi dan akuntabilitas atas pelaksanaan kegiatan serta penggunaan anggaran. Atas perhatian dan kerja sama yang baik, kami ucapkan terima kasih.</p>
HTML;

        $dinas = <<<'HTML'
<p>Dengan hormat,</p>
{{isi_surat}}
{{daftar}}
<p class="indent">{{penutup}}</p>
HTML;

        $akomodasi = <<<'HTML'
<p>Dengan hormat,</p>
<p class="indent">{{latar}} Kegiatan tersebut akan dilaksanakan pada:</p>
<table style="margin-left:1.2cm; margin-bottom:8px"><tbody>
<tr><td style="width:3cm">Hari/Tanggal</td><td>: {{hari_tanggal}}</td></tr>
<tr><td>Tempat</td><td>: {{tempat}}</td></tr>
</tbody></table>
<p>Adapun peserta yang akan mengikuti kegiatan tersebut adalah:</p>
{{peserta}}
<p class="indent">Sehubungan dengan hal tersebut, kami memohon kepada Bapak/Ibu Dekan agar berkenan memberikan persetujuan dan {{dukungan}} guna menunjang keikutsertaan dalam kegiatan tersebut.</p>
<p class="indent">Demikian permohonan ini kami sampaikan. Atas perhatian dan dukungan Bapak/Ibu, kami mengucapkan terima kasih.</p>
HTML;

        $templates = [
            [
                'nama_surat' => 'Surat Baru (Isi Saja)',
                'form_schema' => array_merge([
                    $lampiran,
                    ['name' => 'isi_surat', 'label' => 'Isi surat', 'type' => 'richtext', 'required' => true],
                ], $lampiranSchema),
                // Hanya isi yang diketik; kepala surat, salam pembuka, dan penutup baku sudah tersedia.
                'template_html' => $kepala
                    .'<p>Dengan hormat,</p>{{isi_surat}}'
                    .'<p class="indent">Demikian surat ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>'
                    .$lampiranHalaman,
            ],
            [
                'nama_surat' => 'Surat Pengantar Kerja Praktik',
                'form_schema' => [
                    $lampiran,
                    ['name' => 'nama_mahasiswa', 'label' => 'Nama Mahasiswa', 'type' => 'text', 'required' => true],
                    ['name' => 'nim', 'label' => 'NIM', 'type' => 'text', 'required' => true],
                    ['name' => 'tanggal_mulai', 'label' => 'Tanggal Mulai', 'type' => 'date', 'required' => true],
                    ['name' => 'tanggal_selesai', 'label' => 'Tanggal Selesai', 'type' => 'date', 'required' => true],
                ],
                'template_html' => $kepala
                    .'<p class="indent">Sehubungan dengan pelaksanaan Kerja Praktik sebagai bagian dari kurikulum Program Studi Teknik Informatika S1, kami memohon kesediaan Bapak/Ibu untuk menerima mahasiswa kami berikut ini:</p>'
                    .'<table style="margin-left:1.2cm; margin-bottom:8px"><tr><td style="width:2cm">Nama</td><td>: {{nama_mahasiswa}}</td></tr><tr><td>NIM</td><td>: {{nim}}</td></tr></table>'
                    .'<p class="indent">untuk melaksanakan Kerja Praktik pada instansi yang Bapak/Ibu pimpin, terhitung mulai tanggal {{tanggal_mulai}} sampai dengan {{tanggal_selesai}}.</p>'
                    .'<p class="indent">Demikian surat pengantar ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>',
            ],
            [
                'nama_surat' => 'Surat Tugas Dosen',
                'form_schema' => [
                    ['name' => 'nama_dosen', 'label' => 'Nama Dosen', 'type' => 'text', 'required' => true],
                    ['name' => 'nidn_dosen', 'label' => 'NIDN Dosen', 'type' => 'text', 'required' => false],
                    ['name' => 'kegiatan', 'label' => 'Kegiatan', 'type' => 'textarea', 'required' => true],
                    ['name' => 'tanggal_kegiatan', 'label' => 'Tanggal Kegiatan', 'type' => 'date', 'required' => true],
                    ['name' => 'tempat_kegiatan', 'label' => 'Tempat Kegiatan', 'type' => 'text', 'required' => true],
                ],
                'template_html' => '<p style="text-align:center"><strong><u>SURAT TUGAS</u></strong><br>Nomor: {{nomor_surat}}</p>'
                    .'<p class="indent">Kepala Program Studi Teknik Informatika S1 memberikan tugas kepada:</p>'
                    .'<table style="margin-left:1.2cm; margin-bottom:8px"><tr><td style="width:2cm">Nama</td><td>: {{nama_dosen}}</td></tr><tr><td>NIDN</td><td>: {{nidn_dosen}}</td></tr></table>'
                    .'<p class="indent">untuk melaksanakan kegiatan: {{kegiatan}}, yang diselenggarakan pada tanggal {{tanggal_kegiatan}} bertempat di {{tempat_kegiatan}}.</p>'
                    .'<p class="indent">Demikian surat tugas ini dibuat untuk dilaksanakan dengan penuh tanggung jawab.</p>',
            ],
            [
                'nama_surat' => 'Undangan Rapat',
                'form_schema' => [
                    $lampiran,
                    ['name' => 'pembuka', 'label' => 'Kalimat pembuka undangan', 'type' => 'textarea', 'required' => true,
                        'default' => 'Dalam rangka melakukan koordinasi dan evaluasi kegiatan akademik, Program Studi Teknik Informatika mengundang Bapak/Ibu untuk hadir pada rapat yang akan diselenggarakan pada:'],
                    ['name' => 'hari_tanggal', 'label' => 'Tanggal Rapat', 'type' => 'date', 'required' => true],
                    ['name' => 'waktu', 'label' => 'Waktu', 'type' => 'text', 'required' => true, 'default' => 'Pukul 09.00 WIB s.d. selesai'],
                    ['name' => 'tempat', 'label' => 'Tempat', 'type' => 'text', 'required' => true],
                ],
                'template_html' => $kepala.$undangan,
            ],
            [
                'nama_surat' => 'Surat Pengantar Pertanggungjawaban (SPJ/LPJ)',
                'form_schema' => [
                    array_merge($lampiran, ['default' => '1 Bundel']),
                    ['name' => 'jenis_laporan', 'label' => 'Jenis laporan', 'type' => 'select', 'required' => true,
                        'options' => ['Surat Pertanggungjawaban (SPJ)', 'Laporan Pertanggungjawaban (LPJ)', 'Laporan Keuangan'],
                        'default' => 'Surat Pertanggungjawaban (SPJ)'],
                    ['name' => 'kegiatan', 'label' => 'Nama kegiatan', 'type' => 'text', 'required' => true],
                    ['name' => 'keterangan_pelaksanaan', 'label' => 'Keterangan pelaksanaan (opsional)', 'type' => 'textarea', 'required' => false],
                    ['name' => 'kelengkapan', 'label' => 'Kelengkapan terlampir', 'type' => 'text', 'required' => false,
                        'default' => 'Terlampir rincian keuangan beserta bukti pendukungnya.'],
                ],
                'template_html' => $kepala.$spj,
            ],
            [
                'nama_surat' => 'Surat Dinas Prodi (Pengantar, Permohonan, Imbauan)',
                'form_schema' => array_merge([
                    $lampiran,
                    ['name' => 'isi_surat', 'label' => 'Isi surat (setelah "Dengan hormat,")', 'type' => 'richtext', 'required' => true],
                    ['name' => 'daftar', 'label' => 'Daftar nama atau butir (opsional)', 'type' => 'list', 'required' => false],
                    ['name' => 'penutup', 'label' => 'Kalimat penutup', 'type' => 'textarea', 'required' => false,
                        'default' => 'Demikian surat ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.'],
                ], $lampiranSchema),
                'template_html' => $kepala.$dinas.$lampiranHalaman,
            ],
            [
                'nama_surat' => 'Permohonan Akomodasi Keikutsertaan Kegiatan',
                'form_schema' => [
                    array_merge($lampiran, ['default' => '1 Bundel']),
                    ['name' => 'latar', 'label' => 'Latar belakang kegiatan', 'type' => 'textarea', 'required' => true],
                    ['name' => 'hari_tanggal', 'label' => 'Hari/Tanggal pelaksanaan', 'type' => 'text', 'required' => true],
                    ['name' => 'tempat', 'label' => 'Tempat pelaksanaan', 'type' => 'text', 'required' => true],
                    ['name' => 'peserta', 'label' => 'Daftar peserta atau delegasi', 'type' => 'list', 'required' => true],
                    ['name' => 'dukungan', 'label' => 'Dukungan yang dimohon', 'type' => 'text', 'required' => true,
                        'default' => 'memfasilitasi kebutuhan transportasi, akomodasi, dan biaya perjalanan'],
                ],
                'template_html' => $kepala.$akomodasi,
            ],
            [
                'nama_surat' => 'Permohonan Dana Kegiatan Dosen Tamu',
                'form_schema' => [
                    array_merge($lampiran, ['default' => '1 Bundel']),
                    ['name' => 'tanggal_kegiatan', 'label' => 'Tanggal Kegiatan (kalimat pembuka)', 'type' => 'text', 'required' => true],
                    ['name' => 'narasumber', 'label' => 'Narasumber dan mata kuliah', 'type' => 'list', 'required' => true],
                    ['name' => 'hari_tanggal', 'label' => 'Hari/Tanggal', 'type' => 'text', 'required' => true],
                    ['name' => 'waktu', 'label' => 'Waktu', 'type' => 'text', 'required' => true],
                    ['name' => 'tempat', 'label' => 'Tempat', 'type' => 'text', 'required' => true],
                    ['name' => 'peserta', 'label' => 'Peserta', 'type' => 'text', 'required' => true, 'default' => 'Mahasiswa Program Studi Teknik Informatika S1'],
                ],
                'template_html' => $kepala
                    .'<p class="indent">Dalam rangka meningkatkan kualitas pembelajaran serta memperluas wawasan mahasiswa mengenai perkembangan keilmuan dan penerapan teknologi informasi, Program Studi Teknik Informatika S1 Fakultas Ilmu Komputer Universitas Kuningan bermaksud menyelenggarakan kegiatan perkuliahan dosen tamu (<em>guest lecture</em>). Kegiatan tersebut direncanakan dilaksanakan pada tanggal <strong>{{tanggal_kegiatan}}</strong>, dengan rincian narasumber sebagai berikut:</p>'
                    .'{{narasumber}}'
                    .'<p>Pelaksanaan kegiatan direncanakan pada:</p>'
                    .'<table style="margin-left:1.2cm; margin-bottom:8px"><tr><td style="width:3cm">Hari/Tanggal</td><td>: {{hari_tanggal}}</td></tr><tr><td>Waktu</td><td>: {{waktu}}</td></tr><tr><td>Tempat</td><td>: {{tempat}}</td></tr><tr><td>Peserta</td><td>: {{peserta}}</td></tr></table>'
                    .'<p class="indent">Dalam rangka mendukung pelaksanaan kegiatan tersebut, kami mengajukan permohonan dana untuk honorarium para narasumber. Rincian rencana perkuliahan dan <em>curriculum vitae</em> narasumber kami sampaikan pada lampiran surat ini.</p>'
                    .'<p class="indent">Demikian permohonan ini kami sampaikan. Besar harapan kami agar permohonan dana tersebut dapat disetujui guna mendukung kelancaran pelaksanaan kegiatan dan peningkatan mutu pembelajaran. Atas perhatian dan persetujuannya, kami mengucapkan terima kasih.</p>',
            ],
        ];

        // Nama lama "Surat Bebas" diganti tanpa membuat baris ganda, supaya surat yang sudah ada tetap terhubung.
        JenisSurat::where('nama_surat', 'Surat Bebas')->update(['nama_surat' => 'Surat Baru (Isi Saja)']);

        foreach ($templates as $t) {
            JenisSurat::updateOrCreate(['nama_surat' => $t['nama_surat']], $t);
        }
    }

    /**
     * Super Admin dibuat sekali. Kata sandi diambil dari SUPER_ADMIN_PASSWORD di .env, atau diacak
     * dan ditampilkan satu kali di terminal; seeder berikutnya tidak pernah menimpanya.
     */
    private function buatSuperAdmin(): void
    {
        $email = 'ti@uniku.ac.id';
        $admin = User::where('email', $email)->first();

        if ($admin) {
            $admin->update(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);

            return;
        }

        $password = env('SUPER_ADMIN_PASSWORD') ?: Str::password(14, symbols: false);
        User::create([
            'name' => 'Super Admin',
            'email' => $email,
            'jabatan' => 'Administrator Sistem',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => $password,
        ]);

        if (! env('SUPER_ADMIN_PASSWORD') && $this->command) {
            $this->command->warn("Super Admin dibuat: {$email} / {$password}  (catat sekarang, ganti di Profil Akun)");
        }
    }
}
