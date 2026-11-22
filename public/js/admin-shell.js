(function () {
    'use strict';

    var colorModeKey = 'school-erp-color-mode';

    function setColorMode(isDark) {
        document.body.classList.toggle('admin-dark', isDark);

        var toggle = document.querySelector('[data-color-mode-toggle]');

        if (!toggle) {
            return;
        }

        var icon = toggle.querySelector('i');
        var label = isDark ? 'Switch to light mode' : 'Switch to dark mode';

        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', label);

        if (icon) {
            icon.classList.toggle('fa-moon', !isDark);
            icon.classList.toggle('fa-sun', isDark);
            icon.classList.toggle('fa-regular', !isDark);
            icon.classList.toggle('fa-solid', isDark);
        }
    }

    function initColorMode() {
        var isDark = false;

        try {
            isDark = window.localStorage.getItem(colorModeKey) === 'dark';
        } catch (error) {
            isDark = false;
        }

        setColorMode(isDark);

        var toggle = document.querySelector('[data-color-mode-toggle]');

        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function () {
            isDark = !document.body.classList.contains('admin-dark');
            setColorMode(isDark);

            try {
                window.localStorage.setItem(colorModeKey, isDark ? 'dark' : 'light');
            } catch (error) {
                // The mode still applies for this page when storage is unavailable.
            }
        });
    }

    function initFullscreen() {
        var toggle = document.querySelector('[data-fullscreen-toggle]');

        if (!toggle) {
            return;
        }

        function updateButton() {
            var isFullscreen = Boolean(document.fullscreenElement);
            var label = isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen';
            var icon = toggle.querySelector('i');

            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('title', label);

            if (icon) {
                icon.classList.toggle('fa-expand', !isFullscreen);
                icon.classList.toggle('fa-compress', isFullscreen);
            }
        }

        toggle.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen().catch(function () {});
                return;
            }

            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(function () {});
            }
        });

        document.addEventListener('fullscreenchange', updateButton);
        updateButton();
    }

    function initMenuSearch() {
        var search = document.querySelector('[data-admin-menu-search]');
        var menu = document.querySelector('.admin-menu');

        if (!search || !menu) {
            return;
        }

        function matches(link, query) {
            return link.textContent.toLowerCase().indexOf(query) !== -1;
        }

        search.addEventListener('focus', function () {
            search.setAttribute('aria-keyshortcuts', '/');
        });

        search.addEventListener('input', function () {
            var query = search.value.trim().toLowerCase();
            var groups = menu.querySelectorAll('[data-menu-group]');
            var groupedItems = new Set();

            groups.forEach(function (group) {
                var submenu = group.querySelector('[data-menu-submenu]');
                var items = group.querySelectorAll('.admin-menu-item');
                var hasMatch = false;

                if (!submenu) {
                    return;
                }

                if (query && !group.hasAttribute('data-search-state-saved')) {
                    group.dataset.searchWasOpen = String(!submenu.hidden);
                    group.setAttribute('data-search-state-saved', 'true');
                }

                items.forEach(function (item) {
                    groupedItems.add(item);
                    var itemMatches = query === '' || matches(item, query);
                    item.hidden = !itemMatches;
                    hasMatch = hasMatch || itemMatches;
                });

                if (query) {
                    group.hidden = !hasMatch;
                    submenu.hidden = !hasMatch;
                    group.classList.toggle('is-open', hasMatch);
                } else {
                    group.hidden = false;
                    submenu.hidden = group.dataset.searchWasOpen !== 'true';
                    group.classList.toggle('is-open', !submenu.hidden);
                    group.removeAttribute('data-search-state-saved');
                    delete group.dataset.searchWasOpen;
                }

                var toggle = group.querySelector('[data-menu-group-toggle]');

                if (toggle) {
                    toggle.setAttribute('aria-expanded', String(query ? hasMatch : !submenu.hidden));
                }
            });

            menu.querySelectorAll('.admin-menu-item').forEach(function (item) {
                if (groupedItems.has(item)) {
                    return;
                }

                item.hidden = query !== '' && !matches(item, query);
            });
        });

        document.addEventListener('keydown', function (event) {
            var target = event.target;
            var isTyping = target instanceof HTMLElement
                && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName));

            if (event.key === '/' && !isTyping && !event.ctrlKey && !event.metaKey && !event.altKey) {
                event.preventDefault();
                search.focus();
            }

            if (event.key === 'Escape' && document.activeElement === search) {
                search.value = '';
                search.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initMenuSearch();
        initColorMode();
        initFullscreen();
    });
})();
