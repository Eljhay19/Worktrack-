{{-- WORKTRACK Header: logo, theme switch, notifications, and settings --}}
<header class="topbar" aria-label="WORKTRACK header">
    <div class="brand">
        <span class="brand-mark" aria-hidden="true"><span></span></span>
        <div class="brand-copy">
            <h1>WORKTRACK</h1>
            <div class="profile-note" id="profileNote" hidden>
                <span id="profileGreeting"></span>
                <small id="profileMeta"></small>
            </div>
        </div>
    </div>

    <div class="top-actions" aria-label="App actions">
        <button class="daily-records-trigger" type="button" id="dailyRecordsBtn" aria-label="Open daily records" aria-haspopup="dialog" aria-expanded="false" aria-controls="dailyRecordsOverlay">
            <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false"><rect x="4" y="5" width="16" height="16" rx="2"></rect><path d="M8 3v4M16 3v4M4 10h16M8 14h3M8 17h6"></path></svg>
            <span>Daily Records</span>
        </button>
        <button class="icon-btn mode-btn is-active" type="button" id="themeToggleBtn" aria-label="Switch to dark mode">
            <span aria-hidden="true" class="theme-icon"></span>
        </button>
        <div class="notification-anchor">
            <button class="icon-btn notification-btn" type="button" id="notificationBtn" aria-label="Open notifications" aria-expanded="false" aria-controls="notificationPopover">
                <span aria-hidden="true" class="bell-icon"></span>
                <span class="notification-count" id="notificationCount" aria-hidden="true" hidden></span>
            </button>
            <section class="notification-popover" id="notificationPopover" aria-labelledby="notificationPopoverTitle" hidden>
                <span class="notification-arrow" aria-hidden="true"></span>
                <div class="notification-popover-head">
                    <span class="notification-popover-icon" aria-hidden="true"><i class="bell-icon"></i></span>
                    <h2 id="notificationPopoverTitle">Notifications</h2>
                    <button type="button" class="notification-popover-close" id="closeNotificationPopover" aria-label="Close notifications">&times;</button>
                </div>
                <button type="button" class="notification-mark-all" id="markAllNotificationsRead">Mark all as read</button>
                <div class="notification-popover-body">
                    <ul class="notification-items" id="notificationItems" aria-live="polite" aria-label="Notifications"></ul>
                </div>
                <div class="notification-popover-footer">
                    <button type="button" class="notification-view-all" id="viewAllNotifications">View all notifications</button>
                    <button type="button" class="notification-popover-action" id="notificationPopoverAction">Manage reminders</button>
                </div>
            </section>
        </div>
        <button class="icon-btn" type="button" id="settingsBtn" aria-label="Settings">
            <span aria-hidden="true" class="gear-icon"></span>
        </button>
    </div>
</header>
