/* global jQuery, ajaxurl, wpsSettingsTables */
(function($) {
    document.documentElement.classList.add('wps-has-js');

    function buildRedirectUrl(tableId, status) {
        var url = new URL(window.location.href);
        url.searchParams.set('wps_table', tableId);
        url.searchParams.set(status, '1');
        url.searchParams.delete('edit');
        return url.toString();
    }

    function applySort($table, colIndex, direction) {
        var $tbody = $table.find('tbody');
        var rows = $tbody.find('tr').get();

        rows.sort(function(a, b) {
            var aText = $(a).children('td,th').eq(colIndex).text().trim().toLowerCase();
            var bText = $(b).children('td,th').eq(colIndex).text().trim().toLowerCase();
            if (aText < bText) return direction === 'asc' ? -1 : 1;
            if (aText > bText) return direction === 'asc' ? 1 : -1;
            return 0;
        });

        $.each(rows, function(_, row) {
            $tbody.append(row);
        });
    }

    $('.wps-table').each(function() {
        var $container = $(this);
        var tableId = $container.data('table-id');
        var ajaxAction = $container.data('ajax-action');
        var ajaxNonce = $container.data('ajax-nonce');
        var tableData = (window.wpsSettingsTables && window.wpsSettingsTables[tableId]) ? window.wpsSettingsTables[tableId] : {};
        var rows = tableData.rows || {};

        var $modal = $container.find('.wps-modal');
        var $modalTitle = $modal.find('.wps-modal-title');
        var $modalForm = $modal.find('.wps-modal-form');

        function openModal(title, rowId) {
            $modalTitle.text(title);
            $modal.removeClass('wps-modal-hidden');
            $modal.attr('aria-hidden', 'false');
            $modalForm[0].reset();
            $modalForm.find('input[name="row_id"]').val(rowId || '');

            if (rowId && rows[rowId]) {
                var row = rows[rowId];
                Object.keys(row).forEach(function(key) {
                    var $field = $modalForm.find('[name="' + key + '"]');
                    if (!$field.length) return;
                    if ($field.attr('type') === 'checkbox') {
                        $field.prop('checked', !!row[key]);
                    } else if ($field.attr('type') === 'radio') {
                        $modalForm.find('[name="' + key + '"][value="' + row[key] + '"]').prop('checked', true);
                    } else {
                        $field.val(row[key]);
                    }
                });
            }
        }

        function closeModal() {
            $modal.addClass('wps-modal-hidden');
            $modal.attr('aria-hidden', 'true');
        }

        $container.on('click', '.wps-add-row', function() {
            openModal('Add Item', '');
        });

        $container.on('click', '.wps-edit-row', function(e) {
            e.preventDefault();
            var rowId = $(this).data('row-id');
            openModal('Edit Item', rowId);
        });

        $container.on('click', '.wps-delete-row', function() {
            var rowId = $(this).data('row-id');
            if (!rowId || !confirm('Are you sure you want to delete this item?')) {
                return;
            }
            $.post(ajaxurl, {
                action: ajaxAction,
                nonce: ajaxNonce,
                subaction: 'delete',
                row_id: rowId
            }).done(function(response) {
                if (response.success) {
                    window.location.href = buildRedirectUrl(tableId, 'deleted');
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Error deleting item.');
                }
            });
        });

        $container.on('click', '.wps-status-toggle', function() {
            var rowId = $(this).data('row-id');
            if (!rowId) return;
            $.post(ajaxurl, {
                action: ajaxAction,
                nonce: ajaxNonce,
                subaction: 'toggle',
                row_id: rowId
            }).done(function(response) {
                if (response.success) {
                    window.location.href = buildRedirectUrl(tableId, 'saved');
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Error updating status.');
                }
            });
        });

        $modal.on('click', '.wps-modal-close, .wps-modal-cancel', function() {
            closeModal();
        });

        $modal.on('click', function(e) {
            if ($(e.target).is('.wps-modal')) {
                closeModal();
            }
        });

        $modalForm.on('submit', function(e) {
            e.preventDefault();
            var data = $modalForm.serializeArray();
            var payload = {
                action: ajaxAction,
                nonce: ajaxNonce,
                subaction: 'save'
            };
            data.forEach(function(item) {
                payload[item.name] = item.value;
            });

            $.post(ajaxurl, payload).done(function(response) {
                if (response.success) {
                    window.location.href = buildRedirectUrl(tableId, 'saved');
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Error saving item.');
                }
            });
        });

        $container.on('submit', '.wps-table-form', function(e) {
            var $form = $(this);
            var bulkAction = $form.find('.wps-bulk-action-select').val();
            if (!bulkAction) {
                return;
            }
            e.preventDefault();

            var selected = [];
            $form.find('input[name="selected[]"]:checked').each(function() {
                selected.push($(this).val());
            });

            if (!selected.length) {
                alert('Select at least one item.');
                return;
            }

            $.post(ajaxurl, {
                action: ajaxAction,
                nonce: ajaxNonce,
                subaction: 'bulk',
                bulk_action: bulkAction,
                selected: selected
            }).done(function(response) {
                if (response.success) {
                    window.location.href = buildRedirectUrl(tableId, 'saved');
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Error applying bulk action.');
                }
            });
        });

        $container.on('change', '.wps-select-all', function() {
            var checked = $(this).is(':checked');
            $container.find('input[name="selected[]"]').prop('checked', checked);
        });

        $container.on('click', 'th[data-sort-key]', function() {
            var $th = $(this);
            var $table = $container.find('table');
            var colIndex = $th.index();
            var current = $th.data('sort-direction') || 'none';
            var next = current === 'asc' ? 'desc' : 'asc';
            $th.data('sort-direction', next);
            applySort($table, colIndex, next);
        });
    });
})(jQuery);
