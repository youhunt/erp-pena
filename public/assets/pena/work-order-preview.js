(function () {
    'use strict';

    function initWorkOrderPreview() {
        var form = document.getElementById('workOrderForm');
        if (!form || form.dataset.woPreviewBound === '1') {
            return;
        }
        form.dataset.woPreviewBound = '1';

        var status = document.getElementById('woPreviewStatus');
        var componentBody = document.getElementById('woComponentRows');
        var routingBody = document.getElementById('woRoutingRows');
        var previewUrl = form.getAttribute('data-preview-url') || '';

        if (!previewUrl || !componentBody || !routingBody) {
            return;
        }

        function field(name) {
            return form.querySelector('[name="' + name + '"]');
        }

        function value(name) {
            var el = field(name);
            if (!el) {
                return '';
            }
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && window.jQuery(el).data('select2')) {
                var select2Value = window.jQuery(el).val();
                if (Array.isArray(select2Value)) {
                    return select2Value.join(',');
                }
                return select2Value || '';
            }
            return el.value || '';
        }

        function setValue(name, val) {
            var el = field(name);
            if (!el) {
                return;
            }
            el.value = val == null ? '' : val;
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && window.jQuery(el).data('select2')) {
                window.jQuery(el).trigger('change.select2');
            }
        }

        function esc(val) {
            return String(val == null ? '' : val).replace(/[&<>"']/g, function (char) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
            });
        }

        function numberValue(val) {
            var number = Number(val || 0);
            return Number.isFinite(number) ? String(number) : '0';
        }

        function setStatus(message, type) {
            if (!status) {
                return;
            }
            status.className = 'alert mt-4 mb-0 alert-' + (type || 'info');
            status.innerHTML = '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">'
                + '<span>' + esc(message) + '</span>'
                + '<button type="button" class="btn btn-sm btn-outline-primary" id="btnLoadWoBomRouting">Load BOM & Routing</button>'
                + '</div>';
            var button = document.getElementById('btnLoadWoBomRouting');
            if (button) {
                button.addEventListener('click', function () {
                    loadPreview(true);
                });
            }
        }

        function renderComponents(rows) {
            if (!rows || rows.length === 0) {
                componentBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Tidak ada component dari BOM untuk parent item ini.</td></tr>';
                return;
            }

            componentBody.innerHTML = rows.map(function (row, index) {
                return '<tr>'
                    + '<td><input name="component_line_no[]" class="form-control form-control-sm text-center" readonly value="' + esc(row.line_no || (index + 1)) + '"></td>'
                    + '<td><input name="component_item_code[]" class="form-control form-control-sm" readonly value="' + esc(row.component_item_code) + '"></td>'
                    + '<td><input name="component_item_name[]" class="form-control form-control-sm" readonly value="' + esc(row.component_item_name) + '"></td>'
                    + '<td><input type="number" step="0.000001" name="component_qty_used[]" class="form-control form-control-sm text-end" readonly value="' + esc(numberValue(row.qty_used)) + '"></td>'
                    + '<td><input name="component_uom_code[]" class="form-control form-control-sm" readonly value="' + esc(row.uom_code) + '"></td>'
                    + '<td><input name="component_warehouse_code[]" class="form-control form-control-sm" readonly value="' + esc(row.warehouse_code) + '"></td>'
                    + '<td><input name="component_location_code[]" class="form-control form-control-sm" readonly value="' + esc(row.location_code) + '"></td>'
                    + '<td><input name="component_batch_no[]" class="form-control form-control-sm" readonly value="' + esc(row.batch_no) + '"></td>'
                    + '<td><input type="number" step="0.000001" name="component_booking_qty[]" class="form-control form-control-sm text-end" readonly value="' + esc(numberValue(row.booking_qty)) + '"></td>'
                    + '</tr>';
            }).join('');
        }

        function renderRoutings(rows) {
            if (!rows || rows.length === 0) {
                routingBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada routing untuk parent item ini.</td></tr>';
                return;
            }

            routingBody.innerHTML = rows.map(function (row, index) {
                var wcLabel = String(row.work_center_code || '') + (row.work_center_name ? ' - ' + row.work_center_name : '');
                return '<tr>'
                    + '<td><input name="routing_line_no[]" class="form-control form-control-sm text-center" readonly value="' + esc(row.line_no || (index + 1)) + '"></td>'
                    + '<td><input name="wo_routing_name[]" class="form-control form-control-sm" readonly value="' + esc(row.routing_name) + '"></td>'
                    + '<td><input name="wo_work_center_code[]" type="hidden" value="' + esc(row.work_center_code) + '"><input name="wo_work_center_name[]" class="form-control form-control-sm" readonly value="' + esc(wcLabel) + '"></td>'
                    + '<td><input type="number" step="0.000001" name="wo_hour_qty[]" class="form-control form-control-sm text-end" readonly value="' + esc(numberValue(row.hour_qty)) + '"></td>'
                    + '<td><input name="wo_route_uom[]" class="form-control form-control-sm" readonly value="' + esc(row.uom_code) + '"></td>'
                    + '</tr>';
            }).join('');
        }

        var timer = null;
        function loadPreview(force) {
            clearTimeout(timer);
            timer = setTimeout(function () {
                var parentItem = value('parent_item_code');
                if (!parentItem) {
                    setStatus('Pilih Item Parent dulu, lalu klik Load BOM & Routing.', 'info');
                    return;
                }

                var params = new URLSearchParams({
                    site_code: value('site_code'),
                    department_code: value('department_code'),
                    warehouse_code: value('warehouse_code'),
                    parent_item_code: parentItem,
                    wo_qty: value('wo_qty') || '1'
                });

                setStatus('Mengambil BOM dan Routing...', 'info');

                fetch(previewUrl + '?' + params.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(function (response) {
                        return response.json().then(function (payload) {
                            if (!response.ok) {
                                throw new Error(payload.error || 'Gagal mengambil BOM/Routing.');
                            }
                            return payload;
                        });
                    })
                    .then(function (payload) {
                        setValue('bom_id', payload.bom ? payload.bom.id : '');
                        setValue('routing_id', payload.routing ? payload.routing.id : '');

                        if (payload.bom) {
                            setValue('batch_qty', numberValue(payload.bom.batch_qty || 1));
                            setValue('uom_code', payload.bom.uom_code || 'PCS');
                            if (!value('description') && payload.bom.description) {
                                setValue('description', payload.bom.description);
                            }
                        }

                        if (payload.routing && payload.routing.work_center_code && !value('work_center_code')) {
                            setValue('work_center_code', payload.routing.work_center_code);
                        }

                        renderComponents(payload.components || []);
                        renderRoutings(payload.routings || []);

                        var componentCount = (payload.components || []).length;
                        var routingCount = (payload.routings || []).length;
                        setStatus('BOM berhasil dimuat: ' + componentCount + ' component, routing: ' + routingCount + ' baris.', componentCount > 0 ? 'success' : 'warning');
                    })
                    .catch(function (error) {
                        renderComponents([]);
                        renderRoutings([]);
                        setStatus(error.message || 'BOM/Routing tidak ditemukan.', 'warning');
                    });
            }, force ? 0 : 250);
        }

        form.querySelectorAll('.js-wo-source').forEach(function (element) {
            element.addEventListener('change', function () { loadPreview(false); });
            element.addEventListener('input', function () { loadPreview(false); });
        });

        if (window.jQuery) {
            window.jQuery(form).find('.js-wo-source').on('change select2:select select2:clear', function () {
                loadPreview(false);
            });
        }

        setStatus('Pilih Item Parent, lalu sistem akan memuat BOM dan Routing otomatis. Klik tombol ini jika belum muncul.', 'info');

        // Let Select2/default values settle first, then load automatically.
        window.setTimeout(function () { loadPreview(true); }, 600);
        window.setTimeout(function () { loadPreview(true); }, 1500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWorkOrderPreview);
    } else {
        initWorkOrderPreview();
    }
})();
