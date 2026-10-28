<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sejarah aliran kelulusan (P4): satu baris bagi setiap tindakan ke atas borang.
 * Ditulis oleh BorangSejarahObserver sahaja, jadi rekod `borang` boleh terus ditimpa
 * semasa hantar semula tanpa menghilangkan jejak sokongan/penolakan sebelumnya.
 */
class BorangSejarah extends Model
{
    protected $table = 'borang_sejarah';

    protected $primaryKey = 'borang_sejarah_id';

    public const UPDATED_AT = null;

    protected $fillable = ['borang_id', 'tindakan', 'status_selepas', 'user_id', 'bagi_pihak_id', 'ulasan'];

    public function borang()
    {
        return $this->belongsTo(Borang::class, 'borang_id', 'borang_id');
    }

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id')->withTrashed();
    }

    /** Pegawai asal jika tindakan dibuat melalui delegasi (P5). */
    public function bagiPihak()
    {
        return $this->belongsTo(User::class, 'bagi_pihak_id', 'user_id')->withTrashed();
    }
}
