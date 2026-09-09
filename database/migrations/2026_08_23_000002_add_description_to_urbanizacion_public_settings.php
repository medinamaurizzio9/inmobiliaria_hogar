<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urbanizacion_public_settings', function (Blueprint $table): void {
            $table->string('titulo_descripcion')->nullable()->after('youtube_url');
            $table->text('descripcion_principal')->nullable()->after('titulo_descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('urbanizacion_public_settings', function (Blueprint $table): void {
            $table->dropColumn(['titulo_descripcion', 'descripcion_principal']);
        });
    }
};
