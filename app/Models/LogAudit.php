<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAudit extends Model
{
    // Beritahu jadual yang tepat
    protected $table = 'log_audit';
    protected $primaryKey = 'log_audit_id';

    // Tutup timestamps biasa sebab kita cuma ada created_at dalam jadual
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tindakan',
        'nama_jadual',
        'rekod_id',
        'data_lama',
        'data_baharu',
        'alamat_ip',
        'peranti_pengguna',
        'created_at'
    ];

    // Tukar format JSON automatik
    protected $casts = [
        'data_lama' => 'array',
        'data_baharu' => 'array',
        'created_at' => 'datetime',
    ];

    // Pengguna yang melakukan tindakan (termasuk yang telah dipadam)
    public function pengguna()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id')->withTrashed();
    }
}