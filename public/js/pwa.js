if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(() => {
            // La aplicación web sigue funcionando aunque el navegador rechace el registro.
        });
    });
}
