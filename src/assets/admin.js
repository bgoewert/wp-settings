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

    /**
     * Evaluate a single condition against the current form state.
     *
     * @param {Object} condition - Condition object with field, operator, value.
     * @param {jQuery} $form - The form element to check values in.
     * @return {boolean} Whether the condition is met.
     */
    function evaluateCondition(condition, $form) {
        var fieldName = condition.field;
        var operator = condition.operator;
        var targetValue = condition.value;

        var $field = $form.find('[name="' + fieldName + '"]');
        if (!$field.length) {
            return false;
        }

        var fieldValue;
        if ($field.attr('type') === 'checkbox') {
            fieldValue = $field.is(':checked') ? $field.val() : '';
        } else if ($field.attr('type') === 'radio') {
            fieldValue = $form.find('[name="' + fieldName + '"]:checked').val() || '';
        } else {
            fieldValue = $field.val() || '';
        }

        switch (operator) {
            case 'equals':
                return fieldValue === targetValue;
            case 'not_equals':
                return fieldValue !== targetValue;
            case 'in':
                if (Array.isArray(targetValue)) {
                    return targetValue.indexOf(fieldValue) !== -1;
                }
                return fieldValue === targetValue;
            case 'not_in':
                if (Array.isArray(targetValue)) {
                    return targetValue.indexOf(fieldValue) === -1;
                }
                return fieldValue !== targetValue;
            case 'empty':
                return !fieldValue || fieldValue === '';
            case 'not_empty':
                return fieldValue && fieldValue !== '';
            default:
                return true;
        }
    }

    /**
     * Evaluate all conditions for a field row.
     * All conditions must be true (AND logic).
     *
     * @param {Array} conditions - Array of condition objects.
     * @param {jQuery} $form - The form element.
     * @return {boolean} Whether all conditions are met.
     */
    function evaluateConditions(conditions, $form) {
        if (!conditions || !conditions.length) {
            return true;
        }
        for (var i = 0; i < conditions.length; i++) {
            if (!evaluateCondition(conditions[i], $form)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Apply conditional visibility to all form rows with data-conditions.
     * Handles both:
     * - tr[data-conditions] for WP_Settings_Table modals
     * - .wps-field-wrapper[data-conditions] for regular settings forms
     *
     * @param {jQuery} $form - The form element.
     */
    function applyConditionalVisibility($form) {
        // Handle tr[data-conditions] (table modals).
        $form.find('tr[data-conditions]').each(function() {
            var $row = $(this);
            var conditions;
            try {
                conditions = JSON.parse($row.attr('data-conditions'));
            } catch (e) {
                return;
            }
            if (evaluateConditions(conditions, $form)) {
                $row.show();
            } else {
                $row.hide();
            }
        });

        // Handle .wps-field-wrapper[data-conditions] (regular settings forms).
        $form.find('.wps-field-wrapper[data-conditions]').each(function() {
            var $wrapper = $(this);
            var $row = $wrapper.closest('tr');
            var conditions;
            try {
                conditions = JSON.parse($wrapper.attr('data-conditions'));
            } catch (e) {
                return;
            }
            if (evaluateConditions(conditions, $form)) {
                if ($row.length) {
                    $row.show();
                } else {
                    $wrapper.show();
                }
            } else {
                if ($row.length) {
                    $row.hide();
                } else {
                    $wrapper.hide();
                }
            }
        });
    }

    /**
     * Get all field names that other fields depend on (controlling fields).
     *
     * @param {Object} fieldConditions - Map of field name to conditions array.
     * @return {Array} Array of unique controlling field names.
     */
    function getControllingFields(fieldConditions) {
        var controllingFields = [];
        Object.keys(fieldConditions).forEach(function(fieldName) {
            var conditions = fieldConditions[fieldName];
            conditions.forEach(function(condition) {
                if (condition.field && controllingFields.indexOf(condition.field) === -1) {
                    controllingFields.push(condition.field);
                }
            });
        });
        return controllingFields;
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

            // Apply conditional visibility after form is populated.
            applyConditionalVisibility($modalForm);
        }

        function closeModal() {
            $modal.addClass('wps-modal-hidden');
            $modal.attr('aria-hidden', 'true');
        }

        // Set up conditional visibility listeners for controlling fields.
        var fieldConditions = tableData.field_conditions || {};
        var controllingFields = getControllingFields(fieldConditions);
        if (controllingFields.length) {
            controllingFields.forEach(function(fieldName) {
                $modalForm.on('change', '[name="' + fieldName + '"]', function() {
                    applyConditionalVisibility($modalForm);
                });
            });

            // Also apply to fallback form if it exists.
            var $fallbackForm = $container.find('.wps-fallback-form form');
            if ($fallbackForm.length) {
                controllingFields.forEach(function(fieldName) {
                    $fallbackForm.on('change', '[name="' + fieldName + '"]', function() {
                        applyConditionalVisibility($fallbackForm);
                    });
                });
                // Apply initial visibility on page load for fallback form.
                applyConditionalVisibility($fallbackForm);
            }
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

    // Initialize conditional visibility for regular settings forms (non-table).
    var conditionalConfig = window.wpsSettingsConditionals || {};
    var controllingFields = conditionalConfig.controllingFields || [];

    if (controllingFields.length) {
        // Find all settings forms that might have conditional fields.
        $('form').each(function() {
            var $form = $(this);
            var hasConditionals = $form.find('.wps-field-wrapper[data-conditions]').length > 0;

            if (!hasConditionals) {
                return;
            }

            // Apply initial visibility.
            applyConditionalVisibility($form);

            // Set up change listeners for controlling fields.
            controllingFields.forEach(function(fieldSlug) {
                $form.on('change', '[name="' + fieldSlug + '"]', function() {
                    applyConditionalVisibility($form);
                });
            });
        });
    }
})(jQuery);
