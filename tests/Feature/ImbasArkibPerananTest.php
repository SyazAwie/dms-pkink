<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * S-01: Imbas & Arkib mencipta dokumen terus "Diluluskan", jadi hanya
 * KERANI/ADMIN/SUPERADMIN boleh guna. STAFF & PENGURUS disekat pada
 * SEMUA laluan (bukan sekadar menu disembunyikan).
 */
class ImbasArkibPerananTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
    }

    public static function perananDisekat(): array
    {
        return [
            'STAFF' => ['STAFF'],
            'PENGURUS' => ['PENGURUS'],
            'PENYOKONG' => ['PENYOKONG'],
            'PELULUS' => ['PELULUS'],
            'AUDIT' => ['AUDIT'],
        ];
    }

    public static function perananDibenarkan(): array
    {
        return [
            'KERANI' => ['KERANI'],
            'ADMIN' => ['ADMIN'],
            'SUPERADMIN' => ['SUPERADMIN'],
        ];
    }

    #[DataProvider('perananDisekat')]
    public function test_peranan_disekat_dapat_403_pada_semua_laluan(string $peranan): void
    {
        $user = $this->buatPengguna([$peranan]);

        $this->actingAs($user)->get(route('imbas-arkib.create'))->assertForbidden();
        $this->actingAs($user)->post(route('imbas-arkib.analisis'))->assertForbidden();
        $this->actingAs($user)->post(route('imbas-arkib.simpan'))->assertForbidden();
    }

    #[DataProvider('perananDisekat')]
    public function test_menu_imbas_arkib_disembunyikan_bagi_peranan_disekat(string $peranan): void
    {
        $this->actingAs($this->buatPengguna([$peranan]))
            ->get(route('profil.index'))
            ->assertOk()
            ->assertDontSee(route('imbas-arkib.create'));
    }

    #[DataProvider('perananDibenarkan')]
    public function test_peranan_dibenarkan_boleh_buka_imbas_arkib(string $peranan): void
    {
        $this->actingAs($this->buatPengguna([$peranan]))
            ->get(route('imbas-arkib.create'))
            ->assertOk()
            ->assertSee('Imbas &amp; Arkib', false);
    }

    public function test_staf_yang_juga_kerani_dibenarkan(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF', 'KERANI']))
            ->get(route('imbas-arkib.create'))
            ->assertOk();
    }
}
