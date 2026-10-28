<?php

namespace App\Http\Controllers;

use App\Models\LogMasuk;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sejarah log masuk (P13) untuk AUDIT/SUPERADMIN: berjaya, gagal dan disekat, dengan
 * IP dan pelayar, supaya cubaan meneka kata laluan atau akses luar biasa dapat dikesan.
 */
class LogMasukController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(['berjaya', 'gagal'])],
            'dari' => 'nullable|date',
            'hingga' => 'nullable|date|after_or_equal:dari',
        ]);

        $senarai = LogMasuk::with('pengguna')
            ->when($request->filled('q'), fn ($q) => $q->whereHas('pengguna', fn ($u) => $u->withTrashed()
                ->where('nama_staff', 'like', '%'.$request->q.'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('berjaya', $request->status === 'berjaya'))
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('created_at', '>=', $request->dari))
            ->when($request->filled('hingga'), fn ($q) => $q->whereDate('created_at', '<=', $request->hingga))
            ->latest('log_masuk_id')->paginate(25)->withQueryString();

        $ringkasan = [
            'berjaya' => LogMasuk::where('berjaya', true)->whereDate('created_at', today())->count(),
            'gagal' => LogMasuk::where('berjaya', false)->whereDate('created_at', today())->count(),
            'gagal7' => LogMasuk::where('berjaya', false)->where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return view('log_masuk.index', compact('senarai', 'ringkasan'));
    }
}
