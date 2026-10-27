<?php

namespace App\Http\Controllers;

use App\Models\Bahagian;
use App\Models\Dokumen;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tong Sampah (P3): senarai rekod yang di-soft delete dan pemulihannya. Tiada padam kekal
 * kerana ini sistem arkib. SUPERADMIN/ADMIN sahaja (laluan peranan:).
 */
class TongSampahController extends Controller
{
    /** jenis => [model, label, jadual audit] */
    private const JENIS = [
        'dokumen' => [Dokumen::class, 'Dokumen', 'dokumen'],
        'kategori' => [JenisDokumen::class, 'Kategori Dokumen', 'jenis_dokumen'],
        'bahagian' => [Bahagian::class, 'Bahagian', 'bahagian'],
        'kakitangan' => [User::class, 'Kakitangan', 'users'],
    ];

    public function index(Request $request)
    {
        $jenis = array_key_exists($request->query('jenis'), self::JENIS) ? $request->query('jenis') : 'dokumen';
        [$model, , $jadual] = self::JENIS[$jenis];

        $query = $model::onlyTrashed()->latest('deleted_at');
        if ($jenis === 'dokumen') {
            $query->with(['jenisDokumen', 'bahagian', 'pemuatNaik']);
        } elseif ($jenis === 'kakitangan') {
            $query->with(['bahagian', 'roles']);
        }
        $senarai = $query->paginate(20)->withQueryString();

        // Siapa memadam: log audit "Padam (Soft Delete)" terkini bagi setiap rekod
        $pemadam = LogAudit::with('pengguna')->where('nama_jadual', $jadual)
            ->where('tindakan', 'Padam (Soft Delete)')
            ->whereIn('rekod_id', $senarai->getCollection()->map->getKey())
            ->orderBy('log_audit_id')->get()->keyBy('rekod_id');

        $bilangan = collect(self::JENIS)->map(fn ($j) => $j[0]::onlyTrashed()->count());

        return view('tong_sampah.index', [
            'jenis' => $jenis,
            'senaraiJenis' => collect(self::JENIS)->map(fn ($j) => $j[1]),
            'bilangan' => $bilangan,
            'senarai' => $senarai,
            'pemadam' => $pemadam,
        ]);
    }

    public function pulih(string $jenis, int $id)
    {
        abort_unless(array_key_exists($jenis, self::JENIS), 404);
        [$model, $label, $jadual] = self::JENIS[$jenis];
        /** @var Model $rekod */
        $rekod = $model::onlyTrashed()->findOrFail($id);

        // Sama seperti UserController: ADMIN tidak boleh mengurus akaun Super Admin
        if ($rekod instanceof User && $rekod->roles->contains('kod_roles', 'SUPERADMIN') && ! Auth::user()->hasRole('SUPERADMIN')) {
            abort(403, 'Hanya Super Admin boleh memulihkan akaun Super Admin.');
        }

        try {
            DB::transaction(function () use ($rekod, $jadual) {
                $dipadamPada = $rekod->deleted_at;
                // Senyap: observer akan melog "Kemaskini"; log "Pulihkan" di bawah sudah mencukupi
                $rekod->restoreQuietly();

                // Medan kategori dipadam serentak dengan kategorinya (JenisDokumenObserver): pulihkan bersama
                if ($rekod instanceof JenisDokumen && $dipadamPada) {
                    DokumenField::onlyTrashed()->where('jenis_dokumen_id', $rekod->jenis_dokumen_id)
                        ->whereBetween('deleted_at', [$dipadamPada->copy()->subSeconds(5), $dipadamPada->copy()->addSeconds(5)])
                        ->update(['deleted_at' => null]);
                }

                LogAudit::create([
                    'user_id' => Auth::id(),
                    'tindakan' => 'Pulihkan',
                    'nama_jadual' => $jadual,
                    'rekod_id' => $rekod->getKey(),
                    'data_lama' => ['deleted_at' => $dipadamPada?->toDateTimeString()],
                    'data_baharu' => ['rekod' => self::namaRekod($rekod)],
                    'alamat_ip' => request()->ip(),
                    'peranti_pengguna' => request()->userAgent(),
                    'created_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            // Lajur janaan UNIQUE (kod/emel/No. KP aktif) menolak: rekod aktif lain sudah menggunakannya
            if (($e->errorInfo[1] ?? null) === 1062 || str_contains($e->getMessage(), 'UNIQUE')) {
                return back()->with('error', "{$label} \"".self::namaRekod($rekod).'" tidak dapat dipulihkan kerana kod, emel atau No. KP yang sama sudah digunakan oleh rekod aktif.');
            }
            throw $e;
        }

        return back()->with('success', "{$label} \"".self::namaRekod($rekod).'" telah dipulihkan.');
    }

    public static function namaRekod(Model $rekod): string
    {
        return match (true) {
            $rekod instanceof Dokumen => $rekod->no_rujukan,
            $rekod instanceof JenisDokumen => "{$rekod->kod_dokumen} - {$rekod->nama_dokumen}",
            $rekod instanceof Bahagian => "{$rekod->kod_bahagian} - {$rekod->nama_bahagian}",
            $rekod instanceof User => $rekod->nama_staff,
            default => (string) $rekod->getKey(),
        };
    }
}
