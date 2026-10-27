<?php

namespace App\Notifications;

use App\Support\TeksEmel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notifikasi dalam sistem (loceng topbar & halaman Notifikasi), disimpan dalam jadual
 * `notifications`. Dicipta melalui App\Support\Notifikasi.
 *
 * Data: jenis, tajuk, mesej, url, ikon (Bootstrap Icons), warna, peranan.
 * `peranan` = senarai kod peranan yang berkaitan (cth. ['PENYOKONG']) supaya loceng
 * hanya memaparkannya bila peranan itu aktif; null = notifikasi peribadi (sentiasa dipapar).
 *
 * Juga dihantar sebagai notifikasi peranti (Web Push, public/sw.js) kepada setiap peranti
 * yang pengguna telah hidupkan (jadual push_subscriptions).
 */
class NotifikasiDms extends Notification
{
    public function __construct(
        public string $jenis,
        public string $tajuk,
        public string $mesej,
        public string $url,
        public string $ikon = 'bi-bell',
        public string $warna = 'biru',
        public ?array $peranan = null,
    ) {}

    public function via(object $notifiable): array
    {
        $saluran = ['database'];
        if (config('webpush.vapid.public_key') && method_exists($notifiable, 'pushSubscriptions')
            && $notifiable->pushSubscriptions()->exists()) {
            $saluran[] = WebPushChannel::class;
        }
        // Emel (P2): hanya bila NOTIFIKASI_EMEL=true dan penerima ada alamat emel
        if (config('services.notifikasi.emel') && ! empty($notifiable->email)) {
            $saluran[] = 'mail';
        }

        return $saluran;
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Teks pengguna (nama, ulasan) di-escape supaya tidak menjadi pautan Markdown (lihat TeksEmel)
        return (new MailMessage)
            ->subject("DMS PKINK: {$this->tajuk}")
            ->greeting('Salam sejahtera, '.TeksEmel::selamat($notifiable->nama_staff ?? '').',')
            ->line('**'.TeksEmel::selamat($this->tajuk).'**')
            ->line(TeksEmel::selamat($this->mesej))
            ->action('Buka dalam Sistem', $this->url)
            ->line('Emel ini dijana secara automatik oleh Sistem Arkib Digital PKINK. Sila jangan balas emel ini.')
            ->salutation('Sekian, terima kasih.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'jenis' => $this->jenis,
            'tajuk' => $this->tajuk,
            'mesej' => $this->mesej,
            'url' => $this->url,
            'ikon' => $this->ikon,
            'warna' => $this->warna,
            'peranan' => $this->peranan,
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->tajuk)
            ->body($this->mesej)
            ->icon('/ikon/dms-192.png')
            ->badge('/ikon/dms-192.png')
            ->tag($this->jenis)
            ->data(['url' => $this->url])
            ->options(['TTL' => 86400]); // simpan 24 jam di pelayan push jika peranti luar talian
    }
}
