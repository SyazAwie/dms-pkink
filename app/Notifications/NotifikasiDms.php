<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Notifikasi dalam sistem (loceng topbar & halaman Notifikasi), disimpan dalam jadual
 * `notifications`. Dicipta melalui App\Support\Notifikasi.
 *
 * Data: jenis, tajuk, mesej, url, ikon (Bootstrap Icons), warna, peranan.
 * `peranan` = senarai kod peranan yang berkaitan (cth. ['PENYOKONG']) supaya loceng
 * hanya memaparkannya bila peranan itu aktif; null = notifikasi peribadi (sentiasa dipapar).
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
        return ['database'];
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
}
