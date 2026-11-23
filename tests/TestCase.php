<?php

namespace Tests;

use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Peran, unit, templat folder, dan folder umum adalah data baku, jadi diisi sekali
     * saat basis data uji dimigrasi.
     */
    protected bool $seed = true;

    protected string $seeder = ReferenceDataSeeder::class;
}
