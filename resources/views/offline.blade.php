<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#879A32">
    <title>Sin conexión — Hogar Inmobiliaria</title>
    <style>
        *{box-sizing:border-box}body{margin:0;display:grid;place-items:center;min-height:100vh;padding:24px;color:#20251f;background:#f4f5ef;font-family:Inter,system-ui,sans-serif}.offline-card{width:min(100%,430px);padding:32px;border:1px solid rgba(13,15,13,.1);border-radius:18px;background:#fff;box-shadow:0 18px 50px rgba(13,15,13,.1);text-align:center}.offline-card img{width:88px;height:88px;object-fit:contain}.offline-card h1{margin:20px 0 10px;font-size:30px}.offline-card p{margin:0;color:#737a70;line-height:1.6}.offline-card button{width:100%;min-height:46px;margin-top:24px;border:0;border-radius:10px;color:#fff;background:#879A32;font:inherit;font-weight:700;cursor:pointer}
    </style>
</head>
<body>
<main class="offline-card">
    <img src="{{ asset('pwa/icon-192.png') }}" alt="Hogar Inmobiliaria">
    <h1>Sin conexión</h1>
    <p>Algunas funciones requieren internet. Comprueba tu conexión y vuelve a intentarlo.</p>
    <button type="button" onclick="window.location.reload()">Reintentar</button>
</main>
</body>
</html>
