<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Templat borang kosong bagi satu kategori (Fasa TB). Fail dalam disk `templat` (folder
 * Templat/ di akar projek). Berbilang versi dibenarkan: borang yang berubah format kekal
 * dikenali bagi imbasan lama; `aktif` = digunakan dalam padanan.
 *
 * ocr_json: {"teks": "...", "skor": 91.2, "token": ["borang", "permohonan", ...]}
 */
class TemplatBorang extends Model
{
    use SoftDeletes;

    protected $table = 'templat_borang';

    protected $primaryKey = 'templat_borang_id';

    protected $fillable = [
        'jenis_dokumen_id', 'versi', 'nama_asal', 'lokasi_fail', 'saiz_fail', 'jenis_mime', 'hash_sha256',
        'bilangan_muka', 'ocr_json', 'status_ocr', 'aktif', 'catatan', 'created_by',
    ];

    protected $casts = [
        'ocr_json' => 'array',
        'aktif' => 'boolean',
        'saiz_fail' => 'integer',
        'bilangan_muka' => 'integer',
        'versi' => 'integer',
    ];

    public function jenisDokumen()
    {
        return $this->belongsTo(JenisDokumen::class, 'jenis_dokumen_id', 'jenis_dokumen_id')->withTrashed();
    }

    public function pemuatNaik()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id')->withTrashed();
    }

    /** Token teks templat (huruf kecil, huruf/angka sahaja) jika OCR sudah siap. */
    public function token(): array
    {
        return $this->ocr_json['token'] ?? [];
    }
}
