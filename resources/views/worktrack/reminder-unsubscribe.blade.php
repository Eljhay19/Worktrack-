<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email reminders | WORKTRACK</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:#edf5ee;color:#17251d;font:16px/1.5 system-ui,sans-serif}
        main{width:min(100%,440px);box-sizing:border-box;padding:28px;border:1px solid #dbe8dc;border-radius:18px;background:#fff;box-shadow:0 18px 48px #1f30491f}
        h1{margin:0 0 10px;font-size:24px}p{color:#526174}button{width:100%;min-height:46px;border:0;border-radius:9px;background:#527a39;color:#fff;font-weight:700;font-size:15px;cursor:pointer}
    </style>
</head>
<body>
<main>
    <h1>{{ $disabled ? 'Reminders turned off' : 'Turn off email reminders?' }}</h1>
    @if($disabled)
        <p>Your WORKTRACK email reminders are now disabled. You can turn them on again in Settings.</p>
        <a href="{{ route('worktrack.index') }}">Open WorkTrack</a>
    @else
        <p>Confirm to stop future WORKTRACK reminder emails for this browser profile.</p>
        <form method="post" action="{{ request()->fullUrl() }}">
            @csrf
            <button type="submit">Turn off email reminders</button>
        </form>
    @endif
</main>
</body>
</html>
