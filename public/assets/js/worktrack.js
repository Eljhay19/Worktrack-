'use strict';

// WORKTRACK state and browser identity
let csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const serviceWorkerUrl = document.querySelector('meta[name="worktrack-service-worker"]')?.content;
const browserKey = 'worktrack_browser_id';
const themeKey = 'worktrack_theme';
const routeBasePath = '/worktrack';
const months = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
];

const state = {
  browserId: getBrowserId(),
  profile: null,
  reminderNotifications: null,
  entries: {},
  viewDate: new Date(),
  calendarDirection: null,
  selectedDate: null,
  dailyRecordsMonth: null,
  dailyRecordsPeriod: 'second',
  draft: null,
  entryEditing: false,
  theme: localStorage.getItem(themeKey) || 'light',
};
let reminderCheckInFlight = false;
let reminderPollingInitialized = false;
let lastReminderNotificationSignature = '';
let showAllNotifications = false;
let notificationHoverSuppressed = false;
const seenReminderDeliveries = new Set();
let activeTimeInput = null;
let activeTimeAllowsClear = true;
let reminderVerifiedEmail = '';
let reminderCodeSentTo = '';
let reminderCodeExpiresAt = 0;
let reminderResendAvailableAt = 0;
let reminderCountdownTimer = null;
let reminderCodeSending = false;
let reminderCodeVerifying = false;
let reminderSaving = false;
let selectedReminderDays = new Set();
let settingsEditMode = false;
let accessChallenge = null;
let accessPurpose = 'login';
let accessDraft = null;
let accessSending = false;
let accessVerifying = false;
let accessRequired = false;
let stateSyncInFlight = false;
let stateLoadSequence = 0;
let appInitialized = false;
let appLoadInFlight = false;
let backgroundSyncStarted = false;
let lastRenderedDashboardSignature = null;
let lastStateLoadAt = 0;

const els = {
  // Header and dashboard
  monthTitle: document.getElementById('monthTitle'),
  profileNote: document.getElementById('profileNote'),
  profileGreeting: document.getElementById('profileGreeting'),
  profileMeta: document.getElementById('profileMeta'),
  prevMonthCard: document.getElementById('prevMonthCard'),
  nextMonthCard: document.getElementById('nextMonthCard'),
  calendarPeriodBtn: document.getElementById('calendarPeriodBtn'),
  calendarPeriodPicker: document.getElementById('calendarPeriodPicker'),
  calendarMonthSelect: document.getElementById('calendarMonthSelect'),
  calendarYearSelect: document.getElementById('calendarYearSelect'),
  calendarMonthValue: document.getElementById('calendarMonthValue'),
  calendarYearValue: document.getElementById('calendarYearValue'),
  calendarMonthMenu: document.getElementById('calendarMonthMenu'),
  calendarYearMenu: document.getElementById('calendarYearMenu'),
  closeCalendarPeriodBtn: document.getElementById('closeCalendarPeriodBtn'),
  calendarPeriodTodayBtn: document.getElementById('calendarPeriodTodayBtn'),
  todayBtn: document.getElementById('todayBtn'),
  themeToggleBtn: document.getElementById('themeToggleBtn'),
  dailyRecordsBtn: document.getElementById('dailyRecordsBtn'),
  dailyRecordsOverlay: document.getElementById('dailyRecordsOverlay'),
  closeDailyRecordsBtn: document.getElementById('closeDailyRecordsBtn'),
  closeDailyRecordsFooterBtn: document.getElementById('closeDailyRecordsFooterBtn'),
  dailyRecordsMonth: document.getElementById('dailyRecordsMonth'),
  dailyRecordsList: document.getElementById('dailyRecordsList'),
  dailyRecordsTotal: document.getElementById('dailyRecordsTotal'),
  notificationBtn: document.getElementById('notificationBtn'),
  notificationCount: document.getElementById('notificationCount'),
  notificationPopover: document.getElementById('notificationPopover'),
  notificationItems: document.getElementById('notificationItems'),
  markAllNotificationsRead: document.getElementById('markAllNotificationsRead'),
  viewAllNotifications: document.getElementById('viewAllNotifications'),
  notificationPopoverAction: document.getElementById('notificationPopoverAction'),
  closeNotificationPopover: document.getElementById('closeNotificationPopover'),
  settingsBtn: document.getElementById('settingsBtn'),
  calendarGrid: document.getElementById('calendarGrid'),
  monthlyEarnings: document.getElementById('monthlyEarnings'),
  monthlyHours: document.getElementById('monthlyHours'),
  monthlyHoursLabel: document.getElementById('monthlyHoursLabel'),
  cutoffOnePay: document.getElementById('cutoffOnePay'),
  cutoffOneHours: document.getElementById('cutoffOneHours'),
  cutoffTwoPay: document.getElementById('cutoffTwoPay'),
  cutoffTwoHours: document.getElementById('cutoffTwoHours'),
  cutoffTwoRange: document.getElementById('cutoffTwoRange'),
  normalRateText: document.getElementById('normalRateText'),
  resetDataBtn: document.getElementById('resetDataBtn'),
  weeklyHoursRange: document.getElementById('weeklyHoursRange'),
  weeklyHoursChart: document.getElementById('weeklyHoursChart'),
  weeklyHoursSummary: document.getElementById('weeklyHoursSummary'),
  exportDropdown: document.getElementById('exportDropdown'),
  exportMenuButton: document.getElementById('exportMenuButton'),
  exportMenu: document.getElementById('exportMenu'),
  // Profile setup
  profileOverlay: document.getElementById('profileOverlay'),
  profileForm: document.getElementById('profileForm'),
  profileEmail: document.getElementById('profileEmail'),
  profileAccessOverlay: document.getElementById('profileAccessOverlay'),
  profileAccessForm: document.getElementById('profileAccessForm'),
  profileAccessEmail: document.getElementById('profileAccessEmail'),
  profileAccessTitle: document.getElementById('profileAccessTitle'),
  profileAccessSubtitle: document.getElementById('profileAccessSubtitle'),
  profileAccessMessage: document.getElementById('profileAccessMessage'),
  profileAccessError: document.getElementById('profileAccessError'),
  sendProfileAccessBtn: document.getElementById('sendProfileAccessBtn'),
  settingsAccountEmail: document.getElementById('settingsAccountEmail'),
  settingsEmailVerified: document.getElementById('settingsEmailVerified'),
  settingsEmailAccessBtn: document.getElementById('settingsEmailAccessBtn'),
  logoutProfileBtn: document.getElementById('logoutProfileBtn'),
  profileNickname: document.getElementById('profileNickname'),
  profileWorkType: document.getElementById('profileWorkType'),
  profileEmployeeStatus: document.getElementById('profileEmployeeStatus'),
  profileHourlyRate: document.getElementById('profileHourlyRate'),
  // Work entry modal
  entryOverlay: document.getElementById('entryOverlay'),
  entryForm: document.getElementById('entryForm'),
  entryTitle: document.getElementById('entryTitle'),
  entrySubtitle: document.getElementById('entrySubtitle'),
  closeEntryBtn: document.getElementById('closeEntryBtn'),
  timeInInput: document.getElementById('timeInInput'),
  timeOutInput: document.getElementById('timeOutInput'),
  normalHoursInput: document.getElementById('normalHoursInput'),
  extendedHoursInput: document.getElementById('extendedHoursInput'),
  totalHoursPreview: document.getElementById('totalHoursPreview'),
  rateTypeGroup: document.getElementById('rateTypeGroup'),
  modifierGroup: document.getElementById('modifierGroup'),
  estimatedPay: document.getElementById('estimatedPay'),
  noteInput: document.getElementById('noteInput'),
  noteCount: document.getElementById('noteCount'),
  restDayBtn: document.getElementById('restDayBtn'),
  excusedBtn: document.getElementById('excusedBtn'),
  clearDayBtn: document.getElementById('clearDayBtn'),
  cancelEntryBtn: document.getElementById('cancelEntryBtn'),
  deleteEntryBtn: document.getElementById('deleteEntryBtn'),
  editEntryBtn: document.getElementById('editEntryBtn'),
  saveEntryBtn: document.getElementById('saveEntryBtn'),
  // Settings and reset
  settingsOverlay: document.getElementById('settingsOverlay'),
  reminderSettingsOverlay: document.getElementById('reminderSettingsOverlay'),
  reminderVerifyOverlay: document.getElementById('reminderVerifyOverlay'),
  reminderVerifyForm: document.getElementById('reminderVerifyForm'),
  closeReminderVerifyBtn: document.getElementById('closeReminderVerifyBtn'),
  reminderStepSubtitle: document.getElementById('reminderStepSubtitle'),
  reminderEmailStep: document.getElementById('reminderEmailStep'),
  reminderMainStep: document.getElementById('reminderMainStep'),
  settingsForm: document.getElementById('settingsForm'),
  settingsEditSaveBtn: document.getElementById('settingsEditSaveBtn'),
  closeSettingsBtn: document.getElementById('closeSettingsBtn'),
  openReminderSettingsBtn: document.getElementById('openReminderSettingsBtn'),
  reminderSummary: document.getElementById('reminderSummary'),
  reminderForm: document.getElementById('reminderForm'),
  closeReminderSettingsBtn: document.getElementById('closeReminderSettingsBtn'),
  cancelReminderSettingsBtn: document.getElementById('cancelReminderSettingsBtn'),
  reminderEmail: document.getElementById('reminderEmail'),
  sendReminderCodeBtn: document.getElementById('sendReminderCodeBtn'),
  reminderEmailStatus: document.getElementById('reminderEmailStatus'),
  reminderCode: document.getElementById('reminderCode'),
  reminderCodeEmail: document.getElementById('reminderCodeEmail'),
  reminderCodeExpiry: document.getElementById('reminderCodeExpiry'),
  reminderResendCountdown: document.getElementById('reminderResendCountdown'),
  resendReminderCodeBtn: document.getElementById('resendReminderCodeBtn'),
  reminderCodeError: document.getElementById('reminderCodeError'),
  otpInputs: document.querySelectorAll('[data-otp-index]'),
  backToReminderEmailBtn: document.getElementById('backToReminderEmailBtn'),
  changeReminderEmailBtn: document.getElementById('changeReminderEmailBtn'),
  cancelReminderMainBtn: document.getElementById('cancelReminderMainBtn'),
  reminderVerifiedAddress: document.getElementById('reminderVerifiedAddress'),
  reminderDetails: document.getElementById('reminderDetails'),
  reminderStartDate: document.getElementById('reminderStartDate'),
  verifyReminderCodeBtn: document.getElementById('verifyReminderCodeBtn'),
  reminderDayButtons: document.querySelectorAll('[data-reminder-day]'),
  reminderRepeatPreset: document.getElementById('reminderRepeatPreset'),
  reminderCustomDays: document.getElementById('reminderCustomDays'),
  saveReminderBtn: document.getElementById('saveReminderBtn'),
  settingsNickname: document.getElementById('settingsNickname'),
  settingsWorkType: document.getElementById('settingsWorkType'),
  settingsEmployeeStatus: document.getElementById('settingsEmployeeStatus'),
  settingsRegularHours: document.getElementById('settingsRegularHours'),
  settingsHourlyRate: document.getElementById('settingsHourlyRate'),
  reminderEnabled: document.getElementById('reminderEnabled'),
  reminderTime: document.getElementById('reminderTime'),
  settingsTimeWrap: document.querySelector('.settings-time'),
  settingsSubtitle: document.getElementById('settingsSubtitle'),
  timePickerOverlay: document.getElementById('timePickerOverlay'),
  timePickerForm: document.getElementById('timePickerForm'),
  timePickerTitle: document.getElementById('timePickerTitle'),
  timePickerPreview: document.getElementById('timePickerPreview'),
  timePickerHour: document.getElementById('timePickerHour'),
  timePickerMinute: document.getElementById('timePickerMinute'),
  timePickerHourButton: document.getElementById('timePickerHourButton'),
  timePickerMinuteButton: document.getElementById('timePickerMinuteButton'),
  timePickerHourMenu: document.getElementById('timePickerHourMenu'),
  timePickerMinuteMenu: document.getElementById('timePickerMinuteMenu'),
  timePickerPeriodButtons: document.querySelectorAll('#timePickerOverlay [data-period]'),
  closeTimePickerBtn: document.getElementById('closeTimePickerBtn'),
  clearTimePickerBtn: document.getElementById('clearTimePickerBtn'),
  confirmOverlay: document.getElementById('confirmOverlay'),
  cancelResetBtn: document.getElementById('cancelResetBtn'),
  confirmResetBtn: document.getElementById('confirmResetBtn'),
  toast: document.getElementById('toast'),
  toastMessage: document.getElementById('toastMessage'),
};

function getBrowserId() {
  let id = localStorage.getItem(browserKey);
  if (!id || id.length < 12) {
    if (window.crypto?.randomUUID) {
      id = `wtb_${window.crypto.randomUUID()}`;
    } else if (window.crypto?.getRandomValues) {
      const bytes = window.crypto.getRandomValues(new Uint8Array(16));
      id = `wtb_${Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('')}`;
    } else {
      id = `wtb_${Date.now()}_${Math.random().toString(16).slice(2)}`;
    }
    localStorage.setItem(browserKey, id);
  }
  return id;
}

function pad(value) {
  return String(value).padStart(2, '0');
}

function dateKey(date) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function parseDate(key) {
  const [year, month, day] = key.split('-').map(Number);
  return new Date(year, month - 1, day);
}

function daysInMonth(year, monthIndex) {
  return new Date(year, monthIndex + 1, 0).getDate();
}

function formatMoney(value) {
  return '\u20b1' + Number(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatHours(value) {
  const number = Number(value || 0);
  if (number === 0) return '0';
  return Number.isInteger(number) ? String(number) : number.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

function formatLongDate(key) {
  return parseDate(key).toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

function hourlyRate() {
  return Number(state.profile?.hourly_rate || 0);
}

function defaultRegularHoursFor(employeeStatus) {
  if (employeeStatus === 'part_time') return 4;
  if (employeeStatus === 'rotational') return 10;
  return 8;
}

function employeeStatusLabel(employeeStatus) {
  const labels = {
    full_time: 'Full Time',
    part_time: 'Part Time',
    rotational: 'Rotational',
    custom: 'Custom',
  };
  return labels[employeeStatus] || labels.full_time;
}

function regularHoursValue(value, fallback = 8) {
  const number = Number(value);
  if (!Number.isFinite(number) || number <= 0) return fallback;
  return Math.min(number, 24);
}

function manualHoursValue(value) {
  const source = String(value ?? '').trim();
  if (!source) return null;
  const number = Number(source);
  if (!Number.isFinite(number) || number < 0) return null;
  return number;
}

function validateManualHoursInputs() {
  for (const input of [els.normalHoursInput, els.extendedHoursInput]) {
    const source = input.value.trim();
    if (!source) continue;
    const hours = Number(source);
    if (Number.isFinite(hours) && hours >= 0 && hours <= 24) continue;
    input.focus();
    throw new Error('Enter a valid number of hours from 0 to 24.');
  }
}

function setTheme(theme) {
  state.theme = theme;
  localStorage.setItem(themeKey, theme);
  document.documentElement.dataset.theme = theme;
  els.themeToggleBtn.classList.toggle('is-active', theme === 'light');
  els.themeToggleBtn.setAttribute(
    'aria-label',
    theme === 'light' ? 'Switch to dark mode' : 'Switch to light mode',
  );
}

function toggleTheme() {
  setTheme(state.theme === 'light' ? 'dark' : 'light');
}

function currentSection() {
  const parts = window.location.pathname.split('/').filter(Boolean);
  if (parts[0] !== 'worktrack') return 'calendar';
  return ['calendar', 'settings', 'profile-setup', 'notifications', 'access'].includes(parts[1]) ? parts[1] : 'calendar';
}

function currentFeature() {
  return window.location.pathname.split('/').filter(Boolean)[2] || '';
}

function currentRouteDate() {
  return window.location.pathname.split('/').filter(Boolean)[3] || '';
}

function currentRouteOverlay() {
  return window.location.pathname.split('/').filter(Boolean)[4] || '';
}

function setPageTitle(section, feature = '') {
  const labels = {
    calendar: 'Calendar',
    settings: 'Settings',
    'profile-setup': 'Profile Setup',
    notifications: 'Notifications',
    access: 'Profile Access',
  };
  const page = section === 'settings' && feature === 'reminder'
    ? 'Daily Reminder'
    : section === 'calendar' && feature === 'records'
      ? 'Daily Records'
      : section === 'calendar' && feature === 'reset'
        ? 'Reset Data'
        : labels[section] || labels.calendar;
  document.title = `WORKTRACK | ${page}`;
}

function setRouteSection(section, replace = false) {
  const month = `${state.viewDate.getFullYear()}-${pad(state.viewDate.getMonth() + 1)}`;
  const path = section === 'calendar'
    ? `${routeBasePath}/calendar/${month}`
    : `${routeBasePath}/${section}`;
  const method = replace ? 'replaceState' : 'pushState';
  if (window.location.pathname !== path) {
    window.history[method]({ section }, '', path);
  }
  setPageTitle(section);
}

function setRouteFeature(section, feature, date = '', replace = false) {
  const suffix = date ? `/${date}` : '';
  const path = `${routeBasePath}/${section}/${feature}${suffix}`;
  const method = replace ? 'replaceState' : 'pushState';
  if (window.location.pathname !== path) {
    window.history[method]({ section, feature, date }, '', path);
  }
  setPageTitle(section, feature);
}

function setCalendarOverlayRoute(feature, query = {}, replace = false) {
  const url = new URL(`${routeBasePath}/calendar/${feature}`, window.location.origin);
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value));
  });
  const path = `${url.pathname}${url.search}`;
  const method = replace ? 'replaceState' : 'pushState';
  if (`${window.location.pathname}${window.location.search}` !== path) {
    window.history[method]({ section: 'calendar', feature, query }, '', path);
  }
  setPageTitle('calendar', feature);
}

function restoreCalendarMonthFromRoute() {
  if (currentSection() !== 'calendar') return;
  const feature = currentFeature();
  const routeMonth = feature.match(/^(\d{4})-(\d{2})$/);
  const queryMonth = ['records', 'reset'].includes(feature)
    ? new URLSearchParams(window.location.search).get('month')?.match(/^(\d{4})-(\d{2})$/)
    : null;
  const month = routeMonth || queryMonth;
  if (!month) return;
  const year = Number(month[1]);
  const monthNumber = Number(month[2]);
  if (monthNumber < 1 || monthNumber > 12) return;
  state.viewDate = new Date(year, monthNumber - 1, 1);
}

function normalizeTimeValue(value) {
  const source = String(value || '').trim();
  if (!source) return '';

  const match = source.match(/^(\d{1,2}):(\d{2})(?:\s*(AM|PM))?/i);
  if (!match) return '';

  let hour = Number(match[1]);
  const minute = Number(match[2]);
  const period = match[3]?.toUpperCase();

  if (Number.isNaN(hour) || Number.isNaN(minute) || minute > 59) return '';

  if (period) {
    if (hour < 1 || hour > 12) return '';
    if (period === 'PM' && hour < 12) hour += 12;
    if (period === 'AM' && hour === 12) hour = 0;
  }

  if (hour > 23) return '';
  return `${pad(hour)}:${pad(minute)}`;
}

function parseTimeParts(value) {
  const raw = normalizeTimeValue(value);
  if (!raw) {
    return { hour: '06', minute: '00', period: 'PM' };
  }

  const [hourValue, minuteValue] = raw.split(':').map(Number);
  const period = hourValue >= 12 ? 'PM' : 'AM';
  const hour12 = hourValue % 12 || 12;
  return {
    hour: pad(hour12),
    minute: pad(minuteValue),
    period,
  };
}

function formatDisplayTime(value) {
  const raw = normalizeTimeValue(value);
  if (!raw) return '';
  const parts = parseTimeParts(raw);
  return `${parts.hour}:${parts.minute} ${parts.period}`;
}

function setTimeInput(input, value) {
  if (!input) return;
  const raw = normalizeTimeValue(value);
  input.dataset.value = raw;
  input.value = raw ? formatDisplayTime(raw) : '';
}

function getTimeInput(input) {
  if (!input) return '';
  return normalizeTimeValue(input.dataset.value || input.value);
}

function selectedPickerPeriod() {
  const activeButton = Array.from(els.timePickerPeriodButtons).find((button) => button.classList.contains('is-active'));
  return activeButton?.dataset.period || 'PM';
}

function selectedPickerValue() {
  let hour = Number(els.timePickerHour.dataset.value || els.timePickerHour.textContent);
  const minute = Number(els.timePickerMinute.dataset.value || els.timePickerMinute.textContent);
  const period = selectedPickerPeriod();

  if (period === 'PM' && hour < 12) hour += 12;
  if (period === 'AM' && hour === 12) hour = 0;

  return `${pad(hour)}:${pad(minute)}`;
}

function updateTimePickerPreview() {
  els.timePickerPreview.textContent = formatDisplayTime(selectedPickerValue()) || '--:-- --';
}

function setPickerPeriod(period) {
  els.timePickerPeriodButtons.forEach((button) => {
    button.classList.toggle('is-active', button.dataset.period === period);
  });
  updateTimePickerPreview();
}

function setPickerUnit(output, value) {
  const display = pad(value);
  output.dataset.value = display;
  output.textContent = display;
}

function closeTimeDropdowns() {
  [els.timePickerHourMenu, els.timePickerMinuteMenu].forEach((menu) => {
    menu.classList.remove('is-open');
  });
  [els.timePickerHourButton, els.timePickerMinuteButton].forEach((button) => {
    button.setAttribute('aria-expanded', 'false');
  });
}

function toggleTimeDropdown(unit) {
  const isHour = unit === 'hour';
  const menu = isHour ? els.timePickerHourMenu : els.timePickerMinuteMenu;
  const button = isHour ? els.timePickerHourButton : els.timePickerMinuteButton;
  const shouldOpen = !menu.classList.contains('is-open');

  closeTimeDropdowns();
  menu.classList.toggle('is-open', shouldOpen);
  button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
}

function selectTimeDropdownValue(unit, value) {
  const output = unit === 'hour' ? els.timePickerHour : els.timePickerMinute;
  setPickerUnit(output, value);
  closeTimeDropdowns();
  updateTimePickerPreview();
}

function buildTimeDropdownOptions() {
  if (els.timePickerHourMenu.children.length || els.timePickerMinuteMenu.children.length) return;

  for (let hour = 1; hour <= 12; hour += 1) {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.value = pad(hour);
    button.textContent = pad(hour);
    button.setAttribute('role', 'option');
    els.timePickerHourMenu.appendChild(button);
  }

  for (let minute = 0; minute < 60; minute += 1) {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.value = pad(minute);
    button.textContent = pad(minute);
    button.setAttribute('role', 'option');
    els.timePickerMinuteMenu.appendChild(button);
  }
}

function handleTimeValueChange(input) {
  if (input === els.reminderTime) {
    syncReminderForm();
    return;
  }

  resumeWorkEntry();
  updatePayPreview();
}

function openTimePicker(input, title, allowClear = true) {
  if (input !== els.reminderTime && !state.entryEditing) return false;
  activeTimeInput = input;
  activeTimeAllowsClear = allowClear;
  const fallback = getTimeInput(input) || input.dataset.defaultTime || '18:00';
  const parts = parseTimeParts(fallback);

  els.timePickerTitle.textContent = title;
  setPickerUnit(els.timePickerHour, parts.hour);
  setPickerUnit(els.timePickerMinute, parts.minute);
  setPickerPeriod(parts.period);
  els.clearTimePickerBtn.hidden = !allowClear;
  els.timePickerOverlay.classList.toggle('no-clear', !allowClear);
  els.timePickerOverlay.hidden = false;

  window.requestAnimationFrame(() => {
    els.timePickerHourButton.focus();
  });

  return true;
}

function closeTimePicker() {
  const wasReminderTime = activeTimeInput === els.reminderTime;
  const wasEntryTime = activeTimeInput === els.timeInInput || activeTimeInput === els.timeOutInput;
  closeTimeDropdowns();
  els.timePickerOverlay.hidden = true;
  activeTimeInput = null;
  if (wasReminderTime && currentSection() === 'settings' && currentRouteDate() === 'time') {
    setRouteFeature('settings', 'reminder', 'schedule', true);
  } else if (wasEntryTime && currentSection() === 'calendar' && currentFeature() === 'entry'
    && ['time-in', 'time-out'].includes(currentRouteOverlay())) {
    setRouteFeature('calendar', 'entry', currentRouteDate(), true);
  }
}

function applyTimePicker(event) {
  event.preventDefault();
  if (!activeTimeInput) return;

  setTimeInput(activeTimeInput, selectedPickerValue());
  handleTimeValueChange(activeTimeInput);
  closeTimePicker();
}

function clearTimePicker() {
  if (!activeTimeInput || !activeTimeAllowsClear) return;

  setTimeInput(activeTimeInput, '');
  handleTimeValueChange(activeTimeInput);
  closeTimePicker();
}

function showReminderTimePicker(updateRoute = true) {
  openTimePicker(els.reminderTime, 'Daily Reminder Time', false);
  if (updateRoute) setRouteFeature('settings', 'reminder/time');
}

function openEntryTimePicker(input, title, overlay) {
  if (!openTimePicker(input, title)) return;
  const date = currentRouteDate();
  const path = `${routeBasePath}/calendar/entry/${date}/${overlay}`;
  window.history.pushState({ section: 'calendar', feature: 'entry', date, overlay }, '', path);
  setPageTitle('calendar');
}

function formatReminderDateTime(value) {
  if (!value) return 'No upcoming occurrence';
  return new Intl.DateTimeFormat('en-PH', {timeZone:'Asia/Manila', month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:true}).format(new Date(value)) + ' PHT';
}

function notificationDataItems(data) {
  const items = [];
  if (data?.next_occurrence) {
    const next = data.next_occurrence;
    items.push({
      id: `upcoming:${next.id}`, icon: 'bell',
      title: 'Upcoming reminder',
      message: next.notes || next.schedule || 'Scheduled work log reminder',
      at: next.scheduled_at,
    });
  }
  for (const notice of (data?.system_notifications || [])) {
    items.push({
      id: `system:${notice.id}`, icon: 'user',
      title: notice.type === 'profile_ready' ? 'Welcome to WorkTrack' : 'WorkTrack update',
      message: notice.message, at: notice.created_at,
    });
  }
  for (const delivery of (data?.history || [])) {
    items.push({
      id: `delivery:${delivery.id}`, icon: delivery.status === 'sent' ? 'send' : 'bell',
      title: delivery.status === 'sent' ? 'Reminder sent' : 'Reminder failed',
      message: delivery.status === 'failed' ? (delivery.failure_message || delivery.notes || 'Delivery failed') : (delivery.notes || delivery.schedule || 'Reminder delivery accepted'),
      at: delivery.sent_at || delivery.failed_at || delivery.scheduled_at,
    });
  }
  const upcoming = data?.next_occurrence ? items.shift() : null;
  items.sort((a, b) => Date.parse(b.at || 0) - Date.parse(a.at || 0));
  return upcoming ? [upcoming, ...items] : items;
}

function notificationReadKey() {
  return `worktrack_notification_read:${state.profile?.system_user_id || state.browserId}`;
}

function readNotificationIds() {
  try {
    const stored = JSON.parse(localStorage.getItem(notificationReadKey()) || '[]');
    return new Set(Array.isArray(stored) ? stored : []);
  } catch (_) {
    return new Set();
  }
}

function saveNotificationReadIds(ids) {
  try {
    localStorage.setItem(notificationReadKey(), JSON.stringify([...ids]));
  } catch (_) {}
  lastReminderNotificationSignature = '';
  syncNotificationButton();
}

function syncNotificationButton() {
  if (!els.notificationBtn) return;
  const data = state.reminderNotifications;
  const enabled = Boolean(state.profile?.reminder_enabled);
  const items = notificationDataItems(data);
  const read = readNotificationIds();
  const unreadCount = items.filter((item) => !read.has(item.id)).length;
  const signature = JSON.stringify([enabled, state.profile?.system_user_id, items, [...read], showAllNotifications]);
  if (signature === lastReminderNotificationSignature) return;
  lastReminderNotificationSignature = signature;
  els.notificationBtn.classList.toggle('is-on', enabled);
  els.notificationBtn.classList.toggle('has-system-update', unreadCount > 0);
  els.notificationBtn.classList.remove('is-blocked');
  if (els.notificationCount) {
    els.notificationCount.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
    els.notificationCount.hidden = unreadCount === 0;
  }
  els.notificationBtn.setAttribute('aria-label', unreadCount
    ? `Open notifications, ${unreadCount} unread`
    : 'Open notifications');
  els.notificationItems.replaceChildren();
  for (const item of (showAllNotifications ? items : items.slice(0, 3))) {
    const row = document.createElement('li');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'notification-row';
    button.dataset.notificationId = item.id;
    button.setAttribute('aria-label', `${item.title}: ${item.message}`);
    const icon = document.createElement('span');
    icon.className = `notification-row-icon is-${item.icon}`;
    icon.setAttribute('aria-hidden', 'true');
    const copy = document.createElement('span');
    copy.className = 'notification-row-copy';
    const title = document.createElement('strong');
    title.textContent = item.title;
    if (!read.has(item.id)) {
      const dot = document.createElement('i');
      dot.className = 'notification-unread-dot';
      dot.setAttribute('aria-label', 'Unread');
      title.prepend(dot);
    }
    const message = document.createElement('span');
    message.textContent = item.message;
    const time = document.createElement('small');
    time.textContent = item.at ? formatReminderDateTime(item.at) : '';
    copy.append(title, message, time);
    button.append(icon, copy);
    row.appendChild(button);
    els.notificationItems.appendChild(row);
  }
  if (!items.length) {
    const empty = document.createElement('li');
    empty.className = 'notification-empty';
    empty.textContent = state.profile ? 'No notifications yet.' : 'Complete your profile to set a reminder.';
    els.notificationItems.appendChild(empty);
  }
  els.markAllNotificationsRead.disabled = unreadCount === 0;
  els.viewAllNotifications.hidden = items.length <= 3;
  els.viewAllNotifications.textContent = showAllNotifications ? 'Show recent' : 'View all notifications';
  if (els.reminderSummary) els.reminderSummary.textContent = data?.next_occurrence
    ? 'Next: ' + formatReminderDateTime(data.next_occurrence.scheduled_at)
    : state.profile?.email_verified ? 'Verified email · ' + (enabled ? 'schedule completed' : 'reminders off') : 'Set an email reminder schedule';
  els.notificationPopoverAction.textContent = state.profile ? 'Manage reminders' : 'Complete profile setup';
  els.notificationPopoverAction.disabled = false;
}

function setNotificationPopoverOpen(open) {
  if (open) checkReminder();
  els.notificationPopover.hidden = !open;
  els.notificationBtn.setAttribute('aria-expanded', String(open));
  if (open) fitNotificationPopover();
}

function fitNotificationPopover() {
  if (els.notificationPopover.hidden) return;
  els.notificationPopover.style.setProperty('--notification-shift-x', '0px');
  const rect = els.notificationPopover.getBoundingClientRect();
  const shift = Math.max(12 - rect.left, Math.min(0, window.innerWidth - 12 - rect.right));
  els.notificationPopover.style.setProperty('--notification-shift-x', `${shift}px`);
  els.notificationPopover.style.setProperty('--notification-available-height', `${Math.max(0, window.innerHeight - rect.top - 12)}px`);
}

function closeNotificationPopover(updateRoute = true) {
  notificationHoverSuppressed = true;
  setNotificationPopoverOpen(false);
  if (updateRoute && currentSection() === 'notifications') {
    setRouteSection('calendar', true);
  }
}

async function showWorktrackNotification(title, body, tag = 'worktrack-reminder') {
  const options = { body, tag };

  if ('serviceWorker' in navigator && window.isSecureContext && serviceWorkerUrl) {
    try {
      await navigator.serviceWorker.register(serviceWorkerUrl);
      const registration = await navigator.serviceWorker.ready;
      await registration.showNotification(title, options);
      return true;
    } catch (error) {
      console.warn('Service worker notification failed; trying browser notification.', error);
    }
  }

  if (!('Notification' in window) || Notification.permission !== 'granted') return false;
  new Notification(title, options);
  return true;
}

async function api(path, options = {}, csrfRetried = false) {
  const method = String(options.method || 'GET').toUpperCase();
  const profileEmailWrite = ['/api/worktrack/access/send', '/api/worktrack/access/verify'].includes(path)
    && ['link', 'email_change'].includes(accessPurpose);
  const protectedWrite = !['GET', 'HEAD', 'OPTIONS'].includes(method)
    && (!path.startsWith('/api/worktrack/access/') || profileEmailWrite);
  const expectedProfile = options.headers?.['X-WorkTrack-Profile'] || (protectedWrite ? state.profile?.system_user_id : null);
  const response = await fetch(path, {
    ...options,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(options.headers || {}),
      'X-CSRF-TOKEN': csrfToken,
      ...(expectedProfile ? { 'X-WorkTrack-Profile': expectedProfile } : {}),
    },
  });

  const json = await response.json().catch(() => ({}));
  if (json.csrf_token) updateCsrfToken(json.csrf_token);
  if (response.status === 419 && !csrfRetried) {
    const refresh = await fetch('/api/worktrack/access/status', {
      credentials: 'same-origin', headers: { Accept: 'application/json' },
    });
    const session = await refresh.json().catch(() => ({}));
    if (refresh.ok && session.csrf_token) {
      updateCsrfToken(session.csrf_token);
      return api(path, {
        ...options,
        headers: { ...(options.headers || {}), ...(expectedProfile ? { 'X-WorkTrack-Profile': expectedProfile } : {}) },
      }, true);
    }
  }
  if (!response.ok) {
    if (response.status === 401) {
      clearProfileState();
      openProfileAccess('login', true, { message: 'Session ended. Verify your email to continue.' });
    } else if (response.status === 409 && protectedWrite) {
      discardStaleProfileUi();
      await loadState();
    }
    const firstError = json.errors ? Object.values(json.errors).flat()[0] : null;
    throw new Error(firstError || json.message || 'Request failed');
  }
  if (protectedWrite && expectedProfile && expectedProfile !== state.profile?.system_user_id) {
    discardStaleProfileUi();
    await loadState();
    throw new Error('The active profile changed. Saved records have been reloaded.');
  }
  return json;
}

function updateCsrfToken(token) {
  csrfToken = token;
  document.querySelector('meta[name="csrf-token"]').content = token;
}

function discardStaleProfileUi() {
  closeAll(false);
  accessChallenge = null;
  accessDraft = null;
  state.selectedDate = null;
  clearProfileState();
  setRouteSection('calendar', true);
}

async function loadState({ restoreRoute = true } = {}) {
  const sequence = ++stateLoadSequence;
  const data = await api(`/api/worktrack/state?browser_id=${encodeURIComponent(state.browserId)}`);
  if (sequence !== stateLoadSequence) return;
  lastStateLoadAt = Date.now();
  const profileChanged = Boolean(state.profile?.system_user_id && state.profile.system_user_id !== data.profile?.system_user_id);
  if (profileChanged) {
    discardStaleProfileUi();
    toast('The active profile changed. Saved records have been reloaded.');
  }
  state.profile = data.profile;
  accessRequired = Boolean(data.access_required);
  state.reminderNotifications = data.reminder_notifications || null;
  state.entries = {};
  (data.entries || []).forEach((entry) => {
    state.entries[entry.work_date] = normalizeEntry(entry);
  });
  restoreCalendarMonthFromRoute();
  const routeDate = currentRouteDate();
  if (currentSection() === 'calendar' && currentFeature() === 'entry' && /^\d{4}-\d{2}-\d{2}$/.test(routeDate)) {
    const entryDate = parseDate(routeDate);
    state.selectedDate = routeDate;
    state.viewDate = new Date(entryDate.getFullYear(), entryDate.getMonth(), 1);
  }
  if (lastRenderedDashboardSignature !== dashboardSignature()) renderAll();
  syncNotificationButton();
  if (!restoreRoute && state.profile && !profileChanged) return;
  const accessRoute = currentSection() === 'access' || (currentSection() === 'settings' && currentFeature() === 'email');
  if (accessRoute) {
    await restoreAccessFromRoute();
    return;
  }
  if (!state.profile) {
    if (data.access_required || data.switch_required) {
      openProfileAccess('login', true, { message: data.switch_required
        ? 'Verify the reminder owner’s email to switch.'
        : 'Verify your email to open your saved profile.' });
    } else openProfileSetup(true, true);
    return;
  }
  openSectionFromRoute(false);
}

function normalizeEntry(entry) {
  return {
    ...entry,
    normal_hours: Number(entry.normal_hours || 0),
    extended_hours: Number(entry.extended_hours || 0),
    regular_hours: Number(entry.regular_hours || state.profile?.default_regular_hours || 8),
    total_hours: Number(entry.total_hours || 0),
    daily_pay: Number(entry.daily_pay || 0),
  };
}

function dashboardSignature() {
  const profile = state.profile;
  return JSON.stringify([
    profile && [profile.system_user_id, profile.user_id, profile.nickname,
      profile.work_type, profile.employee_status, profile.hourly_rate],
    state.entries,
    state.viewDate.getFullYear(), state.viewDate.getMonth(),
    state.selectedDate, dateKey(new Date()),
  ]);
}

function renderAll() {
  renderHeader();
  renderSummary();
  renderCalendar();
  renderWeeklyHours();
  lastRenderedDashboardSignature = dashboardSignature();
}

function renderHeader() {
  const year = state.viewDate.getFullYear();
  const month = state.viewDate.getMonth();
  els.monthTitle.innerHTML = `${months[month]} <span>${year}</span>`;
  syncCalendarPeriodPicker();
  els.normalRateText.textContent = state.profile ? `${formatMoney(hourlyRate())}/hr` : '--';

  if (!state.profile) {
    els.profileNote.hidden = true;
    return;
  }

  const nickname = state.profile.nickname || state.profile.user_id || 'User';
  const workType = state.profile.work_type || 'Work';
  const status = employeeStatusLabel(state.profile.employee_status);
  els.profileGreeting.textContent = `Hi, ${nickname}`;
  els.profileMeta.textContent = `${workType} - ${status}`;
  els.profileNote.hidden = false;
}

function syncCalendarPeriodPicker() {
  if (!els.calendarMonthSelect || !els.calendarYearSelect) return;

  if (!els.calendarMonthMenu.children.length) {
    months.forEach((month, index) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'calendar-period-option';
      option.dataset.calendarPeriodOption = 'month';
      option.dataset.value = String(index);
      option.setAttribute('role', 'option');
      option.textContent = month;
      els.calendarMonthMenu.appendChild(option);
    });
  }

  if (!els.calendarYearMenu.children.length) {
    const currentYear = new Date().getFullYear();
    for (let year = 2000; year <= Math.max(2100, currentYear + 10); year += 1) {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'calendar-period-option';
      option.dataset.calendarPeriodOption = 'year';
      option.dataset.value = String(year);
      option.setAttribute('role', 'option');
      option.textContent = String(year);
      els.calendarYearMenu.appendChild(option);
    }
  }

  const month = String(state.viewDate.getMonth());
  const year = String(state.viewDate.getFullYear());
  els.calendarMonthSelect.dataset.value = month;
  els.calendarYearSelect.dataset.value = year;
  els.calendarMonthValue.textContent = months[state.viewDate.getMonth()];
  els.calendarYearValue.textContent = year;
  updateCalendarPeriodOptions(els.calendarMonthMenu, month);
  updateCalendarPeriodOptions(els.calendarYearMenu, year);
}

function updateCalendarPeriodOptions(menu, selectedValue) {
  menu.querySelectorAll('.calendar-period-option').forEach((option) => {
    const isSelected = option.dataset.value === selectedValue;
    option.classList.toggle('is-selected', isSelected);
    option.setAttribute('aria-selected', String(isSelected));
  });
}

function closeCalendarPeriodMenus() {
  [
    [els.calendarMonthSelect, els.calendarMonthMenu],
    [els.calendarYearSelect, els.calendarYearMenu],
  ].forEach(([button, menu]) => {
    if (!button || !menu) return;
    menu.hidden = true;
    button.setAttribute('aria-expanded', 'false');
  });
}

function toggleCalendarPeriodMenu(type) {
  const button = type === 'month' ? els.calendarMonthSelect : els.calendarYearSelect;
  const menu = type === 'month' ? els.calendarMonthMenu : els.calendarYearMenu;
  if (!button || !menu) return;
  const isOpening = menu.hidden;
  closeCalendarPeriodMenus();
  if (isOpening) {
    menu.hidden = false;
    button.setAttribute('aria-expanded', 'true');
  }
}

function chooseCalendarPeriodOption(option) {
  const type = option.dataset.calendarPeriodOption;
  const button = type === 'month' ? els.calendarMonthSelect : els.calendarYearSelect;
  if (!button) return;
  button.dataset.value = option.dataset.value;
  closeCalendarPeriodMenus();
  applyCalendarPeriod(false);
}

function closeCalendarPeriodPicker() {
  if (!els.calendarPeriodPicker || !els.calendarPeriodBtn) return;
  closeCalendarPeriodMenus();
  els.calendarPeriodPicker.hidden = true;
  els.calendarPeriodBtn.setAttribute('aria-expanded', 'false');
}

function toggleCalendarPeriodPicker() {
  if (!els.calendarPeriodPicker || !els.calendarPeriodBtn) return;
  const isOpening = els.calendarPeriodPicker.hidden;
  if (isOpening) {
    syncCalendarPeriodPicker();
    els.calendarPeriodPicker.hidden = false;
    els.calendarPeriodBtn.setAttribute('aria-expanded', 'true');
    return;
  }
  closeCalendarPeriodPicker();
}

function applyCalendarPeriod(closePicker = true) {
  const year = Number(els.calendarYearSelect?.dataset.value);
  const month = Number(els.calendarMonthSelect?.dataset.value);
  if (!Number.isInteger(year) || !Number.isInteger(month) || month < 0 || month > 11) return;

  const monthDelta = (year - state.viewDate.getFullYear()) * 12
    + month - state.viewDate.getMonth();
  state.viewDate = new Date(year, month, 1);
  state.calendarDirection = monthDelta === 0 ? null : monthDelta > 0 ? 'next' : 'previous';
  if (closePicker) closeCalendarPeriodPicker();
  renderAll();
  setRouteSection('calendar', true);
}

function renderSummary() {
  if (!state.profile) {
    setSummaryPlaceholders();
    return;
  }

  const year = state.viewDate.getFullYear();
  const month = state.viewDate.getMonth();
  const lastDay = daysInMonth(year, month);
  let monthPay = 0;
  let monthHours = 0;
  let cutoffOnePay = 0;
  let cutoffOneHours = 0;
  let cutoffTwoPay = 0;
  let cutoffTwoHours = 0;

  Object.values(state.entries).forEach((entry) => {
    const date = parseDate(entry.work_date);
    if (date.getFullYear() !== year || date.getMonth() !== month) return;

    const pay = Number(entry.daily_pay || 0);
    const hours = Number(entry.total_hours || 0);
    monthPay += pay;
    monthHours += hours;

    if (date.getDate() <= 15) {
      cutoffOnePay += pay;
      cutoffOneHours += hours;
    } else {
      cutoffTwoPay += pay;
      cutoffTwoHours += hours;
    }
  });

  els.monthlyEarnings.textContent = formatMoney(monthPay);
  els.monthlyHours.textContent = formatHours(monthHours);
  els.monthlyHoursLabel.textContent = monthHours > 0 ? `${formatHours(monthHours)} hrs logged` : 'No hours logged';
  els.cutoffOnePay.textContent = formatMoney(cutoffOnePay);
  els.cutoffOneHours.textContent = cutoffOneHours > 0 ? `${formatHours(cutoffOneHours)} hrs` : 'No hours';
  els.cutoffTwoPay.textContent = formatMoney(cutoffTwoPay);
  els.cutoffTwoHours.textContent = cutoffTwoHours > 0 ? `${formatHours(cutoffTwoHours)} hrs` : 'No hours';
  els.cutoffTwoRange.textContent = `16-${lastDay}`;
}

function setSummaryPlaceholders() {
  els.monthlyEarnings.textContent = '--';
  els.monthlyHours.textContent = '--';
  els.monthlyHoursLabel.textContent = 'No profile yet';
  els.cutoffOnePay.textContent = '--';
  els.cutoffOneHours.textContent = 'No profile yet';
  els.cutoffTwoPay.textContent = '--';
  els.cutoffTwoHours.textContent = 'No profile yet';
}

function weeklyAnchorDate() {
  if (state.selectedDate) {
    const selected = parseDate(state.selectedDate);
    if (selected.getFullYear() === state.viewDate.getFullYear() && selected.getMonth() === state.viewDate.getMonth()) {
      return selected;
    }
  }

  const today = new Date();
  if (today.getFullYear() === state.viewDate.getFullYear() && today.getMonth() === state.viewDate.getMonth()) {
    return today;
  }
  return new Date(state.viewDate.getFullYear(), state.viewDate.getMonth(), 1);
}

function renderWeeklyHours() {
  if (!els.weeklyHoursChart) return;

  const anchor = weeklyAnchorDate();
  const weekStart = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate() - anchor.getDay());
  const days = [];
  let loggedDays = 0;
  let totalHours = 0;

  for (let index = 0; index < 7; index += 1) {
    const date = new Date(weekStart);
    date.setDate(weekStart.getDate() + index);
    const key = dateKey(date);
    const entry = state.entries[key];
    const hours = entry && entry.status === 'work' ? Number(entry.total_hours || 0) : 0;
    if (hours > 0) loggedDays += 1;
    totalHours += hours;
    days.push({ date, key, hours });
  }

  const weekEnd = days[6].date;
  const sameMonth = weekStart.getMonth() === weekEnd.getMonth();
  const startLabel = weekStart.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
  const endLabel = sameMonth
    ? String(weekEnd.getDate())
    : weekEnd.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  els.weeklyHoursRange.textContent = `${startLabel} - ${endLabel}`;
  els.weeklyHoursSummary.textContent = `${loggedDays} ${loggedDays === 1 ? 'day' : 'days'} logged - ${formatHours(totalHours)} hrs`;

  const maxHours = Math.max(1, ...days.map((day) => day.hours));
  els.weeklyHoursChart.setAttribute(
    'aria-label',
    `Weekly hours from ${startLabel} to ${endLabel}: ${days.map((day) => `${day.date.toLocaleDateString('en-US', { weekday: 'long' })} ${formatHours(day.hours)} hours`).join(', ')}`,
  );
  els.weeklyHoursChart.innerHTML = '';

  days.forEach(({ date, hours }, index) => {
    const item = document.createElement('div');
    item.className = 'weekly-bar-item';
    const value = document.createElement('span');
    value.className = 'weekly-bar-value';
    value.textContent = `${formatHours(hours)}h`;
    const track = document.createElement('div');
    track.className = 'weekly-bar-track';
    const bar = document.createElement('span');
    bar.className = 'weekly-bar-fill';
    bar.style.height = `${hours > 0 ? Math.max(8, (hours / maxHours) * 100) : 2}%`;
    bar.style.setProperty('--bar-delay', `${index * 45}ms`);
    track.appendChild(bar);
    const label = document.createElement('span');
    label.className = 'weekly-bar-day';
    label.textContent = date.toLocaleDateString('en-US', { weekday: 'short' });
    item.append(value, track, label);
    els.weeklyHoursChart.appendChild(item);
  });
}

function buildExportReport() {
  const viewYear = state.viewDate.getFullYear();
  const viewMonth = state.viewDate.getMonth();
  const viewMonthKey = monthKeyForDate(state.viewDate);
  const selected = state.selectedDate ? parseDate(state.selectedDate) : null;
  const today = new Date();
  const anchorDay = selected && selected.getFullYear() === viewYear && selected.getMonth() === viewMonth
    ? selected.getDate()
    : (today.getFullYear() === viewYear && today.getMonth() === viewMonth ? today.getDate() : 1);
  const period = state.dailyRecordsMonth === viewMonthKey
    ? state.dailyRecordsPeriod
    : (anchorDay <= 15 ? 'first' : 'second');
  const startDay = period === 'second' ? 16 : 1;
  const endDay = period === 'first' ? 15 : daysInMonth(viewYear, viewMonth);
  const startKey = dateKey(new Date(viewYear, viewMonth, startDay));
  const endKey = dateKey(new Date(viewYear, viewMonth, endDay));
  const hourlyRateValue = Number(state.profile?.hourly_rate || 0);
  const entries = Object.values(state.entries)
    .filter((entry) => entry.status === 'work' && Number(entry.total_hours || 0) > 0
      && entry.work_date >= startKey && entry.work_date <= endKey)
    .sort((left, right) => left.work_date.localeCompare(right.work_date))
    .map((entry) => {
      const rateMultiplier = entry.rate_type === 'night' ? 1.1 : 1;
      const modifierMultiplier = entry.modifier === 'holiday' ? 1.3 : entry.modifier === 'double' ? 2 : 1;
      const normalHours = Number(entry.normal_hours || 0);
      const extendedHours = Number(entry.extended_hours || 0);
      const appliedRate = Number(entry.hourly_rate_applied ?? hourlyRateValue);
      const regularEarnings = Math.round(normalHours * appliedRate * rateMultiplier * modifierMultiplier * 100) / 100;
      const dailyPay = Number(entry.daily_pay ?? 0);
      const extendedEarnings = Math.round((dailyPay - regularEarnings) * 100) / 100;
      return {
        ...entry,
        normal_hours: normalHours,
        extended_hours: extendedHours,
        total_hours: Number(entry.total_hours || normalHours + extendedHours),
        daily_pay: dailyPay,
        regular_earnings: regularEarnings,
        extended_earnings: extendedEarnings,
      };
    });
  const totals = entries.reduce((sum, entry) => ({
    normal_hours: sum.normal_hours + entry.normal_hours,
    extended_hours: sum.extended_hours + entry.extended_hours,
    total_hours: sum.total_hours + entry.total_hours,
    regular_earnings: sum.regular_earnings + entry.regular_earnings,
    extended_earnings: sum.extended_earnings + entry.extended_earnings,
    earnings: sum.earnings + entry.daily_pay,
  }), {
    normal_hours: 0,
    extended_hours: 0,
    total_hours: 0,
    regular_earnings: 0,
    extended_earnings: 0,
    earnings: 0,
  });
  const periodMonth = new Intl.DateTimeFormat('en-US', { month: 'long' }).format(new Date(viewYear, viewMonth, 1));

  return {
    nickname: state.profile?.nickname || state.profile?.user_id || '—',
    workType: employeeStatusLabel(state.profile?.employee_status || 'full_time').replace(/\s+/g, '-'),
    role: state.profile?.work_type || '—',
    hourlyRate: hourlyRateValue,
    period: `${periodMonth} ${startDay}–${endDay}, ${viewYear}`,
    entries,
    totals,
    daysWorked: entries.length,
    notes: entries.filter((entry) => String(entry.note || '').trim()).map((entry) => ({
      date: new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric' }).format(parseDate(entry.work_date)),
      text: String(entry.note).trim(),
    })),
  };
}

function reportDate(key) {
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(parseDate(key));
}

function reportTime(value) {
  if (!value) return '—';
  const [hourPart, minutePart = '00'] = String(value).split(':');
  const hour24 = Number(hourPart);
  if (!Number.isFinite(hour24)) return '—';
  const period = hour24 >= 12 ? 'PM' : 'AM';
  const hour12 = hour24 % 12 || 12;
  return `${pad(hour12)}:${minutePart.slice(0, 2)} ${period}`;
}

function monthKeyForDate(date) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

function monthLabel(monthKey) {
  const [year, month] = monthKey.split('-').map(Number);
  return new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' })
    .format(new Date(year, month - 1, 1));
}

function openDailyRecords(updateRoute = true, restoreRoute = false) {
  if (!state.profile) {
    openProfileSetup();
    return;
  }

  closeAll(false);
  const visibleMonth = monthKeyForDate(state.viewDate);
  const selectedDate = state.selectedDate ? parseDate(state.selectedDate) : null;
  const today = new Date();
  const anchorDate = selectedDate && monthKeyForDate(selectedDate) === visibleMonth
    ? selectedDate
    : monthKeyForDate(today) === visibleMonth ? today : state.viewDate;
  const query = new URLSearchParams(window.location.search);
  const requestedMonth = restoreRoute ? query.get('month') : '';
  const requestedPeriod = restoreRoute ? query.get('period') : '';
  const validMonth = /^\d{4}-(0[1-9]|1[0-2])$/.test(requestedMonth || '');
  state.dailyRecordsMonth = validMonth ? requestedMonth : visibleMonth;
  state.dailyRecordsPeriod = ['first', 'second', 'full'].includes(requestedPeriod)
    ? requestedPeriod
    : anchorDate.getDate() <= 15 ? 'first' : 'second';
  if (validMonth) {
    const [year, month] = requestedMonth.split('-').map(Number);
    state.viewDate = new Date(year, month - 1, 1);
  }
  renderDailyRecords();
  els.dailyRecordsOverlay.hidden = false;
  els.dailyRecordsBtn.setAttribute('aria-expanded', 'true');
  lockPageScroll();
  if (updateRoute) {
    setCalendarOverlayRoute('records', {
      month: state.dailyRecordsMonth,
      period: state.dailyRecordsPeriod,
    });
  } else {
    setPageTitle('calendar', 'records');
  }
  window.requestAnimationFrame(() => els.closeDailyRecordsBtn.focus());
}

function closeDailyRecords(unlock = true, updateRoute = true) {
  if (!els.dailyRecordsOverlay || els.dailyRecordsOverlay.hidden) return;
  els.dailyRecordsOverlay.hidden = true;
  els.dailyRecordsBtn.setAttribute('aria-expanded', 'false');
  if (updateRoute && currentSection() === 'calendar' && currentFeature() === 'records') {
    setRouteSection('calendar', true);
  }
  if (unlock) {
    unlockPageScroll();
    els.dailyRecordsBtn.focus();
  }
}

function renderDailyRecords() {
  const availableMonths = new Set(Object.keys(state.entries).map((key) => key.slice(0, 7)));
  const years = new Set([
    new Date().getFullYear(),
    state.viewDate.getFullYear(),
    Number(state.dailyRecordsMonth?.slice(0, 4)),
  ]);
  for (const year of years) {
    if (!Number.isInteger(year) || year < 1) continue;
    for (let month = 1; month <= 12; month++) {
      availableMonths.add(`${year}-${pad(month)}`);
    }
  }
  const selectedDateMonth = state.selectedDate ? state.selectedDate.slice(0, 7) : null;
  if (selectedDateMonth) availableMonths.add(selectedDateMonth);
  const sortedMonths = [...availableMonths].sort((a, b) => b.localeCompare(a));

  if (!sortedMonths.includes(state.dailyRecordsMonth)) {
    state.dailyRecordsMonth = sortedMonths[0] || monthKeyForDate(new Date());
  }

  els.dailyRecordsMonth.replaceChildren();
  sortedMonths.forEach((key) => {
    const option = document.createElement('option');
    option.value = key;
    option.textContent = monthLabel(key);
    els.dailyRecordsMonth.appendChild(option);
  });
  els.dailyRecordsMonth.value = state.dailyRecordsMonth;

  els.dailyRecordsOverlay.querySelectorAll('[data-record-period]').forEach((button) => {
    const active = button.dataset.recordPeriod === state.dailyRecordsPeriod;
    button.classList.toggle('is-active', active);
    button.setAttribute('aria-pressed', String(active));
  });

  const [year, month] = state.dailyRecordsMonth.split('-').map(Number);
  const lastDay = daysInMonth(year, month - 1);
  const entries = Object.values(state.entries)
    .filter((entry) => entry.work_date?.slice(0, 7) === state.dailyRecordsMonth)
    .filter((entry) => {
      const day = parseDate(entry.work_date).getDate();
      if (state.dailyRecordsPeriod === 'first') return day <= 15;
      if (state.dailyRecordsPeriod === 'second') return day >= 16;
      return true;
    })
    .sort((a, b) => a.work_date.localeCompare(b.work_date));

  els.dailyRecordsList.replaceChildren();
  if (!entries.length) {
    const empty = document.createElement('p');
    empty.className = 'daily-records-empty';
    empty.textContent = 'No saved records for this period.';
    els.dailyRecordsList.appendChild(empty);
  } else {
    entries.forEach((entry) => {
      const isWork = entry.status === 'work';
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'daily-record-row';
      row.dataset.recordDate = entry.work_date;
      const entryStatus = entry.status === 'rest' ? 'Rest day' : entry.status === 'absent' ? 'Excused absence' : 'Work entry';
      const timeText = isWork && (entry.time_in || entry.time_out)
        ? `${reportTime(entry.time_in)} – ${reportTime(entry.time_out)}`
        : entryStatus;
      const rowLabel = document.createElement('span');
      rowLabel.className = 'daily-record-row-copy';
      const date = document.createElement('strong');
      date.textContent = formatLongDate(entry.work_date);
      const time = document.createElement('span');
      time.className = 'daily-record-row-time';
      time.textContent = timeText;
      rowLabel.append(date, time);
      const noteText = String(entry.note || '').trim();
      if (noteText) {
        const note = document.createElement('span');
        note.className = 'daily-record-row-note';
        note.textContent = noteText;
        rowLabel.appendChild(note);
      }

      const metrics = document.createElement('span');
      metrics.className = 'daily-record-row-metrics';
      const hours = document.createElement('span');
      hours.textContent = isWork ? `${formatHours(entry.total_hours)} hrs` : entryStatus;
      const pay = document.createElement('strong');
      pay.textContent = isWork ? formatMoney(entry.daily_pay) : '—';
      metrics.append(hours, pay);
      const chevron = document.createElement('span');
      chevron.className = 'daily-record-chevron';
      chevron.setAttribute('aria-hidden', 'true');
      chevron.textContent = '›';
      row.append(rowLabel, metrics, chevron);
      row.setAttribute('aria-label', `${formatLongDate(entry.work_date)}, ${timeText}, ${isWork ? `${formatHours(entry.total_hours)} hours, ${formatMoney(entry.daily_pay)}` : entryStatus}`);
      els.dailyRecordsList.appendChild(row);
    });
  }

  const workEntries = entries.filter((entry) => entry.status === 'work');
  const totalHours = workEntries.reduce((sum, entry) => sum + Number(entry.total_hours || 0), 0);
  const earnings = workEntries.reduce((sum, entry) => sum + Number(entry.daily_pay || 0), 0);
  const daysWorked = workEntries.filter((entry) => Number(entry.total_hours || 0) > 0).length;
  const monthShort = new Intl.DateTimeFormat('en-US', { month: 'short' }).format(new Date(year, month - 1, 1));
  const periodTitle = state.dailyRecordsPeriod === 'first'
    ? `${monthShort} 1–15`
    : state.dailyRecordsPeriod === 'second'
      ? `${monthShort} 16–${lastDay}`
      : monthLabel(state.dailyRecordsMonth);
  const title = document.createElement('strong');
  title.textContent = `${state.dailyRecordsPeriod === 'full' ? 'Month total' : 'Cutoff total'} · ${periodTitle}`;
  const detail = document.createElement('span');
  detail.textContent = `${daysWorked} ${daysWorked === 1 ? 'day' : 'days'} · ${formatHours(totalHours)} hrs`;
  const amount = document.createElement('strong');
  amount.className = 'daily-records-total-amount';
  amount.textContent = formatMoney(earnings);
  els.dailyRecordsTotal.replaceChildren(title, detail, amount);
}

function exportFileName(extension) {
  const today = dateKey(new Date());
  return `WorkTrack-${today}.${extension}`;
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function crc32(bytes) {
  let crc = 0xffffffff;
  for (let index = 0; index < bytes.length; index += 1) {
    crc ^= bytes[index];
    for (let bit = 0; bit < 8; bit += 1) crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
  }
  return (crc ^ 0xffffffff) >>> 0;
}

function makeStoredZip(files) {
  const encoder = new TextEncoder();
  const date = new Date();
  const dosTime = (date.getHours() << 11) | (date.getMinutes() << 5) | Math.floor(date.getSeconds() / 2);
  const dosDate = ((date.getFullYear() - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate();
  const localRecords = [];
  const centralRecords = [];
  let localOffset = 0;

  files.forEach((file) => {
    const name = encoder.encode(file.name);
    const contents = encoder.encode(file.contents);
    const checksum = crc32(contents);
    const local = new Uint8Array(30 + name.length + contents.length);
    const localView = new DataView(local.buffer);
    localView.setUint32(0, 0x04034b50, true);
    localView.setUint16(4, 20, true);
    localView.setUint16(6, 0x0800, true);
    localView.setUint16(8, 0, true);
    localView.setUint16(10, dosTime, true);
    localView.setUint16(12, dosDate, true);
    localView.setUint32(14, checksum, true);
    localView.setUint32(18, contents.length, true);
    localView.setUint32(22, contents.length, true);
    localView.setUint16(26, name.length, true);
    localView.setUint16(28, 0, true);
    local.set(name, 30);
    local.set(contents, 30 + name.length);
    localRecords.push(local);

    const central = new Uint8Array(46 + name.length);
    const centralView = new DataView(central.buffer);
    centralView.setUint32(0, 0x02014b50, true);
    centralView.setUint16(4, 20, true);
    centralView.setUint16(6, 20, true);
    centralView.setUint16(8, 0x0800, true);
    centralView.setUint16(10, 0, true);
    centralView.setUint16(12, dosTime, true);
    centralView.setUint16(14, dosDate, true);
    centralView.setUint32(16, checksum, true);
    centralView.setUint32(20, contents.length, true);
    centralView.setUint32(24, contents.length, true);
    centralView.setUint16(28, name.length, true);
    centralView.setUint16(30, 0, true);
    centralView.setUint16(32, 0, true);
    centralView.setUint16(34, 0, true);
    centralView.setUint16(36, 0, true);
    centralView.setUint32(38, 0, true);
    centralView.setUint32(42, localOffset, true);
    central.set(name, 46);
    centralRecords.push(central);
    localOffset += local.length;
  });

  const centralSize = centralRecords.reduce((sum, record) => sum + record.length, 0);
  const end = new Uint8Array(22);
  const endView = new DataView(end.buffer);
  endView.setUint32(0, 0x06054b50, true);
  endView.setUint16(4, 0, true);
  endView.setUint16(6, 0, true);
  endView.setUint16(8, files.length, true);
  endView.setUint16(10, files.length, true);
  endView.setUint32(12, centralSize, true);
  endView.setUint32(16, localOffset, true);
  endView.setUint16(20, 0, true);

  const zip = new Uint8Array(localOffset + centralSize + end.length);
  let offset = 0;
  [...localRecords, ...centralRecords, end].forEach((record) => {
    zip.set(record, offset);
    offset += record.length;
  });
  return zip;
}

function xmlEscape(value) {
  return String(value ?? '')
    .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '')
    .replace(/[&<>"']/g, (character) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;',
    })[character]);
}

function createXlsxBlob(report) {
  const rows = [
    { height: 34, cells: [{ col: 1, value: 'WORKTRACK', style: 1 }] },
    { height: 28, cells: [{ col: 1, value: 'Work Hours & Earnings', style: 2 }] },
    { height: 14, cells: [] },
    { height: 25, cells: [{ col: 1, value: 'Name:', style: 3 }, { col: 2, value: report.nickname, style: 4 }] },
    { height: 25, cells: [{ col: 1, value: 'Period:', style: 3 }, { col: 2, value: report.period, style: 4 }] },
    { height: 14, cells: [] },
    { height: 28, cells: [
      { col: 1, value: 'Date', style: 5 },
      { col: 2, value: 'Time In–Out', style: 5 },
      { col: 3, value: 'Hours', style: 5 },
      { col: 4, value: 'Earnings (PHP)', style: 5 },
    ] },
  ];
  report.entries.forEach((entry) => rows.push({ height: 25, cells: [
    { col: 1, value: reportDate(entry.work_date), style: 6 },
    { col: 2, value: `${reportTime(entry.time_in)}–${reportTime(entry.time_out)}`, style: 7 },
    { col: 3, value: entry.total_hours, style: 8, number: true },
    { col: 4, value: entry.daily_pay, style: 9, number: true },
  ] }));
  rows.push({ height: 28, cells: [
    { col: 1, value: 'TOTAL', style: 10 },
    { col: 2, value: '', style: 10 },
    { col: 3, value: report.totals.total_hours, style: 10, number: true },
    { col: 4, value: report.totals.earnings, style: 11, number: true },
  ] });

  const columnName = (number) => {
    let name = '';
    while (number > 0) {
      const remainder = (number - 1) % 26;
      name = String.fromCharCode(65 + remainder) + name;
      number = Math.floor((number - 1) / 26);
    }
    return name;
  };
  const sheetRows = rows.map((row, rowIndex) => {
    const rowNumber = rowIndex + 1;
    const cells = row.cells.map((cell) => {
      const reference = `${columnName(cell.col)}${rowNumber}`;
      const style = ` s="${cell.style}"`;
      if (cell.number && Number.isFinite(Number(cell.value))) {
        return `<c r="${reference}"${style}><v>${Number(cell.value)}</v></c>`;
      }
      return `<c r="${reference}"${style} t="inlineStr"><is><t xml:space="preserve">${xmlEscape(cell.value)}</t></is></c>`;
    }).join('');
    return `<row r="${rowNumber}" ht="${row.height}" customHeight="1">${cells}</row>`;
  }).join('');
  const styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts><fonts count="4"><font><sz val="11"/><name val="Arial"/><color rgb="FF111111"/></font><font><b/><sz val="18"/><name val="Arial"/><color rgb="FF111111"/></font><font><b/><sz val="14"/><name val="Arial"/><color rgb="FF111111"/></font><font><b/><sz val="11"/><name val="Arial"/><color rgb="FF111111"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF0EA"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF111111"/></left><right style="thin"><color rgb="FF111111"/></right><top style="thin"><color rgb="FF111111"/></top><bottom style="thin"><color rgb="FF111111"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="12"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="1" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment vertical="center"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="1" borderId="1" xfId="0"><alignment vertical="center"/></xf><xf numFmtId="164" fontId="3" fillId="1" borderId="1" xfId="0"><alignment horizontal="right" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
  const files = [
    {
      name: '[Content_Types].xml',
      contents: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
    },
    {
      name: '_rels/.rels',
      contents: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
    },
    {
      name: 'xl/workbook.xml',
      contents: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Records" sheetId="1" r:id="rId1"/></sheets></workbook>',
    },
    {
      name: 'xl/_rels/workbook.xml.rels',
      contents: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
    },
    {
      name: 'xl/styles.xml',
      contents: styles,
    },
    {
      name: 'xl/worksheets/sheet1.xml',
      contents: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:D${rows.length}"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="7" topLeftCell="A8" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="22" customWidth="1"/><col min="2" max="2" width="32" customWidth="1"/><col min="3" max="3" width="16" customWidth="1"/><col min="4" max="4" width="22" customWidth="1"/></cols><sheetData>${sheetRows}</sheetData><mergeCells count="4"><mergeCell ref="A1:D1"/><mergeCell ref="A2:D2"/><mergeCell ref="B4:D4"/><mergeCell ref="B5:D5"/></mergeCells><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/></worksheet>`,
    },
  ];
  return new Blob([makeStoredZip(files)], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
}

function exportExcel() {
  downloadBlob(createXlsxBlob(buildExportReport()), exportFileName('xlsx'));
}

function reportCurrency(value) {
  return `PHP ${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[character]);
}

function buildReportHtml(report) {
  const tableRows = report.entries.map((entry) => `<tr><td>${escapeHtml(reportDate(entry.work_date))}</td><td>${escapeHtml(reportTime(entry.time_in))}</td><td>${escapeHtml(reportTime(entry.time_out))}</td><td class="number">${formatHours(entry.normal_hours)}</td><td class="number">${formatHours(entry.extended_hours)}</td><td class="number">${formatHours(entry.total_hours)}</td><td class="number">${reportCurrency(entry.daily_pay)}</td></tr>`).join('');
  const noteRows = report.notes.length
    ? report.notes.map((note) => `<p><strong>${escapeHtml(note.date)}</strong> — ${escapeHtml(note.text)}</p>`).join('')
    : '<p>No attendance notes for this period.</p>';
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>WORKTRACK Work Hours &amp; Earnings Report</title><style>
    *{box-sizing:border-box}body{margin:0;background:#fff;color:#111;font:14px/1.45 Arial,Helvetica,sans-serif}.report{max-width:1100px;margin:0 auto;padding:38px 44px 54px}header{text-align:center;margin:8px 0 28px}h1{font-size:30px;letter-spacing:.3px;margin:0 0 4px}h2{font-size:23px;margin:0}.meta{display:grid;grid-template-columns:255px 1fr;gap:7px 18px;margin:0 0 24px}.meta strong{font-weight:700}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #222;padding:9px 8px}th{font-weight:700;text-align:center;background:#f3f4f3}td.number{text-align:right;white-space:nowrap}tfoot td{font-weight:700}.summary{display:grid;grid-template-columns:300px 1fr;gap:8px 20px;margin:28px 0}.notes{margin-top:25px}.notes h3{font-size:16px;margin:0 0 8px}.notes p{margin:5px 0}.footer{margin-top:52px}.muted{color:#4b5563}@page{size:A4 portrait;margin:14mm}@media print{.report{max-width:none;padding:0}body{font-size:10pt}table{font-size:8.5pt}th,td{padding:6px 5px}thead{display:table-header-group}tr{break-inside:avoid}.notes,.summary,.meta{break-inside:avoid}}
  </style></head><body><main class="report"><header><h1>WORKTRACK</h1><h2>WORK HOURS &amp; EARNINGS REPORT</h2></header><section class="meta"><strong>Name / Nickname:</strong><span>${escapeHtml(report.nickname)}</span><strong>Work type:</strong><span>${escapeHtml(report.workType)}</span><strong>Role:</strong><span>${escapeHtml(report.role)}</span><strong>Period:</strong><span>${escapeHtml(report.period)}</span><strong>Hourly rate:</strong><span>${reportCurrency(report.hourlyRate)}</span><strong>Extended-hour multiplier:</strong><span>1.25</span></section><table><thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Regular Hrs</th><th>Extended Hrs</th><th>Total Hrs</th><th>Earnings (PHP)</th></tr></thead><tbody>${tableRows || '<tr><td colspan="7" class="muted">No work entries for this period.</td></tr>'}</tbody><tfoot><tr><td><strong>TOTAL</strong></td><td></td><td></td><td class="number">${formatHours(report.totals.normal_hours)}</td><td class="number">${formatHours(report.totals.extended_hours)}</td><td class="number">${formatHours(report.totals.total_hours)}</td><td class="number">${reportCurrency(report.totals.earnings)}</td></tr></tfoot></table><section class="summary"><strong>Days worked:</strong><span>${report.daysWorked}</span><strong>Regular earnings:</strong><span>${reportCurrency(report.totals.regular_earnings)}</span><strong>Extended-hour earnings:</strong><span>${reportCurrency(report.totals.extended_earnings)}</span><strong>Total estimated earnings:</strong><strong>${reportCurrency(report.totals.earnings)}</strong></section><section class="notes"><h3>Daily Notes</h3>${noteRows}</section><p class="footer">Based on saved work records for the selected period.</p></main></body></html>`;
}

function exportPdf() {
  const printWindow = window.open('', '_blank');
  if (!printWindow) throw new Error('Allow pop-ups to open the report for PDF printing.');
  printWindow.document.open();
  printWindow.document.write(buildReportHtml(buildExportReport()));
  printWindow.document.close();
  let printStarted = false;
  const startPrint = () => {
    if (printStarted || printWindow.closed) return;
    printStarted = true;
    printWindow.focus();
    printWindow.print();
  };
  printWindow.addEventListener('load', () => window.setTimeout(startPrint, 120), { once: true });
  window.setTimeout(() => {
    if (!printWindow.closed && printWindow.document.readyState === 'complete') startPrint();
  }, 700);
}

function wrapCanvasText(context, text, maxWidth, maxLines = 3) {
  const words = String(text || '').split(/\s+/);
  const lines = [];
  let line = '';
  words.forEach((word) => {
    const candidate = line ? `${line} ${word}` : word;
    if (line && context.measureText(candidate).width > maxWidth) {
      lines.push(line);
      line = word;
    } else line = candidate;
  });
  if (line) lines.push(line);
  if (lines.length > maxLines) {
    const clipped = lines.slice(0, maxLines);
    clipped[maxLines - 1] = `${clipped[maxLines - 1].replace(/[.…]+$/, '')}…`;
    return clipped;
  }
  return lines;
}

function exportJpg() {
  const report = buildExportReport();
  const width = 1600;
  const margin = 70;
  const tableWidth = width - margin * 2;
  const columns = [200, 180, 180, 195, 195, 195, 315];
  const tableHeaderHeight = 54;
  const rowHeight = 48;
  const textMeasure = document.createElement('canvas').getContext('2d');
  if (!textMeasure) throw new Error('Image export is unavailable in this browser.');
  const notesMeasured = report.notes.map((note) => {
    textMeasure.font = '24px Arial, sans-serif';
    return { ...note, lines: wrapCanvasText(textMeasure, `${note.date} — ${note.text}`, tableWidth - 24, 3) };
  });
  const metaTop = 240;
  const tableTop = metaTop + 6 * 44 + 38;
  const summaryTop = tableTop + tableHeaderHeight + Math.max(1, report.entries.length) * rowHeight + rowHeight + 56;
  const notesTop = summaryTop + 4 * 44 + 50;
  const notesHeight = 68 + notesMeasured.reduce((sum, note) => sum + note.lines.length * 34 + 16, 0);
  const height = Math.max(1150, notesTop + notesHeight + 105);
  const canvas = document.createElement('canvas');
  const context = canvas.getContext('2d');
  if (!context) throw new Error('Image export is unavailable in this browser.');
  canvas.width = width;
  canvas.height = height;
  context.fillStyle = '#fff';
  context.fillRect(0, 0, width, height);
  context.strokeStyle = '#d1d5d1';
  context.lineWidth = 2;
  context.strokeRect(26, 24, width - 52, height - 48);
  context.textAlign = 'center';
  context.fillStyle = '#111';
  context.font = '700 48px Arial, sans-serif';
  context.fillText('WORKTRACK', width / 2, 95);
  context.font = '700 34px Arial, sans-serif';
  context.fillText('WORK HOURS & EARNINGS REPORT', width / 2, 146);
  context.textAlign = 'left';

  const metadata = [
    ['Name / Nickname:', report.nickname],
    ['Work type:', report.workType],
    ['Role:', report.role],
    ['Period:', report.period],
    ['Hourly rate:', reportCurrency(report.hourlyRate)],
    ['Extended-hour multiplier:', '1.25'],
  ];
  let y = metaTop;
  metadata.forEach(([label, value]) => {
    context.fillStyle = '#111';
    context.font = '700 23px Arial, sans-serif';
    context.fillText(label, margin + 2, y);
    context.font = '23px Arial, sans-serif';
    context.fillText(value, margin + 405, y);
    y += 44;
  });

  const headers = ['Date', 'Time In', 'Time Out', 'Regular Hrs', 'Extended Hrs', 'Total Hrs', 'Earnings (PHP)'];
  const drawCell = (x, yPos, cellWidth, cellHeight, text, options = {}) => {
    context.fillStyle = options.fill || '#fff';
    context.fillRect(x, yPos, cellWidth, cellHeight);
    context.strokeStyle = '#222';
    context.lineWidth = 1.5;
    context.strokeRect(x, yPos, cellWidth, cellHeight);
    context.fillStyle = '#111';
    context.font = `${options.bold ? '700' : '400'} ${options.fontSize || 20}px Arial, sans-serif`;
    context.textAlign = options.align || 'center';
    context.textBaseline = 'middle';
    context.fillText(String(text ?? ''), options.align === 'left' ? x + 12 : options.align === 'right' ? x + cellWidth - 12 : x + cellWidth / 2, yPos + cellHeight / 2, cellWidth - 20);
    context.textAlign = 'left';
  };
  let x = margin;
  headers.forEach((header, index) => {
    drawCell(x, tableTop, columns[index], tableHeaderHeight, header, { bold: true, fill: '#f4f5f4', fontSize: 18 });
    x += columns[index];
  });
  const reportRows = report.entries.length ? report.entries : [null];
  reportRows.forEach((entry, rowIndex) => {
    x = margin;
    const values = entry
      ? [reportDate(entry.work_date), reportTime(entry.time_in), reportTime(entry.time_out), formatHours(entry.normal_hours), formatHours(entry.extended_hours), formatHours(entry.total_hours), reportCurrency(entry.daily_pay)]
      : ['No work entries for this period.', '', '', '', '', '', ''];
    values.forEach((value, index) => {
      drawCell(x, tableTop + tableHeaderHeight + rowIndex * rowHeight, columns[index], rowHeight, value, { align: index === 0 ? 'left' : index === 6 ? 'right' : 'center', fontSize: 18 });
      x += columns[index];
    });
  });
  const totalY = tableTop + tableHeaderHeight + reportRows.length * rowHeight;
  const totals = ['TOTAL', '', '', formatHours(report.totals.normal_hours), formatHours(report.totals.extended_hours), formatHours(report.totals.total_hours), reportCurrency(report.totals.earnings)];
  x = margin;
  totals.forEach((value, index) => {
    drawCell(x, totalY, columns[index], rowHeight, value, { bold: true, fill: '#f4f5f4', align: index === 0 ? 'left' : index === 6 ? 'right' : 'center', fontSize: 18 });
    x += columns[index];
  });

  const summary = [
    ['Days worked:', String(report.daysWorked)],
    ['Regular earnings:', reportCurrency(report.totals.regular_earnings)],
    ['Extended-hour earnings:', reportCurrency(report.totals.extended_earnings)],
    ['Total estimated earnings:', reportCurrency(report.totals.earnings)],
  ];
  y = summaryTop;
  summary.forEach(([label, value], index) => {
    context.fillStyle = '#111';
    context.font = `${index === 3 ? '700' : '400'} 23px Arial, sans-serif`;
    context.fillText(label, margin + 2, y);
    context.fillText(value, margin + 500, y);
    y += 44;
  });
  context.font = '700 24px Arial, sans-serif';
  context.fillText('Daily Notes', margin + 2, notesTop);
  y = notesTop + 45;
  context.font = '23px Arial, sans-serif';
  if (!notesMeasured.length) {
    context.fillText('No attendance notes for this period.', margin + 2, y);
    y += 36;
  } else {
    notesMeasured.forEach((note) => {
      note.lines.forEach((line) => {
        context.fillText(line, margin + 2, y);
        y += 34;
      });
      y += 12;
    });
  }
  context.fillStyle = '#333';
  context.font = '20px Arial, sans-serif';
  context.fillText('Based on saved work records for the selected period.', margin + 2, height - 72);

  canvas.toBlob((blob) => {
    if (!blob) {
      toast('Could not create the image file');
      return;
    }
    downloadBlob(blob, exportFileName('jpg'));
  }, 'image/jpeg', 0.94);
}

function closeExportMenu() {
  if (!els.exportMenu || !els.exportMenuButton) return;
  els.exportMenu.hidden = true;
  els.exportMenuButton.setAttribute('aria-expanded', 'false');
}

function toggleExportMenu() {
  const open = els.exportMenu.hidden;
  els.exportMenu.hidden = !open;
  els.exportMenuButton.setAttribute('aria-expanded', String(open));
}

function renderCalendar() {
  const year = state.viewDate.getFullYear();
  const month = state.viewDate.getMonth();
  const firstDay = new Date(year, month, 1);
  const start = new Date(year, month, 1 - firstDay.getDay());
  const todayKey = dateKey(new Date());
  const selectedKey = state.selectedDate;

  const transitionClass = state.calendarDirection === 'next'
    ? 'is-month-entering-next'
    : state.calendarDirection === 'previous'
      ? 'is-month-entering-previous'
      : null;
  els.calendarGrid.classList.remove('is-month-entering-next', 'is-month-entering-previous');
  els.calendarGrid.innerHTML = '';

  for (let index = 0; index < 42; index += 1) {
    const date = new Date(start);
    date.setDate(start.getDate() + index);
    const key = dateKey(date);
    const entry = state.entries[key];
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'day-cell';
    button.dataset.date = key;
    button.setAttribute('aria-label', formatLongDate(key));

    if (date.getMonth() !== month) button.classList.add('is-muted');
    if (key === todayKey) button.classList.add('is-today');
    if (key === selectedKey) button.classList.add('is-selected');
    if (entry) {
      button.classList.add('has-entry');
      if (entry.status === 'rest') button.classList.add('is-rest');
      if (entry.status === 'absent') button.classList.add('is-absent');
      if (entry.modifier === 'holiday') button.classList.add('is-holiday');
      if (entry.modifier === 'double') button.classList.add('is-double');
    }

    button.innerHTML = `
      <span class="day-num">${date.getDate()}</span>
      <span class="day-meta">${entry ? `<span class="day-entry-dot" aria-hidden="true"></span><span>${dayMeta(entry)}</span>` : ''}</span>
    `;
    button.addEventListener('click', () => openEntry(key));
    els.calendarGrid.appendChild(button);
  }

  state.calendarDirection = null;
  if (transitionClass) {
    void els.calendarGrid.offsetWidth;
    els.calendarGrid.classList.add(transitionClass);
    els.calendarGrid.addEventListener('animationend', (event) => {
      if (event.target === els.calendarGrid) els.calendarGrid.classList.remove(transitionClass);
    }, { once: true });
  }
}

function dayMeta(entry) {
  if (entry.status === 'rest') return 'Rest';
  if (entry.status === 'absent') return 'Absent';
  if (entry.total_hours > 0) return `${formatHours(entry.total_hours)}h`;
  return '';
}

function changeMonth(delta) {
  state.viewDate = new Date(state.viewDate.getFullYear(), state.viewDate.getMonth() + delta, 1);
  state.calendarDirection = delta > 0 ? 'next' : 'previous';
  renderAll();
  setRouteSection('calendar', true);
}

function goToday() {
  const today = new Date();
  const monthDelta = (today.getFullYear() - state.viewDate.getFullYear()) * 12
    + today.getMonth() - state.viewDate.getMonth();
  state.viewDate = new Date(today.getFullYear(), today.getMonth(), 1);
  state.calendarDirection = monthDelta === 0 ? null : monthDelta > 0 ? 'next' : 'previous';
  renderAll();
  setRouteSection('calendar', true);
}

function openSectionFromRoute(renderDashboard = true) {
  const section = currentSection();
  const feature = currentFeature();
  const date = currentRouteDate();
  restoreCalendarMonthFromRoute();
  if (section === 'access' || (section === 'settings' && feature === 'email')) {
    restoreAccessFromRoute().catch(handleApiError);
    return;
  }
  if (section === 'calendar' && feature === 'records') {
    if (renderDashboard) renderAll();
    openDailyRecords(false, true);
    return;
  }
  if (section === 'calendar' && feature === 'reset') {
    if (renderDashboard) renderAll();
    closeAll(false);
    els.confirmOverlay.hidden = false;
    lockPageScroll();
    setPageTitle('calendar', 'reset');
    return;
  }
  if (section === 'calendar' && feature === 'entry' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
    openEntry(date, false, renderDashboard);
    const overlay = currentRouteOverlay();
    if (overlay === 'time-in' || overlay === 'time-out') {
      setEntryEditMode(true);
      const isTimeIn = overlay === 'time-in';
      openTimePicker(isTimeIn ? els.timeInInput : els.timeOutInput, isTimeIn ? 'Time In' : 'Time Out');
    }
    return;
  }
  if (section === 'calendar' && renderDashboard) renderAll();
  if (section === 'notifications') {
    closeAll(false);
    setNotificationPopoverOpen(true);
    setPageTitle('notifications');
    return;
  }
  if (section === 'settings') {
    if (feature === 'reminder') {
      openReminderSettings(false, date === 'time' ? 'schedule' : ['verify', 'schedule'].includes(date) ? date : 'email');
      if (date === 'time') showReminderTimePicker(false);
    } else openSettings(false);
    return;
  }
  if (section === 'profile-setup') {
    openProfileSetup(false);
    return;
  }
  closeAll(false);
  setPageTitle('calendar');
}

function openProfileSetup(updateRoute = true, replaceRoute = false) {
  closeAll(false);
  fillProfileForm('profile');
  els.profileOverlay.hidden = false;
  lockPageScroll();
  if (updateRoute) setRouteSection('profile-setup', replaceRoute);
  else setPageTitle('profile-setup');
}

function openSettings(updateRoute = true, replaceRoute = false) {
  accessChallenge = null;
  if (!state.profile) {
    openProfileSetup(updateRoute, replaceRoute);
    return;
  }
  closeAll(false);
  fillProfileForm('settings');
  setSettingsEditMode(false);
  els.settingsSubtitle.textContent = 'Profile and reminder';
  els.settingsOverlay.hidden = false;
  els.settingsOverlay.querySelector('.settings-card').scrollTop = 0;
  lockPageScroll();
  if (updateRoute) setRouteSection('settings', replaceRoute);
  else setPageTitle('settings');
}

function setSettingsEditMode(editing) {
  settingsEditMode = editing;
  els.settingsForm.classList.toggle('is-readonly', !editing);
  [
    els.settingsNickname,
    els.settingsWorkType,
    els.settingsEmployeeStatus,
    els.settingsHourlyRate,
    els.settingsRegularHours,
  ].forEach((field) => {
    if (field.tagName === 'SELECT') field.disabled = !editing;
    else field.readOnly = !editing;
  });

  els.settingsEditSaveBtn.type = 'button';
  els.settingsEditSaveBtn.textContent = editing ? 'Save Settings' : 'Edit';
  els.settingsEditSaveBtn.setAttribute('aria-label', editing ? 'Save profile settings' : 'Edit profile settings');
}

function fillProfileForm(scope) {
  const profile = state.profile || {};
  if (scope === 'profile') {
    els.profileEmail.value = profile.email || '';
    els.profileNickname.value = profile.nickname || '';
    els.profileWorkType.value = profile.work_type || '';
    els.profileEmployeeStatus.value = profile.employee_status || 'full_time';
    els.profileHourlyRate.value = profile.hourly_rate || '';
    return;
  }

  const linked = Boolean(profile.device_access_enabled);
  const verified = Boolean(profile.email_verified);
  els.settingsAccountEmail.textContent = verified ? profile.email : 'No verified email';
  els.settingsEmailVerified.hidden = !verified;
  els.settingsEmailAccessBtn.textContent = linked ? 'Change email' : 'Link email';
  els.logoutProfileBtn.hidden = !linked;
  els.settingsNickname.value = profile.nickname || '';
  els.settingsWorkType.value = profile.work_type || '';
  els.settingsEmployeeStatus.value = profile.employee_status || 'full_time';
  els.settingsRegularHours.value = regularHoursValue(
    profile.default_regular_hours,
    defaultRegularHoursFor(profile.employee_status || 'full_time'),
  );
  els.settingsHourlyRate.value = profile.hourly_rate || '';
}

async function saveProfileFrom(scope) {
  const isSettings = scope === 'settings';
  const payload = {
    browser_id: state.browserId,
    nickname: isSettings ? els.settingsNickname.value.trim() : els.profileNickname.value.trim(),
    work_type: isSettings ? els.settingsWorkType.value.trim() : els.profileWorkType.value.trim(),
    employee_status: isSettings ? els.settingsEmployeeStatus.value : els.profileEmployeeStatus.value,
    default_regular_hours: isSettings
      ? regularHoursValue(els.settingsRegularHours.value, defaultRegularHoursFor(els.settingsEmployeeStatus.value))
      : defaultRegularHoursFor(els.profileEmployeeStatus.value),
    hourly_rate: Number(isSettings ? els.settingsHourlyRate.value : els.profileHourlyRate.value),
  };

  if (!isSettings) {
    const { browser_id: browserId, ...profileDraft } = payload;
    accessDraft = profileDraft;
    accessPurpose = 'register';
    await sendAccessCode(els.profileEmail.value.trim().toLowerCase());
    return;
  }

  const data = await api('/api/worktrack/profile', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  state.profile = data.profile;
  if (isSettings) setSettingsEditMode(false);
  closeAll();
  await loadState();

  toast('Profile saved');
}


// Passwordless profile access shares the existing reminder OTP dialog.
function clearProfileState() {
  ++stateLoadSequence;
  state.profile = null;
  state.entries = {};
  state.reminderNotifications = null;
  state.draft = null;
  reminderVerifiedEmail = '';
  reminderPollingInitialized = false;
  lastReminderNotificationSignature = '';
  seenReminderDeliveries.clear();
  renderAll();
  syncNotificationButton();
}

function accessRoutePath(verify = false) {
  return ['link', 'email_change'].includes(accessPurpose)
    ? routeBasePath + '/settings/email' + (verify ? '/verify' : '')
    : routeBasePath + '/access' + (verify ? '/verify' : '');
}

function setAccessRoute(verify = false, replace = false) {
  const path = accessRoutePath(verify);
  if (window.location.pathname !== path) window.history[replace ? 'replaceState' : 'pushState']({}, '', path);
  setPageTitle('access');
}

function openProfileAccess(purpose = 'login', updateRoute = true, options = {}) {
  closeAll(false);
  accessChallenge = null;
  accessPurpose = purpose;
  const changing = purpose === 'email_change';
  const linking = purpose === 'link';
  els.profileAccessTitle.textContent = changing ? 'Change Email' : linking ? 'Link Email' : 'Access Profile';
  els.profileAccessSubtitle.textContent = changing ? 'Verify your new email before saving.' : linking ? 'Keep your records on every device.' : 'Continue with your verified email.';
  els.profileAccessMessage.textContent = options.message || (changing
    ? 'Your records stay saved after changing email.'
    : linking ? 'Link your saved records to this email.' : 'We will send a code to securely access your profile.');
  els.profileAccessEmail.value = options.email || (linking ? state.profile?.email || '' : '');
  els.profileAccessError.textContent = '';
  els.profileAccessOverlay.hidden = false;
  lockPageScroll();
  if (updateRoute) setAccessRoute(false);
  else setPageTitle('access');
  window.requestAnimationFrame(() => els.profileAccessEmail.focus({ preventScroll: true }));
}

function configureOtpDialog(purpose) {
  const title = {
    register: 'Verify your email', login: 'Verify to access your profile',
    link: 'Verify your email', email_change: 'Verify your new email', reminder: 'Verify Email',
  }[purpose] || 'Verify Email';
  document.getElementById('reminderVerifyTitle').textContent = title;
  els.verifyReminderCodeBtn.textContent = purpose === 'reminder' ? 'Verify Email' : 'Verify';
  els.backToReminderEmailBtn.textContent = 'Back';
}

function showAccessOtp(challenge, updateRoute = true) {
  accessChallenge = challenge;
  accessPurpose = challenge.purpose || accessPurpose;
  configureOtpDialog(accessPurpose);
  els.profileOverlay.hidden = true;
  els.profileAccessOverlay.hidden = true;
  els.reminderSettingsOverlay.hidden = true;
  els.settingsOverlay.hidden = true;
  els.reminderCodeEmail.textContent = challenge.email;
  els.reminderCodeError.textContent = '';
  setReminderCode('');
  startReminderCountdown(challenge.expires_at, challenge.resend_at);
  els.reminderVerifyOverlay.hidden = false;
  lockPageScroll();
  if (updateRoute) setAccessRoute(true);
  window.requestAnimationFrame(() => els.otpInputs[0]?.focus({ preventScroll: true }));
}

async function restoreAccessFromRoute() {
  const requestedRoute = window.location.pathname + window.location.search;
  const result = await api('/api/worktrack/access/status');
  if (requestedRoute !== window.location.pathname + window.location.search) return;
  const wantsVerification = currentSection() === 'access' ? currentFeature() === 'verify' : currentRouteDate() === 'verify';
  if (result.pending && wantsVerification) {
    showAccessOtp(result.pending, false);
    return;
  }
  const purpose = currentSection() === 'settings' && state.profile
    ? state.profile.device_access_enabled ? 'email_change' : 'link'
    : 'login';
  openProfileAccess(purpose, false);
  if (wantsVerification || (currentSection() === 'settings' && !state.profile)) setAccessRoute(false, true);
}

async function sendAccessCode(email, resend = false) {
  if (accessSending || accessVerifying) return;
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) throw new Error('Enter a valid email address.');
  accessSending = true;
  const profileSubmit = els.profileForm.querySelector('[type="submit"]');
  const profileSubmitLabel = profileSubmit?.textContent || 'Save Profile';
  els.sendProfileAccessBtn.disabled = true;
  profileSubmit.disabled = true;
  if (accessPurpose === 'register') {
    profileSubmit.textContent = 'Opening…';
    profileSubmit.setAttribute('aria-busy', 'true');
  }
  els.sendProfileAccessBtn.textContent = 'Sending…';
  els.resendReminderCodeBtn.disabled = true;
  els.profileAccessError.textContent = '';
  try {
    const payload = { purpose: accessPurpose, email, browser_id: state.browserId };
    if (accessPurpose === 'register' && accessDraft) payload.profile = accessDraft;
    if (resend && accessChallenge?.challenge_id) payload.challenge_id = accessChallenge.challenge_id;
    const result = await api('/api/worktrack/access/send', { method: 'POST', body: JSON.stringify(payload) });
    if (result.created) {
      accessChallenge = null;
      accessDraft = null;
      accessRequired = false;
      const redirect = new URL(result.redirect || '/worktrack/calendar', window.location.origin);
      const path = redirect.origin === window.location.origin && redirect.pathname.startsWith('/worktrack')
        ? redirect.pathname + redirect.search : '/worktrack/calendar';
      window.history.replaceState({}, '', path);
      closeAll(false);
      toast('Profile ready');
      void loadState().catch(handleApiError);
      return;
    }
    if (!result.challenge_id) throw new Error(result.message || 'The code could not be sent. Please try again.');
    showAccessOtp({ ...result, email: result.email || email, purpose: result.purpose || accessPurpose });
    toast(resend ? 'New verification code sent' : 'Verification code sent');
  } catch (error) {
    if (resend) els.reminderCodeError.textContent = error.message;
    throw error;
  } finally {
    accessSending = false;
    els.sendProfileAccessBtn.disabled = false;
    profileSubmit.disabled = false;
    profileSubmit.textContent = profileSubmitLabel;
    profileSubmit.removeAttribute('aria-busy');
    els.sendProfileAccessBtn.textContent = 'Send OTP';
    els.resendReminderCodeBtn.disabled = false;
  }
}

async function confirmAccessCode() {
  if (accessVerifying || accessSending || !accessChallenge) return;
  const code = els.reminderCode.value.trim();
  if (!/^\d{6}$/.test(code)) {
    els.reminderCodeError.textContent = 'Enter all six digits from the email.';
    return;
  }
  accessVerifying = true;
  els.verifyReminderCodeBtn.disabled = true;
  els.resendReminderCodeBtn.disabled = true;
  els.verifyReminderCodeBtn.textContent = 'Verifying…';
  els.reminderCodeError.textContent = '';
  const purpose = accessPurpose;
  try {
    const result = await api('/api/worktrack/access/verify', {
      method: 'POST', body: JSON.stringify({ challenge_id: accessChallenge.challenge_id, code }),
    });
    if (!result.verified) throw new Error('We could not verify this code. Request a new one.');
    accessChallenge = null;
    accessDraft = null;
    accessRequired = false;
    clearInterval(reminderCountdownTimer);
    const fallback = ['link', 'email_change'].includes(purpose) ? '/worktrack/settings' : '/worktrack/calendar';
    const redirect = new URL(['link', 'email_change'].includes(purpose) ? fallback : result.redirect || fallback, window.location.origin);
    const path = redirect.origin === window.location.origin && redirect.pathname.startsWith('/worktrack')
      ? redirect.pathname + redirect.search : fallback;
    window.history.replaceState({}, '', path);
    await loadState();
    toast(['link', 'email_change'].includes(purpose) ? 'Email verified and saved' : 'Profile ready');
  } catch (error) {
    els.reminderCodeError.textContent = error.message || 'Invalid or expired code. Please try again.';
  } finally {
    accessVerifying = false;
    els.verifyReminderCodeBtn.disabled = false;
    els.resendReminderCodeBtn.disabled = false;
    els.verifyReminderCodeBtn.textContent = 'Verify';
  }
}

function backFromOtp() {
  if (accessSending || accessVerifying || reminderCodeSending || reminderCodeVerifying) return;
  if (accessChallenge) {
    const challenge = accessChallenge;
    els.reminderVerifyOverlay.hidden = true;
    clearInterval(reminderCountdownTimer);
    if (challenge.purpose === 'register') {
      closeAll(false);
      els.profileOverlay.hidden = false;
      lockPageScroll();
      setRouteSection('profile-setup', true);
    } else openProfileAccess(challenge.purpose, true, { email: challenge.email });
    accessChallenge = null;
    return;
  }
  setReminderStep('email');
}

function backFromAccess() {
  if (accessSending || accessVerifying) return;
  accessChallenge = null;
  if (state.profile && ['link', 'email_change'].includes(accessPurpose)) openSettings();
  else if (accessRequired) openProfileAccess('login', false);
  else openProfileSetup();
}

async function logoutProfile() {
  if (accessSending || accessVerifying) return;
  await api('/api/worktrack/access/logout', { method: 'POST', body: JSON.stringify({}) });
  accessChallenge = null;
  accessDraft = null;
  localStorage.removeItem(browserKey);
  state.browserId = getBrowserId();
  clearProfileState();
  closeAll(false);
  openProfileAccess('login');
  toast('Logged out from this device');
}

async function syncProfileFromServer() {
  const overlayOpen = document.querySelector('.overlay:not([hidden])');
  if (document.documentElement.dataset.appState !== 'ready' || !state.profile || document.hidden || overlayOpen || stateSyncInFlight || accessSending || accessVerifying || Date.now() - lastStateLoadAt < 2000) return;
  stateSyncInFlight = true;
  try { await loadState({ restoreRoute: false }); } catch (error) { handleApiError(error); }
  finally { stateSyncInFlight = false; }
}

function renderReminderDayButtons() {
  els.reminderDayButtons.forEach((button) => {
    const selected = selectedReminderDays.has(Number(button.dataset.reminderDay));
    button.classList.toggle('is-active', selected);
    button.setAttribute('aria-pressed', String(selected));
  });
}

function reminderRepeatPresetForDays(days) {
  const matches = (expected) => expected.length === days.size && expected.every((day) => days.has(day));
  if (matches([1, 2, 3, 4, 5])) return 'weekdays';
  if (matches([1, 2, 3, 4, 5, 6, 7])) return 'everyday';
  if (matches([6, 7])) return 'weekends';
  return 'custom';
}

function syncReminderRepeatControl() {
  const preset = state.profile?.reminder_schedule_mode === 'once' ? 'once' : reminderRepeatPresetForDays(selectedReminderDays);
  els.reminderRepeatPreset.value = preset;
  els.reminderCustomDays.hidden = preset !== 'custom';
}

function syncReminderForm() {
  const email = els.reminderEmail.value.trim().toLowerCase();
  const isVerified = Boolean(email && reminderVerifiedEmail === email);
  const pendingCode = reminderCodeSentTo === email && reminderCodeExpiresAt > Date.now();
  els.sendReminderCodeBtn.textContent = reminderCodeSending ? 'Sending…' : isVerified || pendingCode ? 'Continue' : 'Send OTP';
  els.sendReminderCodeBtn.disabled = reminderCodeSending;
  els.sendReminderCodeBtn.setAttribute('aria-busy', String(reminderCodeSending));
  els.reminderEmail.disabled = reminderCodeSending;
  els.resendReminderCodeBtn.disabled = reminderCodeSending || reminderCodeVerifying;
  els.reminderVerifiedAddress.value = reminderVerifiedEmail || email;
  els.reminderEmailStatus.textContent = isVerified
    ? 'This email is verified and ready for reminders.'
    : pendingCode
      ? 'Code sent. Continue to verify your email.'
      : 'Verify your email to activate reminders.';
  els.reminderEmailStatus.classList.toggle('is-verified', isVerified);
  els.saveReminderBtn.disabled = reminderSaving || !els.reminderEnabled.checked
    || !(isVerified && (els.reminderRepeatPreset.value === 'once' || selectedReminderDays.size > 0)
      && Boolean(getTimeInput(els.reminderTime)) && Boolean(els.reminderStartDate.value));
  els.saveReminderBtn.textContent = reminderSaving ? 'Saving…' : 'Save Reminder';
  els.saveReminderBtn.setAttribute('aria-busy', String(reminderSaving));
}

function localDateValue(date = new Date()) {
  const parts = Object.fromEntries(new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit',
  }).formatToParts(date).map((part) => [part.type, part.value]));
  return `${parts.year}-${parts.month}-${parts.day}`;
}

function reminderStepRoute(step, replace = false) {
  setRouteFeature('settings', 'reminder', step, replace);
}

function setReminderStep(step, updateRoute = true, replaceRoute = false) {
  const screens = {
    email: els.reminderEmailStep,
    schedule: els.reminderMainStep,
  };
  const isVerify = step === 'verify';
  if (isVerify) configureOtpDialog('reminder');
  const parentStep = isVerify ? 'email' : step;
  Object.entries(screens).forEach(([name, screen]) => { screen.hidden = name !== parentStep; });
  els.reminderForm.classList.toggle('is-email-step', parentStep === 'email');
  els.reminderSettingsOverlay.hidden = isVerify;
  els.reminderVerifyOverlay.hidden = !isVerify;
  document.getElementById('reminderTitle').textContent = 'Email Reminder';
  els.reminderStepSubtitle.textContent = 'Get reminders at your preferred time.';
  if (updateRoute) reminderStepRoute(step, replaceRoute);
  else setPageTitle('settings', 'reminder');
  window.requestAnimationFrame(() => {
    const target = isVerify ? els.otpInputs[0] : step === 'email' ? els.reminderEmail : els.reminderEnabled;
    target?.focus({ preventScroll: true });
  });
}

function setReminderCode(value = '') {
  const digits = value.replace(/\D/g, '').slice(0, 6).split('');
  els.otpInputs.forEach((input, index) => { input.value = digits[index] || ''; });
  els.reminderCode.value = digits.join('');
}

function updateReminderCountdown() {
  const secondsLeft = Math.max(0, Math.ceil((reminderCodeExpiresAt - Date.now()) / 1000));
  const resendSeconds = Math.max(0, Math.ceil((reminderResendAvailableAt - Date.now()) / 1000));
  const clock = (seconds) => `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
  els.reminderCodeExpiry.textContent = secondsLeft
    ? `Time left ${clock(secondsLeft)}`
    : 'Request a new code to continue.';
  els.reminderResendCountdown.hidden = resendSeconds === 0;
  els.reminderResendCountdown.textContent = `Resend in ${clock(resendSeconds)}`;
  els.resendReminderCodeBtn.hidden = resendSeconds > 0;
}

function startReminderCountdown(expiresAt, resendAt) {
  clearInterval(reminderCountdownTimer);
  reminderCodeExpiresAt = Date.parse(expiresAt) || Date.now();
  reminderResendAvailableAt = Date.parse(resendAt) || Date.now();
  updateReminderCountdown();
  reminderCountdownTimer = window.setInterval(updateReminderCountdown, 1000);
}

function openReminderSettings(updateRoute = true, requestedStep = 'email') {
  closeAll(false);
  accessChallenge = null;
  const profile = state.profile || {};
  selectedReminderDays = new Set((profile.reminder_days?.length ? profile.reminder_days : [1, 2, 3, 4, 5, 6, 7]).map(Number));
  reminderVerifiedEmail = profile.email_verified ? String(profile.email || '').trim().toLowerCase() : '';
  reminderCodeSentTo = !reminderVerifiedEmail && Date.parse(profile.reminder_verification_expires_at) > Date.now()
    ? String(profile.email || '').trim().toLowerCase() : '';
  els.reminderEmail.value = profile.email || '';
  setReminderCode('');
  els.reminderEnabled.checked = Boolean(profile.reminder_enabled || !profile.reminder_configured);
  els.reminderDetails.value = profile.reminder_details || '';
  els.reminderStartDate.value = profile.reminder_start_date || localDateValue();
  els.reminderStartDate.min = localDateValue();
  setTimeInput(els.reminderTime, profile.reminder_time || '18:00');
  renderReminderDayButtons();
  syncReminderRepeatControl();
  syncReminderForm();
  els.reminderSettingsOverlay.hidden = false;
  const savedChallengeExpiry = profile.reminder_verification_expires_at;
  const savedResendAt = profile.reminder_verification_resend_at;
  reminderCodeExpiresAt = Date.parse(savedChallengeExpiry) || 0;
  reminderResendAvailableAt = Date.parse(savedResendAt) || 0;
  syncReminderForm();
  const step = profile.device_access_enabled && reminderVerifiedEmail ? 'schedule'
    : requestedStep === 'verify' && reminderVerifiedEmail ? 'schedule'
    : requestedStep === 'verify' && reminderCodeSentTo ? 'verify'
    : requestedStep === 'schedule' && reminderVerifiedEmail ? 'schedule' : 'email';
  if (step === 'verify') {
    reminderCodeSentTo = reminderVerifiedEmail ? '' : String(profile.email || '').trim().toLowerCase();
    els.reminderCodeEmail.textContent = maskReminderEmail(reminderCodeSentTo);
    startReminderCountdown(savedChallengeExpiry, savedResendAt);
  } else {
    clearInterval(reminderCountdownTimer);
  }
  const routeStep = currentRouteDate();
  const needsRouteCorrection = !updateRoute
    && currentFeature() === 'reminder'
    && ['email', 'verify', 'schedule'].includes(routeStep)
    && routeStep !== step;
  setReminderStep(step, updateRoute || needsRouteCorrection, !updateRoute);
}

function closeReminderSettings(updateRoute = true) {
  clearInterval(reminderCountdownTimer);
  els.reminderVerifyOverlay.hidden = true;
  els.reminderSettingsOverlay.hidden = true;
  if (updateRoute && currentSection() === 'settings' && currentFeature() === 'reminder') {
    setRouteSection('settings', true);
  } else if (currentSection() === 'settings') {
    setPageTitle('settings');
  }
  openSettings(false);
}

function maskReminderEmail(email) {
  const [localPart, domain] = email.split('@');
  if (!localPart || !domain) return email;
  return `${localPart.slice(0, Math.min(4, localPart.length))}••••@${domain}`;
}

async function sendReminderVerificationCode({ forceResend = false } = {}) {
  if (reminderCodeSending || reminderCodeVerifying) return;
  const email = els.reminderEmail.value.trim().toLowerCase();
  if (!email || !els.reminderEmail.checkValidity()) {
    els.reminderEmail.reportValidity();
    return;
  }
  if (reminderVerifiedEmail === email) {
    els.reminderEnabled.checked = true;
    syncReminderForm();
    setReminderStep('schedule');
    return;
  }
  if (!forceResend && reminderCodeSentTo === email && reminderCodeExpiresAt > Date.now()) {
    els.reminderCodeEmail.textContent = maskReminderEmail(email);
    startReminderCountdown(state.profile?.reminder_verification_expires_at, state.profile?.reminder_verification_resend_at);
    setReminderCode('');
    els.reminderCodeError.textContent = '';
    setReminderStep('verify');
    return;
  }

  reminderCodeSending = true;
  syncReminderForm();
  try {
    const result = await api('/api/worktrack/reminder/email/verify/send', {
      method: 'POST',
      body: JSON.stringify({ browser_id: state.browserId, email }),
    });
    if (result.verified) {
      reminderVerifiedEmail = email;
      reminderCodeSentTo = '';
      if (state.profile) {
        state.profile.email = email;
        state.profile.email_verified = true;
        state.profile.email_verified_at = state.profile.email_verified_at || new Date().toISOString();
      }
      els.reminderEnabled.checked = true;
      syncReminderForm();
      setReminderStep('schedule');
      toast(result.message || 'Email already verified');
      return;
    }
    if (result.sent !== true) throw new Error('The verification email could not be sent. Please try again.');

    reminderCodeSentTo = email;
    reminderVerifiedEmail = '';
    if (state.profile) {
      state.profile.email = email;
      state.profile.email_verified = false;
      state.profile.email_verified_at = null;
      state.profile.reminder_enabled = false;
      state.profile.reminder_verification_expires_at = result.expires_at;
      state.profile.reminder_verification_resend_at = result.resend_at;
    }
    syncNotificationButton();
    els.reminderCodeEmail.textContent = maskReminderEmail(email);
    els.reminderCodeError.textContent = '';
    setReminderCode('');
    startReminderCountdown(result.expires_at, result.resend_at);
    syncReminderForm();
    setReminderStep('verify');
    toast(result.message || 'Verification code sent');
  } finally {
    reminderCodeSending = false;
    syncReminderForm();
  }
}

async function confirmReminderEmailCode() {
  if (reminderCodeVerifying || reminderCodeSending) return;
  const code = els.reminderCode.value.trim();
  const email = els.reminderEmail.value.trim().toLowerCase();
  if (!email || email !== reminderCodeSentTo) {
    els.reminderCodeError.textContent = 'This code belongs to a different email. Send a new code to continue.';
    return;
  }
  if (!/^\d{6}$/.test(code)) {
    els.reminderCodeError.textContent = 'Enter all six digits from the email.';
    return;
  }
  els.reminderCodeError.textContent = '';
  reminderCodeVerifying = true;
  els.verifyReminderCodeBtn.disabled = true;
  els.resendReminderCodeBtn.disabled = true;
  els.verifyReminderCodeBtn.textContent = 'Verifying…';
  try {
    const result = await api('/api/worktrack/reminder/email/verify', {
      method: 'POST',
      body: JSON.stringify({ browser_id: state.browserId, email, code }),
    });
    reminderVerifiedEmail = String(result.profile?.email || '').trim().toLowerCase();
    if (reminderVerifiedEmail !== email) throw new Error('The verified email does not match this reminder.');
    reminderCodeSentTo = '';
    if (result.profile) state.profile = result.profile;
    clearInterval(reminderCountdownTimer);
    syncReminderForm();
    syncNotificationButton();
    toast('Email verified');
    els.reminderEnabled.checked = true;
    syncReminderForm();
    setReminderStep('schedule');
  } catch (error) {
    els.reminderCodeError.textContent = error.message || 'We could not verify that code.';
  } finally {
    reminderCodeVerifying = false;
    els.verifyReminderCodeBtn.disabled = false;
    els.resendReminderCodeBtn.disabled = false;
    els.verifyReminderCodeBtn.textContent = 'Verify Email';
  }
}

async function saveReminderSettings(event, { automaticDisable = false } = {}) {
  event?.preventDefault();
  if (reminderSaving) return;
  const enabled = els.reminderEnabled.checked;
  if (!enabled && !automaticDisable) return;
  const schedule = {
    browser_id: state.browserId,
    email: reminderVerifiedEmail || null,
    enabled,
    reminder_time: getTimeInput(els.reminderTime) || null,
    reminder_start_date: els.reminderStartDate.value || null,
    reminder_details: els.reminderDetails.value.trim(),
    schedule_mode: els.reminderRepeatPreset.value === 'once' ? 'once' : 'recurring',
    days: els.reminderRepeatPreset.value === 'once' ? [] : [...selectedReminderDays].sort((a, b) => a - b),
  };
  if (enabled && (reminderVerifiedEmail !== els.reminderEmail.value.trim().toLowerCase() || (schedule.schedule_mode !== 'once' && !schedule.days.length) || !schedule.reminder_start_date)) {
    syncReminderForm();
    return;
  }

  reminderSaving = true;
  syncReminderForm();
  try {
    const result = await api('/api/worktrack/reminder', {
      method: 'POST',
      body: JSON.stringify(schedule),
    });
    state.profile = result.profile;
    state.reminderNotifications = result.reminder_notifications || null;
    syncNotificationButton();
    if (automaticDisable) {
      toast('Daily reminder turned off');
      return;
    }
    clearInterval(reminderCountdownTimer);
    els.reminderSettingsOverlay.hidden = true;
    els.reminderVerifyOverlay.hidden = true;
    setRouteSection('settings', true);
    await loadState();
    toast(enabled ? 'Daily email reminder saved' : 'Daily reminder turned off');
  } catch (error) {
    if (automaticDisable) els.reminderEnabled.checked = true;
    throw error;
  } finally {
    reminderSaving = false;
    syncReminderForm();
  }
}

function openEntry(key, updateRoute = true, renderDashboard = true) {
  if (!state.profile) {
    openProfileSetup();
    return;
  }

  state.selectedDate = key;
  const existing = state.entries[key];
  state.draft = existing ? { ...existing, clear: false } : {
    work_date: key,
    time_in: '',
    time_out: '',
    rate_type: 'normal',
    modifier: 'none',
    regular_hours: state.profile?.default_regular_hours || defaultRegularHoursFor(state.profile?.employee_status || 'full_time'),
    normal_hours: null,
    extended_hours: null,
    status: 'work',
    note: '',
    clear: false,
  };

  state.viewDate = new Date(parseDate(key).getFullYear(), parseDate(key).getMonth(), 1);
  if (renderDashboard) renderAll();
  closeAll(false);
  if (updateRoute) setRouteFeature('calendar', 'entry', key);
  else setPageTitle('calendar');
  els.entryTitle.textContent = formatLongDate(key);
  setTimeInput(els.timeInInput, state.draft.time_in || '');
  setTimeInput(els.timeOutInput, state.draft.time_out || '');
  els.normalHoursInput.value = Number(state.draft.normal_hours || 0) > 0 ? formatHours(state.draft.normal_hours) : '';
  els.extendedHoursInput.value = Number(state.draft.extended_hours || 0) > 0 ? formatHours(state.draft.extended_hours) : '';
  els.noteInput.value = state.draft.note || '';
  els.noteCount.textContent = `${els.noteInput.value.length}/200`;
  setActiveOption(els.rateTypeGroup, 'rate', state.draft.rate_type);
  setActiveOption(els.modifierGroup, 'modifier', state.draft.modifier);
  renderQuickLabels();
  setEntryEditMode(!existing);
  updatePayPreview();
  els.entryOverlay.hidden = false;
  lockPageScroll();
  window.requestAnimationFrame(fitEntryTitle);
}

function setEntryEditMode(editing) {
  const savedEntry = Boolean(state.draft && state.entries[state.draft.work_date]);
  state.entryEditing = Boolean(editing);
  const readOnly = savedEntry && !state.entryEditing;

  els.entryForm.classList.toggle('entry-view-mode', readOnly);
  els.entryForm.classList.toggle('entry-saved-mode', savedEntry);
  els.entrySubtitle.textContent = savedEntry
    ? (readOnly ? 'Your saved work entry for this date' : 'Edit your saved work entry for this date')
    : 'Add your work entry for this date';
  els.deleteEntryBtn.hidden = !savedEntry;
  els.editEntryBtn.hidden = !savedEntry || state.entryEditing;
  els.saveEntryBtn.hidden = savedEntry && !state.entryEditing;
  els.cancelEntryBtn.hidden = savedEntry;

  els.normalHoursInput.readOnly = readOnly;
  els.extendedHoursInput.readOnly = readOnly;
  els.noteInput.readOnly = readOnly;
  els.rateTypeGroup.querySelectorAll('button').forEach((button) => { button.disabled = readOnly; });
  els.modifierGroup.querySelectorAll('button').forEach((button) => { button.disabled = readOnly; });
  [els.restDayBtn, els.excusedBtn, els.clearDayBtn].forEach((button) => { button.disabled = readOnly; });
}

function fitEntryTitle() {
  const title = els.entryTitle;
  if (!title || els.entryOverlay.hidden) return;

  title.style.removeProperty('font-size');
  let fontSize = Number.parseFloat(window.getComputedStyle(title).fontSize);
  while (title.scrollWidth > title.clientWidth + 1 && fontSize > 12) {
    fontSize -= 0.5;
    title.style.fontSize = `${fontSize}px`;
  }
}

function setActiveOption(group, attr, value) {
  group.querySelectorAll('button').forEach((button) => {
    button.classList.toggle('is-active', button.dataset[attr] === value);
  });
}

function renderQuickLabels() {
  els.restDayBtn.classList.toggle('is-active', state.draft?.status === 'rest');
  els.excusedBtn.classList.toggle('is-active', state.draft?.status === 'absent');
}

function readEntryInputs() {
  if (!state.draft) return;
  state.draft.time_in = getTimeInput(els.timeInInput);
  state.draft.time_out = getTimeInput(els.timeOutInput);
  state.draft.normal_hours = manualHoursValue(els.normalHoursInput.value);
  state.draft.extended_hours = manualHoursValue(els.extendedHoursInput.value);
  state.draft.note = els.noteInput.value.trim();
  if (state.draft.status !== 'rest' && state.draft.status !== 'absent') {
    state.draft.status = 'work';
  }
}

function resumeWorkEntry() {
  if (!state.draft) return;
  state.draft.clear = false;
  if (state.draft.status === 'rest' || state.draft.status === 'absent') {
    state.draft.status = 'work';
    renderQuickLabels();
  }
}

function computeHours(timeIn, timeOut, status, regularHours = 8) {
  if (status !== 'work' || !timeIn || !timeOut) {
    return { normal: 0, extended: 0, total: 0 };
  }

  const [inHour, inMinute] = timeIn.split(':').map(Number);
  const [outHour, outMinute] = timeOut.split(':').map(Number);
  let start = inHour * 60 + inMinute;
  let end = outHour * 60 + outMinute;
  if (end <= start) end += 24 * 60;

  const total = Math.round(((end - start) / 60) * 100) / 100;
  const regularLimit = regularHoursValue(regularHours);
  return {
    normal: Math.min(total, regularLimit),
    extended: Math.max(total - regularLimit, 0),
    total,
  };
}

function manualHoursFromDraft() {
  if (!state.draft || state.draft.status !== 'work') {
    return { normal: 0, extended: 0, total: 0 };
  }

  if (state.draft.normal_hours === null && state.draft.extended_hours === null) {
    return computeHours(state.draft.time_in, state.draft.time_out, state.draft.status,
      state.draft.regular_hours);
  }

  const normal = Number(state.draft.normal_hours || 0);
  const extended = Number(state.draft.extended_hours || 0);
  const total = Math.round((normal + extended) * 100) / 100;
  return { normal, extended, total };
}

function fitManualHoursToTimeSpan(changedInput = null) {
  if (!state.draft || state.draft.status !== 'work' || !state.draft.time_in || !state.draft.time_out) return;
  if (!state.entryEditing && state.entries[state.draft.work_date]) return;

  const span = computeHours(state.draft.time_in, state.draft.time_out, 'work', state.draft.regular_hours).total;
  const normal = manualHoursValue(els.normalHoursInput.value);
  const extended = manualHoursValue(els.extendedHoursInput.value);
  if (normal === null && extended === null) return;

  const normalValue = normal ?? 0;
  const extendedValue = extended ?? 0;
  if (normalValue + extendedValue <= span) return;

  if (changedInput === els.extendedHoursInput) {
    els.normalHoursInput.value = formatHours(Math.max(span - extendedValue, 0));
  } else {
    els.extendedHoursInput.value = formatHours(Math.max(span - normalValue, 0));
  }
}

function computePayFromDraft() {
  if (!state.draft) return null;
  const hours = manualHoursFromDraft();
  if (hours.total === 0) return null;

  const savedRate = !state.entryEditing && state.entries[state.draft.work_date]
    ? Number(state.draft.hourly_rate_applied ?? hourlyRate())
    : hourlyRate();
  const rateMultiplier = state.draft.rate_type === 'night' ? 1.1 : 1;
  const modifierMultiplier = state.draft.modifier === 'holiday' ? 1.3 : state.draft.modifier === 'double' ? 2 : 1;
  return (hours.normal * savedRate * rateMultiplier * modifierMultiplier)
    + (hours.extended * savedRate * rateMultiplier * modifierMultiplier * 1.25);
}

function updatePayPreview() {
  readEntryInputs();
  fitManualHoursToTimeSpan();
  readEntryInputs();
  const hours = manualHoursFromDraft();
  const pay = computePayFromDraft();
  els.totalHoursPreview.textContent = hours.total === 0 ? '--' : `${formatHours(hours.total)} hrs`;
  els.estimatedPay.textContent = pay === null ? '--' : formatMoney(pay);
}

async function saveEntry(event) {
  event.preventDefault();
  if (state.draft && state.entries[state.draft.work_date] && !state.entryEditing) return;
  validateManualHoursInputs();
  readEntryInputs();

  if (state.draft.clear) {
    await deleteEntry(state.draft.work_date);
    return;
  }

  const payload = {
    browser_id: state.browserId,
    work_date: state.draft.work_date,
    time_in: state.draft.status === 'work' ? (state.draft.time_in || null) : null,
    time_out: state.draft.status === 'work' ? (state.draft.time_out || null) : null,
    regular_hours: state.draft.regular_hours,
    normal_hours: state.draft.status === 'work' ? state.draft.normal_hours : null,
    extended_hours: state.draft.status === 'work' ? state.draft.extended_hours : null,
    rate_type: state.draft.rate_type,
    modifier: state.draft.modifier,
    status: state.draft.status,
    note: state.draft.note || '',
  };

  const isUpdate = Boolean(state.entries[state.draft.work_date]);
  const data = await api('/api/worktrack/entries', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  state.entries[data.entry.work_date] = normalizeEntry(data.entry);
  closeAll();
  renderAll();
  toast(isUpdate ? 'Entry updated' : 'Entry saved');
}

async function deleteEntry(key, message = 'Day cleared') {
  await api(`/api/worktrack/entries/${encodeURIComponent(key)}`, {
    method: 'DELETE',
    body: JSON.stringify({ browser_id: state.browserId }),
  });
  delete state.entries[key];
  closeAll();
  renderAll();
  toast(message);
}

function closeAll(updateRoute = true, replaceRoute = false) {
  closeExportMenu();
  closeTimePicker();
  closeCalendarPeriodPicker();
  state.entryEditing = false;
  setNotificationPopoverOpen(false);
  els.profileOverlay.hidden = true;
  els.profileAccessOverlay.hidden = true;
  els.entryOverlay.hidden = true;
  els.settingsOverlay.hidden = true;
  els.reminderSettingsOverlay.hidden = true;
  els.reminderVerifyOverlay.hidden = true;
  clearInterval(reminderCountdownTimer);
  els.confirmOverlay.hidden = true;
  els.dailyRecordsOverlay.hidden = true;
  els.dailyRecordsBtn.setAttribute('aria-expanded', 'false');
  unlockPageScroll();
  if (updateRoute) setRouteSection('calendar', replaceRoute);
  else setPageTitle('calendar');
}

function lockPageScroll() {
  document.documentElement.classList.add('is-overlay-open');
  document.body.classList.add('is-overlay-open');
}

function unlockPageScroll() {
  document.documentElement.classList.remove('is-overlay-open');
  document.body.classList.remove('is-overlay-open');
}

function toast(message) {
  els.toastMessage.textContent = message;
  els.toast.hidden = false;
  clearTimeout(toast.timer);
  toast.timer = setTimeout(() => {
    els.toast.hidden = true;
  }, 1400);
}

function handleApiError(error) {
  toast(error.message || 'Something went wrong');
}

async function checkReminder() {
  if (!state.profile || document.hidden || reminderCheckInFlight) return;
  const identitySequence = stateLoadSequence;
  reminderCheckInFlight = true;
  try {
    const result = await api('/api/worktrack/reminder/notifications?browser_id=' + encodeURIComponent(state.browserId));
    if (identitySequence !== stateLoadSequence) return;
    if (state.profile?.system_user_id !== result.profile?.system_user_id) {
      discardStaleProfileUi();
      await loadState();
      toast('The active profile changed. Saved records have been reloaded.');
      return;
    }
    state.profile = result.profile;
    state.reminderNotifications = result.reminder_notifications;
    syncNotificationButton();
    for (const item of (result.reminder_notifications?.history || [])) {
      if (item.status !== 'sent') continue;
      if (reminderPollingInitialized && !seenReminderDeliveries.has(item.id) && 'Notification' in window && Notification.permission === 'granted') {
        await showWorktrackNotification('WORKTRACK reminder · Sent', item.notes || 'Your scheduled reminder was sent.');
      }
      seenReminderDeliveries.add(item.id);
    }
    reminderPollingInitialized = true;
  } catch (error) {
    console.warn('Reminder status could not be refreshed.');
  } finally {
    reminderCheckInFlight = false;
  }
}

function attachEvents() {
  els.prevMonthCard.addEventListener('click', () => changeMonth(-1));
  els.nextMonthCard.addEventListener('click', () => changeMonth(1));
  els.calendarPeriodBtn.addEventListener('click', toggleCalendarPeriodPicker);
  els.closeCalendarPeriodBtn.addEventListener('click', closeCalendarPeriodPicker);
  els.calendarMonthSelect.addEventListener('click', () => toggleCalendarPeriodMenu('month'));
  els.calendarYearSelect.addEventListener('click', () => toggleCalendarPeriodMenu('year'));
  [els.calendarMonthMenu, els.calendarYearMenu].forEach((menu) => {
    menu.addEventListener('click', (event) => {
      const option = event.target.closest('.calendar-period-option');
      if (option) chooseCalendarPeriodOption(option);
    });
  });
  els.calendarPeriodTodayBtn.addEventListener('click', () => {
    closeCalendarPeriodPicker();
    goToday();
  });
  document.addEventListener('click', (event) => {
    if (els.calendarPeriodPicker.hidden) return;
    if (event.target.closest('#calendarPeriodPicker, #calendarPeriodBtn')) return;
    closeCalendarPeriodPicker();
  });
  els.todayBtn.addEventListener('click', goToday);
  els.themeToggleBtn.addEventListener('click', toggleTheme);
  els.dailyRecordsBtn.addEventListener('click', () => openDailyRecords());
  els.closeDailyRecordsBtn.addEventListener('click', () => closeDailyRecords());
  els.closeDailyRecordsFooterBtn.addEventListener('click', () => closeDailyRecords());
  els.dailyRecordsMonth.addEventListener('change', () => {
    state.dailyRecordsMonth = els.dailyRecordsMonth.value;
    const [year, month] = state.dailyRecordsMonth.split('-').map(Number);
    state.viewDate = new Date(year, month - 1, 1);
    renderDailyRecords();
    if (currentFeature() === 'records') {
      setCalendarOverlayRoute('records', { month: state.dailyRecordsMonth, period: state.dailyRecordsPeriod }, true);
    }
  });
  els.dailyRecordsOverlay.querySelectorAll('[data-record-period]').forEach((button) => {
    button.addEventListener('click', () => {
      state.dailyRecordsPeriod = button.dataset.recordPeriod;
      renderDailyRecords();
      if (currentFeature() === 'records') {
        setCalendarOverlayRoute('records', { month: state.dailyRecordsMonth, period: state.dailyRecordsPeriod }, true);
      }
    });
  });
  els.dailyRecordsList.addEventListener('click', (event) => {
    const row = event.target.closest('button[data-record-date]');
    if (!row) return;
    const key = row.dataset.recordDate;
    closeDailyRecords(false);
    openEntry(key);
  });
  els.dailyRecordsOverlay.addEventListener('click', (event) => {
    if (event.target === els.dailyRecordsOverlay) closeDailyRecords();
  });
  els.notificationBtn.addEventListener('click', () => {
    const open = els.notificationPopover.hidden;
    if (open) {
      notificationHoverSuppressed = false;
      setNotificationPopoverOpen(true);
      setRouteSection('notifications');
    } else {
      closeNotificationPopover();
    }
    syncNotificationButton();
  });
  const notificationAnchor = els.notificationBtn.closest('.notification-anchor');
  const canHoverNotifications = window.matchMedia('(hover: hover) and (pointer: fine)');
  notificationAnchor.addEventListener('mouseenter', () => {
    if (!canHoverNotifications.matches || notificationHoverSuppressed || !els.notificationPopover.hidden) return;
    setNotificationPopoverOpen(true);
    setRouteSection('notifications');
  });
  notificationAnchor.addEventListener('mouseleave', () => {
    notificationHoverSuppressed = false;
  });
  els.closeNotificationPopover.addEventListener('click', (event) => {
    event.stopPropagation();
    closeNotificationPopover();
  });
  els.markAllNotificationsRead.addEventListener('click', () => {
    const read = readNotificationIds();
    for (const item of notificationDataItems(state.reminderNotifications)) read.add(item.id);
    saveNotificationReadIds(read);
  });
  els.viewAllNotifications.addEventListener('click', () => {
    showAllNotifications = !showAllNotifications;
    lastReminderNotificationSignature = '';
    syncNotificationButton();
  });
  els.notificationItems.addEventListener('click', (event) => {
    const row = event.target.closest('[data-notification-id]');
    if (!row) return;
    const read = readNotificationIds();
    read.add(row.dataset.notificationId);
    saveNotificationReadIds(read);
  });
  els.notificationPopoverAction.addEventListener('click', () => {
    setNotificationPopoverOpen(false);
    if (!state.profile) {
      openProfileSetup();
      return;
    }
    openSettings();
    openReminderSettings();
  });
  document.addEventListener('click', (event) => {
    if (!els.notificationPopover.hidden && !event.target.closest('.notification-anchor')) {
      closeNotificationPopover();
    }
    if (!els.exportMenu.hidden && !event.target.closest('#exportDropdown')) closeExportMenu();
  });
  els.exportMenuButton.addEventListener('click', toggleExportMenu);
  els.exportMenu.addEventListener('click', (event) => {
    const option = event.target.closest('[data-export-format]');
    if (!option) return;
    const format = option.dataset.exportFormat;
    closeExportMenu();
    try {
      if (format === 'pdf') exportPdf();
      if (format === 'xlsx') exportExcel();
      if (format === 'jpg') exportJpg();
      toast(format === 'pdf' ? 'PDF report opened for printing or saving' : `${format.toUpperCase()} report downloaded`);
    } catch (error) {
      handleApiError(error);
    }
  });
  els.settingsBtn.addEventListener('click', openSettings);
  document.getElementById('openProfileAccessBtn').addEventListener('click', () => openProfileAccess('login'));
  document.getElementById('closeProfileAccessBtn').addEventListener('click', backFromAccess);
  document.getElementById('backFromProfileAccessBtn').addEventListener('click', backFromAccess);
  els.settingsEmailAccessBtn.addEventListener('click', () => openProfileAccess(state.profile?.device_access_enabled ? 'email_change' : 'link'));
  els.logoutProfileBtn.addEventListener('click', () => logoutProfile().catch(handleApiError));
  els.profileAccessForm.addEventListener('submit', (event) => {
    event.preventDefault();
    sendAccessCode(els.profileAccessEmail.value.trim().toLowerCase()).catch((error) => {
      els.profileAccessError.textContent = error.message;
    });
  });
  els.profileForm.addEventListener('submit', (event) => {
    event.preventDefault();
    saveProfileFrom('profile').catch(handleApiError);
  });
  els.settingsForm.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!settingsEditMode) return;
    saveProfileFrom('settings').catch(handleApiError);
  });
  els.settingsEditSaveBtn.addEventListener('click', () => {
    if (!settingsEditMode) {
      setSettingsEditMode(true);
      els.settingsNickname.focus();
      return;
    }
    els.settingsForm.requestSubmit();
  });
  els.openReminderSettingsBtn.addEventListener('click', () => openReminderSettings());
  els.closeReminderSettingsBtn.addEventListener('click', () => closeReminderSettings());
  els.cancelReminderSettingsBtn.addEventListener('click', () => closeReminderSettings());
  els.cancelReminderMainBtn.addEventListener('click', () => closeReminderSettings());
  els.backToReminderEmailBtn.addEventListener('click', backFromOtp);
  els.closeReminderVerifyBtn.addEventListener('click', backFromOtp);
  els.changeReminderEmailBtn.addEventListener('click', () => {
    if (state.profile?.device_access_enabled) openProfileAccess('email_change');
    else setReminderStep('email');
  });
  els.reminderForm.addEventListener('submit', (event) => {
    event.preventDefault();
    const submission = els.reminderEmailStep.hidden
      ? saveReminderSettings(event)
      : sendReminderVerificationCode();
    submission.catch(handleApiError);
  });
  els.reminderVerifyForm.addEventListener('submit', (event) => {
    event.preventDefault();
    (accessChallenge ? confirmAccessCode() : confirmReminderEmailCode()).catch(handleApiError);
  });
  els.reminderEmail.addEventListener('input', () => {
    const email = els.reminderEmail.value.trim().toLowerCase();
    if (email !== reminderCodeSentTo) {
      if (email !== reminderVerifiedEmail) reminderVerifiedEmail = '';
      setReminderCode('');
    }
    syncReminderForm();
  });
  els.resendReminderCodeBtn.addEventListener('click', () => {
    const request = accessChallenge ? sendAccessCode(accessChallenge.email, true) : sendReminderVerificationCode({ forceResend: true });
    request.catch(handleApiError);
  });
  els.otpInputs.forEach((input, index) => {
    input.addEventListener('input', () => {
      const value = input.value.replace(/\D/g, '');
      if (value.length > 1) {
        setReminderCode(value);
        els.otpInputs[Math.min(value.length, 6) - 1]?.focus();
      } else {
        input.value = value;
        els.reminderCode.value = [...els.otpInputs].map((digit) => digit.value).join('');
        if (value && index < els.otpInputs.length - 1) els.otpInputs[index + 1].focus();
      }
      els.reminderCodeError.textContent = '';
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Backspace' && !input.value && index > 0) els.otpInputs[index - 1].focus();
    });
    input.addEventListener('paste', (event) => {
      const value = event.clipboardData?.getData('text') || '';
      if (!value) return;
      event.preventDefault();
      setReminderCode(value);
      els.otpInputs[Math.min(value.replace(/\D/g, '').length, 6) - 1]?.focus();
    });
  });
  els.reminderEnabled.addEventListener('change', () => {
    syncReminderForm();
    if (!els.reminderEnabled.checked && state.profile?.reminder_enabled) {
      saveReminderSettings(null, { automaticDisable: true }).catch(handleApiError);
    }
  });
  els.reminderStartDate.addEventListener('input', syncReminderForm);
  els.reminderDayButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const day = Number(button.dataset.reminderDay);
      if (selectedReminderDays.has(day)) selectedReminderDays.delete(day);
      else selectedReminderDays.add(day);
      renderReminderDayButtons();
      syncReminderForm();
    });
  });
  els.reminderRepeatPreset.addEventListener('change', () => {
    const presetDays = {
      weekdays: [1, 2, 3, 4, 5],
      everyday: [1, 2, 3, 4, 5, 6, 7],
      weekends: [6, 7],
    }[els.reminderRepeatPreset.value];

    if (presetDays) selectedReminderDays = new Set(presetDays);
    els.reminderCustomDays.hidden = els.reminderRepeatPreset.value !== 'custom';
    renderReminderDayButtons();
    syncReminderForm();
  });
  els.settingsEmployeeStatus.addEventListener('change', () => {
    els.settingsRegularHours.value = defaultRegularHoursFor(els.settingsEmployeeStatus.value);
  });
  els.settingsTimeWrap.addEventListener('click', showReminderTimePicker);
  els.reminderTime.addEventListener('input', syncReminderForm);
  els.reminderTime.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    showReminderTimePicker();
  });
  els.closeSettingsBtn.addEventListener('click', closeAll);
  els.closeEntryBtn.addEventListener('click', closeAll);
  els.cancelEntryBtn.addEventListener('click', closeAll);
  els.editEntryBtn.addEventListener('click', () => {
    setEntryEditMode(true);
    updatePayPreview();
  });
  els.deleteEntryBtn.addEventListener('click', () => {
    const key = state.draft?.work_date;
    if (!key || !state.entries[key]) return;
    if (!window.confirm(`Delete the saved work entry for ${formatLongDate(key)}?`)) return;
    deleteEntry(key, 'Entry deleted').catch(handleApiError);
  });
  els.entryForm.addEventListener('submit', (event) => saveEntry(event).catch(handleApiError));
  els.timeInInput.addEventListener('click', () => openEntryTimePicker(els.timeInInput, 'Time In', 'time-in'));
  els.timeOutInput.addEventListener('click', () => openEntryTimePicker(els.timeOutInput, 'Time Out', 'time-out'));
  [els.timeInInput, els.timeOutInput].forEach((input) => {
    input.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      openTimePicker(input, input === els.timeInInput ? 'Time In' : 'Time Out');
    });
  });
  [els.normalHoursInput, els.extendedHoursInput].forEach((input) => {
    input.addEventListener('input', () => {
      resumeWorkEntry();
      readEntryInputs();
      fitManualHoursToTimeSpan(input);
      updatePayPreview();
    });
  });
  els.timePickerForm.addEventListener('submit', applyTimePicker);
  els.closeTimePickerBtn.addEventListener('click', closeTimePicker);
  els.clearTimePickerBtn.addEventListener('click', clearTimePicker);
  els.timePickerHourButton.addEventListener('click', () => toggleTimeDropdown('hour'));
  els.timePickerMinuteButton.addEventListener('click', () => toggleTimeDropdown('minute'));
  els.timePickerHourMenu.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-value]');
    if (!button) return;
    selectTimeDropdownValue('hour', button.dataset.value);
  });
  els.timePickerMinuteMenu.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-value]');
    if (!button) return;
    selectTimeDropdownValue('minute', button.dataset.value);
  });
  els.timePickerPeriodButtons.forEach((button) => {
    button.addEventListener('click', () => setPickerPeriod(button.dataset.period));
  });
  els.timePickerOverlay.addEventListener('click', (event) => {
    if (event.target === els.timePickerOverlay) closeTimePicker();
  });
  els.noteInput.addEventListener('input', () => {
    if (state.draft) state.draft.clear = false;
    els.noteCount.textContent = `${els.noteInput.value.length}/200`;
    readEntryInputs();
  });
  els.rateTypeGroup.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-rate]');
    if (!button || !state.draft) return;
    state.draft.clear = false;
    state.draft.rate_type = button.dataset.rate;
    state.draft.status = state.draft.status === 'work' ? 'work' : state.draft.status;
    setActiveOption(els.rateTypeGroup, 'rate', state.draft.rate_type);
    updatePayPreview();
  });
  els.modifierGroup.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-modifier]');
    if (!button || !state.draft) return;
    state.draft.clear = false;
    state.draft.modifier = button.dataset.modifier;
    setActiveOption(els.modifierGroup, 'modifier', state.draft.modifier);
    updatePayPreview();
  });
  els.restDayBtn.addEventListener('click', () => {
    if (!state.draft) return;
    state.draft.clear = false;
    state.draft.status = state.draft.status === 'rest' ? 'work' : 'rest';
    state.draft.time_in = '';
    state.draft.time_out = '';
    setTimeInput(els.timeInInput, '');
    setTimeInput(els.timeOutInput, '');
    els.normalHoursInput.value = '';
    els.extendedHoursInput.value = '';
    renderQuickLabels();
    updatePayPreview();
  });
  els.excusedBtn.addEventListener('click', () => {
    if (!state.draft) return;
    state.draft.clear = false;
    state.draft.status = state.draft.status === 'absent' ? 'work' : 'absent';
    state.draft.time_in = '';
    state.draft.time_out = '';
    setTimeInput(els.timeInInput, '');
    setTimeInput(els.timeOutInput, '');
    els.normalHoursInput.value = '';
    els.extendedHoursInput.value = '';
    renderQuickLabels();
    updatePayPreview();
  });
  els.clearDayBtn.addEventListener('click', () => {
    if (!state.draft) return;
    state.draft.clear = true;
    setTimeInput(els.timeInInput, '');
    setTimeInput(els.timeOutInput, '');
    els.normalHoursInput.value = '';
    els.extendedHoursInput.value = '';
    els.noteInput.value = '';
    els.noteCount.textContent = '0/200';
    updatePayPreview();
    toast('Press Save Entry to clear this day');
  });
  els.resetDataBtn.addEventListener('click', () => {
    closeAll(false);
    els.confirmOverlay.hidden = false;
    lockPageScroll();
    setCalendarOverlayRoute('reset', { month: monthKeyForDate(state.viewDate) });
  });
  els.cancelResetBtn.addEventListener('click', closeAll);
  els.confirmResetBtn.addEventListener('click', async () => {
    try {
      await api('/api/worktrack/reset', {
        method: 'DELETE',
        body: JSON.stringify({ browser_id: state.browserId }),
      });
      state.entries = {};
      state.draft = null;
      state.selectedDate = null;
      closeAll(false);
      setRouteSection('calendar', true);
      await loadState({ restoreRoute: false });
      toast('All work logs reset');
    } catch (error) {
      handleApiError(error);
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (els.calendarPeriodPicker && !els.calendarPeriodPicker.hidden) {
      closeCalendarPeriodPicker();
      return;
    }
    if (!els.dailyRecordsOverlay.hidden) {
      closeDailyRecords();
      return;
    }
    if (!els.exportMenu.hidden) {
      closeExportMenu();
      return;
    }
    if (!els.notificationPopover.hidden) {
      closeNotificationPopover();
      return;
    }
    if (!els.reminderVerifyOverlay.hidden) { backFromOtp(); return; }
    if (!els.profileAccessOverlay.hidden) { backFromAccess(); return; }
    if (!state.profile) return;
    if (!els.timePickerOverlay.hidden) {
      closeTimePicker();
      return;
    }
    if (!els.reminderSettingsOverlay.hidden) {
      closeReminderSettings();
      return;
    }
    closeAll();
  });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) { checkReminder(); syncProfileFromServer(); }
  });
  window.addEventListener('popstate', () => {
    if (!els.dailyRecordsOverlay.hidden) closeDailyRecords(true, false);
    openSectionFromRoute();
  });
  window.addEventListener('resize', fitEntryTitle);
  window.addEventListener('resize', fitNotificationPopover);
  window.addEventListener('focus', syncProfileFromServer);
}

async function requestReminderPermission() {
  if (!('Notification' in window)) {
    syncNotificationButton();
    return 'unsupported';
  }

  let permission = Notification.permission;
  if (permission === 'default') {
    try {
      permission = await Notification.requestPermission();
    } catch (error) {
      console.warn('Notification permission request failed.', error);
      permission = 'unavailable';
    }
  }
  syncNotificationButton();
  return permission;
}

async function startApp() {
  if (appLoadInFlight) return;
  appLoadInFlight = true;
  const app = document.querySelector('[data-app]');
  const loadError = document.getElementById('appLoadError');
  const retry = document.getElementById('retryAppLoadBtn');
  const body = document.body;
  const bootCover = document.getElementById('appBootCover');
  loadError.hidden = true;
  document.documentElement.dataset.appState = 'loading';
  body.dataset.appBooting = 'true';
  if (bootCover) bootCover.hidden = false;
  app.hidden = true;
  app.inert = true;
  app.setAttribute('aria-busy', 'true');
  try {
    await Promise.all([loadState(), document.fonts?.ready]);
    // Reveal only the hydrated dashboard and its fully restored route together.
    document.documentElement.dataset.appState = 'ready';
    delete body.dataset.appBooting;
    if (bootCover) bootCover.hidden = true;
    app.hidden = false;
    app.inert = false;
    app.setAttribute('aria-busy', 'false');
    if (!backgroundSyncStarted) {
      backgroundSyncStarted = true;
      setInterval(checkReminder, 5 * 1000);
      setInterval(syncProfileFromServer, 30 * 1000);
    }
  } catch (error) {
    document.documentElement.dataset.appState = 'ready';
    delete body.dataset.appBooting;
    if (bootCover) bootCover.hidden = true;
    app.inert = false;
    app.setAttribute('aria-busy', 'false');
    loadError.hidden = false;
    console.error('WorkTrack failed to initialize.', error);
  } finally {
    appLoadInFlight = false;
  }
}

async function init() {
  if (appInitialized) return;
  appInitialized = true;
  setTheme(state.theme);
  buildTimeDropdownOptions();
  attachEvents();
  document.getElementById('retryAppLoadBtn').addEventListener('click', startApp);
  await startApp();
}

init();
