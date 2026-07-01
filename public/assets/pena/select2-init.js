(function (window, $) {
    'use strict';

    function placeholderFor(select) {
        var label = '';
        var id = select.attr('id');

        if (id) {
            label = $('label[for="' + id + '"]').first().text();
        }

        if (!label) {
            label = select.closest('.mb-3, .form-group, td, th').find('label').first().text();
        }

        label = $.trim(label || '');

        return label ? 'Select / search ' + label : 'Select / search data';
    }

    function init(root) {
        if (!$ || !$.fn || !$.fn.select2) {
            return;
        }

        var scope = root ? $(root) : $(document);
        scope.find('select.form-select:not([data-no-search])').each(function () {
            var select = $(this);
            if (select.hasClass('select2-hidden-accessible')) {
                return;
            }

            select.select2({
                width: '100%',
                allowClear: !select.prop('required'),
                placeholder: placeholderFor(select),
                dropdownParent: select.closest('.modal').length ? select.closest('.modal') : $(document.body)
            });
        });
    }

    window.PenaSelect = {
        init: init
    };

    $(function () {
        init(document);
    });
})(window, window.jQuery);
