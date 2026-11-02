<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Fasa R1: setiap <label> dipautkan kepada medannya (for = id yang wujud), atau membalut
 * kawalannya. Tajuk kumpulan/seksyen bukan <label>.
 */
class AksesibilitiLabelTest extends TestCase
{
    use SkemaUjian;

    private User $admin;
    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->admin = $this->buatPengguna(['SUPERADMIN']);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'UJI', 'nama_dokumen' => 'Borang Uji']);
        foreach ([['teks', 'Nama'], ['dropdown', 'Status'], ['radio', 'Pilihan'], ['tarikh', 'Tarikh Mohon']] as $i => [$jenis, $nama]) {
            DokumenField::create([
                'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => $nama, 'kod_field' => 'm' . $i,
                'jenis_data' => $jenis, 'is_required' => false, 'susunan' => $i + 1,
                'pilihan' => in_array($jenis, ['dropdown', 'radio'], true) ? ['A', 'B'] : null,
            ]);
        }
    }

    private function semakLabel(string $html, string $halaman): void
    {
        preg_match_all('/<label\b([^>]*)>(.*?)<\/label>/s', $html, $padanan, PREG_SET_ORDER);
        $this->assertNotEmpty($padanan, "{$halaman}: tiada label");

        foreach ($padanan as [$penuh, $atribut, $isi]) {
            if (preg_match('/\bfor="([^"]+)"/', $atribut, $for)) {
                $this->assertMatchesRegularExpression('/\bid="' . preg_quote($for[1], '/') . '"/', $html,
                    "{$halaman}: label for=\"{$for[1]}\" tiada medan sepadan");
            } else {
                $this->assertMatchesRegularExpression('/<(input|select|textarea)\b/', $isi,
                    "{$halaman}: label tanpa for dan tidak membalut medan: " . trim(strip_tags($penuh)));
            }
        }
    }

    public function test_label_halaman_borang_dipautkan_kepada_medan(): void
    {
        $dokumen = Dokumen::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => 'UJI/2026/0001',
            'tarikh_dokumen' => '2026-11-02', 'created_by' => $this->admin->user_id]);

        $halaman = [
            'dokumen.create' => route('dokumen.create', ['jenis' => $this->jenis->jenis_dokumen_id]),
            'dokumen.edit' => route('dokumen.edit', $dokumen->dokumen_id),
            'dokumen.index' => route('dokumen.index', ['jenis' => $this->jenis->jenis_dokumen_id]),
            'users.create' => route('users.create'),
            'bahagian.create' => route('bahagian.create'),
            'jenis-dokumen.create' => route('jenis-dokumen.create'),
            'log-audit' => route('log-audit.index'),
        ];

        $this->actingAs($this->admin);
        foreach ($halaman as $nama => $url) {
            $this->semakLabel($this->get($url)->assertOk()->getContent(), $nama);
        }
    }
}
