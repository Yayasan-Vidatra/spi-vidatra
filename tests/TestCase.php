<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Peran adalah data baku dari RoleSeeder, jadi diisi sekali saat basis data uji dimigrasi.
     */
    protected bool $seed = true;

    protected string $seeder = RoleSeeder::class;
}
