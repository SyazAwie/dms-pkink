<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aliran Kelulusan (versi asas). Lihat nota lengkap dalam BorangController.
 *
 * Had yang disengajakan buat masa ini: satu dokumen = satu rekod borang
 * (dikemaskini semula bila dihantar ulang), bukan sejarah berbilang
 * penghantaran.
 */
class Borang extends Model
{
    protected $table = 'borang';

    protected $primaryKey = 'borang_id';

    protected $fillable = [
        'dokumen_id',
        'pemohon_id',
        'pegawai_penyokong_id',
        'pegawai_pelulus_id',
        'status_permohonan',
        'tarikh_permohonan',
        'tarikh_sokongan',
        'tarikh_kelulusan',
        'ulasan_penyokong',
        'ulasan_pelulus',
    ];

    protected $casts = [
        'tarikh_permohonan' => 'datetime',
        'tarikh_sokongan' => 'datetime',
        'tarikh_kelulusan' => 'datetime',
    ];

    /**
     * Hadkan kepada borang yang dokumen induknya masih wujud (tidak dipadam).
     * Skop SoftDeletes pada Dokumen mengecualikan dokumen terpadam secara automatik.
     */
    public function scopeDokumenAktif($query)
    {
        return $query->whereHas('dokumen');
    }

    /**
     * Kecualikan permohonan yang dihantar oleh $userId sendiri. Pemohon tidak boleh
     * menyokong/meluluskan permohonannya, jadi ia bukan giliran tindakannya.
     */
    public function scopeBukanMilik($query, $userId)
    {
        return $query->where(fn ($q) => $q->whereNull('pemohon_id')->orWhere('pemohon_id', '!=', $userId));
    }

    /** Pengguna ini yang menghantar permohonan ini? */
    public function dipohonOleh($userId): bool
    {
        return $this->pemohon_id !== null && (int) $this->pemohon_id === (int) $userId;
    }

    public function dokumen()
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id', 'dokumen_id');
    }

    public function pemohon()
    {
        return $this->belongsTo(User::class, 'pemohon_id', 'user_id');
    }

    public function penyokong()
    {
        return $this->belongsTo(User::class, 'pegawai_penyokong_id', 'user_id');
    }

    public function pelulus()
    {
        return $this->belongsTo(User::class, 'pegawai_pelulus_id', 'user_id');
    }
}