<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    // Rujuk lajur primary key berdasarkan gambar
    protected $primaryKey = 'roles_id';

    protected $fillable = [
        'kod_roles',
        'nama_roles'
    ];

    // Hubungan balik ke User melalui jadual pivot 'user_roles'
    // (lajur pivot: role_id -> roles.roles_id, user_id -> users.user_id)
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
    }
}