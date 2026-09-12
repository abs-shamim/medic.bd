document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('rxDashboardSidebar');
    const overlay = document.querySelector('.rx-sidebar-overlay');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const closeButtons = document.querySelectorAll('[data-sidebar-close]');

    if (!sidebar || !overlay || !toggle) {
        return;
    }

    const openSidebar = function () {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-open');
        document.body.classList.add('rx-sidebar-open');
        toggle.setAttribute('aria-expanded', 'true');
    };

    const closeSidebar = function () {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-open');
        document.body.classList.remove('rx-sidebar-open');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', openSidebar);

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) {
            closeSidebar();
        }
    });
});
