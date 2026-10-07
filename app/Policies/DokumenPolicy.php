<?php

namespace App\Policies;

use App\Models\Dokumen;
use App\Models\User;

/**
 * Kebenaran dokumen ikut peranan:
 *
 *  - Lihat  : SUPERADMIN/ADMIN/KERANI/AUDIT/PENYOKONG/PELULUS = semua;
 *             PENGURUS = bahagian sendiri; STAFF = milik sendiri
 *  - Muat naik : SUPERADMIN/ADMIN/KERANI/PENGURUS/STAFF
 *  - Edit   : SUPERADMIN/ADMIN/KERANI = semua; PENGURUS = bahagian sendiri; STAFF = milik sendiri
 *  - Padam  : SUPERADMIN/ADMIN/KERANI sahaja
 *
 * Skop "lihat" mesti sepadan dengan Dokumen::scopeBolehDilihat().
 */
class DokumenPolicy
{
    private const PERANAN_PENGURUS_PENUH = ['SUPERADMIN', 'ADMIN', 'KERANI'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(...Dokumen::PERANAN_AKSES_SEMUA, ...['PENGURUS', 'STAFF']);
    }

    public function view(User $user, Dokumen $dokumen): bool
    {
        if ($user->hasAnyRole(...Dokumen::PERANAN_AKSES_SEMUA)) {
            return true;
        }

        return $this->dalamSkopSendiri($user, $dokumen);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(...self::PERANAN_PENGURUS_PENUH, ...['PENGURUS', 'STAFF']);
    }

    public function update(User $user, Dokumen $dokumen): bool
    {
        if ($user->hasAnyRole(...self::PERANAN_PENGURUS_PENUH)) {
            return true;
        }

        return $this->dalamSkopSendiri($user, $dokumen);
    }

    public function delete(User $user, Dokumen $dokumen): bool
    {
        return $user->hasAnyRole(...self::PERANAN_PENGURUS_PENUH);
    }

    /**
     * PENGURUS: dokumen bahagian sendiri. STAFF: dokumen yang dimuat naik sendiri.
     */
    private function dalamSkopSendiri(User $user, Dokumen $dokumen): bool
    {
        if ($user->hasRole('PENGURUS')
            && $user->bahagian_id
            && (int) $user->bahagian_id === (int) $dokumen->bahagian_id) {
            return true;
        }

        return $user->hasRole('STAFF')
            && (int) $dokumen->created_by === (int) $user->user_id;
    }
}