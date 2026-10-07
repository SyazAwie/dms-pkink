<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumenOcr extends Model
{
    protected $table = 'dokumen_ocr';

    protected $primaryKey = 'dokumen_ocr_id';

    protected $fillable = [
        'dokumen_scan_id',
        'teks_dibaca',
        'skor_padanan',
        'status_semakan',
    ];

    public function scan()
    {
        return $this->belongsTo(DokumenScan::class, 'dokumen_scan_id', 'dokumen_scan_id');
    }
}