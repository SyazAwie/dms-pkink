<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumenScan extends Model
{
    protected $table = 'dokumen_scan';

    protected $primaryKey = 'dokumen_scan_id';

    protected $fillable = [
        'dokumen_id',
        'nama_fail',
        'lokasi_fail',
        'tarikh_scan',
        'status_ocr',
    ];

    public function dokumen()
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id', 'dokumen_id');
    }

    public function ocr()
    {
        return $this->hasOne(DokumenOcr::class, 'dokumen_scan_id', 'dokumen_scan_id');
    }
}