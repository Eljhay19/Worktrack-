{{-- WORKTRACK Settings: editable profile details and daily reminder time --}}
<section class="overlay" id="settingsOverlay" hidden>
    <form class="settings-card" id="settingsForm">
        <div class="modal-head settings-head">
            <div>
                <h2>Settings</h2>
                <p id="settingsSubtitle">Profile and reminder</p>
            </div>
            <button class="round-close" type="button" id="closeSettingsBtn" aria-label="Close settings">&times;</button>
            <span class="settings-leaf" aria-hidden="true"></span>
        </div>

        <div class="form-grid">
            <label class="settings-field settings-nickname">
                <span>Nickname</span>
                <div class="settings-input-wrap">
                    <i aria-hidden="true"></i>
                    <input type="text" id="settingsNickname" required>
                </div>
            </label>
            <label class="settings-field settings-work">
                <span>Work Type</span>
                <div class="settings-input-wrap">
                    <i aria-hidden="true"></i>
                    <input type="text" id="settingsWorkType" required>
                </div>
            </label>
            <label class="settings-field settings-status">
                <span>Employee Status</span>
                <div class="settings-input-wrap has-caret">
                    <i aria-hidden="true"></i>
                    <select id="settingsEmployeeStatus" required>
                        <option value="full_time">Full Time</option>
                        <option value="part_time">Part Time</option>
                        <option value="rotational">Rotational</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
            </label>
            <label class="settings-field settings-rate">
                <span>Hourly Rate</span>
                <div class="settings-input-wrap">
                    <i aria-hidden="true"></i>
                    <input type="number" id="settingsHourlyRate" min="0" step="0.01" required>
                </div>
            </label>
            <label class="settings-field settings-regular">
                <span>Regular Hours</span>
                <div class="settings-input-wrap">
                    <i aria-hidden="true"></i>
                    <input type="number" id="settingsRegularHours" min="1" max="24" step="0.25" inputmode="decimal" required>
                </div>
            </label>
        </div>

        <div class="settings-section-divider" aria-hidden="true"></div>

        <div class="settings-account-row">
            <span class="settings-account-label">Email address</span>
            <div class="settings-input-wrap settings-email-display" aria-label="Verified email address">
                <i aria-hidden="true"></i>
                <span id="settingsAccountEmail"></span>
            </div>
            <div class="settings-email-actions">
                <span class="settings-email-verified" id="settingsEmailVerified" hidden><i aria-hidden="true">✓</i> Verified</span>
                <button type="button" class="reminder-text-action" id="settingsEmailAccessBtn">Link Email / Enable Device Access</button>
            </div>
        </div>

        <div class="settings-section-divider" aria-hidden="true"></div>

        <div class="reminder-row">
            <button type="button" class="reminder-open-btn" id="openReminderSettingsBtn" aria-haspopup="dialog" aria-controls="reminderSettingsOverlay">
                <span><strong>Daily Reminder</strong><small id="reminderSummary">Set an email reminder schedule</small></span>
                <span class="reminder-open-arrow" aria-hidden="true">›</span>
            </button>
        </div>

        <div class="modal-actions settings-profile-actions">
            <button class="reminder-text-action settings-logout-btn" type="button" id="logoutProfileBtn" hidden>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 5H5v14h5M10 12h11m-4-4 4 4-4 4" /></svg>
                <span>Logout</span>
            </button>
            <button class="primary-btn settings-save-btn" id="settingsEditSaveBtn" type="button" aria-label="Edit profile settings">
                Edit
            </button>
        </div>
    </form>
</section>

<section class="overlay reminder-settings-overlay" id="reminderSettingsOverlay" hidden>
    <form class="reminder-settings-card" id="reminderForm" role="dialog" aria-modal="true" aria-labelledby="reminderTitle" novalidate>
        <div class="reminder-settings-head">
            <div>
                <h2 id="reminderTitle">Email Reminder</h2>
                <p id="reminderStepSubtitle">Get reminders at your preferred time.</p>
            </div>
            <button class="round-close" type="button" id="closeReminderSettingsBtn" aria-label="Close daily reminder settings">&times;</button>
            <span class="settings-leaf" aria-hidden="true"></span>
        </div>

        <section class="reminder-step" id="reminderEmailStep" aria-labelledby="reminderTitle">
            <div class="reminder-field-group">
                <label for="reminderEmail">Email address</label>
                <input type="email" id="reminderEmail" autocomplete="email" maxlength="254" placeholder="you@example.com">
                <p class="reminder-helper" id="reminderEmailStatus" aria-live="polite">Verify your email to activate reminders.</p>
            </div>
            <button type="submit" class="reminder-primary-action" id="sendReminderCodeBtn">Send OTP</button>
            <div class="reminder-modal-actions">
                <button type="button" class="reminder-cancel-btn" id="cancelReminderSettingsBtn">Cancel</button>
            </div>
        </section>

        <section class="reminder-step reminder-main-step" id="reminderMainStep" aria-labelledby="reminderTitle" hidden>
            <label class="reminder-enable-row" for="reminderEnabled">
                <span>Enable email reminder</span>
                <input type="checkbox" id="reminderEnabled">
                <b aria-hidden="true"></b>
            </label>

            <div class="reminder-field-group">
                <label for="reminderVerifiedAddress">Email address</label>
                <div class="reminder-verified-email-row">
                    <div class="settings-input-wrap settings-email reminder-profile-email">
                        <i aria-hidden="true"></i>
                        <input type="text" id="reminderVerifiedAddress" readonly aria-label="Verified email address">
                    </div>
                    <div class="reminder-verified-email-meta">
                        <span class="reminder-verified-badge" id="reminderVerifiedBadge">Verified</span>
                        <button type="button" class="reminder-text-action" id="changeReminderEmailBtn">Change email</button>
                    </div>
                </div>
            </div>

            <div class="reminder-divider"></div>
            <div class="reminder-field-group">
                <label for="reminderDetails">Reminder details</label>
                <textarea id="reminderDetails" rows="2" maxlength="200" placeholder="What should this reminder say?"></textarea>
            </div>

            <div class="reminder-date-time-row">
                <div class="reminder-field-group">
                    <label for="reminderStartDate">Date</label>
                    <input type="date" id="reminderStartDate">
                </div>
                <div class="reminder-field-group">
                    <label for="reminderTime">Time</label>
                    <div class="settings-input-wrap settings-time has-caret">
                        <i aria-hidden="true"></i>
                        <input type="text" id="reminderTime" value="06:00 PM" readonly inputmode="none" data-default-time="18:00" aria-label="Daily reminder time">
                    </div>
                </div>
            </div>
            <p class="reminder-helper">Philippine Time (UTC+8)</p>

            <div class="reminder-schedule-options">
                <fieldset class="reminder-days-fieldset">
                    <legend>Repeat</legend>
                    <div class="settings-input-wrap has-caret reminder-repeat-select-wrap">
                        <i aria-hidden="true"></i>
                        <select id="reminderRepeatPreset" aria-label="Repeat reminder days">
                            <option value="weekdays">Weekdays (Mon–Fri)</option>
                            <option value="everyday">Daily</option>
                            <option value="weekends">Weekends (Sat–Sun)</option>
                            <option value="custom">Selected days</option>
                            <option value="once">Custom date (once)</option>
                        </select>
                    </div>
                    <p class="reminder-helper">Choose daily, selected days, or a one-time date.</p>
                    <div class="reminder-day-options" id="reminderCustomDays" role="group" aria-label="Custom reminder days" hidden>
                        <button type="button" data-reminder-day="1" aria-pressed="false">Mon</button>
                        <button type="button" data-reminder-day="2" aria-pressed="false">Tue</button>
                        <button type="button" data-reminder-day="3" aria-pressed="false">Wed</button>
                        <button type="button" data-reminder-day="4" aria-pressed="false">Thu</button>
                        <button type="button" data-reminder-day="5" aria-pressed="false">Fri</button>
                        <button type="button" data-reminder-day="6" aria-pressed="false">Sat</button>
                        <button type="button" data-reminder-day="7" aria-pressed="false">Sun</button>
                    </div>
                </fieldset>
            </div>

            <div class="reminder-modal-actions">
                <button type="button" class="reminder-cancel-btn" id="cancelReminderMainBtn">Cancel</button>
                <button type="submit" class="reminder-save-btn" id="saveReminderBtn" disabled>Save Reminder</button>
            </div>
        </section>
    </form>
</section>
