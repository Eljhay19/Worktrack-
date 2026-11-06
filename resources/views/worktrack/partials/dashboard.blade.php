{{-- WORKTRACK Dashboard: monthly summary, calendar, legends, and reset action --}}
@php
    $initialMonth = now();
    $requestedMonth = request()->route('month') ?: request()->query('month');
    if (is_string($requestedMonth) && preg_match('/^\d{4}-\d{2}$/', $requestedMonth)) {
        try {
            $initialMonth = \Carbon\Carbon::createFromFormat('!Y-m', $requestedMonth);
        } catch (\Throwable) {
            $initialMonth = now();
        }
    }
@endphp
<main>
    <section class="summary-card" aria-label="Monthly summary">
        <article>
            <span>Monthly Earnings</span>
            <strong id="monthlyEarnings">--</strong>
            <small id="monthlyHoursLabel">No hours logged</small>
        </article>
        <article>
            <span>Total Hours</span>
            <strong id="monthlyHours">--</strong>
            <small>this month</small>
        </article>
        <article>
            <span>1st Cutoff <em>1-15</em></span>
            <strong id="cutoffOnePay">--</strong>
            <small id="cutoffOneHours">No hours</small>
        </article>
        <article>
            <span>2nd Cutoff <em id="cutoffTwoRange">16-31</em></span>
            <strong id="cutoffTwoPay">--</strong>
            <small id="cutoffTwoHours">No hours</small>
        </article>
    </section>

    <section class="content-grid">
        <section class="calendar-card" aria-label="Work calendar">
            <div class="calendar-toolbar">
                <button class="icon-btn subtle" type="button" id="prevMonthCard" aria-label="Previous month">
                    <span aria-hidden="true">&lsaquo;</span>
                </button>
                <div class="calendar-heading">
                    <button
                        class="calendar-period-btn"
                        type="button"
                        id="calendarPeriodBtn"
                        aria-haspopup="dialog"
                        aria-expanded="false"
                        aria-controls="calendarPeriodPicker"
                    >
                        <strong id="monthTitle">{{ $initialMonth->format('F Y') }}</strong>
                        <span class="calendar-period-chevron" aria-hidden="true"></span>
                    </button>
                    <div class="calendar-period-picker" id="calendarPeriodPicker" role="dialog" aria-label="Choose calendar month" hidden>
                        <div class="calendar-period-picker-head">
                            <strong>Choose month</strong>
                            <button class="round-close calendar-period-close" type="button" id="closeCalendarPeriodBtn" aria-label="Close month picker">&times;</button>
                        </div>
                        <div class="calendar-period-fields">
                            <div class="calendar-period-field" data-calendar-period-field="month">
                                <span>Month</span>
                                <button class="calendar-period-select" type="button" id="calendarMonthSelect" aria-haspopup="listbox" aria-expanded="false" aria-controls="calendarMonthMenu">
                                    <span id="calendarMonthValue"></span>
                                    <span class="calendar-period-select-chevron" aria-hidden="true"></span>
                                </button>
                                <div class="calendar-period-menu" id="calendarMonthMenu" role="listbox" aria-label="Calendar month options" hidden></div>
                            </div>
                            <div class="calendar-period-field" data-calendar-period-field="year">
                                <span>Year</span>
                                <button class="calendar-period-select" type="button" id="calendarYearSelect" aria-haspopup="listbox" aria-expanded="false" aria-controls="calendarYearMenu">
                                    <span id="calendarYearValue"></span>
                                    <span class="calendar-period-select-chevron" aria-hidden="true"></span>
                                </button>
                                <div class="calendar-period-menu" id="calendarYearMenu" role="listbox" aria-label="Calendar year options" hidden></div>
                            </div>
                        </div>
                        <div class="calendar-period-actions">
                            <button class="calendar-period-today" type="button" id="calendarPeriodTodayBtn">Today</button>
                        </div>
                    </div>
                    <button class="today-btn" type="button" id="todayBtn">Today</button>
                </div>
                <button class="icon-btn subtle" type="button" id="nextMonthCard" aria-label="Next month">
                    <span aria-hidden="true">&rsaquo;</span>
                </button>
            </div>

            <div class="weekday-row" aria-hidden="true">
                <span>Sun</span>
                <span>Mon</span>
                <span>Tue</span>
                <span>Wed</span>
                <span>Thu</span>
                <span>Fri</span>
                <span>Sat</span>
            </div>
            <div class="calendar-grid" id="calendarGrid" role="grid" aria-label="Monthly calendar"></div>
        </section>

        <aside class="side-panel" aria-label="WorkTrack details">
            <section class="info-card">
                <h2>Rate Legend</h2>
                <ul class="legend-list">
                    <li><i class="dot green"></i><span>Normal rate</span><strong id="normalRateText">--</strong></li>
                    <li><i class="dot blue"></i><span>Night differential</span><strong>+10%</strong></li>
                    <li><i class="dot yellow"></i><span>Holiday</span><strong>+30%</strong></li>
                    <li><i class="dot red"></i><span>Double pay</span><strong>x2</strong></li>
                </ul>
                <p>Extended hours are paid at an additional 1.25x overtime premium on top of the selected rate and modifier.</p>
            </section>

            <section class="info-card">
                <h2>Day Status</h2>
                <ul class="status-grid">
                    <li><i class="status today"></i><span>Today</span></li>
                    <li><i class="status selected"></i><span>Selected</span></li>
                    <li><i class="status rest"></i><span>Rest day</span></li>
                    <li><i class="status absent"></i><span>Excused absence</span></li>
                    <li><i class="status holiday"></i><span>Holiday</span></li>
                    <li><i class="status double"></i><span>Double pay</span></li>
                </ul>
            </section>

            <section class="info-card weekly-hours-card" aria-label="Weekly hours">
                <div class="weekly-hours-head">
                    <h2>Weekly Hours</h2>
                    <span id="weeklyHoursRange"></span>
                </div>
                <div class="weekly-hours-chart" id="weeklyHoursChart" role="img" aria-label="Weekly work hours chart"></div>
                <p class="weekly-hours-summary" id="weeklyHoursSummary">No hours logged this week</p>
            </section>

            <div class="dashboard-actions">
                <button class="reset-btn" type="button" id="resetDataBtn">
                    <span aria-hidden="true" class="trash-icon"></span>
                    Reset all data
                </button>
                <div class="export-dropdown" id="exportDropdown">
                    <button class="export-btn" type="button" id="exportMenuButton" aria-haspopup="menu" aria-expanded="false" aria-controls="exportMenu">
                        <span aria-hidden="true" class="export-icon">
                            <svg viewBox="0 0 20 20" focusable="false">
                                <path d="M10 2.5v9m0 0 3.5-3.5M10 11.5 6.5 8M3.5 13v3a1.5 1.5 0 0 0 1.5 1.5h10a1.5 1.5 0 0 0 1.5-1.5v-3" />
                            </svg>
                        </span>
                        <span>Export</span>
                        <span aria-hidden="true" class="export-caret"></span>
                    </button>
                    <div class="export-menu" id="exportMenu" role="menu" aria-label="Export work data" hidden>
                        <button type="button" role="menuitem" data-export-format="pdf">
                            <span class="export-menu-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><path d="M5 2.5h7l4 4v11H5zM12 2.5v4h4M7.5 11h6M7.5 14h6" /></svg></span> PDF (.pdf)
                        </button>
                        <button type="button" role="menuitem" data-export-format="xlsx">
                            <span class="export-menu-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><rect x="3" y="3" width="14" height="14" rx="1.5"/><path d="M3 7.5h14M3 12h14M7.5 3v14M12 3v14" /></svg></span> Excel (.xlsx)
                        </button>
                        <button type="button" role="menuitem" data-export-format="jpg">
                            <span class="export-menu-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><rect x="2.5" y="3.5" width="15" height="13" rx="1.5"/><circle cx="7" cy="8" r="1.4"/><path d="m4 14 4-3.5 2.5 2 2.5-3 3 4.5" /></svg></span> Image (.jpg)
                        </button>
                    </div>
                </div>
            </div>
        </aside>
    </section>
</main>
