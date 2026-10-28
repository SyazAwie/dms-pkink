<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Sejarah log masuk (P13): setiap cubaan berjaya atau gagal. No. KP yang dicuba TIDAK
 * disimpan (data peribadi); user_id null bila No. KP tidak wujud.
 */
class LogMasuk extends Model
{
    protected $table = 'log_masuk';

    protected $primaryKey = 'log_masuk_id';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'berjaya', 'sebab', 'alamat_ip', 'peranti_pengguna'];

    protected $casts = ['berjaya' => 'boolean'];

    public static function rekod(Request $request, ?int $userId, bool $berjaya, ?string $sebab = null): void
    {
        static::create([
            'user_id' => $userId,
            'berjaya' => $berjaya,
            'sebab' => $sebab,
            'alamat_ip' => $request->ip(),
            'peranti_pengguna' => $request->userAgent(),
        ]);
    }

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id')->withTrashed();
    }
}
