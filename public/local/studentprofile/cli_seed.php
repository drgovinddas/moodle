<?php
/**
 * CLI script to seed degrees and colleges into local_studentprofile tables.
 *
 * Usage:
 *   php local/studentprofile/cli_seed.php
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

global $DB;

$now = time();
$jsonfile = __DIR__ . '/seed_data.json';

if (!file_exists($jsonfile)) {
    cli_error("Seed file not found: {$jsonfile}");
}

$rawjson = file_get_contents($jsonfile);
$seeddata = json_decode($rawjson, true);

if (!$seeddata || !is_array($seeddata)) {
    cli_error("Failed to parse JSON from seed file.");
}

cli_heading("Seeding local_studentprofile Master Data");

// 1. Seed Degrees
$degrees = $seeddata['degrees'] ?? [];
$deg_inserted = 0;
$deg_updated  = 0;

foreach ($degrees as $degree) {
    $degreename = trim($degree['degreename'] ?? '');
    $degreetype = trim($degree['degreetype'] ?? 'UG');
    $sortorder  = (int)($degree['sortorder'] ?? 0);

    if (empty($degreename)) {
        continue;
    }

    $existing = $DB->get_record('local_studentprofile_degrees', ['degreename' => $degreename]);

    if ($existing) {
        $update = new stdClass();
        $update->id           = $existing->id;
        $update->degreetype   = $degreetype;
        $update->sortorder    = $sortorder;
        $update->timemodified = $now;
        $DB->update_record('local_studentprofile_degrees', $update);
        $deg_updated++;
    } else {
        $record = new stdClass();
        $record->degreename   = $degreename;
        $record->degreetype   = $degreetype;
        $record->sortorder    = $sortorder;
        $record->timecreated  = $now;
        $record->timemodified = $now;
        $DB->insert_record('local_studentprofile_degrees', $record);
        $deg_inserted++;
    }
}

cli_writeln("Degrees process finished: {$deg_inserted} inserted, {$deg_updated} updated.");

// 2. Seed Colleges
$colleges = $seeddata['colleges'] ?? [];
$col_inserted = 0;
$col_updated  = 0;

foreach ($colleges as $college) {
    $shortname   = trim($college['shortname'] ?? '');
    $collegename = trim($college['collegename'] ?? '');
    $longname    = trim($college['longname'] ?? '');
    $degreelist  = trim($college['degrees'] ?? '');
    $sortorder   = (int)($college['sortorder'] ?? 0);

    if (empty($shortname) || empty($collegename)) {
        continue;
    }

    $existing = $DB->get_record('local_studentprofile_colleges', ['shortname' => $shortname]);

    if ($existing) {
        $update = new stdClass();
        $update->id           = $existing->id;
        $update->collegename  = $collegename;
        $update->longname     = $longname;
        $update->degrees      = $degreelist;
        $update->sortorder    = $sortorder;
        $update->timemodified = $now;
        $DB->update_record('local_studentprofile_colleges', $update);
        $col_updated++;
    } else {
        $record = new stdClass();
        $record->shortname    = $shortname;
        $record->collegename  = $collegename;
        $record->longname     = $longname;
        $record->degrees      = $degreelist;
        $record->sortorder    = $sortorder;
        $record->timecreated  = $now;
        $record->timemodified = $now;
        $DB->insert_record('local_studentprofile_colleges', $record);
        $col_inserted++;
    }
}

cli_writeln("Colleges process finished: {$col_inserted} inserted, {$col_updated} updated.");
cli_writeln("Done!");
