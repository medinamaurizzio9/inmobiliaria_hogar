<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urbanizacion_public_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('urbanizacion_id')->unique()->constrained('urbanizaciones')->cascadeOnDelete();
            $table->string('hero_image')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('info_title')->nullable();
            $table->timestamps();
        });

        Schema::create('urbanizacion_public_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('urbanizacion_id')->constrained('urbanizaciones')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descripcion');
            $table->unsignedTinyInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['urbanizacion_id', 'activo', 'orden'], 'urbanizacion_public_features_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urbanizacion_public_features');
        Schema::dropIfExists('urbanizacion_public_settings');
    }
};
