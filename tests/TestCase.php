<?php

namespace Tests;

use App\Support\KeadaanKeselamatan;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private string $failKeadaan;

    protected function setUp(): void
    {
        parent::setUp();

        // Keadaan kecemasan (mod kecemasan, log keluar paksa) ujian dalam fail sementara,
        // supaya ujian tidak pernah menulis storage/app/private/keselamatan.json sebenar
        $this->failKeadaan = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dms_keselamatan_'.uniqid().'.json';
        config(['services.keselamatan.fail_keadaan' => $this->failKeadaan]);
        KeadaanKeselamatan::kosongkanCache();
    }

    protected function tearDown(): void
    {
        @unlink($this->failKeadaan);
        KeadaanKeselamatan::kosongkanCache();
        parent::tearDown();
    }
}
