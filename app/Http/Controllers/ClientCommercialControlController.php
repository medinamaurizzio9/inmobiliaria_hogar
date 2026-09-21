<?php

namespace App\Http\Controllers;

use App\Models\Asesor;
use App\Models\ClientCommercialEvent;
use App\Models\Cliente;
use App\Models\User;
use App\Services\ReservationVisibilityService;
use App\Support\UrbanizacionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientCommercialControlController extends Controller
{
    public function __invoke(Request $request, ReservationVisibilityService $visibility): View
    {
        abort_unless($request->user()->hasAnyRole(['administrador', 'gerente', 'supervisor']), 403);
        $urbanizacionId = UrbanizacionContext::currentId();
        $visibleIds = $visibility->visibleUserIds($request->user());
        $base = ClientCommercialEvent::query()->where('urbanizacion_id', $urbanizacionId);
        $this->applyScope($base, $visibleIds);

        $eventsQuery = (clone $base)->with([
            'cliente:id,nombre,documento,protection_expires_at',
            'originalAdvisor:id,name',
            'involvedAdvisor:id,name',
        ]);
        $this->applyFilters($eventsQuery, $request, $visibility);

        $clientBase = UrbanizacionContext::clientes(Cliente::query(), $urbanizacionId);
        if ($visibleIds !== null) {
            $clientBase->where(function (Builder $query) use ($visibleIds): void {
                $query->whereIn('created_by', $visibleIds)->orWhereIn('responsible_user_id', $visibleIds);
            });
        }

        return view('reportes.control-comercial-clientes', [
            'events' => $eventsQuery->latest()->paginate(50)->withQueryString(),
            'protectedClients' => (clone $clientBase)->where('protection_expires_at', '>', now())->count(),
            'expiredClients' => (clone $clientBase)->whereNotNull('protection_expires_at')->where('protection_expires_at', '<=', now())->count(),
            'matchesCount' => (clone $base)->whereIn('event_type', [ClientCommercialEvent::PHONE_MATCH, ClientCommercialEvent::EMAIL_MATCH])->count(),
            'duplicateAttempts' => (clone $base)->where('event_type', ClientCommercialEvent::DUPLICATE_DOCUMENT)->count(),
            'protectedOperations' => (clone $base)->where('event_type', ClientCommercialEvent::PROTECTED_OPERATION)->count(),
            'pendingReview' => (clone $base)->whereNull('reviewed_at')->whereIn('event_type', [ClientCommercialEvent::DUPLICATE_DOCUMENT, ClientCommercialEvent::PHONE_MATCH, ClientCommercialEvent::EMAIL_MATCH])->count(),
            'advisors' => $this->advisors($request, $visibility),
            'supervisors' => $request->user()->hasAnyRole(['administrador', 'gerente'])
                ? $this->supervisors()
                : collect(),
            'eventTypes' => [
                ClientCommercialEvent::DUPLICATE_DOCUMENT => 'Intento de CI duplicado',
                ClientCommercialEvent::PHONE_MATCH => 'Coincidencia de teléfono',
                ClientCommercialEvent::EMAIL_MATCH => 'Coincidencia de correo',
                ClientCommercialEvent::PROTECTED_OPERATION => 'Nueva operación protegida',
                ClientCommercialEvent::ADVISOR_REASSIGNED => 'Reasignación de asesor',
            ],
        ]);
    }

    private function applyScope(Builder $query, ?array $visibleIds): void
    {
        if ($visibleIds !== null) {
            $query->where(function (Builder $nested) use ($visibleIds): void {
                $nested->whereIn('original_advisor_id', $visibleIds)
                    ->orWhereIn('involved_advisor_id', $visibleIds);
            });
        }
    }

    private function applyFilters(Builder $query, Request $request, ReservationVisibilityService $visibility): void
    {
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->query('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->query('hasta'));
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->whereHas('cliente', fn (Builder $client) => $client
                ->where('nombre', 'like', "%{$term}%")
                ->orWhere('documento', 'like', "%{$term}%"));
        }
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->query('event_type'));
        }
        if ($request->filled('advisor_id') && $visibility->canFilterUser($request->user(), $request->integer('advisor_id'))) {
            $query->where(function (Builder $nested) use ($request): void {
                $nested->where('original_advisor_id', $request->integer('advisor_id'))
                    ->orWhere('involved_advisor_id', $request->integer('advisor_id'));
            });
        }
        if ($request->filled('supervisor_id') && $request->user()->hasAnyRole(['administrador', 'gerente'])) {
            $supervisorId = $request->integer('supervisor_id');
            if ($this->supervisors()->contains('id', $supervisorId)) {
                $teamIds = Asesor::where('supervisor_id', $supervisorId)->pluck('user_id')->push($supervisorId)->all();
                $query->where(function (Builder $nested) use ($teamIds): void {
                    $nested->whereIn('original_advisor_id', $teamIds)
                        ->orWhereIn('involved_advisor_id', $teamIds);
                });
            }
        }
        if ($request->query('protection') === 'protected') {
            $query->whereHas('cliente', fn (Builder $client) => $client->where('protection_expires_at', '>', now()));
        } elseif ($request->query('protection') === 'expired') {
            $query->whereHas('cliente', fn (Builder $client) => $client->where('protection_expires_at', '<=', now()));
        }
    }

    private function advisors(Request $request, ReservationVisibilityService $visibility)
    {
        $ids = $visibility->visibleUserIds($request->user());

        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['vendedor', 'supervisor']))
            ->when($ids !== null, fn (Builder $query) => $query->whereIn('id', $ids))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function supervisors()
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query
                ->where('name', 'supervisor')
                ->where('guard_name', 'web'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
