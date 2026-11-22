/**
 * Collapses marked GET filter forms while keeping an accessible control for
 * opening them and a count of the currently selected filters.
 */
(function () {
    'use strict';

    function hasActiveValue(field) {
        if (field.disabled || !field.name || field.name === '_token') {
            return false;
        }

        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.checked;
        }

        if (field.tagName === 'SELECT') {
            return Array.prototype.some.call(field.selectedOptions, function (option) {
                return option.value !== '';
            });
        }

        if (['hidden', 'submit', 'button', 'reset', 'image'].indexOf(field.type) !== -1) {
            return false;
        }

        return String(field.value || '').trim() !== '';
    }

    function activeFilterCount(form) {
        return Array.prototype.filter.call(
            form.querySelectorAll('input[name], select[name], textarea[name]'),
            hasActiveValue
        ).length;
    }

    function pageActionContainer() {
        var heading = document.querySelector('.admin-content .content-heading');

        if (!heading) {
            return null;
        }

        var actions = heading.querySelector('.page-actions');

        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'page-actions';
            actions.setAttribute('aria-label', 'Page actions');
            heading.appendChild(actions);
        }

        return actions;
    }

    function standaloneFilterSurface(form) {
        var surface = form.parentElement;
        var hasOtherContent = Array.prototype.some.call(surface.children, function (child) {
            return child !== form;
        });

        if (hasOtherContent || !surface.classList.contains('admin-card')) {
            return null;
        }

        surface.classList.add('admin-filter-surface');

        return surface;
    }

    function createToggle(form, index) {
        var label = form.getAttribute('aria-label') || 'Filters';
        var formId = form.id || 'filter-form-' + (index + 1);
        var button = document.createElement('button');
        var icon = document.createElement('i');
        var text = document.createElement('span');
        var count = document.createElement('span');

        form.id = formId;
        form.hidden = true;
        form.setAttribute('aria-label', label);

        var surface = standaloneFilterSurface(form);

        if (surface) {
            surface.hidden = true;
        }

        button.type = 'button';
        button.className = 'btn btn-outline-secondary admin-filter-toggle';
        button.setAttribute('aria-controls', formId);
        button.setAttribute('aria-expanded', 'false');
        icon.className = 'fa-solid fa-sliders';
        icon.setAttribute('aria-hidden', 'true');
        text.textContent = 'Filters';
        count.className = 'admin-filter-count';
        count.hidden = true;

        button.append(icon, text, count);

        var actions = pageActionContainer();

        if (actions) {
            actions.appendChild(button);
        } else {
            form.insertAdjacentElement('beforebegin', button);
        }

        function refreshCount() {
            var total = activeFilterCount(form);

            count.textContent = total + ' active';
            count.hidden = total === 0;
        }

        button.addEventListener('click', function () {
            var expanded = button.getAttribute('aria-expanded') === 'true';

            form.hidden = expanded;

            if (surface) {
                surface.hidden = expanded;
            }

            button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            refreshCount();
        });

        form.addEventListener('change', refreshCount);
        form.addEventListener('input', refreshCount);
        refreshCount();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-filter-form').forEach(createToggle);
    });
})();
