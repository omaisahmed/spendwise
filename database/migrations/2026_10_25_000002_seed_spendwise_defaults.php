<?php

use Database\Seeders\SpendWiseDefaultsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new SpendWiseDefaultsSeeder)->run();
    }

    public function down(): void
    {
        // User data is intentionally preserved when rolling this migration back.
    }
};
