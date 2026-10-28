<?php

namespace Tests\Feature;

use App\Models\PendaftaranStaf;
use App\Models\User;
use App\Notifications\NotifikasiDms;
use App\Notifications\PendaftaranDiluluskan;
use App\Notifications\PendaftaranDitolak;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Part\DataPart;
use Tests\TestCase;

/**
 * Templat emel berjenama PKINK: logo, kaki emel BM dan kotak peringatan
 * keselamatan bagi emel yang membawa pautan/kata laluan. Tiada DB diperlukan.
 */
class EmelBerjenamaTest extends TestCase
{
    private function pengguna(): User
    {
        return (new User)->forceFill(['nama_staff' => 'Ali bin Abu', 'email' => 'ali@pkink.test']);
    }

    private function permohonan(): PendaftaranStaf
    {
        return (new PendaftaranStaf)->forceFill([
            'nama_staff' => 'Siti binti Ahmad', 'email' => 'siti@pkink.test',
            'ic_pekerja' => '900101010101', 'sebab_tolak' => 'Tidak lengkap.',
        ]);
    }

    private function html($mel): string
    {
        return html_entity_decode((string) $mel->render(), ENT_QUOTES);
    }

    public function test_emel_sensitif_ada_peringatan_keselamatan(): void
    {
        $reset = $this->html((new ResetPassword('token'))->toMail($this->pengguna()));
        $lulus = $this->html((new PendaftaranDiluluskan($this->permohonan(), 'Sementara12', 72))
            ->toMail(Notification::route('mail', 'siti@pkink.test')));

        foreach ([$reset, $lulus] as $html) {
            $this->assertStringContainsString('class="peringatan"', $html);
            $this->assertStringContainsString('Peringatan keselamatan.', $html);
            $this->assertStringContainsString('tidak akan sekali-kali meminta kata laluan anda', $html);
        }
        $this->assertStringContainsString('Jangan kongsi emel ini atau pautan di dalamnya dengan sesiapa', $reset);
        $this->assertStringContainsString('Jangan kongsi No. KP, kata laluan sementara atau emel ini dengan sesiapa', $lulus);
    }

    public function test_emel_lain_tanpa_peringatan_tetapi_berjenama(): void
    {
        $tolak = $this->html((new PendaftaranDitolak($this->permohonan()))
            ->toMail(Notification::route('mail', 'siti@pkink.test')));
        $notifikasi = $this->html((new NotifikasiDms('ujian', 'Tajuk', 'Mesej', 'http://localhost'))
            ->toMail($this->pengguna()));

        foreach ([$tolak, $notifikasi] as $html) {
            $this->assertStringNotContainsString('class="peringatan"', $html);
            $this->assertStringContainsString('images/emel/logo-pkink.png', $html); // pratonton: URL biasa
            $this->assertStringContainsString('Perbadanan Kemajuan Iktisad Negeri Kelantan (PKINK)', $html);
            $this->assertStringContainsString('Sila jangan balas emel ini.', $html);
            $this->assertStringNotContainsString('All rights reserved', $html);
        }
        // Ayat "jangan balas" kini hanya di kaki emel, tidak berulang dalam badan
        $this->assertSame(1, substr_count($notifikasi, 'Sila jangan balas emel ini.'));
    }

    public function test_subcopy_dalam_bahasa_melayu(): void
    {
        $html = $this->html((new ResetPassword('token'))->toMail($this->pengguna()));

        $this->assertStringContainsString('Jika butang "Tetapkan Semula Kata Laluan" tidak berfungsi', $html);
        $this->assertStringNotContainsString("If you're having trouble", $html);
    }

    public function test_emel_sebenar_benam_logo_sekali_sahaja(): void
    {
        config(['mail.default' => 'array']);

        Notification::route('mail', 'siti@pkink.test')
            ->notifyNow(new PendaftaranDiluluskan($this->permohonan(), 'Sementara12', 72));

        $mesej = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $mesej);
        $emel = $mesej->first()->getOriginalMessage();

        // Templat dirender dua kali (HTML + teks), logo mesti dilampirkan sekali sahaja
        $logo = array_filter($emel->getAttachments(), fn (DataPart $p) => $p->getFilename() === 'logo-pkink.png');
        $this->assertCount(1, $logo);
        $cid = reset($logo)->getContentId();
        $this->assertStringContainsString('src="cid:'.$cid.'"', $emel->getHtmlBody());
        $this->assertStringNotContainsString('images/emel/logo-pkink.png', $emel->getHtmlBody());
        $this->assertStringContainsString('Peringatan keselamatan.', $emel->getTextBody());
    }
}
