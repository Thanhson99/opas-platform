/**
 * Coin Alert Settings - Toggle Active Status
 *
 * Handles enabling/disabling alerts via AJAX without page reload.
 * Route is passed from Blade via data-url to avoid hardcoding.
 */

import $ from 'jquery';

$(function () {
    $('.toggle-status').on('click', function () {
        const $btn = $(this);
        const $row = $btn.closest('tr');
        const url = $btn.data('url');

        $.ajax({
            url: url,
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (res) {
                const isActive = Boolean(res.is_active);

                // Update button style and icon
                // Dynamically switch the icon between fa-toggle-off and fa-toggle-on
                // along with button text (On/Off) to reflect the current state
                $btn
                    .toggleClass('btn-success', !isActive)
                    .toggleClass('btn-warning', isActive)
                    .html(
                        `<i class="fas ${isActive ? 'fa-toggle-off' : 'fa-toggle-on'}"></i> ` +
                        (isActive ? 'On' : 'Off')
                    );
            },
            error: function (xhr) {
                console.error('[Toggle Error]', xhr.responseText);
                alert('Error toggling status. Please try again.');
            }
        });
    });
});
