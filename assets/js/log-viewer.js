/**
 * Log Viewer Admin Interface
 *
 * Handles AJAX interactions for the log viewer admin page.
 *
 * @package FieldsBright\Enrollment
 * @since   1.2.0
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // View context modal
        $('.fb-view-context').on('click', function () {
            const context = $(this).data('context');
            const contextFormatted = JSON.stringify(context, null, 2);
            $('#fb-context-data').text(contextFormatted);
            $('#fb-context-modal').fadeIn();
        });

        // Close modal
        $('.fb-modal-close').on('click', function () {
            $('#fb-context-modal').fadeOut();
        });

        // Close modal on outside click
        $(window).on('click', function (e) {
            if ($(e.target).is('#fb-context-modal')) {
                $('#fb-context-modal').fadeOut();
            }
        });

        // Export logs
        $('#export-logs').on('click', function () {
            const button = $(this);
            button.prop('disabled', true).text('Exporting...');

            $.ajax({
                url: fieldsBrightLogs.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fields_bright_export_logs',
                    nonce: fieldsBrightLogs.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Create download
                        const blob = new Blob([response.data.logs], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'enrollment-logs-' + new Date().toISOString().split('T')[0] + '.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);

                        alert('Logs exported successfully!');
                    } else {
                        alert('Error exporting logs: ' + response.data.message);
                    }
                },
                error: function () {
                    alert('Error exporting logs. Please try again.');
                },
                complete: function () {
                    button.prop('disabled', false).text('Export Logs');
                }
            });
        });

        // Refresh logs
        $('#refresh-logs').on('click', function () {
            location.reload();
        });

        // Auto-refresh every 30 seconds
        setInterval(function () {
            // Only auto-refresh if no filters are applied
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('level') && !urlParams.has('search')) {
                location.reload();
            }
        }, 30000);
    });
})(jQuery);

