<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('urbanizacion_id')->nullable()->constrained('urbanizaciones')->nullOnDelete();
            $table->string('original_name');
            $table->string('file_hash', 64)->index();
            $table->string('status')->default('preview');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('existing_rows')->default(0);
            $table->unsignedInteger('match_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('client_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('client_import_batches')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status');
            $table->string('detail', 500)->nullable();
            $table->json('payload');
            $table->boolean('match_accepted')->default(false);
            $table->timestamps();
            $table->unique(['batch_id', 'row_number']);
        });

        $permission = Permission::firstOrCreate(['name' => 'importar clientes', 'guard_name' => 'web']);
        Role::where('guard_name', 'web')->whereIn('name', ['super administrador', 'administrador', 'gerente'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo($permission));
    }

    public function down(): void
    {
        Schema::dropIfExists('client_import_rows');
        Schema::dropIfExists('client_import_batches');
    }
};
