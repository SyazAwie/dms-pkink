<?php

namespace App\Http\Controllers;

/**
 * ============================================================================
 * IMBAS & HANTAR (OCR 3 Situasi, Situasi 3) — staf isi borang FIZIKAL sendiri,
 * imbas, OCR + AI isi borang digital, staf SAHKAN isi betul, kemudian borang
 * dihantar "Dalam Proses" kepada Penyokong → Pelulus (BorangController).
 *
 * Aliran token, pratonton dan simpan sama seperti ImbasArkibController (mod
 * 'hantar'); perbezaan mod dikendalikan di sana:
 *   - medan auto-isi profil mengatasi nilai AI (borang milik pengguna sendiri)
 *   - kotak pengesahan wajib sebelum hantar
 *   - borang "Dalam Proses" (bukan "Diluluskan"), log "Hantar untuk Kelulusan"
 *
 * Akses: semua peranan yang boleh muat naik (DokumenPolicy::create).
 * ============================================================================
 */
class ImbasHantarController extends ImbasArkibController
{
    protected function mod(): string
    {
        return 'hantar';
    }
}
