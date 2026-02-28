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
 * Accept uploading files by web service token to the user draft file area.
 *
 * POST params:
 *  token => the web service user token (needed for authentication)
 *  filepath => file path (where files will be stored)
 *  [_FILES] => for example you can send the files with <input type=file>,
 *              or with curl magic: 'file_1' => '@/path/to/file', or ...
 *  itemid   => The draftid - this can be used to add a list of files
 *              to a draft area in separate requests. If it is 0, a new draftid will be generated.
 *
 * @package    core_webservice
 * @copyright  2011 Dongsheng Cai <dongsheng@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * AJAX_SCRIPT - exception will be converted into JSON
 */
define('AJAX_SCRIPT', true);

/**
 * NO_MOODLE_COOKIES - we don't want any cookie
 */
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->dirroot . '/repository/googledocs/lib.php');

// ---------------------------------------------------------------------------
// Helper: upload a local file to Google Drive and return a stored_file record
// (FILE_CONTROLLED_LINK) in the user draft area.
//
// Returns stdClass file record on success, or false if Google Drive is not
// configured — callers fall back to normal server storage in that case.
// ---------------------------------------------------------------------------

// krushal debug function
/* function mlog($message, $file = 'system.log', $level = null, $forceLog = false) {

    $logDir = "/home/mbbs/public_html/logs/";
    $logFile = $logDir . basename($file); // prevent directory traversal

    // Ensure log directory exists
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    // Convert array/object to readable string
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }

    // Format message
    $timestamp = date('Y-m-d H:i:s');
    $levelText = $level ? strtoupper($level) : 'INFO';
    $formattedMessage = "[$timestamp] [$levelText] $message" . PHP_EOL;

    // Write with file locking
    file_put_contents($logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
} */
// krushal gdrive upload function start
function webservice_upload_to_googledrive(
    stdClass $filerecord,
    string $localpath,
    stdClass $site
): bool {
    global $CFG, $DB;

    // Get the Google Docs repository instance.
    $gdrepos = repository::get_instances(['type' => 'googledocs']);
    if (empty($gdrepos)) {
        return false;
    }
    /** @var repository_googledocs $gdrepo */
    $gdrepo = reset($gdrepos);

    // Get system OAuth client.
    $issuerid = get_config('googledocs', 'issuerid');
    try {
        $issuer = \core\oauth2\api::get_issuer($issuerid);
    } catch (dml_missing_record_exception $e) {
        return false;
    }
    $systemauth = \core\oauth2\api::get_system_oauth_client($issuer);
    if ($systemauth === false) {
        return false;
    }
    $client = new repository_googledocs\rest($systemauth);

    // Build Drive folder hierarchy based on user-context draft area.
    // For mobile uploads the context is always the user context; we don't
    // know the course/module yet (it's set when the draft is submitted).
    // Folder structure: root / <site shortname> / webservice_uploads / <id>_<firstname> / <itemid>
    // ------------------------------------------------------------------
    $uploaduser = $DB->get_record('user', ['id' => $filerecord->userid], 'id, firstname', IGNORE_MISSING);
    $userfolder = clean_param(
        $filerecord->userid . '_' . ($uploaduser ? $uploaduser->firstname : 'user'),
        PARAM_PATH
    );
    $allfolders = [
        clean_param($site->shortname . ' (id ' . $site->id . ')', PARAM_PATH),
        'webservice_uploads',
        $userfolder,
        clean_param((string) $filerecord->itemid . '_' . (function () use ($DB, $filerecord): string{
            // Attempt 1: course from user's most recently modified assignment submission.
            $sql = "SELECT c.shortname
                      FROM {assign_submission} asub
                      JOIN {assign} a ON a.id = asub.assignment
                      JOIN {course} c ON c.id = a.course
                     WHERE asub.userid = :userid
                     ORDER BY asub.timemodified DESC
                     LIMIT 1";
            $row = $DB->get_record_sql($sql, ['userid' => $filerecord->userid]);
            if ($row && !empty($row->shortname)) {
                return $row->shortname;
            }
            // Attempt 2: most recently accessed course that has an assign module.
            $sql2 = "SELECT c.shortname
                       FROM {user_lastaccess} ula
                       JOIN {course} c ON c.id = ula.courseid
                      WHERE ula.userid = :userid
                        AND EXISTS (
                            SELECT 1 FROM {assign} a
                            JOIN {course_modules} cm ON cm.instance = a.id
                            JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                            WHERE a.course = c.id AND cm.deletioninprogress = 0
                        )
                      ORDER BY ula.timeaccess DESC
                      LIMIT 1";
            $row2 = $DB->get_record_sql($sql2, ['userid' => $filerecord->userid]);
            if ($row2 && !empty($row2->shortname)) {
                return $row2->shortname;
            }
            return '';
        })(), PARAM_PATH),
        // Assignment name folder.
        clean_param((function () use ($DB, $filerecord): string{
            $sql = "SELECT a.name
                      FROM {assign_submission} asub
                      JOIN {assign} a ON a.id = asub.assignment
                     WHERE asub.userid = :userid
                     ORDER BY asub.timemodified DESC
                     LIMIT 1";
            $row = $DB->get_record_sql($sql, ['userid' => $filerecord->userid]);
            return ($row && !empty($row->name)) ? $row->name : '';
        })(), PARAM_PATH),
    ];
    $cache = cache::make('repository_googledocs', 'folder');
    $parentid = 'root';
    $fullpath = 'root';

    foreach ($allfolders as $foldername) {
        $fullpath .= '/' . $foldername;
        $folderid = $cache->get($fullpath);
        if (empty($folderid)) {
            // Search Drive for existing folder.
            $q = '\'' . addslashes($parentid) . '\' in parents'
                . ' and trashed = false'
                . ' and name = \'' . addslashes($foldername) . '\'';
            $resp = $client->call('list', ['q' => $q, 'fields' => 'files(id,name)']);
            $folderid = false;
            if (!empty($resp->files)) {
                foreach ($resp->files as $child) {
                    if ($child->name == $foldername) {
                        $folderid = $child->id;
                        break;
                    }
                }
            }
        }
        if (empty($folderid)) {
            // Create the folder.
            $body = json_encode([
                'mimeType' => 'application/vnd.google-apps.folder',
                'name' => $foldername,
                'parents' => [$parentid],
            ]);
            $created = $client->call('create', ['fields' => 'id'], $body);
            $folderid = $created->id ?? null;
            if (empty($folderid)) {
                return false;
            }
        }
        $cache->set($fullpath, $folderid);
        $parentid = $folderid;
    }

    // Upload the file content.
    $mimetype = mime_content_type($localpath) ?: 'application/octet-stream';
    $uploaded = $gdrepo->upload_file($client, $localpath, $filerecord->filename, $mimetype, $parentid);
    if (empty($uploaded->id)) {
        return false;
    }

    // Make readable by anyone with the link.
    $perm = json_encode(['type' => 'anyone', 'role' => 'reader', 'allowFileDiscovery' => 'false']);
    $client->call('create_permission', ['fileid' => $uploaded->id, 'supportsAllDrives' => 'true'], $perm);

    // Get the web view link.
    $meta = $client->call('get', ['fileid' => $uploaded->id, 'fields' => 'id,name,webViewLink,webContentLink']);
    $link = $meta->webViewLink ?? ($meta->webContentLink ?? '');

    // Build the FILE_CONTROLLED_LINK reference (googledocs format).
    $reference = json_encode([
        'id' => $uploaded->id,
        'name' => $filerecord->filename,
        'link' => $link,
        'exportformat' => 'download',
        'usesystem' => true,
    ]);

    // Store as a reference in the draft area.
    $gdrepoid = $gdrepo->id;
    $fs = get_file_storage();
    $storedfile = $fs->create_file_from_reference($filerecord, $gdrepoid, $reference);

    return $storedfile !== false;
}
// krushal gdrive upload function end
// Allow CORS requests.
header('Access-Control-Allow-Origin: *');

$filepath = optional_param('filepath', '/', PARAM_PATH);
$itemid = optional_param('itemid', 0, PARAM_INT);

echo $OUTPUT->header();

// Authenticate the user.
$token = required_param('token', PARAM_ALPHANUM);
$webservicelib = new webservice();
$authenticationinfo = $webservicelib->authenticate_user($token);
$fileuploaddisabled = empty($authenticationinfo['service']->uploadfiles);
if ($fileuploaddisabled) {
    throw new webservice_access_exception('Web service file upload must be enabled in external service settings');
}

$context = context_user::instance($USER->id);

$fs = get_file_storage();

$totalsize = 0;
$files = array();
foreach ($_FILES as $fieldname => $uploadedfile) {
    // Check upload errors.
    if (!empty($_FILES[$fieldname]['error'])) {
        switch ($_FILES[$fieldname]['error']) {
            case UPLOAD_ERR_INI_SIZE:
                throw new moodle_exception('upload_error_ini_size', 'repository_upload');
                break;
            case UPLOAD_ERR_FORM_SIZE:
                throw new moodle_exception('upload_error_form_size', 'repository_upload');
                break;
            case UPLOAD_ERR_PARTIAL:
                throw new moodle_exception('upload_error_partial', 'repository_upload');
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new moodle_exception('upload_error_no_file', 'repository_upload');
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new moodle_exception('upload_error_no_tmp_dir', 'repository_upload');
                break;
            case UPLOAD_ERR_CANT_WRITE:
                throw new moodle_exception('upload_error_cant_write', 'repository_upload');
                break;
            case UPLOAD_ERR_EXTENSION:
                throw new moodle_exception('upload_error_extension', 'repository_upload');
                break;
            default:
                throw new moodle_exception('nofile');
        }
    }

    // Scan for viruses.
    $avscanstarttime = microtime(true);
    \core\antivirus\manager::scan_file($_FILES[$fieldname]['tmp_name'], $_FILES[$fieldname]['name'], true);

    $file = new stdClass();
    $file->avscantime = microtime(true) - $avscanstarttime;
    $file->filename = clean_param($_FILES[$fieldname]['name'], PARAM_FILE);
    // Check system maxbytes setting.
    if (($_FILES[$fieldname]['size'] > get_max_upload_file_size($CFG->maxbytes))) {
        // Oversize file will be ignored, error added to array to notify
        // web service client.
        $file->errortype = 'fileoversized';
        $file->error = get_string('maxbytes', 'error');
    } else {
        $file->filepath = $_FILES[$fieldname]['tmp_name'];
        // Calculate total size of upload.
        $totalsize += $_FILES[$fieldname]['size'];
        // Size of individual file.
        $file->size = $_FILES[$fieldname]['size'];
    }
    $files[] = $file;
}

$fs = get_file_storage();

if ($itemid <= 0) {
    $itemid = file_get_unused_draft_itemid();
}

// Get any existing file size limits.
$maxupload = get_user_max_upload_file_size($context, $CFG->maxbytes);

// Check the size of this upload.
if ($maxupload !== USER_CAN_IGNORE_FILE_SIZE_LIMITS && $totalsize > $maxupload) {
    throw new file_exception('userquotalimit');
}

$results = array();
foreach ($files as $file) {
    if (!empty($file->error)) {
        // Including error and filename.
        $results[] = $file;
        continue;
    }
    $filerecord = new stdClass;
    $filerecord->component = 'user';
    $filerecord->contextid = $context->id;
    $filerecord->userid = $USER->id;
    $filerecord->filearea = 'draft';
    $filerecord->filename = $file->filename;
    $filerecord->filepath = $filepath;
    $filerecord->itemid = $itemid;
    $filerecord->license = $CFG->sitedefaultlicense;
    $filerecord->author = fullname($authenticationinfo['user']);
    $filerecord->source = serialize((object) array('source' => $file->filename));
    $filerecord->filesize = $file->size;

    // Check if the file already exist.
    $existingfile = $fs->file_exists(
        $filerecord->contextid,
        $filerecord->component,
        $filerecord->filearea,
        $filerecord->itemid,
        $filerecord->filepath,
        $filerecord->filename
    );
    if ($existingfile) {
        $file->errortype = 'filenameexist';
        $file->error = get_string('filenameexist', 'webservice', $file->filename);
        $results[] = $file;
    } else {
        // krushal gdrive upload function start
        $gdrive_ok = webservice_upload_to_googledrive($filerecord, $file->filepath, $SITE);
        if ($gdrive_ok) {
            // File successfully stored in Google Drive as a controlled link.
            // Re-fetch the stored_file so logging has a valid object.
            $storedfile = $fs->get_file(
                $filerecord->contextid,
                $filerecord->component,
                $filerecord->filearea,
                $filerecord->itemid,
                $filerecord->filepath,
                $filerecord->filename
            );
        } else {
            // Google Drive unavailable — fall back to server storage.
            $storedfile = $fs->create_file_from_pathname($filerecord, $file->filepath);
        }
        // krushal gdrive upload function end
        $results[] = $filerecord;

        // Log the event when a file is uploaded to the draft area.
        $logevent = \core\event\draft_file_added::create([
            'objectid' => $storedfile->get_id(),
            'context' => $context,
            'other' => [
                'itemid' => $filerecord->itemid,
                'filename' => $filerecord->filename,
                'filesize' => $filerecord->filesize,
                'filepath' => $filerecord->filepath,
                'contenthash' => $storedfile->get_contenthash(),
                'avscantime' => $file->avscantime,
            ],
        ]);
        $logevent->trigger();
    }
}
echo json_encode($results);
