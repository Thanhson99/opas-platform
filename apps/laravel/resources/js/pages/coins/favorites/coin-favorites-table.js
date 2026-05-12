/**
 * Coin Favorites Table Script
 *
 * Handles:
 * - DataTables sorting
 * - Updating sort icons
 * - Toggle favorite via AJAX
 * - Toast messages
 */

import $ from 'jquery';
import 'datatables.net-bs5';
import toastr from 'toastr';

$(function () {
    const $table = $('.datatable');

    // Initialize DataTable
    const table = $table.DataTable({
        order: [],
        columnDefs: [
            { orderable: false, targets: 4 },
            { targets: '_all', orderSequence: ['asc', 'desc', ''] },
        ],
    });

    /**
     * Update table sort icons based on current order state.
     *
     * - Reset all to default icon.
     * - Highlight active sorted column.
     */
    const updateSortIcons = () => {
        const order = table.order();

        $('thead th').each(function (index) {
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
     * Toggle favorite coin status.
     */
    $('.favorite-toggle').on('click', function (e) {
        e.preventDefault();

        const $btn = $(this);
        const symbol = $btn.data('symbol');
        const $icon = $btn.find('i');
        const toggleUrl = $('meta[name="toggle-favorite-url"]').attr('content');
        const csrf = $('meta[name="csrf-token"]').attr('content');

        $.ajax({
            url: toggleUrl,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
            },
            data: { symbol },
            success(response) {
                if (response.success) {
                    $icon.toggleClass('fas text-danger far text-muted');
                    showToast('success', response.message);
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
