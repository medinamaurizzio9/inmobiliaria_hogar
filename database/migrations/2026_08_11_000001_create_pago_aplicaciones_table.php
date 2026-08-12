<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_movement_id')->constrained('cash_movements')->cascadeOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas')->cascadeOnDelete();
            $table->decimal('monto_aplicado', 14, 2);
            $table->timestamps();

            $table->index('cash_movement_id');
            $table->index('cuota_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_aplicaciones');
    }
};
