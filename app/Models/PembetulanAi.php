<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Pembetulan staf ke atas cadangan AI (P16): satu baris bagi setiap medan EAV yang AI
 * cuba isi semasa Imbas & Arkib/Hantar. `sama` = staf menyimpan nilai AI tanpa ubah.
 * Digunakan untuk (1) ketepatan setiap medan di Status Sistem dan (2) contoh few-shot
 * dalam prompt AI bagi kategori yang sama.
 */
class PembetulanAi extends Model
{
    protected $table = 'pembetulan_ai';

    protected $primaryKey = 'pembetulan_ai_id';

    public const UPDATED_AT = null;

    /** Bilangan maksimum contoh pembetulan dalam prompt (prompt pendek = pantas, num_ctx 4096). */
    public const HAD_CONTOH = 8;

    protected $fillable = ['dokumen_id', 'jenis_dokumen_id', 'dokumen_field_id', 'nilai_ai', 'nilai_disimpan', 'sama', 'model_ai', 'user_id'];

    protected $casts = ['sama' => 'boolean'];

    /** Rekod satu medan; tiada baris jika AI dan staf kedua-duanya kosong. */
    public static function rekod(Dokumen $dokumen, DokumenField $medan, ?string $nilaiAi, ?string $nilaiDisimpan, ?int $userId): void
    {
        if (self::normal($nilaiAi) === '' && self::normal($nilaiDisimpan) === '') {
            return;
        }

        static::create([
            'dokumen_id' => $dokumen->dokumen_id,
            'jenis_dokumen_id' => $dokumen->jenis_dokumen_id,
            'dokumen_field_id' => $medan->dokumen_field_id,
            'nilai_ai' => $nilaiAi,
            'nilai_disimpan' => $nilaiDisimpan,
            'sama' => self::normal($nilaiAi) === self::normal($nilaiDisimpan),
            'model_ai' => config('services.ollama.model'),
            'user_id' => $userId,
        ]);
    }

    /** Bandingan longgar: ruang & huruf besar/kecil diabaikan. */
    public static function normal(?string $nilai): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $nilai)));
    }

    /**
     * Pembetulan terkini (paling baharu dahulu) bagi medan kategori ini, satu bagi setiap
     * medan, untuk contoh few-shot.
     *
     * @param  array<int>  $idMedan
     * @return Collection<int, self>
     */
    public static function contohTerkini(int $jenisDokumenId, array $idMedan): Collection
    {
        if ($idMedan === []) {
            return collect();
        }

        return static::where('jenis_dokumen_id', $jenisDokumenId)->where('sama', false)
            ->whereIn('dokumen_field_id', $idMedan)
            ->latest('pembetulan_ai_id')->limit(self::HAD_CONTOH * 5)->get()
            ->unique('dokumen_field_id')->take(self::HAD_CONTOH)->values();
    }

    public function medan()
    {
        return $this->belongsTo(DokumenField::class, 'dokumen_field_id', 'dokumen_field_id')->withTrashed();
    }

    public function jenisDokumen()
    {
        return $this->belongsTo(JenisDokumen::class, 'jenis_dokumen_id', 'jenis_dokumen_id')->withTrashed();
    }
}
