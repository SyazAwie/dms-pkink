<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * Halaman Log Audit (baca sahaja). Akses dihadkan kepada SUPERADMIN & AUDIT
 * melalui middleware 'peranan' dalam routes/web.php.
 * Tiada fungsi ubah/padam: log audit tidak boleh dipinda melalui aplikasi.
 */
class LogAuditController extends Controller
{
    public function index(Request $request)
    {
        $senarai = $this->queryDitapis($request)->paginate(25)->withQueryString();

        $penggunaSenarai = User::withTrashed()->orderBy('nama_staff')->get(['user_id', 'nama_staff']);
        $jadualSenarai = LogAudit::query()->distinct()->orderBy('nama_jadual')->pluck('nama_jadual');
        $tindakanSenarai = LogAudit::query()->distinct()->orderBy('tindakan')->pluck('tindakan');

        $adaPenapis = collect($request->only(['pengguna', 'jadual', 'tindakan', 'rekod', 'dari', 'hingga']))
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->isNotEmpty();

        return view('log_audit.index', compact('senarai', 'penggunaSenarai', 'jadualSenarai', 'tindakanSenarai', 'adaPenapis'));
    }

    /**
     * Eksport CSV log audit ikut penapis semasa (P14), untuk juruaudit. UTF-8 dengan BOM
     * (Excel). Eksport itu sendiri dilog.
     */
    public function eksport(Request $request)
    {
        $query = $this->queryDitapis($request);
        $bilangan = (clone $query)->count();

        LogAudit::create([
            'user_id' => Auth::id(), 'tindakan' => 'Eksport CSV', 'nama_jadual' => 'log_audit', 'rekod_id' => null,
            'data_lama' => null, 'data_baharu' => ['bilangan' => $bilangan, 'penapis' => array_filter($request->query())],
            'alamat_ip' => $request->ip(), 'peranti_pengguna' => $request->userAgent(), 'created_at' => now(),
        ]);

        return response()->streamDownload(function () use ($query) {
            $keluar = fopen('php://output', 'w');
            Csv::bom($keluar);
            Csv::baris($keluar, ['ID', 'Masa', 'Pengguna', 'Tindakan', 'Jadual', 'ID Rekod', 'Alamat IP', 'Data Lama', 'Data Baharu']);
            $query->chunk(500, function ($senarai) use ($keluar) {
                foreach ($senarai as $log) {
                    Csv::baris($keluar, [
                        $log->log_audit_id, $log->created_at?->format('d/m/Y H:i:s'), $log->pengguna->nama_staff ?? 'Sistem / tetamu',
                        $log->tindakan, $log->nama_jadual, $log->rekod_id, $log->alamat_ip,
                        self::json($log->data_lama),
                        self::json($log->data_baharu),
                    ]);
                }
            });
            fclose($keluar);
        }, 'log-audit-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Seperti paparan log: lajur sensitif tidak dieksport walaupun tersimpan dalam rekod lama */
    private static function json(mixed $data): string
    {
        if (! $data) {
            return '';
        }

        return json_encode(is_array($data) ? Arr::except($data, ['password', 'remember_token']) : $data, JSON_UNESCAPED_UNICODE);
    }

    private function queryDitapis(Request $request)
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
            $query->where('created_at', '>=', $request->dari.' 00:00:00');
        }
        if ($request->filled('hingga')) {
            $query->where('created_at', '<=', $request->hingga.' 23:59:59');
        }

        return $query;
    }
}
