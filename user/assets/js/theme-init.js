        (function () {
            const savedTheme = localStorage.getItem('user_panel_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
