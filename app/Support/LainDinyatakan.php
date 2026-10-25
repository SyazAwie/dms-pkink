<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * Teks "Lain-lain (nyatakan)" (medan_lain.{id}) wajib diisi bila pilihan
 * Lain-lain dipilih dalam medan_data.{id}. Berfungsi untuk satu nilai
 * (dropdown/radio) dan senarai nilai (kotak semak), tidak seperti required_if.
 */
class LainDinyatakan implements DataAwareRule, ValidationRule
{
    /** Dijalankan walaupun medan_lain kosong / tiada dalam permintaan. */
    public bool $implicit = true;

    private array $data = [];

    public function __construct(
        private readonly int $idMedan,
        private readonly string $namaMedan,
    ) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $dipilih = Arr::wrap(Arr::get($this->data, "medan_data.{$this->idMedan}"));

        if (in_array(JenisMedan::NILAI_LAIN, $dipilih, true) && trim((string) $value) === '') {
            $fail("Sila nyatakan pilihan \"Lain-lain\" bagi {$this->namaMedan}.");
        }
    }
}
