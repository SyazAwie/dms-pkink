<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DokumenScan extends Model
{
    protected $table = 'dokumen_scan';

    protected $primaryKey = 'dokumen_scan_id';

    protected $fillable = [
        'dokumen_id',
        'nama_fail',
        'lokasi_fail',
        'saiz_fail',
        'jenis_mime',
        'hash_sha256',
        'tarikh_scan',
        'status_ocr',
    ];

    protected $casts = [
        'saiz_fail' => 'integer',
    ];

    /**
     * Saiz, jenis MIME dan SHA-256 diisi automatik bagi setiap fail baharu (Muat Naik, Kemaskini,
     * Imbas & Arkib/Hantar), supaya integriti arkib boleh disahkan kemudian (fail:integriti).
     */
    protected static function booted(): void
    {
        static::creating(function (DokumenScan $scan) {
            if ($scan->hash_sha256 === null && ($meta = $scan->metadataFail())) {
                $scan->fill($meta);
            }
        });
    }

    /**
     * Metadata fail sebenar dalam disk `local`, atau null jika fail tiada.
     *
     * @return array{saiz_fail: int, jenis_mime: string|null, hash_sha256: string}|null
     */
    public function metadataFail(): ?array
    {
        $disk = Storage::disk('local');
        if (! $this->lokasi_fail || ! $disk->exists($this->lokasi_fail)) {
            return null;
        }

        return [
            'saiz_fail' => (int) $disk->size($this->lokasi_fail),
            'jenis_mime' => $disk->mimeType($this->lokasi_fail) ?: null,
            'hash_sha256' => hash_file('sha256', $disk->path($this->lokasi_fail)),
        ];
    }

    /** Saiz fail dalam bentuk mudah dibaca (cth. "1.2 MB"). */
    public function saizMudahDibaca(): ?string
    {
        if ($this->saiz_fail === null) {
            return null;
        }

        return $this->saiz_fail >= 1048576
            ? number_format($this->saiz_fail / 1048576, 1) . ' MB'
            : number_format(max(1, $this->saiz_fail / 1024)) . ' KB';
    }

    public function dokumen()
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id', 'dokumen_id');
    }

    public function ocr()
    {
        return $this->hasOne(DokumenOcr::class, 'dokumen_scan_id', 'dokumen_scan_id');
    }
}
