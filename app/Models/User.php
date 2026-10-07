<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    // Beritahu Laravel nama table yang betul
    protected $table = 'users';

    // Beritahu Laravel Primary Key kita adalah user_id, bukan id
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'ic_pekerja',
        'nama_staff',
        'email',
        'password',
        'bahagian_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Hubungan dengan jadual bahagian
    public function bahagian()
    {
        return $this->belongsTo(Bahagian::class, 'bahagian_id', 'bahagian_id');
    }

    /**
     * Hubungan Many-to-Many dengan roles melalui jadual pivot user_roles.
     * PENTING: lajur pivot dalam pangkalan data ialah `user_id` dan `role_id`
     * (BUKAN `roles_id`), manakala primary key jadual roles ialah `roles_id`.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    /**
     * Senarai kod peranan pengguna (cth: ['ADMIN', 'STAFF']).
     * Guna relasi yang dimuat sekali sahaja setiap request.
     */
    public function kodPeranan(): array
    {
        return $this->roles->pluck('kod_roles')->all();
    }

    // Semak satu peranan berdasarkan kod_roles (cth: 'SUPERADMIN')
    public function hasRole(string $kod): bool
    {
        return in_array($kod, $this->kodPeranan(), true);
    }

    /**
     * Semak sekurang-kurangnya satu daripada peranan yang diberi.
     * Tanpa argumen: semak pengguna mempunyai APA-APA peranan.
     */
    public function hasAnyRole(string ...$kod): bool
    {
        $dimiliki = $this->kodPeranan();

        if (empty($kod)) {
            return !empty($dimiliki);
        }

        return !empty(array_intersect($kod, $dimiliki));
    }
}