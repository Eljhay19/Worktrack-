{{-- WORKTRACK Time Picker: shared clean time selector for work entries and reminders --}}
<section class="time-picker-overlay" id="timePickerOverlay" hidden>
    <form class="time-picker-card" id="timePickerForm">
        <div class="time-picker-head">
            <div>
                <strong id="timePickerTitle">Set time</strong>
                <span>Choose the exact reminder or work log time</span>
            </div>
            <button type="button" id="closeTimePickerBtn" aria-label="Close time picker">&times;</button>
        </div>

        <output class="time-picker-preview" id="timePickerPreview">06:00 PM</output>

        <div class="time-picker-controls">
            <div class="time-picker-unit time-select" data-time-select="hour">
                <span>Hour</span>
                <button type="button" class="time-select-trigger" id="timePickerHourButton" aria-haspopup="listbox" aria-expanded="false">
                    <output id="timePickerHour">06</output>
                    <b aria-hidden="true"></b>
                </button>
                <div class="time-select-menu" id="timePickerHourMenu" role="listbox" aria-label="Hour"></div>
            </div>
            <div class="time-picker-unit time-select" data-time-select="minute">
                <span>Minute</span>
                <button type="button" class="time-select-trigger" id="timePickerMinuteButton" aria-haspopup="listbox" aria-expanded="false">
                    <output id="timePickerMinute">00</output>
                    <b aria-hidden="true"></b>
                </button>
                <div class="time-select-menu" id="timePickerMinuteMenu" role="listbox" aria-label="Minute"></div>
            </div>
            <div class="time-period-group" role="group" aria-label="Period">
                <button type="button" data-period="AM">AM</button>
                <button type="button" data-period="PM" class="is-active">PM</button>
            </div>
        </div>

        <div class="time-picker-actions">
            <button type="button" class="time-picker-clear" id="clearTimePickerBtn">Clear</button>
            <button type="submit" class="time-picker-apply">Set Time</button>
        </div>
    </form>
</section>
