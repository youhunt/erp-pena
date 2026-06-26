(function (window, document) {
    'use strict';

    function isTargetPage() {
        return window.location.pathname.toLowerCase().indexOf('/setup/suppliers') !== -1;
    }

    function setLabel(name, text) {
        var input = document.querySelector('[name="' + name + '"]');
        if (!input || !input.id) return;
        var label = document.querySelector('label[for="' + input.id + '"]');
        if (label) label.textContent = text;
    }

    function init() {
        if (!isTargetPage()) return;
        setLabel('employee', 'Internal PIC / Purchasing Staff (Optional)');
        setLabel('purchasing', 'Purchasing Group / Notes (Optional)');
        setLabel('supplierref', 'Supplier Reference (Optional)');
        setLabel('contactnar', 'Supplier Contact Person (Optional)');
    }

    document.addEventListener('DOMContentLoaded', init);
})(window, document);
