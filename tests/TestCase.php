<?php

namespace Tests;

use App\Support\AppSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        AppSettings::forget();
    }

    protected function tearDown(): void
    {
        AppSettings::forget();

        parent::tearDown();
    }
}
