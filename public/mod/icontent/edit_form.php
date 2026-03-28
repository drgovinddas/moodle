<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Chapter edit form.
 *
 * @package    mod_icontent
 * @copyright  2015-2016 Leo Santos {@link http://github.com/leorenis}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');

/**
 * Class mod_icontent_pages_edit_form
 */
class icontent_pages_edit_form extends moodleform {

    /**
     * Define form elements
     * @throws coding_exception
     * @throws dml_exception
     */
    public function definition() {
        global $CFG, $COURSE;

        $page = $this->_customdata['page'];
        $pageicontentoptions = $this->_customdata['pageicontentoptions'];

        $mform = $this->_form;
        $icontentconfig = get_config('mod_icontent');

        $page->bgcolor = self::format_colour_for_picker($page->bgcolor ?? $icontentconfig->bgcolor ?? '#FCFCFC', '#FCFCFC');
        $page->bordercolor = self::format_colour_for_picker($page->bordercolor ?? $icontentconfig->bordercolor ?? '#E4E4E4', '#E4E4E4');

        if (!empty($page->id)) {
            $mform->addElement('header', 'general', get_string('editingpage', 'icontent'));
            $timemodified = time();
            $timecreated = 0;
        } else {
            $mform->addElement('header', 'general', get_string('addafter', 'icontent'));
            $timecreated = time();
            $timemodified = 0;
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $page->id);

        $mform->addElement('hidden', 'icontentid');
        $mform->setType('icontentid', PARAM_INT);
        $mform->setDefault('icontentid', $page->icontentid);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->setDefault('cmid', $page->cmid);

        // Need to see about using the pagenum to implement the move capability.
        $mform->addElement('hidden', 'pagenum');
        $mform->setType('pagenum', PARAM_INT);
        $mform->setDefault('pagenum', $page->pagenum);

        $mform->addElement('hidden', 'timemodified');
        $mform->setType('timemodified', PARAM_INT);
        $mform->setDefault('timemodified', $timemodified);

        $mform->addElement('hidden', 'timecreated');
        $mform->setType('timecreated', PARAM_INT);
        $mform->setDefault('timecreated', $timecreated);

        $mform->addElement('text', 'title', get_string('pagetitle', 'icontent'), ['class' => 'input-xxlarge']);
        $mform->setType('title', PARAM_RAW);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'coverpage', get_string('coverpage', 'icontent'));
        $mform->addHelpButton('coverpage', 'coverpage', 'icontent');
        $mform->setType('coverpage', PARAM_INT);
        $mform->setDefault('coverpage', 0);

        $mform->addElement('editor', 'pageicontent_editor', get_string('icontent', 'mod_icontent'), null, $pageicontentoptions);
        $mform->setType('pageicontent_editor', PARAM_RAW);
        $mform->addRule('pageicontent_editor', get_string('required'), 'required', null, 'client');

        // 20240920 Added tags to edit_form page.
        if (core_tag_tag::is_enabled('mod_icontent', 'icontent_pages')) {
            $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));
        }
        $mform->addElement(
            'tags',
            'tags',
            get_string('tags'),
            [
                'itemtype' => 'icontent_pages',
                'component' => 'mod_icontent',
            ]
        );

        $mform->addElement('header', 'appearance', get_string('appearance'));

        $layouts = [
            '1' => get_string('fluid', 'icontent'),
            '2' => get_string('columns2', 'icontent'),
            '3' => get_string('columns3', 'icontent'),
            '4' => get_string('columns4', 'icontent'),
            '5' => get_string('columns5', 'icontent'),
        ];
        $mform->addElement('select', 'layout', get_string('layout', 'icontent'), $layouts);
        $mform->addHelpButton('layout', 'layouthelp', 'icontent');

        $mform->addElement('advcheckbox', 'showtitle', get_string('showtitle', 'icontent'));
        $mform->addHelpButton('showtitle', 'showtitle', 'icontent');
        $mform->setType('showtitle', PARAM_INT);
        $mform->setDefault('showtitle', 1);

        $mform->addElement('advcheckbox', 'showbgimage', get_string('showbgimage', 'icontent'));
        $mform->addHelpButton('showbgimage', 'showbgimage', 'icontent');
        $mform->setType('showbgimage', PARAM_INT);
        $mform->setDefault('showbgimage', 1);

        // Set up options for the filemanager setting.
        $filemanageroptions = [];
                $filemanageroptions['subdirs'] = 0;
                $filemanageroptions['maxbytes'] = $COURSE->maxbytes;
                $filemanageroptions['maxfiles'] = 1;
                $filemanageroptions['accepted_types'] = ['.jpg', '.png'];
                $filemanageroptions['return_types'] = FILE_INTERNAL | FILE_EXTERNAL;
        $mform->addElement('filemanager', 'bgimage', get_string('bgimage', 'icontent'), null, $filemanageroptions);
        $mform->setType('bgimage', PARAM_INT);
        $mform->addHelpButton('bgimage', 'bgimagepagehelp', 'icontent');

        // ...$mform->addElement('text', 'bgcolor', get_string('bgcolor', 'icontent'), ['class' => 'color', 'value' => 'FCFCFC']);.
        // ...$mform->setType('bgcolor', PARAM_TEXT);.
        // ...$mform->addHelpButton('bgcolor', 'bgcolorpagehelp', 'icontent');.

        $bgattributes = ['id' => 'icontent_bgcolor_picker', 'size' => '10', 'maxlength' => '7'];
        $mform->addElement('text', 'bgcolor', get_string('bgcolor', 'icontent'), $bgattributes);
        $mform->setType('bgcolor', PARAM_TEXT);
        $mform->addHelpButton('bgcolor', 'bgcolorpagehelp', 'icontent');
        $mform->setDefault('bgcolor', $page->bgcolor);

        $borderattributes = ['id' => 'icontent_bordercolor_picker', 'size' => '10', 'maxlength' => '7'];
        $mform->addElement('text', 'bordercolor', get_string('bordercolor', 'icontent'), $borderattributes);
        $mform->setType('bordercolor', PARAM_TEXT);
        $mform->addHelpButton('bordercolor', 'bgcolorpagehelp', 'icontent');
        $mform->setDefault('bordercolor', $page->bordercolor);

        $mform->addElement('html', "
            <script>
                (function() {
                    var normalizeHex = function(value, fallback) {
                        var raw = (value || '').toString().trim();
                        if (raw.charAt(0) !== '#') {
                            raw = '#' + raw;
                        }
                        if (!/^#[0-9a-fA-F]{6}$/.test(raw)) {
                            return fallback;
                        }
                        return raw.toUpperCase();
                    };

                    var initColorInput = function(id, fallback) {
                        var input = document.getElementById(id);
                        if (!input) {
                            return;
                        }
                        input.value = normalizeHex(input.value, fallback);
                        input.type = 'color';
                        input.style.width = '60px';
                        input.style.height = '35px';
                        input.style.cursor = 'pointer';
                        input.addEventListener('change', function() {
                            input.value = normalizeHex(input.value, fallback);
                        });
                    };

                    initColorInput('icontent_bgcolor_picker', '#FCFCFC');
                    initColorInput('icontent_bordercolor_picker', '#E4E4E4');
                })();
            </script>
        ");

        $opts = icontent_add_borderwidth_options();
        $mform->addElement('select', 'borderwidth', get_string('borderwidth', 'icontent'), $opts);
        $mform->setType('borderwidth', PARAM_INT);
        $mform->addHelpButton('borderwidth', 'borderwidthpagehelp', 'icontent');
        // 20240216 Use default in config setting for iContent.
        $mform->setDefault('borderwidth', $icontentconfig->borderwidth);

        $attributes = ['class' => "x-large",
                       'value' => $icontentconfig->maxnotesperpages,
                       'size' => "10",
                      ];
        $mform->setType('maxnotesperpages', PARAM_INT);
        $mform->addElement('text', 'maxnotesperpages', get_string('maxnotesperpages', 'icontent'), $attributes);
        $mform->addHelpButton('maxnotesperpages', 'maxnotesperpageshelp', 'icontent');
        $mform->setDefault('maxnotesperpages', $icontentconfig->maxnotesperpages);
        $mform->addRule('maxnotesperpages', null, 'numeric', null, 'client');
        $mform->addRule('maxnotesperpages', get_string('maximumdigits', 'icontent', 3), 'maxlength', 3, 'client');

        $mform->addElement('advcheckbox', 'expandquestionsarea', get_string('expandquestionsarea', 'icontent'));
        $mform->addHelpButton('expandquestionsarea', 'expandquestionsarea', 'icontent');
        $mform->setType('expandquestionsarea', PARAM_INT);
        $mform->setDefault('expandquestionsarea', 0);

        $mform->addElement('advcheckbox', 'expandnotesarea', get_string('expandnotesarea', 'icontent'));
        $mform->addHelpButton('expandnotesarea', 'expandnotesarea', 'icontent');
        $mform->setType('expandnotesarea', PARAM_INT);
        $mform->setDefault('expandnotesarea', 0);

        $mform->addElement('header', 'grade', get_string('gradenoun'));
        $mform->addElement('select', 'attemptsallowed', get_string('attemptsallowed', 'icontent'),
            [
                '0' => get_string('unlimited'),
                '1' => '1 '.get_string('attempt', 'mod_icontent'),
            ]
        );
        $mform->addHelpButton('attemptsallowed', 'attemptsallowedhelp', 'icontent');

        $mform->addElement('header', 'effects', get_string('effects', 'icontent'));
        $effects = [
            '0' => get_string('noeffect', 'mod_icontent'),
            'blind' => 'Blind',
            'bounce' => 'Bounce',
            'clip' => 'Clip',
            'drop' => 'Drop',
            'explode' => 'Explode',
            'fade' => 'Fade',
            'fold' => 'Fold',
            'puff' => 'Puff',
            'pulsate' => 'Pulsate',
            'scale' => 'Scale',
            'shake' => 'Shake',
            'size' => 'Size',
            'slide' => 'Slide',
        ];

        $mform->addElement('select', 'transitioneffect', get_string('transitioneffect', 'icontent'), $effects);
        $mform->addHelpButton('transitioneffect', 'transitioneffecthelp', 'icontent');
        $mform->setDefault('transitioneffect', 0);

        $this->add_action_buttons(true);

        // Set the defaults.
        $this->set_data($page);
    }

    /**
     * Format a color for color picker fields as #RRGGBB.
     *
     * @param string|null $value
     * @param string $fallback
     * @return string
     */
    protected static function format_colour_for_picker($value, $fallback) {
        $default = strtoupper((string)$fallback);
        if ($default === '' || $default[0] !== '#') {
            $default = '#'.ltrim($default, '#');
        }
        if (!preg_match('/^#[0-9A-F]{6}$/', $default)) {
            $default = '#FCFCFC';
        }

        $raw = '#'.strtoupper(ltrim(trim((string)$value), '#'));
        if (!preg_match('/^#[0-9A-F]{6}$/', $raw)) {
            return $default;
        }
        return $raw;
    }
}
