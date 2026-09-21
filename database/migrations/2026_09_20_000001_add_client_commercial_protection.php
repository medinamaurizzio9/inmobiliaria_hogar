<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urbanizacion_commercial_settings', function (Blueprint $table): void {
            $table->unsignedInteger('proteccion_cliente_dias')->default(30)->after('reserva_dias_habiles_asesor');
        });

        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('documento_normalizado', 80)->nullable()->after('documento');
            $table->string('telefono_normalizado', 32)->nullable()->after('telefono');
            $table->string('email_normalizado')->nullable()->after('email');
            $table->foreignId('responsible_user_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('protected_at')->nullable()->after('responsible_user_id');
            $table->timestamp('protection_expires_at')->nullable()->after('protected_at');
            $table->index('telefono_normalizado', 'clientes_telefono_normalizado_index');
            $table->index('email_normalizado', 'clientes_email_normalizado_index');
        });

        $this->backfillClientProtection();

        Schema::table('clientes', function (Blueprint $table): void {
            $table->unique('documento_normalizado', 'clientes_documento_normalizado_unique');
        });

        Schema::create('client_commercial_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('urbanizacion_id')->nullable()->constrained('urbanizaciones')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('original_advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('involved_advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('match_type', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['urbanizacion_id', 'event_type', 'created_at'], 'client_commercial_events_report_index');
            $table->index(['involved_advisor_id', 'created_at'], 'client_commercial_events_advisor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_commercial_events');

        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropUnique('clientes_documento_normalizado_unique');
            $table->dropIndex('clientes_telefono_normalizado_index');
            $table->dropIndex('clientes_email_normalizado_index');
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn([
                'documento_normalizado',
                'telefono_normalizado',
                'email_normalizado',
                'protected_at',
                'protection_expires_at',
            ]);
        });

        Schema::table('urbanizacion_commercial_settings', function (Blueprint $table): void {
            $table->dropColumn('proteccion_cliente_dias');
        });
    }

    private function backfillClientProtection(): void
    {
        $clients = DB::table('clientes')
            ->select(['id', 'urbanizacion_id', 'created_by', 'documento', 'telefono', 'email', 'created_at'])
            ->orderBy('id')
            ->get();
        $normalizedDocuments = $clients
            ->mapWithKeys(fn (object $client): array => [$client->id => $this->normalizeDocument($client->documento)])
            ->filter();
        $documentCounts = $normalizedDocuments->countBy();
        $protectionDays = DB::table('urbanizacion_commercial_settings')->pluck('proteccion_cliente_dias', 'urbanizacion_id');

        foreach ($clients as $client) {
            $normalizedDocument = $normalizedDocuments[$client->id] ?? null;
            $protectedAt = $client->created_at ? Carbon::parse($client->created_at) : now();
            $days = max(1, (int) ($protectionDays[$client->urbanizacion_id] ?? 30));

            DB::table('clientes')->where('id', $client->id)->update([
                // Los grupos históricos ambiguos quedan nulos para no fusionar ni bloquear el despliegue.
                'documento_normalizado' => $normalizedDocument && ($documentCounts[$normalizedDocument] ?? 0) === 1
                    ? $normalizedDocument
                    : null,
                'telefono_normalizado' => $this->normalizePhone($client->telefono),
                'email_normalizado' => $this->normalizeEmail($client->email),
                'responsible_user_id' => $client->created_by,
                'protected_at' => $protectedAt,
                'protection_expires_at' => $protectedAt->copy()->addDays($days),
            ]);
        }
    }

    private function normalizeDocument(?string $value): ?string
    {
        $normalized = preg_replace('/[^\pL\pN]/u', '', mb_strtoupper(trim((string) $value)));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) === 11 && str_starts_with($digits, '591')) {
            $digits = substr($digits, 3);
        }

        return $digits !== '' ? $digits : null;
    }

    private function normalizeEmail(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }
};
