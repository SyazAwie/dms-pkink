<?php

namespace App\Support;

use App\Models\JenisDokumen;

/**
 * Templat permulaan bagi pembina medan Kategori Dokumen (memo, surat, minit
 * mesyuarat, borang permohonan). Admin pilih satu templat dan medannya dimuatkan
 * ke dalam pembina untuk diubah suai sebelum disimpan; tiada apa yang disimpan
 * secara automatik.
 *
 * Setiap medan dalam format baris pembina (sama seperti old('medan')):
 *   nama_field, kod_field, jenis_data, is_required, pilihan ("a, b"), lain,
 *   bantuan, lajur [{nama, jenis, pilihan?}], baris_tetap ("a, b").
 * Tarikh dan Perkara sudah ada pada setiap dokumen, jadi tidak diulang di sini.
 */
final class TemplatKategori
{
    /** Cadangan nilai "Kategori Kumpulan" (digabung dengan kategori sedia ada). */
    public const KATEGORI_CADANGAN = [
        'Borang', 'Memo', 'Surat Masuk', 'Surat Keluar', 'Minit Mesyuarat',
        'Laporan', 'Kewangan', 'Pentadbiran', 'Digital',
    ];

    private const KLASIFIKASI = 'Terbuka, Terhad, Sulit, Rahsia';

    /** @return array<string, array{nama: string, kategori: string, keterangan: string, medan: array}> */
    public static function senarai(): array
    {
        return [
            'memo' => [
                'nama' => 'Memo Dalaman',
                'kategori' => 'Memo',
                'keterangan' => 'Daripada, Kepada, s.k., rujukan, klasifikasi dan tindakan diperlukan.',
                'medan' => [
                    self::medan('Daripada', 'teks', wajib: true, bantuan: 'Nama dan jawatan pengirim'),
                    self::medan('Kepada', 'teks', wajib: true),
                    self::medan('Salinan Kepada (s.k.)', 'textarea', kod: 'salinan_kepada'),
                    self::medan('Rujukan Kami', 'teks'),
                    self::medan('Rujukan Tuan', 'teks'),
                    self::medan('Klasifikasi', 'dropdown', pilihan: self::KLASIFIKASI),
                    self::medan('Tindakan Diperlukan', 'radio', pilihan: 'Untuk Makluman, Untuk Tindakan, Untuk Kelulusan', lain: true),
                ],
            ],
            'surat_masuk' => [
                'nama' => 'Surat Masuk',
                'kategori' => 'Surat Masuk',
                'keterangan' => 'Pengirim, rujukan pengirim, tarikh diterima dan tindakan.',
                'medan' => [
                    self::medan('Pengirim / Organisasi', 'teks', kod: 'pengirim', wajib: true),
                    self::medan('Alamat Pengirim', 'textarea'),
                    self::medan('Rujukan Pengirim', 'teks'),
                    self::medan('Tarikh Diterima', 'tarikh', wajib: true),
                    self::medan('Cara Diterima', 'dropdown', pilihan: 'Pos, Serahan Tangan, Emel, Faks', lain: true),
                    self::medan('Klasifikasi', 'dropdown', pilihan: self::KLASIFIKASI),
                    self::medan('Untuk Tindakan', 'teks', bantuan: 'Pegawai atau bahagian yang bertanggungjawab'),
                    self::medan('Tarikh Akhir Tindakan', 'tarikh'),
                ],
            ],
            'surat_keluar' => [
                'nama' => 'Surat Keluar',
                'kategori' => 'Surat Keluar',
                'keterangan' => 'Penerima, rujukan kami/tuan, penandatangan dan cara dihantar.',
                'medan' => [
                    self::medan('Penerima / Organisasi', 'teks', kod: 'penerima', wajib: true),
                    self::medan('Alamat Penerima', 'textarea'),
                    self::medan('Rujukan Kami', 'teks'),
                    self::medan('Rujukan Tuan', 'teks'),
                    self::medan('Ditandatangani Oleh', 'teks'),
                    self::medan('Klasifikasi', 'dropdown', pilihan: self::KLASIFIKASI),
                    self::medan('Cara Dihantar', 'dropdown', pilihan: 'Pos Biasa, Pos Berdaftar, Serahan Tangan, Emel', lain: true),
                    self::medan('Salinan Kepada (s.k.)', 'textarea', kod: 'salinan_kepada'),
                ],
            ],
            'minit_mesyuarat' => [
                'nama' => 'Minit Mesyuarat',
                'kategori' => 'Minit Mesyuarat',
                'keterangan' => 'Butiran mesyuarat, kehadiran dan jadual keputusan & tindakan.',
                'medan' => [
                    self::medan('Nama Mesyuarat', 'teks', wajib: true),
                    self::medan('Bil. Mesyuarat', 'teks', kod: 'bil_mesyuarat', bantuan: 'Cth: 3/2026'),
                    self::medan('Tarikh & Masa', 'tarikh_masa', kod: 'tarikh_masa_mesyuarat', wajib: true),
                    self::medan('Tempat', 'teks'),
                    self::medan('Pengerusi', 'teks'),
                    self::medan('Urus Setia', 'teks'),
                    self::medan('Kehadiran', 'seksyen', kod: 'seksyen_kehadiran'),
                    self::medan('Hadir', 'jadual', lajur: [['Nama', 'teks'], ['Jawatan', 'teks'], ['Bahagian / Jabatan', 'teks']]),
                    self::medan('Tidak Hadir (Dengan Maaf)', 'jadual', kod: 'tidak_hadir', lajur: [['Nama', 'teks'], ['Jawatan', 'teks']]),
                    self::medan('Keputusan Mesyuarat', 'seksyen', kod: 'seksyen_keputusan'),
                    self::medan('Keputusan & Tindakan', 'jadual', kod: 'keputusan_tindakan', lajur: [
                        ['Perkara', 'teks'], ['Keputusan', 'teks'], ['Tindakan Oleh', 'teks'], ['Tarikh Siap', 'tarikh'],
                    ]),
                    self::medan('Tarikh Mesyuarat Akan Datang', 'tarikh'),
                ],
            ],
            'borang_permohonan' => [
                'nama' => 'Borang Permohonan',
                'kategori' => 'Borang',
                'keterangan' => 'Maklumat pemohon, butiran permohonan, sokongan dan kelulusan.',
                'medan' => [
                    self::medan('Nama Pemohon', 'teks', wajib: true),
                    self::medan('Jawatan / Gred', 'teks', kod: 'jawatan_gred'),
                    self::medan('Bahagian / Jabatan', 'teks', kod: 'bahagian_jabatan'),
                    self::medan('No. Telefon', 'telefon', kod: 'no_telefon'),
                    self::medan('Emel', 'emel'),
                    self::medan('Butiran Permohonan', 'textarea', wajib: true),
                    self::medan('Sokongan', 'seksyen', kod: 'seksyen_sokongan'),
                    self::medan('Status Sokongan', 'radio', pilihan: 'Disokong, Tidak Disokong'),
                    self::medan('Ulasan Penyokong', 'textarea'),
                    self::medan('Kelulusan', 'seksyen', kod: 'seksyen_kelulusan'),
                    self::medan('Status Kelulusan', 'radio', pilihan: 'Diluluskan, Tidak Diluluskan'),
                    self::medan('Ulasan Pelulus', 'textarea'),
                ],
            ],
        ];
    }

    /**
     * Medan kategori sedia ada dalam format baris pembina (tanpa id, jadi disimpan
     * sebagai medan baharu) untuk pilihan "Salin daripada kategori sedia ada".
     *
     * @return array<int, array{nama: string, kod: string, medan: array}>
     */
    public static function salinanKategori(): array
    {
        return JenisDokumen::with('fields')->orderBy('nama_dokumen')->get()
            ->map(fn (JenisDokumen $j) => [
                'id' => $j->jenis_dokumen_id,
                'nama' => $j->nama_dokumen,
                'kod' => $j->kod_dokumen,
                'kategori' => $j->kategori,
                'medan' => $j->fields->map(fn ($f) => [
                    'nama_field' => $f->nama_field,
                    'kod_field' => $f->kod_field,
                    'jenis_data' => $f->jenis_data,
                    'is_required' => $f->is_required,
                    'pilihan' => $f->pilihan,
                    'tetapan' => $f->tetapan,
                ])->values()->all(),
            ])->values()->all();
    }

    /** Cadangan Kategori Kumpulan: senarai lalai + kategori yang sudah digunakan. */
    public static function kategoriCadangan(): array
    {
        $sediaAda = JenisDokumen::whereNotNull('kategori')->where('kategori', '!=', '')->distinct()->pluck('kategori')->all();

        return array_values(array_unique(array_merge(self::KATEGORI_CADANGAN, $sediaAda)));
    }

    /** @param array<int, array{0: string, 1: string}> $lajur [nama, jenis] */
    private static function medan(
        string $nama,
        string $jenis,
        ?string $kod = null,
        bool $wajib = false,
        string $pilihan = '',
        bool $lain = false,
        string $bantuan = '',
        array $lajur = [],
    ): array {
        return [
            'nama_field' => $nama,
            'kod_field' => $kod ?? \Illuminate\Support\Str::slug($nama, '_'),
            'jenis_data' => $jenis,
            'is_required' => $wajib ? '1' : '',
            'pilihan' => $pilihan,
            'lain' => $lain ? '1' : '',
            'bantuan' => $bantuan,
            'lajur' => array_map(fn ($l) => ['nama' => $l[0], 'jenis' => $l[1]], $lajur),
            'baris_tetap' => '',
        ];
    }
}
