<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('foto')->nullable()->after('estado'));
        Schema::table('clientes', fn (Blueprint $table) => $table->string('foto')->nullable()->after('direccion'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('foto'));
        Schema::table('clientes', fn (Blueprint $table) => $table->dropColumn('foto'));
    }
};
