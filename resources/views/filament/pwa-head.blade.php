<link rel="manifest" href="/manifest.webmanifest?v=4">
<meta name="theme-color" content="#111827">
<meta name="color-scheme" content="light dark">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ZagaDogs">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="ZagaDogs">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180-v4.png">
<link rel="apple-touch-icon" sizes="192x192" href="/icons/icon-192-v4.png">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192-v4.png">
<link rel="stylesheet" href="/pwa.css?v=20">
<style>
    html,
    body {
        background-color: #f9fafb;
        overscroll-behavior: none;
    }

    html.dark,
    html.dark body {
        background-color: #111827;
    }

    body {
        touch-action: pan-x pan-y;
    }
</style>
<script>
    // Il colore della barra del telefono segue il tema scelto (chiaro/scuro) anche quando lo si cambia dal selettore.
    (function () {
        const meta = document.querySelector('meta[name="theme-color"]');
        const apply = () => meta && meta.setAttribute('content', document.documentElement.classList.contains('dark') ? '#111827' : '#ffffff');

        apply();
        new MutationObserver(apply).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    })();
</script>
<script>
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
        document.documentElement.classList.add('is-pwa-standalone');
    }

    const viewport = document.querySelector('meta[name="viewport"]');
    if (viewport) {
        viewport.setAttribute(
            'content',
            'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover'
        );
    }
</script>
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js?v=16').catch(() => {});
        });
    }
</script>
