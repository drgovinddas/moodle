define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    var init = function() {
        // Listen for save button clicks on the rollno inputs
        $('body').on('click', '.save-rollno-btn', function(e) {
            e.preventDefault();
            
            var btn = $(this);
            var container = btn.closest('.rollno-edit-container');
            var userid = container.data('userid');
            var rollnoInput = container.find('.rollno-input').val();
            var rollnotypeSelect = container.find('.rollnotype-select').val();
            var rollnostatusSelect = container.find('.rollnostatus-select').val();

            btn.prop('disabled', true);
            var originalText = btn.html();
            btn.html('<i class="fa fa-spinner fa-spin"></i>');

            var request = {
                methodname: 'local_studentprofile_update_rollno',
                args: {
                    userid: userid,
                    rollno: rollnoInput,
                    rollnotype: rollnotypeSelect,
                    rollnostatus: rollnostatusSelect
                }
            };

            Ajax.call([request])[0].done(function(response) {
                if (response.success) {
                    Str.get_string('changessaved', 'core').done(function(str) {
                        // Using a simple toast or notification
                        // We can just reload the page to show the new name format, or update the DOM
                        window.location.reload();
                    }).fail(Notification.exception);
                }
            }).fail(function(ex) {
                btn.prop('disabled', false);
                btn.html(originalText);
                Notification.exception(ex);
            });
        });
    };

    return {
        init: init
    };
});
