<?php

namespace App\Notifications;

use App\Models\PendaftaranStaf;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emel kepada pemohon: pendaftaran tidak diterima, berserta sebab.
 * Dihantar SEGERAK (notifyNow) di dalam transaksi penolakan.
 */
class PendaftaranDitolak extends Notification
{
    public function __construct(private PendaftaranStaf $pendaftaran)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pendaftaran Akaun Tidak Diterima - Sistem Arkib Digital PKINK')
            ->greeting('Salam Sejahtera, ' . $this->pendaftaran->nama_staff)
            ->line('Dukacita dimaklumkan bahawa permohonan pendaftaran akaun anda dalam Sistem Arkib Digital PKINK **tidak diterima**.')
            ->line('**Sebab:** ' . $this->pendaftaran->sebab_tolak)
            ->line('Jika anda mempunyai sebarang pertanyaan, sila hubungi Bahagian Digital PKINK.')
            ->salutation('Sekian, terima kasih. Bahagian Digital PKINK');
    }
}
