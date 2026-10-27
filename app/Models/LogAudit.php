<?php

namespace App\Models;

use App\Support\Notifikasi;
use Illuminate\Database\Eloquent\Model;

class LogAudit extends Model
{
    // Beritahu jadual yang tepat
    protected $table = 'log_audit';

    protected $primaryKey = 'log_audit_id';

    // Tutup timestamps biasa sebab kita cuma ada created_at dalam jadual
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tindakan',
        'nama_jadual',
        'rekod_id',
        'data_lama',
        'data_baharu',
        'alamat_ip',
        'peranti_pengguna',
        'created_at',
    ];

    // Tukar format JSON automatik
    protected $casts = [
        'data_lama' => 'array',
        'data_baharu' => 'array',
        'created_at' => 'datetime',
    ];

    /** Amaran padam beramai (P14): bilangan tindakan padam oleh seorang pengguna dalam tempoh minit */
    public const AMARAN_PADAM_BILANGAN = 5;

    public const AMARAN_PADAM_MINIT = 10;

    public const JADUAL_AMARAN_PADAM = ['dokumen', 'jenis_dokumen', 'bahagian', 'users'];

    protected static function booted(): void
    {
        // Pentadbir dimaklumkan sekali (tepat pada ambang) bila seorang pengguna memadam
        // banyak rekod dalam masa singkat: kesilapan atau akaun disalah guna
        static::created(function (LogAudit $log) {
            // Medan kategori (dokumen_field) tidak dikira: ia dipadam beramai semasa sunting kategori
            if (! $log->user_id || ! str_starts_with((string) $log->tindakan, 'Padam') || ! in_array($log->nama_jadual, self::JADUAL_AMARAN_PADAM, true)) {
                return;
            }
            $bilangan = static::where('user_id', $log->user_id)->where('tindakan', 'like', 'Padam%')
                ->whereIn('nama_jadual', self::JADUAL_AMARAN_PADAM)
                ->where('created_at', '>=', now()->subMinutes(self::AMARAN_PADAM_MINIT))->count();
            if ($bilangan === self::AMARAN_PADAM_BILANGAN) {
                $nama = $log->pengguna->nama_staff ?? "ID {$log->user_id}";
                Notifikasi::amaranSistem('Amaran: banyak rekod dipadam',
                    "{$nama} telah memadam {$bilangan} rekod dalam ".self::AMARAN_PADAM_MINIT.' minit. Semak Log Audit dan Tong Sampah.');
            }
        });
    }

    // Pengguna yang melakukan tindakan (termasuk yang telah dipadam)
    public function pengguna()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id')->withTrashed();
    }
}
