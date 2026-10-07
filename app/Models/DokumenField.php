<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DokumenField extends Model
{
    use SoftDeletes;

    protected $table = 'dokumen_field';

    protected $primaryKey = 'dokumen_field_id';

    protected $fillable = [
        'jenis_dokumen_id',
        'nama_field',
        'kod_field',
        'jenis_data',
        'pilihan',
        'is_required',
        'susunan',
    ];

    protected $casts = [
        'pilihan' => 'array',
        'is_required' => 'boolean',
    ];

    public function jenisDokumen()
    {
        return $this->belongsTo(JenisDokumen::class, 'jenis_dokumen_id', 'jenis_dokumen_id');
    }
}