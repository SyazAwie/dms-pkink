<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Halaman Log Audit (baca sahaja). Akses dihadkan kepada SUPERADMIN & AUDIT
 * melalui middleware 'peranan' dalam routes/web.php.
 * Tiada fungsi ubah/padam: log audit tidak boleh dipinda melalui aplikasi.
 */
class LogAuditController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'pengguna' => 'nullable|integer',
            'jadual' => 'nullable|string|max:100',
            'tindakan' => 'nullable|string|max:100',
            'rekod' => 'nullable|integer',
            'dari' => 'nullable|date',
            'hingga' => 'nullable|date|after_or_equal:dari',
        ]);

        $query = LogAudit::with('pengguna')->orderByDesc('log_audit_id');

        if ($request->filled('pengguna')) {
            $query->where('user_id', $request->pengguna);
        }
        if ($request->filled('jadual')) {
            $query->where('nama_jadual', $request->jadual);
        }
        if ($request->filled('tindakan')) {
            $query->where('tindakan', $request->tindakan);
        }
        if ($request->filled('rekod')) {
            $query->where('rekod_id', $request->rekod);
        }
        // Julat tarikh tanpa whereDate() supaya indeks created_at boleh digunakan
        if ($request->filled('dari')) {
            $query->where('created_at', '>=', $request->dari . ' 00:00:00');
        }
        if ($request->filled('hingga')) {
            $query->where('created_at', '<=', $request->hingga . ' 23:59:59');
        }

        $senarai = $query->paginate(25)->withQueryString();

        $penggunaSenarai = User::withTrashed()->orderBy('nama_staff')->get(['user_id', 'nama_staff']);
        $jadualSenarai = LogAudit::query()->distinct()->orderBy('nama_jadual')->pluck('nama_jadual');
        $tindakanSenarai = LogAudit::query()->distinct()->orderBy('tindakan')->pluck('tindakan');

        $adaPenapis = collect($request->only(['pengguna', 'jadual', 'tindakan', 'rekod', 'dari', 'hingga']))
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->isNotEmpty();

        return view('log_audit.index', compact('senarai', 'penggunaSenarai', 'jadualSenarai', 'tindakanSenarai', 'adaPenapis'));
    }
}