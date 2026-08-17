<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('noticias', function (Blueprint $table): void {
            $table->boolean('destacada')->default(false)->index()->after('publicada');
            $table->unsignedInteger('orden')->nullable()->after('destacada');
        });
    }

    public function down(): void
    {
        Schema::table('noticias', function (Blueprint $table): void {
            $table->dropIndex(['destacada']);
            $table->dropColumn(['destacada', 'orden']);
        });
    }
};
