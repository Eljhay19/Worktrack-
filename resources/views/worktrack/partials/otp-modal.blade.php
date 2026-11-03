{{-- One OTP dialog reused for registration, access, email changes, and reminder verification. --}}
<section class="overlay reminder-verify-overlay" id="reminderVerifyOverlay" hidden>
    <form class="reminder-settings-card reminder-verify-card" id="reminderVerifyForm" role="dialog" aria-modal="true" aria-labelledby="reminderVerifyTitle" novalidate>
        <div class="reminder-settings-head settings-head">
            <div>
                <h2 id="reminderVerifyTitle">Verify Email</h2>
            </div>
            <button class="round-close" type="button" id="closeReminderVerifyBtn" aria-label="Close email verification">&times;</button>
            <span class="settings-leaf" aria-hidden="true"></span>
        </div>
        <section class="reminder-step reminder-verify-step" id="reminderVerifyStep" aria-labelledby="reminderVerifyTitle">
            <p class="reminder-step-copy">Enter the 6-digit code sent to <strong id="reminderCodeEmail"></strong></p>
            <input type="text" id="reminderCode" class="visually-hidden-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" aria-label="6-digit verification code">
            <div class="reminder-otp-inputs" role="group" aria-label="Six digit verification code">
                @for ($digit = 1; $digit <= 6; $digit++)
                    <input type="text" inputmode="numeric" maxlength="1" autocomplete="{{ $digit === 1 ? 'one-time-code' : 'off' }}" aria-label="Code digit {{ $digit }}" data-otp-index="{{ $digit - 1 }}">
                @endfor
            </div>
            <div class="reminder-code-meta">
                <p class="reminder-helper" id="reminderCodeExpiry">Time left 05:00</p>
                <p class="reminder-helper" id="reminderResendCountdown" hidden>Resend in 01:00</p>
                <button type="button" class="reminder-text-action" id="resendReminderCodeBtn" hidden>Resend code</button>
            </div>
            <p class="reminder-helper reminder-error" id="reminderCodeError" aria-live="polite"></p>
            <div class="reminder-modal-actions">
                <button type="button" class="reminder-cancel-btn" id="backToReminderEmailBtn">Back</button>
                <button type="submit" class="reminder-save-btn" id="verifyReminderCodeBtn">Verify Email</button>
            </div>
        </section>
    </form>
</section>
