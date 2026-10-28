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
        'pegawai_penolak_id',
        'status_permohonan',
        'tarikh_permohonan',
        'tarikh_sokongan',
        'tarikh_kelulusan',
        'tarikh_tolak',
        'ulasan_penyokong',
        'ulasan_pelulus',
    ];

    /**
     * Pegawai asal bila tindakan seterusnya dibuat melalui delegasi (P5). Bukan lajur
     * jadual: dibaca oleh BorangSejarahObserver dan dikosongkan selepas itu.
     */
    public ?int $bagiPihakId = null;

    protected $casts = [
        'tarikh_permohonan' => 'datetime',
        'tarikh_sokongan' => 'datetime',
        'tarikh_kelulusan' => 'datetime',
        'tarikh_tolak' => 'datetime',
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

    /**
     * Kod pengesahan slip kelulusan (P6): HMAC pendek dengan APP_KEY, jadi tidak boleh diteka
     * walaupun borang_id berjujukan. Tarikh kelulusan termasuk dalam kod, jadi slip lama
     * menjadi tidak sah bila permohonan dihantar semula dan diluluskan semula.
     * Null jika permohonan belum (atau tidak lagi) diluluskan.
     */
    public function kodPengesahan(): ?string
    {
        if ($this->status_permohonan !== 'Diluluskan' || ! $this->tarikh_kelulusan) {
            return null;
        }

        $data = "slip|{$this->borang_id}|{$this->dokumen_id}|{$this->tarikh_kelulusan->timestamp}";

        return strtoupper(substr(hash_hmac('sha256', $data, (string) config('app.key')), 0, 12));
    }

    /** Sejarah tindakan (P4), terdahulu dahulu. */
    public function sejarah()
    {
        return $this->hasMany(BorangSejarah::class, 'borang_id', 'borang_id')->orderBy('created_at')->orderBy('borang_sejarah_id');
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

    /** Pegawai yang menolak permohonan (peringkat sokongan atau kelulusan). */
    public function penolak()
    {
        return $this->belongsTo(User::class, 'pegawai_penolak_id', 'user_id')->withTrashed();
    }
}