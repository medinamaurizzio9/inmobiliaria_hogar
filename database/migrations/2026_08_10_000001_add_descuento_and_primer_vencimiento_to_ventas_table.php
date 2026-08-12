<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'descuento')) {
                $table->decimal('descuento', 14, 2)->default(0)->after('precio_final');
            }

            if (! Schema::hasColumn('ventas', 'descuento_autorizado_por')) {
                $table->foreignId('descuento_autorizado_por')
                    ->nullable()
                    ->after('descuento')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ventas', 'descuento_autorizado_en')) {
                $table->timestamp('descuento_autorizado_en')->nullable()->after('descuento_autorizado_por');
            }

            if (! Schema::hasColumn('ventas', 'fecha_primer_vencimiento')) {
                $table->date('fecha_primer_vencimiento')->nullable()->after('numero_cuotas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['descuento', 'descuento_autorizado_en', 'fecha_primer_vencimiento']);
            if (Schema::hasColumn('ventas', 'descuento_autorizado_por')) {
                $table->dropConstrainedForeignId('descuento_autorizado_por');
            }
        });
    }
};
