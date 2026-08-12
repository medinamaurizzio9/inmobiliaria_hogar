<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_movements', 'banco')) {
                $table->string('banco')->nullable()->after('referencia');
            }

            if (! Schema::hasColumn('cash_movements', 'motivo_rechazo')) {
                $table->text('motivo_rechazo')->nullable()->after('banco');
            }

            if (! Schema::hasColumn('cash_movements', 'confirmado_por')) {
                $table->foreignId('confirmado_por')
                    ->nullable()
                    ->after('motivo_rechazo')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('cash_movements', 'confirmado_en')) {
                $table->timestamp('confirmado_en')->nullable()->after('confirmado_por');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropColumn(['banco', 'motivo_rechazo', 'confirmado_en']);
            if (Schema::hasColumn('cash_movements', 'confirmado_por')) {
                $table->dropConstrainedForeignId('confirmado_por');
            }
        });
    }
};
