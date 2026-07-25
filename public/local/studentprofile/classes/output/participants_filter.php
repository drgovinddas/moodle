<?php
/**
 * Custom participants filter class for local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\output;

defined('MOODLE_INTERNAL') || die();

class participants_filter extends \core_user\output\participants_filter {
    /**
     * Get data for all filter types, appending local student profile ones.
     *
     * @return array
     */
    protected function get_filtertypes(): array {
        global $DB;
        $filtertypes = parent::get_filtertypes();

        // 1. College filter — driven from DB table local_studentprofile_colleges.
        //    Also includes any custom college names entered by students.
        $collegeoptions = [];

        // DB-managed colleges.
        $colleges = $DB->get_records('local_studentprofile_colleges', null, 'sortorder ASC, collegename ASC');
        foreach ($colleges as $c) {
            $collegeoptions[] = (object) [
                'value' => $c->collegename,
                'title' => $c->collegename . ' (' . $c->shortname . ')',
            ];
        }

        // Custom colleges used by students (collegeid = 0 means custom).
        $customcolleges = $DB->get_records_sql(
            "SELECT DISTINCT collegename FROM {local_studentprofile_data}
              WHERE collegeid = 0 OR collegeid IS NULL
                AND collegename != ''
             ORDER BY collegename ASC"
        );
        foreach ($customcolleges as $cc) {
            $alreadylisted = false;
            foreach ($collegeoptions as $opt) {
                if ($opt->value === $cc->collegename) {
                    $alreadylisted = true;
                    break;
                }
            }
            if (!$alreadylisted && !empty($cc->collegename)) {
                $collegeoptions[] = (object) [
                    'value' => $cc->collegename,
                    'title' => $cc->collegename . ' (custom)',
                ];
            }
        }

        if (!empty($collegeoptions)) {
            $filtertypes[] = $this->get_filter_object(
                'college',
                get_string('collegename', 'local_studentprofile'),
                false,
                true,
                'local_studentprofile/filtertypes/string_filter',
                $collegeoptions
            );
        }

        // 2. Degree filter — driven from DB table local_studentprofile_degrees.
        $degreeoptions = [];
        $degrees = $DB->get_records('local_studentprofile_degrees', null, 'sortorder ASC, degreename ASC');
        foreach ($degrees as $d) {
            $degreeoptions[] = (object) [
                'value' => $d->degreename,
                'title' => $d->degreename,
            ];
        }
        // Fallback to config if no DB entries.
        if (empty($degreeoptions)) {
            $rawdegrees = explode("\n", str_replace("\r", "", (string)get_config('local_studentprofile', 'degrees')));
            foreach ($rawdegrees as $d) {
                $d = trim($d);
                if (!empty($d)) {
                    $degreeoptions[] = (object)['value' => $d, 'title' => $d];
                }
            }
        }
        if (!empty($degreeoptions)) {
            $filtertypes[] = $this->get_filter_object(
                'degree',
                get_string('degree', 'local_studentprofile'),
                false,
                true,
                'local_studentprofile/filtertypes/string_filter',
                $degreeoptions
            );
        }

        // 3. Degree Type (UG / PG) filter.
        $degreetypeoptions = [
            (object)['value' => 'UG', 'title' => get_string('ugpg_ug', 'local_studentprofile')],
            (object)['value' => 'PG', 'title' => get_string('ugpg_pg', 'local_studentprofile')],
        ];
        $filtertypes[] = $this->get_filter_object(
            'degreetype',
            get_string('degreetype', 'local_studentprofile'),
            false,
            true,
            'local_studentprofile/filtertypes/string_filter',
            $degreetypeoptions
        );

        // 4. Admission Year filter.
        $yearrange = explode('-', (string)get_config('local_studentprofile', 'admissionyears'));
        $startyear = isset($yearrange[0]) ? (int)trim($yearrange[0]) : 2010;
        $endyear   = isset($yearrange[1]) ? (int)trim($yearrange[1]) : 2030;
        $yearoptions = [];
        for ($y = $endyear; $y >= $startyear; $y--) {
            $yearoptions[] = (object) [
                'value' => $y,
                'title' => (string)$y,
            ];
        }
        if (!empty($yearoptions)) {
            $filtertypes[] = $this->get_filter_object(
                'year',
                get_string('admissionyear', 'local_studentprofile'),
                false,
                true,
                null,
                $yearoptions
            );
        }

        return $filtertypes;
    }
}
