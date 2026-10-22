<?php

namespace Tests\Unit;

use App\Support\TeksEmel;
use PHPUnit\Framework\TestCase;

class TeksEmelTest extends TestCase
{
    public function test_aksara_markdown_di_escape(): void
    {
        $this->assertSame('\[Klik\]\(https://evil\.test\)', TeksEmel::selamat('[Klik](https://evil.test)'));
        $this->assertSame('\# tajuk', TeksEmel::selamat('# tajuk'));
        $this->assertSame('\*\*tebal\*\* \_condong\_', TeksEmel::selamat('**tebal** _condong_'));
        $this->assertSame('\=\=\=', TeksEmel::selamat('==='));
        $this->assertSame('a\\\\b', TeksEmel::selamat('a\\b'));
    }

    public function test_aksara_yang_diurus_blade_tidak_disentuh(): void
    {
        // ' & < > " ditukar kepada entiti oleh Blade; backslash akan merosakkan paparan
        $this->assertSame("O'Brien", TeksEmel::selamat("O'Brien"));
        $this->assertSame('A & B <x> "y"', TeksEmel::selamat('A & B <x> "y"'));
        $this->assertSame('Siti a/p Muthu', TeksEmel::selamat('Siti a/p Muthu'));
        $this->assertSame('Lee @ Ali', TeksEmel::selamat('Lee @ Ali'));
        $this->assertSame('', TeksEmel::selamat(null));
    }
}
