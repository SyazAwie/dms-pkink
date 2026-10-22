<?php

namespace App\Notifications;

use App\Models\PendaftaranStaf;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emel kepada pemohon: pendaftaran diluluskan + kata laluan sementara.
 * Dihantar SEGERAK (notifyNow) di dalam transaksi kelulusan, supaya jika emel
 * gagal, kelulusan dibatalkan dan kata laluan sementara tidak pernah terdedah.
 */
class PendaftaranDiluluskan extends Notification
{
    public function __construct(
        private PendaftaranStaf $pendaftaran,
        private string $kataLaluanSementara,
        private int $jamSah,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pendaftaran Akaun Diluluskan - Sistem Arkib Digital PKINK')
            ->greeting('Salam Sejahtera, ' . $this->pendaftaran->nama_staff)
            ->line('Permohonan pendaftaran akaun anda dalam Sistem Arkib Digital PKINK telah **diluluskan**.')
            ->line('Gunakan maklumat berikut untuk log masuk:')
            ->line('**No. Kad Pengenalan:** ' . $this->pendaftaran->ic_pekerja)
            ->line('**Kata Laluan Sementara:** ' . $this->kataLaluanSementara)
            ->action('Log Masuk Sekarang', route('login'))
            ->line("Kata laluan sementara ini sah selama {$this->jamSah} jam sahaja. Anda akan diminta menukarnya kepada kata laluan baharu sebaik sahaja log masuk.")
            ->line('Jika kata laluan sementara telah tamat tempoh, gunakan pautan "Lupa Kata Laluan?" di halaman log masuk.')
            ->salutation('Sekian, terima kasih. Bahagian Digital PKINK');
    }
}
