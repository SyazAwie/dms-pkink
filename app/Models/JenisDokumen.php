<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisDokumen extends Model
{
    use SoftDeletes;

    // Beritahu nama jadual yang tepat
    protected $table = 'jenis_dokumen';

    // Beritahu nama Primary Key
    protected $primaryKey = 'jenis_dokumen_id';

    // Benarkan lajur ini diisi melalui borang
    protected $fillable = [
        'kod_dokumen',
        'nama_dokumen',
        'kategori',
        'tempoh_simpanan_tahun',
        'tindakan_tamat_tempoh',
    ];

    /**
     * Tindakan yang disyorkan bila dokumen kategori ini tamat tempoh simpanan (P12),
     * ikut amalan pengurusan rekod Arkib Negara: semak semula, musnah, atau serah.
     */
    public const TINDAKAN_TAMAT = ['Semak Semula', 'Musnah', 'Serah Arkib Negara'];

    /**
     * Medan dinamik (EAV) yang ditakrifkan untuk kategori dokumen ini,
     * disusun ikut lajur `susunan`.
     */
    public function fields()
    {
        return $this->hasMany(DokumenField::class, 'jenis_dokumen_id', 'jenis_dokumen_id')
                    ->orderBy('susunan');
    }

    public function dokumen()
    {
        return $this->hasMany(Dokumen::class, 'jenis_dokumen_id', 'jenis_dokumen_id');
    }
}