<?php

namespace App\Models;

use App\Observers\JenisDokumenObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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
}