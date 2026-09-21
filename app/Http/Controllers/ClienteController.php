<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteRequest;
use App\Models\Asesor;
use App\Models\ClientCommercialEvent;
use App\Models\Cliente;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ClientCommercialEventService;
use App\Services\ClientCommercialProtectionService;
use App\Services\ClientMatchingService;
use App\Services\ClientRegistrationService;
use App\Services\DeletionDependencyService;
use App\Support\UrbanizacionContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        $urbanizacionId = UrbanizacionContext::currentId();
        $perPage = $this->perPage($request);
        $search = trim((string) $request->query('q', ''));
        $ventas = (string) $request->query('ventas', '');
        $sort = $this->sort($request);

        $query = UrbanizacionContext::clientes(Cliente::withCount('ventas')->with('createdBy'), $urbanizacionId);
        $this->applyVisibility($query, $request->user());

        if ($request->filled('usuario_id') && $this->canFilterAsesor($request->user())) {
            $query->where('created_by', $request->integer('usuario_id'));
        }

        $clientes = $query
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('nombre', 'like', "%{$search}%")
                        ->orWhere('documento', 'like', "%{$search}%")
                        ->orWhere('telefono', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($ventas === 'con_ventas', fn ($query) => $query->has('ventas'))
            ->when($ventas === 'sin_ventas', fn ($query) => $query->doesntHave('ventas'))
            ->when($sort['field'] === 'ventas', fn ($query) => $query->orderBy('ventas_count', $sort['direction']), fn ($query) => $query->orderBy($sort['field'], $sort['direction']))
            ->paginate($perPage)
            ->appends($request->query());

        return view('clientes.index', [
            'clientes' => $clientes,
            'asesores' => $this->asesoresForFilter($request->user()),
            'canFilterAsesor' => $this->canFilterAsesor($request->user()),
            'filters' => [
                'q' => $search,
                'ventas' => $ventas,
                'per_page' => $perPage,
                'usuario_id' => $request->integer('usuario_id') ?: null,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('clientes.form', [
            'cliente' => new Cliente,
            'loteId' => $request->integer('lote_id') ?: null,
        ]);
    }

    public function buscar(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $normalizedDocument = app(ClientMatchingService::class)->normalizeDocument($q);
        $query = Cliente::query();
        $visibleIds = $this->visibleCreatorIds($request->user());

        $clientes = $query
            ->where(function ($query) use ($q, $normalizedDocument, $visibleIds): void {
                $query->where(function ($visible) use ($q, $visibleIds): void {
                    UrbanizacionContext::clientes($visible);
                    if ($visibleIds !== null) {
                        $visible->whereIn('created_by', $visibleIds);
                    }
                    $visible->where(function ($fields) use ($q): void {
                        $fields->where('nombre', 'like', "%{$q}%")
                            ->orWhere('documento', 'like', "%{$q}%")
                            ->orWhere('telefono', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    });
                })->when($normalizedDocument !== null, fn ($nested) => $nested->orWhere('documento_normalizado', $normalizedDocument));
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id', 'nombre', 'documento', 'telefono', 'email']);

        return response()->json($clientes);
    }

    public function matches(Request $request, ClientMatchingService $matching): JsonResponse
    {
        $data = $request->validate([
            'documento' => ['nullable', 'string', 'max:80'],
            'telefono' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $exact = $matching->findExactClient($data['documento'] ?? null);
        $potential = $exact ? collect() : $matching->findPotentialMatches($data);

        return response()->json([
            'exact' => $exact ? $matching->summary($exact, $request->user()) : null,
            'potential' => $potential->map(fn (Cliente $cliente): array => $matching->summary($cliente, $request->user()))->values(),
        ]);
    }

    public function store(
        StoreClienteRequest $request,
        AuditService $auditService,
        ClientMatchingService $matching,
        ClientRegistrationService $registration,
        ClientCommercialEventService $events,
    ): RedirectResponse {
        $urbanizacionId = UrbanizacionContext::currentId();
        $data = $request->validated();
        $loteId = $request->integer('lote_id') ?: null;
        unset($data['lote_id']);
        unset($data['confirm_potential_match']);

        if ($duplicate = $matching->findExactClient($data['documento'] ?? null)) {
            $events->registerCommercialEvent($duplicate, ClientCommercialEvent::DUPLICATE_DOCUMENT, $request->user(), $urbanizacionId, 'document');

            return $this->duplicateResponse($request, $matching, $duplicate, $loteId);
        }

        $potentialMatches = $matching->findPotentialMatches($data);
        if ($potentialMatches->isNotEmpty() && ! $request->boolean('confirm_potential_match')) {
            foreach ($potentialMatches as $potential) {
                foreach ($potential->getAttribute('commercial_match_types') as $type) {
                    $events->registerCommercialEvent(
                        $potential,
                        $type === 'phone' ? ClientCommercialEvent::PHONE_MATCH : ClientCommercialEvent::EMAIL_MATCH,
                        $request->user(),
                        $urbanizacionId,
                        $type,
                    );
                }
            }

            return back()->withInput()->with('potential_client_matches', $potentialMatches
                ->map(fn (Cliente $cliente): array => $matching->summary($cliente, $request->user()))
                ->values()
                ->all());
        }

        $result = $registration->register($data, $request->user(), $urbanizacionId);
        if (! $result['created']) {
            $events->registerCommercialEvent($result['client'], ClientCommercialEvent::DUPLICATE_DOCUMENT, $request->user(), $urbanizacionId, 'document', ['race_prevented' => true]);

            return $this->duplicateResponse($request, $matching, $result['client'], $loteId);
        }

        $cliente = $result['client'];
        $auditService->log($cliente, 'crear_cliente', 'Cliente creado.', null, $cliente->toArray(), $request);

        return redirect()->route('clientes.index')->with('status', 'Cliente creado.');
    }

    public function show(Cliente $cliente, ClientCommercialProtectionService $protection, ClientMatchingService $matching): View
    {
        abort_unless(UrbanizacionContext::clienteBelongsToCurrent($cliente), 403, 'No tienes acceso a este cliente.');
        abort_unless($this->canSeeCliente($cliente, request()->user()), 403, 'No tienes acceso a este cliente.');

        $cliente->load('createdBy', 'responsibleAdvisor', 'urbanizacion', 'ventas.lote.manzano.urbanizacion', 'ventas.cuotas', 'reservas.lote.manzano.urbanizacion');
        $cliente->loadCount('commercialEvents');

        return view('clientes.show', [
            'cliente' => $cliente,
            'commercialProtection' => $protection->getCommercialProtection($cliente),
            'commercialSummary' => $matching->summary($cliente, request()->user()),
            'canSeeFinancial' => request()->user()->can('ver ventas') || request()->user()->can('cobrar cuotas'),
        ]);
    }

    public function edit(Cliente $cliente): View
    {
        abort_unless(UrbanizacionContext::clienteBelongsToCurrent($cliente), 403, 'No tienes acceso a este cliente.');
        abort_unless($this->canSeeCliente($cliente, request()->user()), 403, 'No tienes acceso a este cliente.');

        return view('clientes.form', compact('cliente'));
    }

    public function update(StoreClienteRequest $request, Cliente $cliente, AuditService $auditService, ClientMatchingService $matching): RedirectResponse
    {
        abort_unless(UrbanizacionContext::clienteBelongsToCurrent($cliente), 403, 'No tienes acceso a este cliente.');
        abort_unless($this->canSeeCliente($cliente, $request->user()), 403, 'No tienes acceso a este cliente.');

        $data = $request->validated();
        if ($matching->findExactClient($data['documento'] ?? null, $cliente->id)) {
            return back()->withInput()->withErrors(['documento' => 'Este CI pertenece a otra persona registrada.']);
        }
        $before = $cliente->toArray();
        $cliente->update([
            ...$data,
            'documento_normalizado' => $matching->normalizeDocument($data['documento'] ?? null),
            'telefono_normalizado' => $matching->normalizePhone($data['telefono'] ?? null),
            'email_normalizado' => $matching->normalizeEmail($data['email'] ?? null),
        ]);
        $auditService->log($cliente, 'editar_cliente', 'Cliente actualizado.', $before, $cliente->fresh()->toArray(), $request);

        return redirect()->route('clientes.index')->with('status', 'Cliente actualizado.');
    }

    public function destroy(Request $request, Cliente $cliente, AuditService $auditService, DeletionDependencyService $dependencyService): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['administrador', 'gerente']), 403, 'No tienes permiso para eliminar clientes.');
        abort_unless(UrbanizacionContext::clienteBelongsToCurrent($cliente), 403, 'No tienes acceso a este cliente.');
        abort_unless($this->canSeeCliente($cliente, $request->user()), 403, 'No tienes acceso a este cliente.');

        $dependencies = $dependencyService->forCliente($cliente);
        if ($dependencyService->hasDependencies($dependencies)) {
            return back()->withErrors([
                'delete' => $dependencyService->message('el cliente', $dependencies),
            ]);
        }

        $before = $cliente->toArray();
        $auditService->log($cliente, 'eliminar_cliente', 'Cliente eliminado.', $before, null, $request);
        $cliente->delete();

        return back()->with('status', 'Cliente eliminado.');
    }

    private function duplicateResponse(Request $request, ClientMatchingService $matching, Cliente $duplicate, ?int $loteId): RedirectResponse
    {
        return back()
            ->withInput()
            ->with('duplicate_cliente_id', $duplicate->id)
            ->with('duplicate_cliente_message', 'Esta persona ya está registrada y no será duplicada. Puede reutilizarla en una nueva reserva o venta.')
            ->with('duplicate_cliente_data', $matching->summary($duplicate, $request->user()))
            ->with('duplicate_lote_id', $loteId);
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', 50);

        return in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 50;
    }

    private function applyVisibility($query, User $user): void
    {
        $ids = $this->visibleCreatorIds($user);

        if ($ids !== null) {
            $query->whereIn('created_by', $ids);
        }
    }

    private function visibleCreatorIds(User $user): ?array
    {
        if ($user->hasAnyRole(['super administrador', 'administrador', 'gerente'])) {
            return null;
        }

        if ($user->hasRole('supervisor')) {
            $ids = Asesor::where('supervisor_id', $user->id)->pluck('user_id')->all();
            $ids[] = $user->id;

            return array_values(array_unique($ids));
        }

        if ($user->hasRole('vendedor')) {
            return [$user->id];
        }

        return [];
    }

    private function canSeeCliente(Cliente $cliente, User $user): bool
    {
        $ids = $this->visibleCreatorIds($user);

        return $ids === null
            || in_array((int) $cliente->created_by, $ids, true)
            || in_array((int) $cliente->responsible_user_id, $ids, true)
            || $cliente->ventas()->whereIn('user_id', $ids)->exists()
            || $cliente->reservas()->whereIn('usuario_id', $ids)->exists();
    }

    private function canFilterAsesor(User $user): bool
    {
        return $user->hasAnyRole(['super administrador', 'administrador', 'gerente']);
    }

    private function asesoresForFilter(User $user)
    {
        if (! $this->canFilterAsesor($user)) {
            return collect();
        }

        return User::whereHas('roles', fn ($query) => $query->whereIn('name', ['vendedor', 'supervisor']))
            ->orderBy('name')
            ->get();
    }

    private function sort(Request $request): array
    {
        $allowedSorts = ['nombre', 'documento', 'telefono', 'email', 'ventas'];
        $field = $request->query('sort', 'nombre');

        return [
            'field' => in_array($field, $allowedSorts, true) ? $field : 'nombre',
            'direction' => $request->query('direction') === 'desc' ? 'desc' : 'asc',
        ];
    }
}
