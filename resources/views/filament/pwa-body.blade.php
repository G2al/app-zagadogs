<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        });
    }

    // Disable pinch + double-tap zoom for a more app-like feel
    document.addEventListener('gesturestart', (event) => event.preventDefault());
    document.addEventListener('gesturechange', (event) => event.preventDefault());
    document.addEventListener('gestureend', (event) => event.preventDefault());

    let lastTouchEnd = 0;
    document.addEventListener(
        'touchend',
        (event) => {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        },
        { passive: false }
    );
</script>
