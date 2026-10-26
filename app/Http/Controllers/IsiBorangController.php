<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use App\Models\JenisDokumen;
use Illuminate\Support\Facades\Gate;

/**
 * Halaman "Isi Borang" (OCR 3 Situasi, Fasa C): pintu masuk pemohon. Setiap
 * kategori boleh diisi secara digital (Muat Naik, Situasi 2) atau diimbas
 * daripada borang fizikal yang sudah diisi (Imbas & Hantar, Situasi 3).
 */
class IsiBorangController extends Controller
{
    public function index()
    {
        Gate::authorize('create', Dokumen::class);

        $kumpulan = JenisDokumen::with('fields')
            ->orderBy('nama_dokumen')
            ->get()
            ->groupBy(fn (JenisDokumen $j) => $j->kategori ?: 'Lain-lain')
            ->sortKeys();

        return view('isi_borang.index', compact('kumpulan'));
    }
}
