<!doctype html>
<html lang="en">
<body style="margin:0;background:#edf5ee;color:#17251d;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:520px;margin:28px auto;padding:30px 26px;background:#fff;border:1px solid #dbe8dc;border-radius:18px;">
        <p style="margin:0 0 8px;color:#527a39;font-weight:700;letter-spacing:.08em;">WORKTRACK</p>
        <h1 style="margin:0 0 16px;font-size:24px;">{{ $purpose === 'login' ? 'Verify to access your profile' : ($purpose === 'email_change' ? 'Verify your new email' : 'Verify your email') }}</h1>
        <p style="color:#526174;line-height:1.55;">Enter this code in the WORKTRACK verification window. It expires in 5 minutes and can only be used once. Do not share it with anyone.</p>
        <p style="margin:24px 0;padding:18px;border-radius:10px;background:#f2f7ef;text-align:center;font-size:30px;font-weight:800;letter-spacing:.2em;">{{ $code }}</p>
        <p style="margin:0;color:#526174;font-size:13px;">If you did not request this code, ignore this email. Your profile has not been changed.</p>
    </div>
</body>
</html>
