<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Lupa kata laluan tidak mendedahkan sama ada emel berdaftar: emel wujud, tiada,
 * di-throttle atau SMTP gagal semuanya mendapat respons yang sama.
 */
class LupaKataLaluanTest extends TestCase
{
    use SkemaUjian;

    private const MESEJ = 'Jika emel ini berdaftar dalam sistem, pautan tetapan semula kata laluan telah dihantar.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Notification::fake();
    }

    private function mohon(string $emel)
    {
        return $this->from(route('password.request'))->post(route('password.email'), ['email' => $emel]);
    }

    public function test_emel_berdaftar_dapat_pautan(): void
    {
        $user = $this->buatPengguna(['STAFF'], ['email' => 'ada@pkink.test']);

        $this->mohon('ada@pkink.test')
            ->assertRedirect(route('password.request'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, self::MESEJ));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_emel_tidak_berdaftar_dapat_respons_sama(): void
    {
        $this->mohon('tiada@pkink.test')
            ->assertRedirect(route('password.request'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, self::MESEJ));

        Notification::assertNothingSent();
    }

    public function test_permintaan_berulang_dapat_respons_sama(): void
    {
        $this->buatPengguna(['STAFF'], ['email' => 'ada@pkink.test']);
        $this->mohon('ada@pkink.test');

        // Broker Laravel throttle permintaan kedua untuk emel yang wujud sahaja
        $this->mohon('ada@pkink.test')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, self::MESEJ));
    }

    public function test_smtp_gagal_tidak_dedah_ralat(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('Connection could not be established with host smtp.ujian'));

        $this->mohon('ada@pkink.test')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, self::MESEJ));

        $this->assertStringNotContainsString('smtp.ujian', (string) session('success'));
    }

    public function test_format_emel_tidak_sah_ditolak(): void
    {
        $this->mohon('bukan-emel')
            ->assertSessionHasErrors(['email' => 'Format alamat emel tidak sah.']);
    }
}
