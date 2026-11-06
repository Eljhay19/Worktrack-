<!doctype html>
<html lang="en" data-theme="light" data-app-state="loading">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="worktrack-service-worker" content="{{ asset('worktrack-sw.js') }}">
    <title>WORKTRACK</title>
    <style>
        [hidden] { display: none !important; }
        html:not([data-app-state="ready"]) body > :not(#appLoadError):not(script):not(noscript),
        body[data-app-booting="true"] > :not(#appLoadError):not(script):not(noscript) { visibility: hidden !important; opacity: 0 !important; }
        #appLoadError { position: fixed; inset: 0; z-index: 200; display: grid; place-content: center; justify-items: center; gap: 12px; padding: 24px; color: #26392d; background: #edf5ee; font: 500 13px/1.5 system-ui, sans-serif; text-align: center; }
        #appBootCover { position: fixed; inset: 0; z-index: 1000; background: #edf5ee; }
        [data-theme="dark"] #appBootCover { background: #111714; }
        [data-theme="dark"] #appLoadError { color: #eef3ef; background: #111714; }
        #appLoadError button { padding: 8px 20px; border: 1px solid currentColor; border-radius: 8px; color: inherit; background: transparent; font: inherit; cursor: pointer; }
    </style>
    <script>
        try {
            document.documentElement.dataset.theme = localStorage.getItem('worktrack_theme') === 'dark' ? 'dark' : 'light';
        } catch (_) {}
        function markAppLoading() {
            document.documentElement.dataset.appState = 'loading';
            if (document.body) document.body.dataset.appBooting = 'true';
        }
        window.addEventListener('beforeunload', markAppLoading);
        window.addEventListener('pagehide', markAppLoading);
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Libre+Baskerville:wght@700&family=Plus+Jakarta+Sans:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/worktrack.css') }}?v={{ filemtime(public_path('assets/css/worktrack.css')) }}">
</head>
<body data-app-booting="true">
    <section id="appLoadError" aria-live="assertive" aria-label="WorkTrack loading error" hidden>
        <p id="appLoadErrorMessage">WorkTrack could not load. Check your connection and try again.</p>
        <button type="button" id="retryAppLoadBtn">Try again</button>
    </section>
    <div id="appBootCover" aria-hidden="true" style="position:fixed;top:0;right:0;bottom:0;left:0;z-index:1000;background:#edf5ee;"></div>
    <noscript><style>html:not([data-app-state="ready"]) body > noscript { visibility: visible !important; }</style><p>Enable JavaScript to open WorkTrack.</p></noscript>
    <div class="app-shell" data-app aria-busy="true" inert hidden>
        @include('worktrack.partials.header')
        @include('worktrack.partials.dashboard')
    </div>

    @include('worktrack.partials.profile-setup')
    @include('worktrack.partials.access-modal')
    @include('worktrack.partials.otp-modal')
    @include('worktrack.partials.daily-records')
    @include('worktrack.partials.entry-modal')
    @include('worktrack.partials.settings-modal')
    @include('worktrack.partials.time-picker')
    @include('worktrack.partials.reset-confirmation')

    <div class="toast" id="toast" role="status" aria-live="polite" aria-atomic="true" hidden><span id="toastMessage"></span></div>

    <script src="{{ asset('assets/js/worktrack.js') }}?v={{ filemtime(public_path('assets/js/worktrack.js')) }}"></script>
</body>
</html>
