<?php

namespace Tests\Feature;

use App\Http\Controllers\ImbasArkibController;
use App\Http\Middleware\ModDemo;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\LogMasuk;
use App\Models\User;
use App\Notifications\NotifikasiDms;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use App\Support\Demo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Fasa D: mod demo (log masuk butang, sekatan tindakan, URL terowong, halaman /demo)
 * dan D4 (satu analisis OCR/AI pada satu masa).
 */
class ModDemoTest extends TestCase
{
    use SkemaUjian;

    private User $admin;

    private User $staf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $bahagian = $this->buatBahagian();
        $this->admin = $this->buatPengguna(['ADMIN'], ['ic_pekerja' => '990101010101', 'nama_staff' => 'Admin Demo', 'bahagian_id' => $bahagian->bahagian_id]);
        $this->staf = $this->buatPengguna(['STAFF'], ['ic_pekerja' => '990101010104', 'nama_staff' => 'Staf Demo', 'bahagian_id' => $bahagian->bahagian_id]);
        config(['demo.akaun' => ['990101010101', '990101010104']]);
    }

    private function hidupkan(string $mod = Demo::DEMO): void
    {
        config(['demo.mod' => $mod]);
    }

    /** Storan sementara dengan terowong.json (degupan = berapa saat yang lalu). Pulangkan laluan. */
    private function terowong(int $saatLalu = 0): string
    {
        $storan = sys_get_temp_dir().'/dms-demo-ujian-'.uniqid();
        $this->app->useStoragePath($storan);
        File::ensureDirectoryExists($storan.'/app/demo');
        File::put($storan.'/app/demo/terowong.json', json_encode([
            'url' => 'https://contoh-ujian.trycloudflare.com', 'degupan' => time() - $saatLalu,
        ]));
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($storan));

        return $storan;
    }

    public function test_mod_mati_tiada_kesan(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('name="password"', false)->assertDontSee('Pilih peranan untuk mencuba');
        $this->get(route('demo.halaman'))->assertNotFound();
        $this->post(route('demo.masuk'), ['ic' => '990101010101'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_halaman_log_masuk_papar_butang_akaun_demo_tanpa_borang_kata_laluan(): void
    {
        $this->hidupkan();

        $this->get(route('login'))->assertOk()
            ->assertSee('Mod Demo')
            ->assertSeeInOrder(['ADMIN', 'Admin Demo', 'STAFF', 'Staf Demo'])
            ->assertDontSee('name="password"', false)
            ->assertDontSee('id="modalDaftar"', false)
            ->assertDontSee('id="passwordToggle"', false);
    }

    public function test_masuk_sebagai_akaun_demo(): void
    {
        $this->hidupkan();

        $this->post(route('demo.masuk'), ['ic' => '990101010104'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->staf);
        $this->assertSame('Mod demo', LogMasuk::latest('log_masuk_id')->value('sebab'));

        $this->get(route('dashboard'))->assertOk()->assertSee('Mod Demo.');
    }

    public function test_akaun_bukan_demo_tidak_boleh_dimasuki(): void
    {
        $this->hidupkan();
        $lain = $this->buatPengguna(['SUPERADMIN']);

        $this->post(route('demo.masuk'), ['ic' => $lain->ic_pekerja])->assertSessionHasErrors('ic');
        $this->assertGuest();
    }

    public function test_log_masuk_kata_laluan_dan_pendaftaran_disekat(): void
    {
        $this->hidupkan();

        $this->from(route('login'))->post(route('login.proses'), ['ic_pekerja' => $this->admin->ic_pekerja, 'password' => 'Lama1234'])
            ->assertRedirect(route('login'))->assertSessionHas('demo_disekat', ModDemo::MESEJ);
        $this->assertGuest();

        $this->post(route('password.email'), ['email' => $this->admin->email])->assertSessionHas('demo_disekat');
        $this->post(route('pendaftaran.simpan'), [])->assertSessionHas('demo_disekat');
    }

    public function test_tindakan_berisiko_disekat_tetapi_halaman_boleh_dilihat(): void
    {
        $this->hidupkan();
        $this->actingAs($this->admin);

        $this->get(route('users.index'))->assertOk();
        $this->from(route('users.index'))->delete(route('users.destroy', $this->staf->user_id))
            ->assertRedirect(route('users.index'))->assertSessionHas('demo_disekat');
        $this->assertNotSoftDeleted($this->staf);

        $this->put(route('profil.kata-laluan'), ['kata_laluan_semasa' => 'Lama1234', 'password' => 'Baharu123', 'password_confirmation' => 'Baharu123'])
            ->assertSessionHas('demo_disekat');
        $this->postJson(route('keselamatan.kecemasan'))->assertForbidden()->assertJson(['message' => ModDemo::MESEJ]);
    }

    public function test_halaman_demo_papar_qr_dan_akaun(): void
    {
        $this->hidupkan();

        $this->get(route('demo.halaman'))->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('Cuba sistem di telefon anda')
            ->assertSee('Admin Demo')
            ->assertSee('Terowong belum berjalan');
    }

    public function test_url_ikut_alamat_terowong_bagi_permintaan_cloudflare(): void
    {
        $this->hidupkan();
        $this->terowong();

        // Tanpa header Cloudflare (akses di komputer sendiri): URL biasa
        $this->get(route('login'))->assertDontSee('contoh-ujian.trycloudflare.com');
        // Melalui terowong: URL dijana ikut alamat terowong (https)
        $this->withHeader('CF-Connecting-IP', '203.0.113.9')->get(route('login'))
            ->assertSee('https://contoh-ujian.trycloudflare.com/favicon.ico', false);
    }

    public function test_alamat_terowong_tanpa_degupan_baharu_diabaikan(): void
    {
        $this->terowong(Demo::DEGUPAN_SAAT + 5);
        $this->assertNull(Demo::urlTerowong());

        $this->terowong(2);
        $this->assertSame('https://contoh-ujian.trycloudflare.com', Demo::urlTerowong());
    }

    public function test_ip_peserta_diambil_daripada_header_cloudflare(): void
    {
        $this->withHeader('CF-Connecting-IP', '203.0.113.9')
            ->post(route('login.proses'), ['ic_pekerja' => $this->admin->ic_pekerja, 'password' => 'salah']);

        $this->assertSame('203.0.113.9', LogMasuk::latest('log_masuk_id')->value('alamat_ip'));
    }

    public function test_header_cloudflare_daripada_ip_luar_tidak_dipercayai(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->withHeader('CF-Connecting-IP', '203.0.113.9')
            ->post(route('login.proses'), ['ic_pekerja' => $this->admin->ic_pekerja, 'password' => 'salah']);

        $this->assertSame('198.51.100.7', LogMasuk::latest('log_masuk_id')->value('alamat_ip'));
    }

    public function test_nama_db_demo_tidak_boleh_sama_dengan_db_sebenar(): void
    {
        config(['demo.db' => config('database.connections.'.config('database.default').'.database')]);

        $this->expectException(\RuntimeException::class);
        Demo::namaDb();
    }

    // ---------- Mod Percubaan: sistem penuh untuk latihan ----------

    public function test_percubaan_log_masuk_dan_pendaftaran_biasa_dibuka(): void
    {
        $this->hidupkan(Demo::PERCUBAAN);

        $this->get(route('login'))->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee('id="modalDaftar"', false)
            ->assertSee('Mod Percubaan.')
            ->assertSee('akan dipadam selepas sesi tamat')
            ->assertDontSee('name="ic"', false);
        $this->post(route('demo.masuk'), ['ic' => '990101010101'])->assertNotFound();

        $this->post(route('login.proses'), ['ic_pekerja' => $this->admin->ic_pekerja, 'password' => 'Lama1234'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);
        $this->get(route('dashboard'))->assertSee('Mod Percubaan.');
    }

    public function test_percubaan_tiada_sekatan_tindakan(): void
    {
        $this->hidupkan(Demo::PERCUBAAN);

        $this->actingAs($this->admin)->delete(route('users.destroy', $this->staf->user_id))->assertSessionMissing('demo_disekat');
        $this->assertSoftDeleted($this->staf);
    }

    public function test_percubaan_alih_akses_tempatan_ke_terowong_dan_url_emel_ikut_terowong(): void
    {
        $this->hidupkan(Demo::PERCUBAAN);
        $this->terowong();

        // Pentadbir membuka dms-pkink.test semasa terowong hidup: dialihkan supaya satu domain/sesi
        $this->get('/lupa-kata-laluan?a=1')->assertRedirect('https://contoh-ujian.trycloudflare.com/lupa-kata-laluan?a=1');
        // Melalui terowong: tiada pengalihan, pautan ikut alamat terowong
        $this->withHeader('CF-Connecting-IP', '203.0.113.9')->get(route('login'))
            ->assertOk()->assertSee('https://contoh-ujian.trycloudflare.com/lupa-kata-laluan', false);
        // POST tempatan (cth. lulus pendaftaran) tidak dialihkan; URL yang dijana (emel) ikut terowong
        $this->post(route('login.proses'), ['ic_pekerja' => $this->admin->ic_pekerja, 'password' => 'Lama1234'])
            ->assertRedirect('https://contoh-ujian.trycloudflare.com/dashboard');
    }

    public function test_percubaan_tanpa_terowong_akses_tempatan_biasa(): void
    {
        $this->hidupkan(Demo::PERCUBAAN);
        $this->terowong(Demo::DEGUPAN_SAAT + 60); // terowong ditutup (Ctrl+C), fail lama tertinggal

        $this->get(route('login'))->assertOk()->assertDontSee('contoh-ujian.trycloudflare.com');
    }

    // ---------- Kod akses (permintaan melalui terowong) ----------

    private function sediakanKod(): string
    {
        $this->hidupkan();
        $this->terowong();

        return Demo::janaKod();
    }

    private function melaluiTerowong(): static
    {
        return $this->withHeader('CF-Connecting-IP', '203.0.113.9');
    }

    public function test_kod_akses_diperlukan_melalui_terowong_tetapi_bukan_tempatan(): void
    {
        $this->sediakanKod();

        $this->melaluiTerowong()->get(route('login'))->assertForbidden()->assertSee('Masukkan kod akses');
        $this->melaluiTerowong()->post(route('demo.masuk'), ['ic' => '990101010101'])->assertForbidden();
        $this->assertGuest();

        // Di komputer sendiri (tanpa header Cloudflare): tiada kod
        $this->flushHeaders()->get(route('login'))->assertOk()->assertSee('Pilih peranan untuk mencuba');
    }

    public function test_kod_dalam_pautan_qr_membuka_akses_dan_dibuang_dari_alamat(): void
    {
        $kod = $this->sediakanKod();

        $this->melaluiTerowong()->get('/demo?kod='.strtolower($kod))
            ->assertRedirect('https://contoh-ujian.trycloudflare.com/demo');
        $this->melaluiTerowong()->get(route('demo.halaman'))->assertOk()->assertSee($kod);
        $this->melaluiTerowong()->post(route('demo.masuk'), ['ic' => '990101010104'])->assertRedirect();
        $this->assertAuthenticatedAs($this->staf);
    }

    public function test_url_permintaan_terowong_guna_alamat_terowong_untuk_intended(): void
    {
        $kod = $this->sediakanKod();
        $this->melaluiTerowong()->post(route('demo.kod'), ['kod' => $kod, 'ke' => '/']);

        // Pautan emel ke halaman dilindungi sebelum log masuk: selepas log masuk mesti kembali
        // ke alamat terowong, bukan Host Herd yang ditulis semula oleh cloudflared
        // URL mutlak dengan Host Herd: seperti yang dihantar oleh cloudflared (--http-host-header)
        $this->melaluiTerowong()->get('http://dms-pkink.test/dokumen?carian=a')->assertRedirect();
        $this->assertSame('https://contoh-ujian.trycloudflare.com/dokumen?carian=a', session('url.intended'));
    }

    public function test_permintaan_terowong_sendiri_beralamat_terowong(): void
    {
        // request()->url()/fullUrl() digunakan oleh paginasi & pengalihan: mesti alamat terowong (https)
        Route::middleware('web')->get('/_ujian-alamat', fn () => request()->fullUrl().'|'.(request()->isSecure() ? 'https' : 'http'));
        $kod = $this->sediakanKod();
        $this->melaluiTerowong()->post(route('demo.kod'), ['kod' => $kod, 'ke' => '/']);

        $this->melaluiTerowong()->get('http://dms-pkink.test/_ujian-alamat?halaman=2')->assertOk()
            ->assertSeeText('https://contoh-ujian.trycloudflare.com/_ujian-alamat?halaman=2|https', false);
    }

    public function test_kod_salah_ditolak(): void
    {
        $this->sediakanKod();

        $this->melaluiTerowong()->get('/?kod=SALAH1')->assertForbidden()->assertSee('Kod akses tidak sah');
        $this->melaluiTerowong()->post(route('demo.kod'), ['kod' => 'SALAH1', 'ke' => '/demo'])
            ->assertForbidden()->assertSee('Kod akses tidak sah');
        $this->melaluiTerowong()->get(route('login'))->assertForbidden();
    }

    public function test_borang_kod_kembali_ke_halaman_asal_dalaman_sahaja(): void
    {
        $kod = $this->sediakanKod();

        $this->melaluiTerowong()->post(route('demo.kod'), ['kod' => $kod, 'ke' => '/demo'])
            ->assertRedirect('https://contoh-ujian.trycloudflare.com/demo');
        $this->melaluiTerowong()->get(route('login'))->assertOk();

        $this->melaluiTerowong()->post(route('demo.kod'), ['kod' => $kod, 'ke' => '//laman-jahat.test/x'])
            ->assertRedirect('https://contoh-ujian.trycloudflare.com');
    }

    public function test_kod_baharu_membatalkan_akses_lama(): void
    {
        $kod = $this->sediakanKod();
        $this->melaluiTerowong()->post(route('demo.kod'), ['kod' => $kod, 'ke' => '/']);
        $this->melaluiTerowong()->get(route('login'))->assertOk();

        Demo::janaKod();
        $this->melaluiTerowong()->get(route('login'))->assertForbidden();
    }

    public function test_akaun_dikunci_tidak_diemel_notifikasi(): void
    {
        config(['services.notifikasi.emel' => true]);
        $n = new NotifikasiDms('ujian', 'Tajuk', 'Mesej', 'http://localhost');
        $this->assertContains('mail', $n->via($this->admin));

        $this->admin->forceFill(['dikunci_hingga' => now()->addYears(50)])->save();
        $this->assertNotContains('mail', $n->via($this->admin->fresh()));
    }

    public function test_halaman_percubaan_papar_langkah_daftar(): void
    {
        $this->hidupkan(Demo::PERCUBAAN);

        $this->get(route('demo.halaman'))->assertOk()
            ->assertSee('Cuba sistem sebenar')
            ->assertSee('Daftar Akaun Baharu')
            ->assertDontSee('Admin Demo');
    }

    // ---------- D4: satu analisis OCR/AI pada satu masa ----------

    private function sediakanImbas(): JenisDokumen
    {
        Storage::fake('local');
        $jenis = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon Komputer']);
        DokumenField::create(['jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'nama_field' => 'Nama',
            'kod_field' => 'nama', 'jenis_data' => 'teks', 'is_required' => false, 'susunan' => 1]);

        return $jenis;
    }

    private function analisis(JenisDokumen $jenis): array
    {
        $res = $this->actingAs($this->admin)->post(route('imbas-arkib.analisis'), [
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id,
            'fail_scan' => UploadedFile::fake()->create('borang.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $token = basename(parse_url($res->headers->get('Location'), PHP_URL_PATH));

        return json_decode(Storage::disk('local')->get("ocr_sementara/{$token}/meta.json"), true);
    }

    public function test_analisis_menunggu_giliran_bila_analisis_lain_berjalan(): void
    {
        $jenis = $this->sediakanImbas();
        $ocr = $this->spy(OcrService::class);
        config(['services.ocr.tunggu_giliran' => 0]);

        $kunci = Cache::lock(ImbasArkibController::KUNCI_ANALISIS, 60);
        $this->assertTrue($kunci->get());
        try {
            $meta = $this->analisis($jenis);
        } finally {
            $kunci->release();
        }

        $this->assertStringContainsString('Sistem sedang sibuk menganalisis dokumen lain', $meta['mesej_amaran']);
        $ocr->shouldNotHaveReceived('proses');
    }

    public function test_kunci_dilepaskan_selepas_analisis(): void
    {
        $jenis = $this->sediakanImbas();
        $this->mock(OcrService::class, fn (MockInterface $m) => $m->shouldReceive('proses')->andThrow(new \RuntimeException('Tesseract mati')));
        $this->mock(OllamaExtractionService::class);

        $meta = $this->analisis($jenis);

        $this->assertStringContainsString('OCR/AI gagal', $meta['mesej_amaran']);
        // Kunci dilepaskan walaupun OCR gagal: analisis seterusnya tidak tersekat
        $this->assertTrue(Cache::lock(ImbasArkibController::KUNCI_ANALISIS, 5)->get());
    }
}
