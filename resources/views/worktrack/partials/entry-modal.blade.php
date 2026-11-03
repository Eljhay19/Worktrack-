{{-- WORKTRACK Entry Modal: time in/out, pay modifier, notes, and day status --}}
<section class="overlay" id="entryOverlay" hidden>
    <form class="entry-modal" id="entryForm">
        <div class="modal-head">
            <div>
                <h2 id="entryTitle">Friday, October 2, 2026</h2>
                <p id="entrySubtitle">Add your work entry for this date</p>
            </div>
            <button class="round-close" type="button" id="closeEntryBtn" aria-label="Close entry">&times;</button>
        </div>

        <div class="time-grid">
            <label class="icon-field in">
                <span><i aria-hidden="true"></i> Time In</span>
                <input type="text" id="timeInInput" class="time-input" placeholder="--:-- --" readonly inputmode="none" data-default-time="09:00" aria-label="Time in">
            </label>
            <label class="icon-field out">
                <span><i aria-hidden="true"></i> Time Out</span>
                <input type="text" id="timeOutInput" class="time-input" placeholder="--:-- --" readonly inputmode="none" data-default-time="18:00" aria-label="Time out">
            </label>
        </div>

        <div class="hours-preview" aria-label="Work hours">
            <article class="hours-input-card">
                <label for="normalHoursInput">Normal Hours</label>
                <input type="text" id="normalHoursInput" inputmode="decimal" autocomplete="off" placeholder="Input your normal hr">
            </article>
            <article class="hours-input-card">
                <label for="extendedHoursInput">Extended Hours</label>
                <input type="text" id="extendedHoursInput" inputmode="decimal" autocomplete="off" placeholder="Input your extended hr">
            </article>
            <article>
                <span>Total Hours</span>
                <strong id="totalHoursPreview">--</strong>
            </article>
        </div>

        <div class="option-block">
            <h3><span class="mini-icon bars" aria-hidden="true"></span>Rate Type</h3>
            <div class="segmented two" id="rateTypeGroup">
                <button type="button" data-rate="normal" class="is-active">Normal</button>
                <button type="button" data-rate="night">Night diff. <small>+10%</small></button>
            </div>
        </div>

        <div class="option-block">
            <h3><span class="mini-icon coins" aria-hidden="true"></span>Pay Modifier</h3>
            <div class="segmented three" id="modifierGroup">
                <button type="button" data-modifier="none" class="is-active">None</button>
                <button type="button" data-modifier="holiday">Holiday<small>+30%</small></button>
                <button type="button" data-modifier="double">Double<small>x2</small></button>
            </div>
        </div>

        <div class="pay-preview">
            <span><span class="calc-icon" aria-hidden="true"></span>Estimated pay</span>
            <output id="estimatedPay">--</output>
        </div>

        <label class="note-field">
            <span><span class="note-icon" aria-hidden="true"></span>Attendance Note <small>(optional)</small></span>
            <textarea id="noteInput" maxlength="200" placeholder="e.g. left early for appointment"></textarea>
            <small id="noteCount">0/200</small>
        </label>

        <div class="option-block">
            <h3><span class="tag-icon" aria-hidden="true"></span>Quick Labels</h3>
            <div class="quick-actions">
                <button type="button" id="restDayBtn">Rest Day</button>
                <button type="button" id="excusedBtn">Excused Absence</button>
                <button type="button" id="clearDayBtn">Clear Day</button>
            </div>
        </div>

        <div class="modal-actions">
            <button class="ghost-btn" type="button" id="cancelEntryBtn">Cancel</button>
            <div class="entry-actions-right">
                <button class="danger-btn" type="button" id="deleteEntryBtn" hidden>
                    <span aria-hidden="true" class="delete-icon"></span>Delete
                </button>
                <button class="save-btn" type="button" id="editEntryBtn" hidden>
                    <span aria-hidden="true" class="edit-icon"></span>Edit
                </button>
                <button class="save-btn" type="submit" id="saveEntryBtn">
                    <span aria-hidden="true" class="save-icon"></span>Save Entry
                </button>
            </div>
        </div>
    </form>
</section>
