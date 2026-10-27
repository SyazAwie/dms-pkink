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
}