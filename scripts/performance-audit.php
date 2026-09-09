<?php

use App\Models\Noticia;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

config(['session.driver' => 'array']);

$queries = [];
DB::listen(function (QueryExecuted $query) use (&$queries): void {
    $queries[] = [
        'sql' => $query->sql,
        'time_ms' => round($query->time, 3),
    ];
});

$urbanizacion = Urbanizacion::query()->where('estado', 'activa')->orderBy('id')->first();
$admin = User::role(['administrador', 'gerente'])->where('estado', 'activo')->first();
$supervisor = User::role('supervisor')->where('estado', 'activo')->first();
$asesor = User::role('vendedor')->where('estado', 'activo')->first();

$routes = [
    ['public.home', '/', null],
    ['public.availability', '/disponibilidad', null],
];

if ($urbanizacion?->slug) {
    $routes[] = ['public.urbanization', '/u/'.$urbanizacion->slug, null];
}

$noticia = Noticia::query()->where('publicada', true)->orderBy('id')->first();
if ($noticia?->slug) {
    $routes[] = ['public.news', '/noticias/'.$noticia->slug, null];
}

if ($admin) {
    foreach ([
        ['admin.dashboard', '/dashboard'],
        ['admin.urbanizations', '/urbanizaciones'],
        ['admin.lots', '/lotes'],
        ['admin.map', '/mapa'],
        ['admin.clients', '/clientes'],
        ['admin.reservations', '/reservas'],
        ['admin.sales', '/ventas'],
        ['admin.collections', '/cobranza'],
        ['admin.reports', '/reportes'],
        ['admin.report.lots', '/reportes/lotes-estado'],
        ['admin.report.reservations', '/reportes/reservas'],
        ['admin.report.installments', '/reportes/cuotas'],
        ['admin.report.income', '/reportes/ingresos'],
        ['admin.news', '/administracion/noticias'],
    ] as [$name, $uri]) {
        $routes[] = [$name, $uri, $admin];
    }
}

if ($supervisor) {
    foreach ([
        ['supervisor.dashboard', '/dashboard'],
        ['supervisor.team', '/asesores'],
        ['supervisor.reservations', '/reservas'],
        ['supervisor.clients', '/clientes'],
    ] as [$name, $uri]) {
        $routes[] = [$name, $uri, $supervisor];
    }
}

if ($asesor) {
    foreach ([
        ['advisor.dashboard', '/dashboard'],
        ['advisor.availability', '/disponibilidad'],
        ['advisor.map', '/mapa'],
        ['advisor.reservations', '/reservas'],
        ['advisor.clients', '/clientes'],
    ] as [$name, $uri]) {
        $routes[] = [$name, $uri, $asesor];
    }
}

$kernel = $app->make(HttpKernel::class);
$results = [];

foreach ($routes as [$name, $uri, $user]) {
    $startQuery = count($queries);
    $memoryBefore = memory_get_usage(true);
    $startedAt = hrtime(true);

    if ($user) {
        Auth::guard()->setUser($user);
    } else {
        Auth::guard()->forgetUser();
    }
    $session = app('session')->driver();
    $session->put('urbanizacion_id', $urbanizacion?->id);

    $request = Request::create($uri, 'GET');
    $request->setLaravelSession($session);
    $response = $kernel->handle($request);
    $content = $response->getContent();

    $elapsedMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);
    $routeQueries = array_slice($queries, $startQuery);
    $normalized = array_map(
        fn (array $query): string => preg_replace('/\s+/', ' ', strtolower(trim($query['sql']))),
        $routeQueries,
    );
    $frequencies = array_count_values($normalized);
    $duplicates = array_sum(array_map(fn (int $count): int => max(0, $count - 1), $frequencies));
    $slowest = collect($routeQueries)->sortByDesc('time_ms')->first();

    $results[] = [
        'route' => $name,
        'status' => $response->getStatusCode(),
        'time_ms' => $elapsedMs,
        'queries' => count($routeQueries),
        'duplicates' => $duplicates,
        'sql_ms' => round(array_sum(array_column($routeQueries, 'time_ms')), 2),
        'slowest_ms' => $slowest['time_ms'] ?? 0,
        'memory_kb' => round(max(0, memory_get_usage(true) - $memoryBefore) / 1024),
        'html_kb' => round(strlen($content) / 1024, 1),
    ];

    $kernel->terminate($request, $response);
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
