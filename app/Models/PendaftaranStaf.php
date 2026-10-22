<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Permohonan pendaftaran akaun oleh staf baharu (dari halaman log masuk).
 * Akaun dalam `users` hanya dicipta apabila SUPERADMIN/ADMIN meluluskannya.
 *
 * Status: Menunggu / Diluluskan / Ditolak. Satu permohonan "Menunggu" sahaja
 * bagi setiap No. KP / emel (lajur janaan ic_menunggu & email_menunggu UNIQUE).
 */
class PendaftaranStaf extends Model
{
    public const MENUNGGU = 'Menunggu';
    public const DILULUSKAN = 'Diluluskan';
    public const DITOLAK = 'Ditolak';

    protected $table = 'pendaftaran_staf';

    protected $primaryKey = 'pendaftaran_staf_id';

    protected $fillable = [
        'ic_pekerja',
        'nama_staff',
        'email',
        'bahagian_id',
        'alamat_ip',
    ];

    protected $casts = [
        'disemak_pada' => 'datetime',
    ];

    public function bahagian()
    {
        // withTrashed: permohonan lama masih papar nama bahagian walaupun bahagian telah dipadam
        return $this->belongsTo(Bahagian::class, 'bahagian_id', 'bahagian_id')->withTrashed();
    }

    // Admin yang meluluskan / menolak
    public function penyemak()
    {
        return $this->belongsTo(User::class, 'disemak_oleh', 'user_id')->withTrashed();
    }

    // Akaun yang dicipta apabila diluluskan
    public function akaun()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id')->withTrashed();
    }
}
