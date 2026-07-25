<?php
/**
 * External API definitions for local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class external extends \external_api {
    /**
     * Describe parameters for get_participants_table.
     *
     * @return \external_function_parameters
     */
    public static function get_participants_table_parameters() {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Course ID'),
            'college' => new \external_value(PARAM_TEXT, 'College filter', VALUE_DEFAULT, ''),
            'degree' => new \external_value(PARAM_TEXT, 'Degree filter', VALUE_DEFAULT, ''),
            'year' => new \external_value(PARAM_INT, 'Year filter', VALUE_DEFAULT, 0),
            'search' => new \external_value(PARAM_TEXT, 'Search query', VALUE_DEFAULT, ''),
            'page' => new \external_value(PARAM_INT, 'Page index', VALUE_DEFAULT, 0),
            'perpage' => new \external_value(PARAM_INT, 'Records per page', VALUE_DEFAULT, 20),
            'tsort' => new \external_value(PARAM_TEXT, 'Sort column', VALUE_DEFAULT, ''),
            'tifirst' => new \external_value(PARAM_TEXT, 'First name initial filter', VALUE_DEFAULT, ''),
            'tilast' => new \external_value(PARAM_TEXT, 'Last name initial filter', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Get filtered participants table HTML.
     *
     * @param int $courseid
     * @param string $college
     * @param string $degree
     * @param int $year
     * @param string $search
     * @param int $page
     * @param int $perpage
     * @param string $tsort
     * @param string $tifirst
     * @param string $tilast
     * @return array
     */
    public static function get_participants_table($courseid, $college = '', $degree = '', $year = 0, $search = '', $page = 0, $perpage = 20, $tsort = '', $tifirst = '', $tilast = '') {
        global $DB, $PAGE;

        $params = self::validate_parameters(self::get_participants_table_parameters(), [
            'courseid' => $courseid,
            'college' => $college,
            'degree' => $degree,
            'year' => $year,
            'search' => $search,
            'page' => $page,
            'perpage' => $perpage,
            'tsort' => $tsort,
            'tifirst' => $tifirst,
            'tilast' => $tilast,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id, MUST_EXIST);
        self::validate_context($context);

        // Inject request parameters so table class picks them up.
        $_GET['page'] = $params['page'];
        $_GET['perpage'] = $params['perpage'];
        if (!empty($params['tsort'])) {
            $_GET['tsort'] = $params['tsort'];
        }
        if (!empty($params['tifirst'])) {
            $_GET['tifirst'] = $params['tifirst'];
        }
        if (!empty($params['tilast'])) {
            $_GET['tilast'] = $params['tilast'];
        }

        // Setup the page url correctly for table rendering.
        $PAGE->set_url('/local/studentprofile/participants.php', ['id' => $course->id]);

        ob_start();
        $table = new \local_studentprofile\table\participants("user-index-participants-{$course->id}");
        $table->set_studentprofile_filters($params['college'], $params['degree'], $params['year'], $params['search']);

        $filterset = new \core_user\table\participants_filterset();
        $filterset->add_filter(new \core_table\local\filter\integer_filter('courseid', \core_table\local\filter\filter::JOINTYPE_DEFAULT, [(int)$course->id]));
        $table->set_filterset($filterset);

        $table->out($params['perpage'], true);
        $html = ob_get_clean();

        return ['html' => $html];
    }

    /**
     * Describe returns for get_participants_table.
     *
     * @return \external_single_structure
     */
    public static function get_participants_table_returns() {
        return new \external_single_structure([
            'html' => new \external_value(PARAM_RAW, 'Table HTML'),
        ]);
    }
}
