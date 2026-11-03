{{-- WORKTRACK Profile Setup: first-visit profile creation --}}
<section class="overlay" id="profileOverlay" hidden>
    <form class="setup-card profile-setup-card" id="profileForm">
        <div class="profile-visual-panel" aria-hidden="true">
            <img src="{{ asset('assets/images/profile-setup-visual.svg') }}?v={{ filemtime(public_path('assets/images/profile-setup-visual.svg')) }}" alt="">
        </div>

        <div class="profile-form-panel">
            <div class="profile-setup-intro">
                <h2>Welcome to WorkTrack</h2>
                <i aria-hidden="true"></i>
                <p>
                    <span>Complete your profile to get started</span>
                    <span>and keep track of your work hours.</span>
                </p>
            </div>

            <div class="profile-form-stack">
                <label>
                    <span>Email Address</span>
                    <div class="profile-input-field profile-input-email">
                        <span class="profile-input-icon" aria-hidden="true"></span>
                        <input type="email" id="profileEmail" autocomplete="email" maxlength="254" placeholder="you@example.com" required>
                    </div>
                </label>
                <label>
                    <span>Nickname</span>
                    <div class="profile-input-field profile-input-nickname">
                        <span class="profile-input-icon" aria-hidden="true"></span>
                        <input type="text" id="profileNickname" autocomplete="nickname" placeholder="Enter your nickname" required>
                    </div>
                </label>
                <label>
                    <span>Work Type</span>
                    <div class="profile-input-field profile-input-work">
                        <span class="profile-input-icon" aria-hidden="true"></span>
                        <input type="text" id="profileWorkType" autocomplete="off" placeholder="Enter your work type" required>
                    </div>
                </label>
                <label>
                    <span>Employee Status</span>
                    <div class="profile-input-field profile-input-status has-caret">
                        <span class="profile-input-icon" aria-hidden="true"></span>
                        <select id="profileEmployeeStatus" required>
                            <option value="full_time">Full Time</option>
                            <option value="part_time">Part Time</option>
                            <option value="rotational">Rotational</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                </label>
                <label>
                    <span>Hourly Rate</span>
                    <div class="profile-input-field profile-input-rate">
                        <span class="profile-input-icon" aria-hidden="true"></span>
                        <input type="number" id="profileHourlyRate" min="0" step="0.01" inputmode="decimal" placeholder="Enter your hourly rate" required>
                    </div>
                </label>
            </div>

            <div class="profile-actions">
                <button class="profile-save-btn" type="submit">
                    <span aria-hidden="true" class="profile-save-icon"></span>
                    Save Profile
                </button>
            </div>
            <button class="profile-access-link" type="button" id="openProfileAccessBtn">Already have a profile? Access here.</button>
        </div>
    </form>
</section>
