<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Delegasi kelulusan semasa cuti (P5): penerima memegang peranan PENYOKONG/PELULUS
 * pemberi dalam julat tarikh. Peranan delegasi dimasukkan dalam User::semuaKodPeranan(),
 * jadi menu, papan pemuka dan kebenaran kelulusan mengikutinya secara automatik.
 */
class DelegasiKelulusan extends Model
{
    protected $table = 'delegasi_kelulusan';

    protected $primaryKey = 'delegasi_id';

    public const PERANAN = ['PENYOKONG', 'PELULUS'];

    protected $fillable = ['pemberi_id', 'penerima_id', 'kod_peranan', 'tarikh_mula', 'tarikh_tamat', 'sebab', 'dicipta_oleh'];

    protected $casts = [
        'tarikh_mula' => 'date',
        'tarikh_tamat' => 'date',
        'dibatalkan_pada' => 'datetime',
    ];

    /**
     * Delegasi yang berkuat kuasa hari ini: belum dibatalkan, dalam julat tarikh, dan
     * pemberi masih aktif serta masih memegang peranan itu.
     */
    public function scopeAktif(Builder $query): Builder
    {
        $hariIni = today()->toDateString();

        return $query->whereNull('dibatalkan_pada')
            ->whereDate('tarikh_mula', '<=', $hariIni)
            ->whereDate('tarikh_tamat', '>=', $hariIni)
            ->whereHas('pemberi', fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereColumn('roles.kod_roles', 'delegasi_kelulusan.kod_peranan')));
    }

    /** Status untuk paparan: Aktif, Akan Datang, Tamat atau Dibatalkan. */
    public function status(): string
    {
        return match (true) {
            $this->dibatalkan_pada !== null => 'Dibatalkan',
            $this->tarikh_tamat->lt(today()) => 'Tamat',
            $this->tarikh_mula->gt(today()) => 'Akan Datang',
            default => 'Aktif',
        };
    }

    public function pemberi()
    {
        return $this->belongsTo(User::class, 'pemberi_id', 'user_id');
    }

    public function penerima()
    {
        return $this->belongsTo(User::class, 'penerima_id', 'user_id');
    }

    public function pencipta()
    {
        return $this->belongsTo(User::class, 'dicipta_oleh', 'user_id')->withTrashed();
    }
}
