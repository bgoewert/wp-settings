/* global jQuery */
(function($) {
    function reindexList($list) {
        var $items = $list.children('.wps-sortable-item');
        var count = $items.length;
        $items.each(function(index) {
            var $item = $(this);
            var $input = $item.find('.wps-sortable-order');
            $input.attr('max', count);
            $input.val(index + 1);
        });
    }

    function moveItem($list, $item, newIndex) {
        var $items = $list.children('.wps-sortable-item');
        var count = $items.length;
        var targetIndex = Math.max(0, Math.min(newIndex, count - 1));
        var currentIndex = $item.index();

        if (currentIndex === targetIndex) {
            return;
        }

        if (targetIndex === 0) {
            $list.prepend($item);
            return;
        }

        if (targetIndex >= count - 1) {
            $list.append($item);
            return;
        }

        if (targetIndex > currentIndex) {
            $items.eq(targetIndex).after($item);
        } else {
            $items.eq(targetIndex).before($item);
        }
    }

    function initSortableList($list) {
        $list.on('change', '.wps-sortable-order', function() {
            var $input = $(this);
            var $item = $input.closest('.wps-sortable-item');
            var count = $list.children('.wps-sortable-item').length;
            var desired = parseInt($input.val(), 10);

            if (Number.isNaN(desired)) {
                reindexList($list);
                return;
            }

            desired = Math.max(1, Math.min(desired, count));
            moveItem($list, $item, desired - 1);
            reindexList($list);
        });

        if ($.fn.sortable) {
            $list.sortable({
                handle: '.wps-sortable-handle',
                axis: 'y',
                update: function() {
                    reindexList($list);
                }
            });
        }

        reindexList($list);
    }

    $(function() {
        $('.wps-sortable-list').each(function() {
            initSortableList($(this));
        });
    });
})(jQuery);
