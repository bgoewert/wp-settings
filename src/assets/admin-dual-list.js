/**
 * WordPress Settings Library - Dual List Field
 *
 * Two listboxes: drag items within the chosen list to order them, or across to
 * move them, with buttons and keys doing the same for anyone not using a
 * pointer. The chosen list is mirrored into hidden inputs on every change,
 * because that list — not a selection inside it — is the value being saved.
 *
 * The sides are `ul[role=listbox]` rather than `select[multiple]` because an
 * `<option>` cannot be dragged: Firefox and Safari fire no drag events on one.
 * Selection, focus and the keyboard are therefore ours to implement, and what
 * follows is the WAI-ARIA listbox pattern.
 *
 * Plain DOM: nothing here needs jQuery, so the field does not load it.
 */
(function() {
    'use strict';

    /**
     * Items of a list.
     *
     * @param {HTMLElement} list Listbox.
     * @return {HTMLElement[]} Item elements.
     */
    function items(list) {
        return Array.prototype.slice.call(list.querySelectorAll('.wps-dual-list-item'));
    }

    /**
     * Items currently selected in a list.
     *
     * @param {HTMLElement} list Listbox.
     * @return {HTMLElement[]} Selected items.
     */
    function selected(list) {
        return items(list).filter(function(item) {
            return 'true' === item.getAttribute('aria-selected');
        });
    }

    /**
     * Move the roving focus marker.
     *
     * @param {HTMLElement} list Listbox.
     * @param {HTMLElement} item Item to make active.
     */
    function setActive(list, item) {
        items(list).forEach(function(other) {
            other.classList.toggle('is-active', other === item);
        });

        list.setAttribute('aria-activedescendant', item ? item.id : '');

        if (item) {
            // Without this the active item walks off-screen and a keyboard user
            // loses track of where they are.
            item.scrollIntoView({ block: 'nearest' });
        }
    }

    /**
     * Mark one item selected, or add it to the selection.
     *
     * @param {HTMLElement} list Listbox.
     * @param {HTMLElement} item Item to select.
     * @param {boolean}     keep Whether to keep the existing selection.
     */
    function select(list, item, keep) {
        if (!keep) {
            items(list).forEach(function(other) {
                other.setAttribute('aria-selected', 'false');
            });
        }

        item.setAttribute('aria-selected', 'true');
        setActive(list, item);
    }

    /**
     * Select every item between the active one and the given one.
     *
     * @param {HTMLElement} list Listbox.
     * @param {HTMLElement} to   Item at the far end of the range.
     */
    function selectRange(list, to) {
        var all = items(list);
        var active = list.querySelector('.is-active') || all[0];
        var from = all.indexOf(active);
        var target = all.indexOf(to);
        var start = Math.min(from, target);
        var end = Math.max(from, target);

        all.forEach(function(item, index) {
            item.setAttribute('aria-selected', index >= start && index <= end ? 'true' : 'false');
        });

        setActive(list, to);
    }

    function initDualList(root) {
        var name = root.getAttribute('data-name');
        var available = root.querySelector('[data-role="available"]');
        var chosen = root.querySelector('[data-role="chosen"]');
        var inputs = root.querySelector('[data-role="inputs"]');
        var dragged = [];

        /**
         * Rewrite the hidden inputs to match the chosen list, in its order.
         *
         * Built as elements rather than an HTML string: setting .value assigns a
         * property, so an option key is never parsed as markup and nothing has
         * to be escaped. Escaping it as text would not have been enough anyway —
         * that leaves `"` alone, and a key containing one closes the value
         * attribute (#22).
         */
        function sync() {
            while (inputs.firstChild) {
                inputs.removeChild(inputs.firstChild);
            }

            // The empty sentinel keeps the field in $_POST when nothing is
            // chosen, so "display nothing" saves instead of silently keeping
            // whatever was stored before.
            [''].concat(items(chosen).map(function(item) {
                return item.getAttribute('data-key');
            })).forEach(function(value) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name + '[]';
                input.value = value;
                inputs.appendChild(input);
            });
        }

        /**
         * Move the selected items from one list to the other.
         *
         * @param {HTMLElement} from Source list.
         * @param {HTMLElement} to   Destination list.
         */
        function move(from, to) {
            selected(from).forEach(function(item) {
                item.setAttribute('aria-selected', 'false');
                to.appendChild(item);
            });

            sync();
        }

        /**
         * Shift the selected items within the chosen list.
         *
         * @param {number} delta -1 to move up, 1 to move down.
         */
        function shift(delta) {
            var all = items(chosen);
            var picked = selected(chosen);

            if (delta > 0) {
                picked.reverse();
            }

            picked.forEach(function(item) {
                var index = items(chosen).indexOf(item);
                var target = index + delta;

                if (target < 0 || target >= all.length) {
                    return;
                }

                var sibling = items(chosen)[target];

                /*
                 * Swapping with a sibling that is also selected transposes the
                 * run rather than moving it: once a block reaches an end, the
                 * item stuck there becomes the next one's swap partner, and the
                 * pair flips on every further press.
                 */
                if ('true' === sibling.getAttribute('aria-selected')) {
                    return;
                }

                chosen.insertBefore(item, delta < 0 ? sibling : sibling.nextSibling);
            });

            sync();
        }

        /**
         * Clear the visual drag state on both lists.
         */
        function cleanUpDrag() {
            dragged.forEach(function(item) {
                item.classList.remove('is-dragging');
            });
            dragged = [];

            [available, chosen].forEach(function(list) {
                list.classList.remove('is-drop-into');
                items(list).forEach(function(item) {
                    item.classList.remove('is-drop-target');
                });
            });
        }

        /**
         * Wire selection, keyboard and drag for one list.
         *
         * @param {HTMLElement} list  Listbox.
         * @param {HTMLElement} other The list items move to on Enter or double-click.
         */
        function wire(list, other) {
            /*
             * Pressing an item that is already selected must not collapse the
             * selection, or a group can never be dragged: `dragstart` would find
             * one item left. The collapse is deferred to mouseup, and only when
             * no drag happened — the rule every file manager follows.
             */
            var pendingCollapse = null;

            list.addEventListener('mousedown', function(event) {
                var item = event.target.closest('.wps-dual-list-item');
                pendingCollapse = null;

                if (!item) {
                    return;
                }

                if (event.shiftKey) {
                    // Stop the browser selecting the label text instead.
                    event.preventDefault();
                    selectRange(list, item);
                    return;
                }

                if (event.ctrlKey || event.metaKey) {
                    select(list, item, true);
                    return;
                }

                if ('true' === item.getAttribute('aria-selected')) {
                    setActive(list, item);
                    pendingCollapse = item;
                    return;
                }

                select(list, item, false);
            });

            list.addEventListener('mouseup', function() {
                if (pendingCollapse) {
                    select(list, pendingCollapse, false);
                    pendingCollapse = null;
                }
            });

            // Double-click is the shortcut people expect from this control.
            list.addEventListener('dblclick', function(event) {
                if (event.target.closest('.wps-dual-list-item')) {
                    move(list, other);
                }
            });

            list.addEventListener('keydown', function(event) {
                var all = items(list);

                if (!all.length) {
                    return;
                }

                var active = list.querySelector('.is-active');
                var down = 'ArrowDown' === event.key;

                if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
                    event.preventDefault();

                    // Arriving from the outside, an arrow enters the list at the
                    // end it points from — the same as a native select. Moving
                    // past the first item would leave it unreachable.
                    if (!active) {
                        select(list, down ? all[0] : all[all.length - 1], false);
                        return;
                    }

                    var next = all[all.indexOf(active) + (down ? 1 : -1)];

                    if (!next) {
                        return;
                    }

                    if (event.shiftKey) {
                        selectRange(list, next);
                        return;
                    }

                    select(list, next, false);
                    return;
                }

                if (' ' === event.key || 'Spacebar' === event.key) {
                    event.preventDefault();
                    var toggle = active || all[0];
                    toggle.setAttribute(
                        'aria-selected',
                        'true' === toggle.getAttribute('aria-selected') ? 'false' : 'true'
                    );
                    setActive(list, toggle);
                    return;
                }

                if ('Enter' === event.key) {
                    // The lists sit in a settings form, so a bare Enter would
                    // submit it before the move was made.
                    event.preventDefault();
                    move(list, other);
                }
            });

            list.addEventListener('dragstart', function(event) {
                var item = event.target.closest('.wps-dual-list-item');

                if (!item) {
                    return;
                }

                // Dragging an unselected item drags that item alone, which is
                // what every file manager does.
                if ('true' !== item.getAttribute('aria-selected')) {
                    select(list, item, false);
                }

                // A drag is under way, so the click that started it must not
                // collapse the selection when the button comes up.
                pendingCollapse = null;

                dragged = selected(list);
                dragged.forEach(function(picked) {
                    picked.classList.add('is-dragging');
                });

                event.dataTransfer.effectAllowed = 'move';
                // Firefox ignores a drag that sets no data.
                event.dataTransfer.setData('text/plain', item.getAttribute('data-key'));
            });

            list.addEventListener('dragover', function(event) {
                if (!dragged.length) {
                    return;
                }

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';

                var over = event.target.closest('.wps-dual-list-item');

                items(list).forEach(function(item) {
                    item.classList.toggle('is-drop-target', item === over);
                });

                // The list is a drop target in its own right, so a drop below
                // the last item appends rather than doing nothing.
                list.classList.toggle('is-drop-into', !over);
            });

            list.addEventListener('dragleave', function() {
                list.classList.remove('is-drop-into');
            });

            list.addEventListener('drop', function(event) {
                if (!dragged.length) {
                    return;
                }

                event.preventDefault();

                var over = event.target.closest('.wps-dual-list-item');

                /*
                 * One reference node for the whole group, and every item goes
                 * before it in turn: inserting each one relative to the item
                 * under the pointer would reverse a multi-item drag, because
                 * each insert lands in front of the last.
                 */
                var reference = null;

                if (over && -1 === dragged.indexOf(over)) {
                    // Drop above the item the pointer is over; past its
                    // midpoint, below it.
                    var box = over.getBoundingClientRect();
                    reference = event.clientY > box.top + box.height / 2 ? over.nextSibling : over;
                }

                dragged.forEach(function(item) {
                    if (reference) {
                        list.insertBefore(item, reference);
                        return;
                    }

                    list.appendChild(item);
                });

                cleanUpDrag();
                sync();
            });

            list.addEventListener('dragend', cleanUpDrag);
        }

        wire(available, chosen);
        wire(chosen, available);

        // Every button acts on whatever is selected, so a press with nothing
        // selected is a no-op rather than a silent whole-list shuffle.
        root.addEventListener('click', function(event) {
            var button = event.target.closest('.wps-dual-list-move');

            if (!button) {
                return;
            }

            var action = button.getAttribute('data-move');

            if ('add' === action) {
                move(available, chosen);
            } else if ('remove' === action) {
                move(chosen, available);
            } else {
                shift('up' === action ? -1 : 1);
            }
        });

        sync();
    }

    function boot() {
        Array.prototype.forEach.call(document.querySelectorAll('.wps-dual-list'), initDualList);
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
