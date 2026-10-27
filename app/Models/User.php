<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

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
        'jawatan',
        'email',
        'no_telefon',
        'password',
        'bahagian_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // perlu_tukar_kata_laluan & kata_laluan_tamat_pada sengaja TIDAK dalam $fillable:
    // hanya ditetapkan oleh kod pelayan (forceFill / tetapan terus), bukan input borang.
    protected $casts = [
        'perlu_tukar_kata_laluan' => 'boolean',
        'kata_laluan_tamat_pada' => 'datetime',
    ];

    // Hubungan dengan jadual bahagian
    public function bahagian()
    {
        // withTrashed: kakitangan masih papar nama bahagian walaupun bahagian telah dipadam
        return $this->belongsTo(Bahagian::class, 'bahagian_id', 'bahagian_id')->withTrashed();
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

    /** Kunci sesi bagi peranan aktif (tukar peranan dari menu nama di topbar). */
    public const SESI_PERANAN_AKTIF = 'peranan_aktif';

    /** Semua kod peranan yang dimiliki pengguna, tanpa mengira peranan aktif. */
    public function semuaKodPeranan(): array
    {
        return $this->roles->pluck('kod_roles')->all();
    }

    /**
     * Peranan aktif pengguna yang sedang log masuk (null = "Semua peranan").
     * Hanya bagi pengguna sesi semasa; pengguna lain yang dimuat (cth. dalam senarai
     * kakitangan atau penerima notifikasi) sentiasa dinilai dengan semua perananya.
     */
    public function perananAktif(): ?string
    {
        if (! Auth::hasUser() || Auth::id() !== $this->user_id || ! app()->bound('session')) {
            return null;
        }

        $kod = session(self::SESI_PERANAN_AKTIF);

        return is_string($kod) && in_array($kod, $this->semuaKodPeranan(), true) ? $kod : null;
    }

    /**
     * Senarai kod peranan yang BERKUAT KUASA (cth: ['ADMIN', 'STAFF'], atau ['STAFF'] bila
     * pengguna menukar peranan aktif kepada Staf). Semua semakan kebenaran (middleware
     * peranan:, DokumenPolicy, Dokumen::scopeBolehDilihat, menu) melalui kaedah ini.
     */
    public function kodPeranan(): array
    {
        $aktif = $this->perananAktif();

        return $aktif ? [$aktif] : $this->semuaKodPeranan();
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