/* global jQuery */
(function($) {
    function hiddenInput(name, value) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name + '[]';
        input.value = value;
        return input;
    }

    // The selects are the interface; these hidden inputs are what actually posts.
    // They are rewritten from the chosen <select> after every change, including
    // the empty sentinel, so "nothing chosen" still submits the field.
    //
    // Built as elements rather than an HTML string: setting .value assigns a
    // property, so an option key is never parsed as markup and nothing has to be
    // escaped. Escaping it as text would not have been enough anyway — that
    // leaves `"` alone, and a key containing one closes the value attribute (#22).
    function syncInputs($field) {
        var name = $field.data('name');
        var inputs = $field.find('[data-role="inputs"]').empty();

        inputs.append(hiddenInput(name, ''));

        $field.find('[data-role="chosen"] option').each(function() {
            inputs.append(hiddenInput(name, this.value));
        });
    }

    // Every button acts on whatever is selected, so a move with nothing selected
    // is a no-op rather than a silent whole-list shuffle.
    function moveAcross($from, $to) {
        var $selected = $from.find('option:selected');
        if (!$selected.length) {
            return false;
        }
        $to.append($selected);
        return true;
    }

    // Walking up from the top item (or down from the bottom) would wrap the
    // list, so the run at that end stays put and the rest still moves.
    function moveWithin($select, direction) {
        var $selected = $select.find('option:selected');
        if (!$selected.length) {
            return false;
        }

        var items = $selected.get();
        if (direction === 'down') {
            items.reverse();
        }

        var moved = false;
        $.each(items, function(_, option) {
            var $option = $(option);
            var $sibling = direction === 'up' ? $option.prev() : $option.next();
            if (!$sibling.length || $sibling.is(':selected')) {
                return;
            }
            if (direction === 'up') {
                $sibling.before($option);
            } else {
                $sibling.after($option);
            }
            moved = true;
        });

        return moved;
    }

    function initDualList($field) {
        var $available = $field.find('[data-role="available"]');
        var $chosen = $field.find('[data-role="chosen"]');

        $field.on('click', '.wps-dual-list-move', function() {
            var move = $(this).data('move');
            var changed = false;

            if (move === 'add') {
                changed = moveAcross($available, $chosen);
            } else if (move === 'remove') {
                changed = moveAcross($chosen, $available);
            } else {
                changed = moveWithin($chosen, move);
            }

            if (changed) {
                syncInputs($field);
            }
        });

        // Double-click is the shortcut people expect from this control.
        $field.on('dblclick', 'option', function() {
            var $option = $(this);
            var $source = $option.closest('select');

            $source.find('option').prop('selected', false);
            $option.prop('selected', true);

            moveAcross($source, $source.is($chosen) ? $available : $chosen);
            syncInputs($field);
        });

        syncInputs($field);
    }

    $(function() {
        $('.wps-dual-list').each(function() {
            initDualList($(this));
        });
    });
})(jQuery);
