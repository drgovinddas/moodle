<?php
/**
 * Installation code for local_studentprofile.
 *
 * Automatically seeds default degrees and colleges when installed on a new site.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Perform post-install data seeding.
 */
function xmldb_local_studentprofile_install() {
    global $DB;

    $now = time();
    $jsonfile = __DIR__ . '/../seed_data.json';

    if (!file_exists($jsonfile)) {
        return;
    }

    $rawjson = file_get_contents($jsonfile);
    $seeddata = json_decode($rawjson, true);

    if (!$seeddata || !is_array($seeddata)) {
        return;
    }

    // 1. Seed Degrees
    if (!empty($seeddata['degrees']) && is_array($seeddata['degrees'])) {
        foreach ($seeddata['degrees'] as $degree) {
            $degreename = trim($degree['degreename'] ?? '');
            $degreetype = trim($degree['degreetype'] ?? 'UG');
            $sortorder  = (int)($degree['sortorder'] ?? 0);

            if (!empty($degreename) && !$DB->record_exists('local_studentprofile_degrees', ['degreename' => $degreename])) {
                $DB->insert_record('local_studentprofile_degrees', (object)[
                    'degreename'   => $degreename,
                    'degreetype'   => $degreetype,
                    'sortorder'    => $sortorder,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
            }
        }
    }

    // 2. Seed Colleges
    if (!empty($seeddata['colleges']) && is_array($seeddata['colleges'])) {
        foreach ($seeddata['colleges'] as $college) {
            $shortname   = trim($college['shortname'] ?? '');
            $collegename = trim($college['collegename'] ?? '');
            $longname    = trim($college['longname'] ?? '');
            $degreelist  = trim($college['degrees'] ?? '');
            $sortorder   = (int)($college['sortorder'] ?? 0);

            if (!empty($shortname) && !empty($collegename) && !$DB->record_exists('local_studentprofile_colleges', ['shortname' => $shortname])) {
                $DB->insert_record('local_studentprofile_colleges', (object)[
                    'shortname'    => $shortname,
                    'collegename'  => $collegename,
                    'longname'     => $longname,
                    'degrees'      => $degreelist,
                    'sortorder'    => $sortorder,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
            }
        }
    }
}
