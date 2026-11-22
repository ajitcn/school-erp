/**
 * Initializes searchable Bootstrap select controls while keeping their native
 * select elements as the submitted form fields.
 */
(function () {
    'use strict';

    if (!window.jQuery || !jQuery.fn.selectpicker) {
        return;
    }

    jQuery.fn.selectpicker.Constructor.BootstrapVersion = '4';

    // Portal-based dropdowns can overlap when several dynamic form rows contain pickers.
    jQuery(document)
        .off('show.bs.select.btSelectClosePeers', 'select[multiple], select.bt-select')
        .on('show.bs.select.btSelectClosePeers', 'select[multiple], select.bt-select', function () {
            var current = this;

            jQuery('select[multiple], select.bt-select').not(current).each(function () {
                var peer = jQuery(this);
                var wrapper = peer.parent('.bootstrap-select');

                if (peer.data('selectpicker') && (wrapper.hasClass('show') || wrapper.hasClass('open'))) {
                    peer.selectpicker('toggle');
                }
            });
        });

    function initializeSelect(select) {
        var control = jQuery(select);

        if (control.data('selectpicker')) {
            return;
        }

        var multiple = control.prop('multiple');
        var liveSearch = control.attr('data-live-search');

        control.selectpicker({
            actionsBox: multiple && control.attr('data-actions-box') !== 'false',
            liveSearch: liveSearch === 'true' || (liveSearch === undefined && (multiple || control.hasClass('bt-select'))),
            noneSelectedText: multiple ? 'Select one or more' : 'Choose an option',
            selectedTextFormat: multiple ? 'count > 2' : 'values',
            size: 8,
            style: 'form-control',
            width: '100%',
            container: 'body',
        });
    }

    /** Initialize picker controls inside dynamically inserted content. */
    window.initBtSelect = function (scope) {
        var root = jQuery(scope || document);
        root.find('select[multiple], select.bt-select')
            .addBack('select[multiple], select.bt-select')
            .filter(function () {
                return !this.closest('template');
            })
            .each(function () {
                initializeSelect(this);
            });
    };

    window.refreshBtSelect = function (select) {
        if (!select || !jQuery.fn.selectpicker) {
            return;
        }

        initializeSelect(select);
        jQuery(select).selectpicker('refresh');
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.initBtSelect(document);
    });
})();
