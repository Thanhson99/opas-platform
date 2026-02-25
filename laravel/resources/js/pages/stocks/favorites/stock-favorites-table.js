/**
 * Stock Favorites Table Script
 *
 * Handles:
 * - DataTables sorting
 * - Updating sort icons
 * - Search input binding
 * - Toggle favorite via AJAX
 * - Toast messages
 */

import $ from 'jquery';
import 'datatables.net-bs5';
import toastr from 'toastr';

$(function () {
    const $table = $('.datatable');
    const $searchInput = $('#stock-search');
    const $searchClear = $('#stock-search-clear');

    // Initialize DataTable
    const table = $table.DataTable({
        dom: 'rt<"d-flex justify-content-between align-items-center mt-2"ip>',
        order: [],
        columnDefs: [
            { orderable: false, targets: 3 },
            { targets: '_all', orderSequence: ['asc', 'desc', ''] },
        ],
    });

    /**
     * Update table sort icons based on current order state.
     */
    const updateSortIcons = () => {
        const order = table.order();

        $('thead th').each(function () {
            const $icon = $(this).find('i.fas');
            if (!$icon.length) return;

            $icon
                .removeClass('fa-sort fa-sort-up fa-sort-down text-primary')
                .addClass('fa-sort text-muted');
        });

        if (order.length && order[0].length === 2) {
            const [colIndex, dir] = order[0];
            const $th = $('thead th').eq(colIndex);
            const $icon = $th.find('i.fas');

            if ($icon.length) {
                $icon.removeClass('fa-sort text-muted');
                $icon.addClass(
                    dir === 'asc' ? 'fa-sort-up text-primary' : 'fa-sort-down text-primary'
                );
            }
        }
    };

    table.on('order.dt', updateSortIcons);
    updateSortIcons();

    /**
     * Search input handler.
     */
    $searchInput.on('input', function () {
        const value = $(this).val();
        table.search(value).draw();
    });

    $searchClear.on('click', function () {
        $searchInput.val('');
        table.search('').draw();
    });

    /**
     * Make search case-insensitive by normalizing input and row data.
     */
    $.fn.dataTable.ext.search.push(function (settings, data) {
        if (settings.nTable !== $table.get(0)) {
            return true;
        }

        const raw = $searchInput.val();
        if (!raw) {
            return true;
        }

        const needle = raw.toString().toLowerCase().trim();
        if (!needle) {
            return true;
        }

        const haystack = data.join(' ').toLowerCase();

        return haystack.includes(needle);
    });

    /**
     * Show toast message.
     *
     * @param {'success'|'error'} type
     * @param {string} message
     */
    const showToast = (type, message) => {
        if (type === 'success') {
            toastr.success(message);
        } else {
            toastr.error(message);
        }
    };

    /**
     * Toggle favorite stock status.
     */
    $table.on('click', '.favorite-toggle', function (e) {
        e.preventDefault();

        const $btn = $(this);
        const symbol = $btn.data('symbol');
        const $icon = $btn.find('i');
        const addUrl = $('meta[name="add-favorite-url"]').attr('content');
        const removeUrl = $('meta[name="remove-favorite-url"]').attr('content');
        const csrf = $('meta[name="csrf-token"]').attr('content');
        const isFavorited = $btn.data('favorited') === 1 || $btn.data('favorited') === '1';
        const requestUrl = isFavorited ? removeUrl : addUrl;

        $.ajax({
            url: requestUrl,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
            },
            data: { symbol },
            success(response) {
                if (response.success) {
                    $icon.toggleClass('fas text-danger far text-muted');
                    $btn.data('favorited', isFavorited ? 0 : 1);
                    showToast('success', response.message);

                    const $row = $btn.closest('tr');
                    if (!isFavorited) {
                        $row.prependTo($table.find('tbody'));
                    } else {
                        $row.appendTo($table.find('tbody'));
                    }

                    table.rows().invalidate().draw(false);
                } else {
                    showToast('error', response.message || 'Something went wrong.');
                }
            },
            error() {
                showToast('error', 'Server error. Please try again.');
            },
        });
    });
});
