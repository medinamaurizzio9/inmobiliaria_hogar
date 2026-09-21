<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(RolesAndPermissionsSeeder::class)->run();
    }

    public function down(): void
    {
        // Structural authorization data is intentionally preserved on rollback.
    }
};
