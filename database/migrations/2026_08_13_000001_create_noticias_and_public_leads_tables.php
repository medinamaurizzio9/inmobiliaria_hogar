<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noticias', function (Blueprint $table): void {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->text('resumen');
            $table->longText('contenido');
            $table->string('imagen')->nullable();
            $table->boolean('publicada')->default(false)->index();
            $table->timestamp('fecha_publicacion')->nullable()->index();
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('public_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('celular', 50);
            $table->string('email')->nullable();
            $table->foreignId('urbanizacion_id')->nullable()->constrained('urbanizaciones')->nullOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->text('mensaje');
            $table->string('origen')->default('web');
            $table->string('estado')->default('nuevo')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_leads');
        Schema::dropIfExists('noticias');
    }
};
