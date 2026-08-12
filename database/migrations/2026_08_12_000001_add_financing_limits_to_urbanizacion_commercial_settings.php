<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urbanizacion_commercial_settings', function (Blueprint $table): void {
            $table->unsignedInteger('max_cuotas_semicontado')->default(36)->after('incremento_credito_valor');
            $table->unsignedInteger('max_cuotas_credito')->default(36)->after('max_cuotas_semicontado');
        });
    }

    public function down(): void
    {
        Schema::table('urbanizacion_commercial_settings', function (Blueprint $table): void {
            $table->dropColumn(['max_cuotas_semicontado', 'max_cuotas_credito']);
        });
    }
};
