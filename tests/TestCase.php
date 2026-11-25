<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Arsip surat ditulis ke disk lokal; tes tidak boleh mengisi folder storage asli.
        Storage::fake('local');
    }
}
