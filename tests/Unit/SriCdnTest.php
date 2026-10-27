<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Setiap skrip/gaya dari CDN mesti ada Subresource Integrity (integrity + crossorigin),
 * supaya fail yang diubah di CDN ditolak oleh pelayar.
 */
class SriCdnTest extends TestCase
{
    public function test_semua_tag_cdn_ada_integrity(): void
    {
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/resources/views'));
        $tanpaSri = [];
        $jumlah = 0;

        foreach ($views as $fail) {
            if (! str_ends_with($fail->getFilename(), '.blade.php')) {
                continue;
            }
            preg_match_all('/<(?:link|script)\b[^>]*cdn\.jsdelivr\.net[^>]*>/i', file_get_contents($fail->getPathname()), $padanan);
            foreach ($padanan[0] as $tag) {
                $jumlah++;
                if (! str_contains($tag, 'integrity="sha384-') || ! str_contains($tag, 'crossorigin="anonymous"')) {
                    $tanpaSri[] = $fail->getFilename() . ': ' . $tag;
                }
            }
        }

        $this->assertGreaterThan(0, $jumlah);
        $this->assertSame([], $tanpaSri);
    }
}
