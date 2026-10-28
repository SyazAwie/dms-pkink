<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Alamat IP yang disekat (Fasa Keselamatan S3): manual oleh pentadbir, atau automatik
 * selama 1 jam selepas lonjakan log masuk gagal. tamat_pada NULL = sehingga dibuka.
 * Localhost tidak pernah disekat (pentadbir pelayan tidak boleh terkunci di luar).
 */
class IpDisekat extends Model
{
    protected $table = 'ip_disekat';

    protected $primaryKey = 'ip_disekat_id';

    public const UPDATED_AT = null;

    public const LOCALHOST = ['127.0.0.1', '::1'];

    private const KUNCI_CACHE = 'keselamatan:ip-disekat';

    protected $fillable = ['alamat_ip', 'sebab', 'automatik', 'disekat_oleh', 'tamat_pada'];

    protected $casts = ['automatik' => 'boolean', 'tamat_pada' => 'datetime'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::KUNCI_CACHE));
        static::deleted(fn () => Cache::forget(self::KUNCI_CACHE));
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('tamat_pada')->orWhere('tamat_pada', '>', now()));
    }

    /**
     * IP yang tidak pernah disekat: localhost, dan KESELAMATAN_IP_DIPERCAYAI (cth. IP awam
     * pejabat PKINK yang dikongsi ramai staf melalui NAT: beberapa salah taip kata laluan
     * tidak boleh menyekat seluruh pejabat).
     */
    public static function dipercayai(?string $ip): bool
    {
        $senarai = array_filter(array_map('trim', explode(',', (string) config('services.keselamatan.ip_dipercayai'))));

        return $ip !== null && (in_array($ip, self::LOCALHOST, true) || in_array($ip, $senarai, true));
    }

    /** Disemak setiap permintaan: senarai disimpan 30 saat dalam cache. */
    public static function disekat(?string $ip): bool
    {
        if (! $ip || self::dipercayai($ip)) {
            return false;
        }

        $senarai = Cache::remember(self::KUNCI_CACHE, 30, fn () => static::aktif()->pluck('alamat_ip')->unique()->values()->all());

        return in_array($ip, $senarai, true);
    }

    public function pengsekat()
    {
        return $this->belongsTo(User::class, 'disekat_oleh', 'user_id')->withTrashed();
    }
}
