<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bahagian extends Model
{
    use SoftDeletes;
    
    protected $table = 'bahagian';
    protected $primaryKey = 'bahagian_id';

    protected $fillable = [
        'kod_bahagian',
        'nama_bahagian'
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'bahagian_id', 'bahagian_id');
    }

    public function dokumen()
    {
        return $this->hasMany(Dokumen::class, 'bahagian_id', 'bahagian_id');
    }

    /** Permohonan akaun baharu yang masih menunggu pengesahan bagi bahagian ini. */
    public function pendaftaranMenunggu()
    {
        return $this->hasMany(PendaftaranStaf::class, 'bahagian_id', 'bahagian_id')
            ->where('status', PendaftaranStaf::MENUNGGU);
    }
}