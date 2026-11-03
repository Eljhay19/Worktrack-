{{-- Daily Records: saved entries by month and pay cutoff --}}
<section class="overlay daily-records-overlay" id="dailyRecordsOverlay" aria-labelledby="dailyRecordsTitle" hidden>
    <div class="daily-records-card" role="dialog" aria-modal="true" aria-labelledby="dailyRecordsTitle" aria-describedby="dailyRecordsSubtitle" tabindex="-1">
        <header class="daily-records-head">
            <div>
                <h2 id="dailyRecordsTitle">Daily Records</h2>
                <p id="dailyRecordsSubtitle">Your daily hours and notes</p>
            </div>
            <span class="settings-leaf daily-records-leaf" aria-hidden="true"></span>
            <button class="daily-records-close" type="button" id="closeDailyRecordsBtn" aria-label="Close daily records">&times;</button>
        </header>

        <div class="daily-records-controls">
            <label class="daily-records-month-wrap" for="dailyRecordsMonth">
                <span class="sr-only">Select month</span>
                <select id="dailyRecordsMonth" aria-label="Select records month"></select>
            </label>
            <div class="daily-records-periods" role="group" aria-label="Filter records by cutoff period">
                <button type="button" data-record-period="first" aria-pressed="false">1–15</button>
                <button type="button" data-record-period="second" aria-pressed="false">16–31</button>
                <button type="button" data-record-period="full" aria-pressed="false">Full month</button>
            </div>
        </div>

        <div class="daily-records-list" id="dailyRecordsList" aria-live="polite"></div>

        <footer class="daily-records-footer">
            <div class="daily-records-total" id="dailyRecordsTotal" aria-live="polite"></div>
            <button class="daily-records-close-footer" type="button" id="closeDailyRecordsFooterBtn">Close</button>
        </footer>
    </div>
</section>
