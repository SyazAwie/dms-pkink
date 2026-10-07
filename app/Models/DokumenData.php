<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumenData extends Model
{
    protected $table = 'dokumen_data';

    protected $primaryKey = 'dokumen_data_id';

    protected $fillable = [
        'dokumen_id',
        'dokumen_field_id',
        'nilai_data',
    ];

    public function dokumen()
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id', 'dokumen_id');
    }

    public function field()
    {
        // withTrashed: nilai lama masih perlu papar nama medan walaupun medan telah dipadam
        return $this->belongsTo(DokumenField::class, 'dokumen_field_id', 'dokumen_field_id')->withTrashed();
    }
}