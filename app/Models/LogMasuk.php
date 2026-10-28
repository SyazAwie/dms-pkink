<?php

namespace App\Models;

use App\Support\Keselamatan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // Amaran keselamatan (lonjakan gagal dari satu IP, pentadbir dari IP baharu).
        // Kegagalan amaran tidak boleh menghalang log masuk.
        try {
            if (! $berjaya) {
                Keselamatan::selepasGagal($request->ip());
            } elseif ($userId && ($user = User::find($userId))) {
                Keselamatan::selepasBerjaya($user, $request->ip());
            }
        } catch (\Throwable $e) {
            Log::warning('Semakan keselamatan log masuk gagal: '.$e->getMessage());
        }
    }

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id')->withTrashed();
    }
}
