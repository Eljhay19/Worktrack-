{{-- Passwordless access and verified email linking use the same compact reminder frame. --}}
<section class="overlay" id="profileAccessOverlay" hidden>
    <form class="reminder-settings-card profile-access-card is-email-step" id="profileAccessForm" role="dialog" aria-modal="true" aria-labelledby="profileAccessTitle">
        <div class="reminder-settings-head settings-head">
            <div>
                <h2 id="profileAccessTitle">Access Profile</h2>
                <p id="profileAccessSubtitle">Continue with your verified email.</p>
            </div>
            <button class="round-close" type="button" id="closeProfileAccessBtn" aria-label="Close profile access">&times;</button>
            <span class="settings-leaf" aria-hidden="true"></span>
        </div>
        <section class="reminder-step profile-access-step">
            <div class="reminder-field-group">
                <label for="profileAccessEmail">Email address</label>
                <input type="email" id="profileAccessEmail" autocomplete="email" maxlength="254" placeholder="you@example.com" required>
                <p class="reminder-helper" id="profileAccessMessage">We will send a code to securely access your profile.</p>
            </div>
            <p class="reminder-helper reminder-error" id="profileAccessError" aria-live="polite"></p>
            <button type="submit" class="reminder-primary-action" id="sendProfileAccessBtn">Send OTP</button>
            <div class="reminder-modal-actions">
                <button type="button" class="reminder-cancel-btn" id="backFromProfileAccessBtn">Back</button>
            </div>
        </section>
    </form>
</section>
