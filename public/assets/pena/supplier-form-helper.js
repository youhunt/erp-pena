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

    function addNote() {
        var form = document.querySelector('form');
        if (!form || document.getElementById('supplier-e2e-note')) return;
        var note = document.createElement('div');
        note.id = 'supplier-e2e-note';
        note.className = 'alert alert-info mb-3';
        note.innerHTML = '<strong>Testing E2E PO:</strong> cukup isi <strong>Supplier Code</strong> dan <strong>Supplier Name</strong>. Field lain boleh dikosongkan kalau tidak required.';
        form.insertBefore(note, form.firstChild);
    }

    function init() {
        if (!isTargetPage()) return;
        setLabel('employee', 'Internal PIC / Purchasing Staff (Optional)');
        setLabel('purchasing', 'Purchasing Group / Notes (Optional)');
        setLabel('supplierref', 'Supplier Reference (Optional)');
        setLabel('contactnar', 'Supplier Contact Person (Optional)');
        addNote();
    }

    document.addEventListener('DOMContentLoaded', init);
})(window, document);
