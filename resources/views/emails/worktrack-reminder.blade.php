<!doctype html>
<html lang="en">
<body style="margin:0;background:#edf5ee;color:#17251d;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:600px;margin:28px auto;padding:32px 28px;background:#fff;border:1px solid #dbe8dc;border-radius:18px;">
        <p style="margin:0 0 8px;color:#527a39;font-weight:700;letter-spacing:.08em;">WORKTRACK</p>
        <h1 style="margin:0 0 22px;font-size:25px;line-height:1.25;">Your scheduled reminder</h1>
        <p>Hi {{ $nickname }},</p>
        <div style="margin:20px 0;padding:16px;border-left:3px solid #6d914f;background:#f4f8f2;line-height:1.65;white-space:pre-wrap;">{{ $details !== '' ? $details : 'You have a scheduled reminder.' }}</div>
        <p style="line-height:1.6;"><strong>Scheduled for:</strong> {{ $scheduledFor ?: $date }}</p>
        @if ($scheduleLabel)<p><strong>Schedule:</strong> {{ $scheduleLabel }}</p>@endif
        <p style="margin:26px 0;text-align:center;"><a href="{{ $openUrl }}" style="display:inline-block;padding:13px 24px;border-radius:9px;background:#527a39;color:#fff;text-decoration:none;font-weight:700;">Open WorkTrack</a></p>
        <hr style="border:0;border-top:1px solid #dbe8dc;margin:24px 0;">
        <p style="color:#526174;font-size:13px;line-height:1.55;">You enabled this reminder in WorkTrack. Manage its schedule in Settings, or <a href="{{ $turnOffUrl }}" style="color:#527a39;">turn off email reminders</a>.</p>
    </div>
</body>
</html>
