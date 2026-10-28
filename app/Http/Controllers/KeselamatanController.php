<?php

namespace App\Http\Controllers;

use App\Support\Keselamatan;

/**
 * Pusat Keselamatan (Fasa Keselamatan S2), SUPERADMIN/ADMIN: ringkasan ancaman 24 jam,
 * senarai semak pengerasan (pangkalan data, debug, antivirus, 2FA, kata laluan lalai,
 * sandaran, salinan luar talian, integriti fail) dan tindakan sensitif terkini.
 * Butang kecemasan ditambah dalam S3.
 */
class KeselamatanController extends Controller
{
    public function index()
    {
        $ringkasan = Keselamatan::ringkasan();
        $senaraiSemak = Keselamatan::senaraiSemak();
        $bilMasalah = collect($senaraiSemak)->whereIn('status', ['ralat', 'amaran'])->count();

        return view('keselamatan.index', compact('ringkasan', 'senaraiSemak', 'bilMasalah'));
    }
}
