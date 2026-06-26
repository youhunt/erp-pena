(function (window, document, $) {
    'use strict';

    const currencyOptions = [
        { code: 'IDR', name: 'Indonesian Rupiah' },
        { code: 'USD', name: 'US Dollar' },
        { code: 'EUR', name: 'Euro' },
        { code: 'SGD', name: 'Singapore Dollar' },
        { code: 'JPY', name: 'Japanese Yen' },
        { code: 'CNY', name: 'Chinese Yuan' },
        { code: 'MYR', name: 'Malaysian Ringgit' },
        { code: 'AUD', name: 'Australian Dollar' }
    ];

    function enhanceBaseCurrency() {
        const input = document.getElementById('base_currency');
        if (!input || input.tagName.toLowerCase() === 'select' || input.dataset.enhanced === '1') {
            return;
        }

        const currentValue = (input.value || 'IDR').toUpperCase();
        const select = document.createElement('select');
        select.id = input.id;
        select.name = input.name;
        select.className = 'form-select';
        select.dataset.enhanced = '1';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select Base Currency';
        select.appendChild(placeholder);

        const knownCodes = [];
        currencyOptions.forEach(function (currency) {
            knownCodes.push(currency.code);
            const option = document.createElement('option');
            option.value = currency.code;
            option.textContent = currency.code + ' - ' + currency.name;
            if (currency.code === currentValue) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        if (currentValue !== '' && knownCodes.indexOf(currentValue) === -1) {
            const customOption = document.createElement('option');
            customOption.value = currentValue;
            customOption.textContent = currentValue;
            customOption.selected = true;
            select.appendChild(customOption);
        }

        input.parentNode.replaceChild(select, input);

        if (window.PenaSelect) {
            window.PenaSelect.init(select.parentElement || document);
        } else if ($ && $.fn && $.fn.select2) {
            $(select).select2({ width: '100%', allowClear: true, placeholder: 'Pilih / cari Base Currency' });
        }
    }

    document.addEventListener('DOMContentLoaded', enhanceBaseCurrency);
})(window, document, window.jQuery);
