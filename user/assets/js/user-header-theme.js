    function applyUserTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('user_panel_theme', theme);

        const desktopIcon = document.getElementById('themeToggleIcon');
        const desktopText = document.getElementById('themeToggleText');
        const mobileIcon = document.getElementById('mobileThemeToggleIcon');
        const mobileText = document.getElementById('mobileThemeToggleText');

        if (theme === 'dark') {
            if (desktopIcon) desktopIcon.textContent = '☀️';
            if (desktopText) desktopText.textContent = 'Light';
            if (mobileIcon) mobileIcon.textContent = '☀️';
            if (mobileText) mobileText.textContent = 'Light Mode';
        } else {
            if (desktopIcon) desktopIcon.textContent = '🌙';
            if (desktopText) desktopText.textContent = 'Dark';
            if (mobileIcon) mobileIcon.textContent = '🌙';
            if (mobileText) mobileText.textContent = 'Dark Mode';
        }
    }

    function toggleUserTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
        applyUserTheme(nextTheme);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const savedTheme = localStorage.getItem('user_panel_theme') || 'light';

        applyUserTheme(savedTheme);

        const desktopBtn = document.getElementById('themeToggleBtn');
        const mobileBtn = document.getElementById('mobileThemeToggleBtn');

        if (desktopBtn) {
            desktopBtn.addEventListener('click', toggleUserTheme);
        }

        if (mobileBtn) {
            mobileBtn.addEventListener('click', toggleUserTheme);
        }
    });
