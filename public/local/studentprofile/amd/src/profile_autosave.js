/**
 * AMD module for auto-saving the student profile completion form as a draft.
 * Sends form data via AJAX every `interval` ms. Does NOT log sensitive data.
 *
 * @module     local_studentprofile/profile_autosave
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    var _config = {};
    var _timer = null;

    /**
     * Collect all current form field values safely.
     *
     * @param {string} formid  The id attribute of the form element.
     * @returns {Object|null}
     */
    function collectFormData(formid) {
        var form = document.getElementById(formid);
        if (!form) {
            return null;
        }
        // Use safe DOM property access — no innerHTML.
        function val(name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) { return ''; }
            return el.value || '';
        }
        return {
            firstname:         val('firstname'),
            middlename:        val('middlename'),
            lastname:          val('lastname'),
            collegeid_key:     val('collegeid_key'),
            customcollegename: val('customcollegename'),
            admissionyear:     parseInt(val('admissionyear')) || 0,
            degree:            val('degree'),
            degreetype:        val('degreetype'),
        };
    }

    /**
     * Send draft data to the server.
     */
    function saveDraft() {
        var data = collectFormData(_config.formid);
        if (!data) {
            return;
        }
        Ajax.call([{
            methodname: 'local_studentprofile_save_draft',
            args: data,
            done: function(response) {
                if (response.success) {
                    // Show a subtle "Draft saved" indicator via safe DOM manipulation.
                    showDraftSavedIndicator();
                }
            },
            fail: function() {
                // Silent fail for auto-save — do not alert user.
            }
        }]);
    }

    /**
     * Show a "Draft saved" text indicator using safe DOM methods (no innerHTML).
     */
    function showDraftSavedIndicator() {
        var indicatorId = 'sp-draft-saved-indicator';
        var existing = document.getElementById(indicatorId);
        if (!existing) {
            var indicator = document.createElement('div');
            indicator.id = indicatorId;
            indicator.setAttribute('role', 'status');
            indicator.setAttribute('aria-live', 'polite');
            indicator.style.cssText = [
                'position:fixed',
                'bottom:20px',
                'right:24px',
                'background:#1a7f37',
                'color:#fff',
                'padding:8px 18px',
                'border-radius:6px',
                'font-size:0.9rem',
                'opacity:0',
                'transition:opacity 0.4s',
                'z-index:9999',
            ].join(';');
            // Set text content safely (no innerHTML).
            indicator.textContent = M.util.get_string('draftsaved', 'local_studentprofile');
            document.body.appendChild(indicator);
            existing = indicator;
        }
        existing.style.opacity = '1';
        clearTimeout(existing._fadeTimer);
        existing._fadeTimer = setTimeout(function() {
            existing.style.opacity = '0';
        }, 2500);
    }

    return {
        /**
         * Initialize the auto-save loop.
         *
         * @param {Object} config
         * @param {string} config.formid   ID attribute of the form element.
         * @param {number} config.interval Interval in milliseconds (default 30000).
         */
        init: function(config) {
            _config.formid  = config.formid  || 'mform1';
            _config.interval = config.interval || 30000;

            if (_timer) {
                clearInterval(_timer);
            }
            _timer = setInterval(saveDraft, _config.interval);
        }
    };
});
