<?php

namespace Tests\Feature;

use App\Models\DokumenData;
use App\Models\JenisDokumen;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * OCR 3 Situasi, Fasa C1: jawatan & no. telefon dalam profil (users.jawatan,
 * users.no_telefon) dan sebagai sumber auto-isi medan borang.
 */
class ProfilJawatanTelefonTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
    }

    public function test_profil_simpan_jawatan_dan_telefon(): void
    {
        $user = $this->buatPengguna(['STAFF']);

        $this->actingAs($user)->get(route('profil.index'))
            ->assertOk()
            ->assertSee('name="jawatan"', false)
            ->assertSee('name="no_telefon"', false);

        $this->actingAs($user)->put(route('profil.kemaskini'), [
            'nama_staff' => $user->nama_staff,
            'email' => $user->email,
            'jawatan' => 'Pegawai Teknologi Maklumat F41',
            'no_telefon' => '012-345 6789',
        ])->assertRedirect(route('profil.index'))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Pegawai Teknologi Maklumat F41', $user->jawatan);
        $this->assertSame('012-345 6789', $user->no_telefon);

        // Kedua-duanya pilihan: kosongkan semula
        $this->actingAs($user)->put(route('profil.kemaskini'), [
            'nama_staff' => $user->nama_staff, 'email' => $user->email, 'jawatan' => '', 'no_telefon' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->jawatan);
        $this->assertNull($user->fresh()->no_telefon);
    }

    public function test_profil_tolak_format_tidak_sah(): void
    {
        $user = $this->buatPengguna(['STAFF']);

        $this->actingAs($user)->put(route('profil.kemaskini'), [
            'nama_staff' => $user->nama_staff,
            'email' => $user->email,
            'jawatan' => '[Klik](http://jahat.test)',
            'no_telefon' => 'bukan nombor',
        ])->assertSessionHasErrorsIn('maklumat', [
            'jawatan' => "Jawatan hanya boleh mengandungi huruf, nombor, ruang dan aksara ' . , - @ / ( ) &",
            'no_telefon' => 'Format no. telefon tidak sah. Cth: 012-345 6789 atau 09-748 1234.',
        ]);
        $this->assertNull($user->fresh()->jawatan);
    }

    public function test_admin_tambah_dan_kemaskini_jawatan_telefon_kakitangan(): void
    {
        $admin = $this->buatPengguna(['SUPERADMIN']);
        $bahagian = $this->buatBahagian();
        Role::firstOrCreate(['kod_roles' => 'STAFF'], ['nama_roles' => 'Staf']);

        $this->actingAs($admin)->post(route('users.store'), [
            'ic_pekerja' => '900101030003', 'nama_staff' => 'Staf Baharu', 'email' => 'staf.baharu@pkink.test',
            'jawatan' => 'Pembantu Tadbir N19', 'no_telefon' => '09-748 1234',
            'bahagian_id' => $bahagian->bahagian_id, 'password' => 'rahsia1',
        ])->assertRedirect(route('users.index'));

        $baharu = User::where('ic_pekerja', '900101030003')->sole();
        $this->assertSame('Pembantu Tadbir N19', $baharu->jawatan);
        $this->assertSame('09-748 1234', $baharu->no_telefon);

        $this->actingAs($admin)->get(route('users.edit', $baharu->user_id))
            ->assertSee('value="Pembantu Tadbir N19"', false);

        $this->actingAs($admin)->put(route('users.update', $baharu->user_id), [
            'ic_pekerja' => $baharu->ic_pekerja, 'nama_staff' => $baharu->nama_staff, 'email' => $baharu->email,
            'jawatan' => 'Penolong Pegawai Tadbir N29', 'no_telefon' => '',
            'bahagian_id' => $bahagian->bahagian_id,
        ])->assertRedirect(route('users.index'));

        $this->assertSame('Penolong Pegawai Tadbir N29', $baharu->fresh()->jawatan);
        $this->assertNull($baharu->fresh()->no_telefon);
    }

    public function test_auto_isi_jawatan_dan_telefon(): void
    {
        Storage::fake('local');
        $admin = $this->buatPengguna(['ADMIN']);
        $staf = $this->buatPengguna(['STAFF'], ['jawatan' => 'Juruteknik Komputer FT19', 'no_telefon' => '013-222 3333']);

        $this->actingAs($admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan',
            'medan' => [
                ['nama_field' => 'Jawatan', 'kod_field' => 'jawatan', 'jenis_data' => 'teks', 'auto_isi' => 'jawatan'],
                ['nama_field' => 'Telefon', 'kod_field' => 'telefon', 'jenis_data' => 'telefon', 'auto_isi' => 'telefon'],
                // Medan telefon hanya serasi dengan sumber telefon
                ['nama_field' => 'Telefon Lain', 'kod_field' => 'telefon_lain', 'jenis_data' => 'telefon', 'auto_isi' => 'nama'],
            ],
        ])->assertSessionHasNoErrors();

        $jenis = JenisDokumen::with('fields')->sole();
        $id = fn (string $kod) => $jenis->fields->firstWhere('kod_field', $kod)->dokumen_field_id;
        $this->assertNull($jenis->fields->firstWhere('kod_field', 'telefon_lain')->tetapan);

        $this->actingAs($staf)->get(route('jenis-dokumen.medan', $jenis->jenis_dokumen_id))
            ->assertSee('value="Juruteknik Komputer FT19"  readonly', false)
            ->assertSee('value="013-222 3333" maxlength="20" placeholder="Cth: 09-748 1234"  readonly', false);

        $this->actingAs($staf)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [$id('jawatan') => 'Palsu', $id('telefon') => '011-000 0000'],
            'fail_scan' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Juruteknik Komputer FT19', DokumenData::where('dokumen_field_id', $id('jawatan'))->value('nilai_data'));
        $this->assertSame('013-222 3333', DokumenData::where('dokumen_field_id', $id('telefon'))->value('nilai_data'));
    }
}
