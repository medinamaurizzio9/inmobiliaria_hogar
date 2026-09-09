<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ !empty($systemSettings['primary_color']) ? $systemSettings['primary_color'] : '#879A32' }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Hogar">
<link rel="apple-touch-icon" sizes="192x192" href="{{ asset('pwa/icon-192.png') }}">
