<?php

namespace Tests\Feature;

use App\Notifications\NotifikasiDms;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * P-D: notifikasi peranti (Web Push) - langganan setiap peranti, saluran dan kandungan.
 * Tiada sambungan sebenar ke perkhidmatan push dibuat dalam ujian.
 */
class NotifikasiPerantiTest extends TestCase
{
    use SkemaUjian;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/ujian-peranti-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        config(['webpush.vapid.public_key' => 'kunci-awam-ujian']);
    }

    private function langganan(array $tambahan = []): array
    {
        return $tambahan + ['endpoint' => self::ENDPOINT, 'keys' => ['p256dh' => 'BKkunci', 'auth' => 'tokenAuth'], 'contentEncoding' => 'aes128gcm'];
    }

    public function test_langgan_dan_henti_langgan_peranti(): void
    {
        $staf = $this->buatPengguna(['STAFF']);

        $this->actingAs($staf)->postJson(route('notifikasi.langgan'), $this->langganan())->assertOk();
        $this->assertSame([self::ENDPOINT], $staf->fresh()->pushSubscriptions->pluck('endpoint')->all());

        $this->actingAs($staf)->deleteJson(route('notifikasi.henti-langgan'), ['endpoint' => self::ENDPOINT])->assertOk();
        $this->assertSame(0, $staf->fresh()->pushSubscriptions()->count());
    }

    public function test_peranti_dikongsi_berpindah_kepada_pengguna_yang_log_masuk(): void
    {
        $a = $this->buatPengguna(['STAFF']);
        $b = $this->buatPengguna(['STAFF']);

        $this->actingAs($a)->postJson(route('notifikasi.langgan'), $this->langganan())->assertOk();
        $this->actingAs($b)->postJson(route('notifikasi.langgan'), $this->langganan())->assertOk();

        $this->assertSame(0, $a->fresh()->pushSubscriptions()->count());
        $this->assertSame(1, $b->fresh()->pushSubscriptions()->count());
    }

    public function test_langganan_tidak_sah_ditolak(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))
            ->postJson(route('notifikasi.langgan'), ['endpoint' => 'bukan-url'])
            ->assertStatus(422);
    }

    public function test_endpoint_hanya_perkhidmatan_push_dikenali(): void
    {
        $staf = $this->buatPengguna(['STAFF']);

        // SSRF: alamat dalaman / bukan HTTPS / hos lain ditolak
        foreach (['http://127.0.0.1:11434/api/generate', 'https://127.0.0.1/x', 'http://fcm.googleapis.com/fcm/send/x',
            'https://evil.example.com/fcm.googleapis.com', 'https://fcm.googleapis.com.evil.com/x'] as $endpoint) {
            $this->actingAs($staf)->postJson(route('notifikasi.langgan'), $this->langganan(['endpoint' => $endpoint]))
                ->assertStatus(422)->assertJsonValidationErrors('endpoint');
        }
        $this->assertSame(0, $staf->fresh()->pushSubscriptions()->count());

        // Chrome/Android, Firefox, Edge (WNS), Safari/iPhone diterima
        foreach (['https://fcm.googleapis.com/fcm/send/a', 'https://updates.push.services.mozilla.com/wpush/v2/b',
            'https://wns2-par02p.notify.windows.com/w/?token=c', 'https://web.push.apple.com/d'] as $endpoint) {
            $this->actingAs($staf)->postJson(route('notifikasi.langgan'), $this->langganan(['endpoint' => $endpoint]))->assertOk();
        }
        $this->assertSame(4, $staf->fresh()->pushSubscriptions()->count());
        $this->assertFalse(config('webpush.client_options.allow_redirects'));
    }

    public function test_saluran_web_push_hanya_bila_ada_langganan_dan_kunci(): void
    {
        $staf = $this->buatPengguna(['STAFF']);
        $notifikasi = new NotifikasiDms('diluluskan', 'Permohonan anda diluluskan', 'BPK/2026/0001 telah diluluskan.', 'http://localhost/dokumen/1', 'bi-check-circle', 'hijau');

        $this->assertSame(['database'], $notifikasi->via($staf));

        $staf->updatePushSubscription(self::ENDPOINT, 'BKkunci', 'tokenAuth', 'aes128gcm');
        $this->assertSame(['database', WebPushChannel::class], $notifikasi->via($staf->fresh()));

        config(['webpush.vapid.public_key' => null]);
        $this->assertSame(['database'], $notifikasi->via($staf->fresh()));
    }

    public function test_kandungan_notifikasi_peranti(): void
    {
        $staf = $this->buatPengguna(['STAFF']);
        $mesej = (new NotifikasiDms('diluluskan', 'Permohonan anda diluluskan', 'BPK/2026/0001 telah diluluskan.', 'http://localhost/dokumen/1'))
            ->toWebPush($staf, null)->toArray();

        $this->assertSame('Permohonan anda diluluskan', $mesej['title']);
        $this->assertSame('BPK/2026/0001 telah diluluskan.', $mesej['body']);
        $this->assertSame(['url' => 'http://localhost/dokumen/1'], $mesej['data']);
        $this->assertSame('/ikon/dms-192.png', $mesej['icon']);
    }

    public function test_layout_muatkan_manifest_dan_kunci_awam(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('dashboard'))->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('<meta name="vapid-public-key" content="kunci-awam-ujian">', false)
            ->assertSee('id="locengPeranti"', false);

        $this->assertFileExists(public_path('sw.js'));
        $this->assertSame('DMS PKINK', json_decode(file_get_contents(public_path('manifest.webmanifest')), true)['short_name']);
    }
}
