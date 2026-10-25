<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Arahan imbas-arkib:bersih: padam folder token & fail format lama dalam
 * ocr_sementara yang lebih lama daripada --jam (lalai 24), dengan mod --cuba.
 */
class BersihImbasArkibTest extends TestCase
{
    private string $tokenLama;
    private string $tokenBaharu;
    private string $tokenDiproses;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tokenLama = Str::random(40);
        $this->tokenBaharu = Str::random(40);
        $this->tokenDiproses = Str::random(40);

        $this->tulis("ocr_sementara/{$this->tokenLama}/fail.pdf", 30);
        $this->tulis("ocr_sementara/{$this->tokenLama}/meta.json", 30);
        $this->tulis("ocr_sementara/{$this->tokenBaharu}/fail.pdf", 1);
        $this->tulis("ocr_sementara/{$this->tokenBaharu}/meta.json", 1);
        $this->tulis("ocr_sementara/{$this->tokenDiproses}/fail.pdf", 48);
        $this->tulis("ocr_sementara/{$this->tokenDiproses}/meta.json.diproses", 48);

        $this->tulis('ocr_sementara/AbCdEfGhIjKlMnOpQrSt_borang lama.pdf', 72);
        $this->tulis('ocr_sementara/ZyXwVuTsRqPoNmLkJiHg_borang baharu.pdf', 2);
        $this->tulis('ocr_sementara/nota-penting.txt', 500);
        $this->tulis('ocr_sementara/folder_lain/x.pdf', 500);
        $this->tulis('dokumen_scan/inv20250001/kekal.pdf', 500);
    }

    /** Tulis fail dan tetapkan masa ubah suai kepada $jam jam yang lalu. */
    private function tulis(string $laluan, int $jam): void
    {
        $disk = Storage::disk('local');
        $disk->put($laluan, 'kandungan');
        touch($disk->path($laluan), now()->subHours($jam)->getTimestamp());
    }

    public function test_mod_cuba_hanya_senaraikan_tanpa_padam(): void
    {
        $this->artisan('imbas-arkib:bersih', ['--cuba' => true])
            ->expectsOutputToContain($this->tokenLama)
            ->expectsOutputToContain('AbCdEfGhIjKlMnOpQrSt_borang lama.pdf')
            ->expectsOutputToContain('Mod --cuba: 3 item')
            ->assertSuccessful();

        $disk = Storage::disk('local');
        $disk->assertExists("ocr_sementara/{$this->tokenLama}/fail.pdf");
        $disk->assertExists('ocr_sementara/AbCdEfGhIjKlMnOpQrSt_borang lama.pdf');
    }

    public function test_padam_yang_lama_sahaja_dan_tidak_sentuh_fail_lain(): void
    {
        $this->artisan('imbas-arkib:bersih')
            ->expectsOutputToContain('3 item')
            ->assertSuccessful();

        $disk = Storage::disk('local');
        // Dipadam: lebih 24 jam
        $disk->assertMissing("ocr_sementara/{$this->tokenLama}");
        $disk->assertMissing("ocr_sementara/{$this->tokenDiproses}");
        $disk->assertMissing('ocr_sementara/AbCdEfGhIjKlMnOpQrSt_borang lama.pdf');
        // Dikekalkan: masih baharu, atau bukan format Imbas & Arkib
        $disk->assertExists("ocr_sementara/{$this->tokenBaharu}/meta.json");
        $disk->assertExists('ocr_sementara/ZyXwVuTsRqPoNmLkJiHg_borang baharu.pdf');
        $disk->assertExists('ocr_sementara/nota-penting.txt');
        $disk->assertExists('ocr_sementara/folder_lain/x.pdf');
        $disk->assertExists('dokumen_scan/inv20250001/kekal.pdf');
    }

    public function test_pilihan_jam_ubah_had_umur(): void
    {
        $this->artisan('imbas-arkib:bersih', ['--jam' => 60])
            ->expectsOutputToContain('1 item')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing('ocr_sementara/AbCdEfGhIjKlMnOpQrSt_borang lama.pdf');
        Storage::disk('local')->assertExists("ocr_sementara/{$this->tokenDiproses}/meta.json.diproses");
    }

    public function test_jam_tidak_sah_ditolak(): void
    {
        $this->artisan('imbas-arkib:bersih', ['--jam' => 0])->assertFailed();
        Storage::disk('local')->assertExists("ocr_sementara/{$this->tokenLama}/fail.pdf");
    }

    public function test_dijadualkan_setiap_hari(): void
    {
        $acara = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'imbas-arkib:bersih'));

        $this->assertNotNull($acara);
        $this->assertSame('0 0 * * *', $acara->expression);
    }
}
