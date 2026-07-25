/**
 * AMD JavaScript module for AJAX filtering and pagination of the student profile participants list.
 *
 * @module     local_studentprofile/participants_filter
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    return {
        /**
         * Initialize the filtering logic.
         *
         * @param {Object} config Configuration values.
         */
        init: function(config) {
            var container = $('#studentprofile-participants-container');
            var form = $('#studentprofile-filter-form');

            /**
             * Send Web Service call to fetch updated table.
             *
             * @param {Object} params Request parameters.
             */
            function loadTable(params) {
                container.css('opacity', '0.5');
                Ajax.call([{
                    methodname: 'local_studentprofile_get_participants_table',
                    args: params,
                    done: function(response) {
                        container.html(response.html);
                        container.css('opacity', '1');
                    },
                    fail: function(ex) {
                        container.css('opacity', '1');
                        Notification.exception(ex);
                    }
                }]);
            }

            // Handle filter form submission.
            form.on('submit', function(e) {
                e.preventDefault();
                var params = {
                    courseid: config.courseid,
                    college: $('#filter-college').val(),
                    degree: $('#filter-degree').val(),
                    year: parseInt($('#filter-year').val()) || 0,
                    search: $('#filter-search').val(),
                    page: 0
                };
                loadTable(params);
            });

            // Handle sorting headers and pagination links.
            container.on('click', '.pagination a, th.header a', function(e) {
                e.preventDefault();
                var href = $(this).attr('href');
                if (!href) {
                    return;
                }

                // Parse the URL parameters.
                var urlParams = new URL(href, window.location.origin).searchParams;
                var page = parseInt(urlParams.get('page')) || 0;
                var perpage = parseInt(urlParams.get('perpage')) || 20;
                var tsort = urlParams.get('tsort') || '';
                var tifirst = urlParams.get('tifirst') || '';
                var tilast = urlParams.get('tilast') || '';

                var params = {
                    courseid: config.courseid,
                    college: $('#filter-college').val(),
                    degree: $('#filter-degree').val(),
                    year: parseInt($('#filter-year').val()) || 0,
                    search: $('#filter-search').val(),
                    page: page,
                    perpage: perpage,
                    tsort: tsort,
                    tifirst: tifirst,
                    tilast: tilast
                };

                loadTable(params);
            });
        }
    };
});
