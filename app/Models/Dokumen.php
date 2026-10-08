<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dokumen extends Model
{
    use SoftDeletes;

    /**
     * Peranan yang boleh MELIHAT semua dokumen (tanpa had bahagian/pemilik).
     * PENGURUS: dokumen bahagian sendiri. STAFF: dokumen sendiri.
     */
    public const PERANAN_AKSES_SEMUA = ['SUPERADMIN', 'ADMIN', 'KERANI', 'AUDIT', 'PENYOKONG', 'PELULUS'];

    protected $table = 'dokumen';

    protected $primaryKey = 'dokumen_id';

    protected $fillable = [
        'jenis_dokumen_id',
        'no_rujukan',
        'tarikh_dokumen',
        'perkara',
        'status',
        'bahagian_id',
        'created_by',
    ];

    protected $casts = [
        'tarikh_dokumen' => 'date',
    ];

    /**
     * Hadkan senarai dokumen kepada yang dibenarkan dilihat oleh pengguna.
     */
    public function scopeBolehDilihat($query, User $user)
    {
        if ($user->hasAnyRole(...self::PERANAN_AKSES_SEMUA)) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $ada = false;

            if ($user->hasRole('PENGURUS') && $user->bahagian_id) {
                $q->where('bahagian_id', $user->bahagian_id);
                $ada = true;
            }

            if ($user->hasRole('STAFF')) {
                $ada ? $q->orWhere('created_by', $user->user_id) : $q->where('created_by', $user->user_id);
                $ada = true;
            }

            // Tiada peranan berkaitan: tiada dokumen dipaparkan
            if (!$ada) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    public function jenisDokumen()
    {
        // withTrashed: dokumen lama masih papar nama jenis walaupun kategori telah dipadam
        return $this->belongsTo(JenisDokumen::class, 'jenis_dokumen_id', 'jenis_dokumen_id')->withTrashed();
    }

    public function bahagian()
    {
        // withTrashed: dokumen lama masih papar nama bahagian walaupun bahagian telah dipadam
        return $this->belongsTo(Bahagian::class, 'bahagian_id', 'bahagian_id')->withTrashed();
    }

    public function pemuatNaik()
    {
        // withTrashed: dokumen lama masih papar nama pemuat naik walaupun akaun telah dipadam
        return $this->belongsTo(User::class, 'created_by', 'user_id')->withTrashed();
    }

    /**
     * Nilai medan EAV (dokumen_data) untuk dokumen ini.
     */
    public function data()
    {
        return $this->hasMany(DokumenData::class, 'dokumen_id', 'dokumen_id');
    }

    /**
     * Fail imbasan yang dilampirkan pada dokumen ini.
     */
    public function scans()
    {
        return $this->hasMany(DokumenScan::class, 'dokumen_id', 'dokumen_id');
    }

    /**
     * Aliran Kelulusan (versi asas): satu dokumen = satu rekod borang
     * (lihat nota had dalam BorangController). hasOne ambil rekod PERTAMA
     * jika lebih daripada satu wujud secara tak sengaja di luar aplikasi ini.
     */
    public function borang()
    {
        return $this->hasOne(Borang::class, 'dokumen_id', 'dokumen_id');
    }
}