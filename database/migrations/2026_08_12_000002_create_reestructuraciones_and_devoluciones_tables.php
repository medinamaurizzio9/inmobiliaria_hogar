<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reestructuraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->decimal('saldo_antes', 14, 2);
            $table->unsignedInteger('numero_cuotas_pendientes_antes');
            $table->unsignedInteger('plazo_anterior');
            $table->unsignedInteger('nuevo_plazo');
            $table->decimal('cuota_referencia', 14, 2)->nullable();
            $table->date('fecha');
            $table->date('fecha_primer_vencimiento');
            $table->foreignId('administrador_id')->constrained('users')->restrictOnDelete();
            $table->text('motivo');
            $table->text('observaciones')->nullable();
            $table->json('snapshot_antes');
            $table->json('snapshot_despues');
            $table->timestamps();
        });

        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->decimal('monto_pagado', 14, 2);
            $table->decimal('monto_devuelto', 14, 2);
            $table->decimal('monto_retenido_empresa', 14, 2);
            $table->text('motivo');
            $table->text('observaciones')->nullable();
            $table->foreignId('responsable_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha');
            $table->string('estado')->default('confirmada');
            $table->timestamps();
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('devolucion_id')->nullable()->after('installment_id')->constrained('devoluciones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('devolucion_id'));
        Schema::dropIfExists('devoluciones');
        Schema::dropIfExists('reestructuraciones');
    }
};
