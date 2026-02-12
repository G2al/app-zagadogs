<link rel="manifest" href="/manifest.webmanifest?v=4">
<meta name="theme-color" content="#111827">
<meta name="color-scheme" content="dark">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ZagaDogs">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="ZagaDogs">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180-v4.png">
<link rel="apple-touch-icon" sizes="192x192" href="/icons/icon-192-v4.png">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192-v4.png">
<link rel="stylesheet" href="/pwa.css?v=18">
<style>
    html,
    body {
        background-color: #111827;
        overscroll-behavior: none;
    }

    body {
        touch-action: pan-x pan-y;
    }
</style>
<script>
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
            navigator.serviceWorker.register('/sw.js?v=14').catch(() => {});
        });
    }
</script>
