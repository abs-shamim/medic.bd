(function () {
    var tabList = document.querySelector('.doctor-profile-tabs[role="tablist"]');

    if (!tabList) {
        return;
    }

    var tabs = Array.prototype.slice.call(tabList.querySelectorAll('[role="tab"]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-doctor-tab-panel]'));
    var page = document.querySelector('.doctor-profile-page');

    if (!tabs.length || !panels.length || !page) {
        return;
    }

    page.classList.add('doctor-profile-tabs-ready');

    function activateTab(tabName, updateHash) {
        var selectedTab = null;

        tabs.forEach(function (tab) {
            var isActive = tab.getAttribute('data-doctor-tab') === tabName;

            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.tabIndex = isActive ? 0 : -1;

            if (isActive) {
                selectedTab = tab;
            }
        });

        panels.forEach(function (panel) {
            var isActive = panel.getAttribute('data-doctor-tab-panel') === tabName;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });

        if (selectedTab && updateHash && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + tabName);
        }
    }

    var initialTab = window.location.hash.replace('#', '');
    var allowedTabs = ['info', 'experience', 'reviews'];

    if (allowedTabs.indexOf(initialTab) === -1) {
        initialTab = 'info';
    }

    activateTab(initialTab, false);

    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () {
            activateTab(tab.getAttribute('data-doctor-tab'), true);
        });

        tab.addEventListener('keydown', function (event) {
            var nextIndex = null;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                nextIndex = (index + 1) % tabs.length;
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                nextIndex = (index - 1 + tabs.length) % tabs.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = tabs.length - 1;
            }

            if (nextIndex === null) {
                return;
            }

            event.preventDefault();
            tabs[nextIndex].focus();
            activateTab(tabs[nextIndex].getAttribute('data-doctor-tab'), true);
        });
    });

    window.addEventListener('hashchange', function () {
        var hashTab = window.location.hash.replace('#', '');

        if (allowedTabs.indexOf(hashTab) !== -1) {
            activateTab(hashTab, false);
        }
    });
}());
