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
 * Google Drive Upload Repository.
 *
 * This plugin presents the same "Upload a file" interface as the core
 * repository_upload plugin, but instead of saving the file to the Moodle
 * server it pushes the file directly to Google Drive via the system OAuth
 * account and returns a FILE_CONTROLLED_LINK reference.
 *
 * The Drive folder hierarchy mirrors the one created by repository_googledocs
 * (via reference_file_selected()):
 *
 *   root / <Site (shortname)> / <Course (id N)> / <Module (id N)> /
 *          <component> / <filearea> / <itemid>
 *
 * @package    repository_gdrive_upload
 * @copyright  2026 medi-wiki.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->dirroot . '/repository/googledocs/lib.php');

/**
 * Google Drive Upload repository class.
 *
 * Extends the base repository class to provide a "Upload a file" tab
 * experience that transparently pushes uploaded files to Google Drive.
 *
 * @package    repository_gdrive_upload
 * @copyright  2026 medi-wiki.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_gdrive_upload extends repository {

    /**
     * Show the upload form (mimics repository_upload::get_listing).
     *
     * @param string $path  Unused.
     * @param string $page  Unused.
     * @return array
     */
    public function get_listing($path = '', $page = '') {
        return [
            'nologin'    => true,
            'nosearch'   => true,
            'norefresh'  => true,
            'list'       => [],
            'dynload'    => false,
            'upload'     => [
                'label' => get_string('attachment', 'repository'),
                'id'    => 'repo-form',
            ],
            'allowcaching' => true,
        ];
    }

    /**
     * No login required for the upload form.
     *
     * @return array
     */
    public function print_login() {
        return $this->get_listing();
    }

    /**
     * Process an uploaded file: push it to Google Drive and return a
     * FILE_CONTROLLED_LINK reference so Moodle tracks it as a Drive file.
     *
     * @param string $saveasfilename  Desired filename (may be empty).
     * @param int    $maxbytes        Maximum allowed file size.
     * @return array  Same structure as repository_upload::upload().
     */
    public function upload($saveasfilename, $maxbytes) {
        global $CFG, $USER, $SITE, $DB;

        $types        = optional_param_array('accepted_types', '*', PARAM_RAW);
        $savepath     = optional_param('savepath', '/', PARAM_PATH);
        $itemid       = optional_param('itemid', 0, PARAM_INT);
        $license      = optional_param('license', $CFG->sitedefaultlicense, PARAM_TEXT);
        $author       = optional_param('author', '', PARAM_TEXT);
        $areamaxbytes = optional_param('areamaxbytes', FILE_AREA_MAX_BYTES_UNLIMITED, PARAM_INT);
        $contextid    = optional_param('ctx_id', 0, PARAM_INT);
        $component    = optional_param('component', 'user', PARAM_COMPONENT);
        $filearea     = optional_param('filearea', 'draft', PARAM_AREA);
        $overwrite    = optional_param('overwrite', false, PARAM_BOOL);

        // ----------------------------------------------------------------
        // 1. Basic file validation (mirrors repository_upload).
        // ----------------------------------------------------------------
        $elname = 'repo_upload_file';

        if (!isset($_FILES[$elname])) {
            throw new moodle_exception('nofile');
        }
        if (!empty($_FILES[$elname]['error'])) {
            $errormap = [
                UPLOAD_ERR_INI_SIZE  => 'upload_error_ini_size',
                UPLOAD_ERR_FORM_SIZE => 'upload_error_form_size',
                UPLOAD_ERR_PARTIAL   => 'upload_error_partial',
                UPLOAD_ERR_NO_FILE   => 'upload_error_no_file',
                UPLOAD_ERR_NO_TMP_DIR=> 'upload_error_no_tmp_dir',
                UPLOAD_ERR_CANT_WRITE=> 'upload_error_cant_write',
                UPLOAD_ERR_EXTENSION => 'upload_error_extension',
            ];
            $key = $_FILES[$elname]['error'];
            $str = isset($errormap[$key]) ? $errormap[$key] : 'nofile';
            throw new moodle_exception($str, 'repository_upload');
        }

        // Determine the filename to use.
        $origname = clean_param($_FILES[$elname]['name'], PARAM_FILE);
        if (empty($saveasfilename)) {
            $filename = $origname;
        } else {
            // Preserve extension from original name.
            $ext = '';
            if (preg_match('/\.([a-z0-9]+)$/i', $origname, $m)) {
                $ext = $m[1];
            }
            if (!empty($ext) && !preg_match('#\.(' . preg_quote($ext, '#') . ')$#i', $saveasfilename)) {
                $filename = $saveasfilename . '.' . $ext;
            } else {
                $filename = $saveasfilename;
            }
        }

        // File-size checks.
        $filesize = filesize($_FILES[$elname]['tmp_name']);
        if ($maxbytes !== -1 && $filesize > $maxbytes) {
            throw new file_exception('maxbytesfile', (object)[
                'file' => $filename,
                'size' => display_size($maxbytes, 0),
            ]);
        }

        // ----------------------------------------------------------------
        // 2. Get the Google Docs repository instance + system OAuth client.
        // ----------------------------------------------------------------
        // Bypass UI capability checks since users do not need the view capability
        // to have their assignment automatically uploaded via the system account.
        $gdrepoid = $DB->get_field_sql(
            "SELECT i.id
               FROM {repository_instances} i
               JOIN {repository} r ON r.id = i.typeid
              WHERE r.type = 'googledocs'
           ORDER BY i.id ASC",
            [], IGNORE_MULTIPLE);

        if (empty($gdrepoid)) {
            throw new repository_exception(get_string('nogoogledocsrepo', 'repository_gdrive_upload'));
        }
        /** @var repository_googledocs $gdrepo */
        $gdrepo = repository::get_instance($gdrepoid);

        // Retrieve the OAuth 2 issuer configured for the Google Docs plugin.
        $issuerid = get_config('googledocs', 'issuerid');
        try {
            $issuer = \core\oauth2\api::get_issuer($issuerid);
        } catch (dml_missing_record_exception $e) {
            throw new repository_exception(get_string('nosystemaccount', 'repository_gdrive_upload'));
        }

        $systemauth = \core\oauth2\api::get_system_oauth_client($issuer);
        if ($systemauth === false) {
            throw new repository_exception(get_string('nosystemaccount', 'repository_gdrive_upload'));
        }

        $systemservice = new repository_googledocs\rest($systemauth);

        // ----------------------------------------------------------------
        // 3. Build the Drive folder hierarchy (same as reference_file_selected).
        // ----------------------------------------------------------------

        // ---------------------------------------------------------------
        // Build folder structure — SAME as webservice/upload.php:
        //   root / {site_shortname} (id N) / webservice_uploads / {userid}_{firstname} / {itemid}_{course_shortname}
        // ---------------------------------------------------------------

        // User folder: {userid}_{firstname}
        $uploaduser = $DB->get_record('user', ['id' => $USER->id], 'id, firstname', IGNORE_MISSING);
        $userfolder = clean_param(
            $USER->id . '_' . ($uploaduser ? $uploaduser->firstname : $USER->id),
            PARAM_PATH
        );

        // ------------------------------------------------------------------
        // Resolve course shortname + assignment name in ONE query, directly
        // from the module context passed by the client (ctx_id POST param).
        // No heuristic fallbacks — if contextid is absent or not a module
        // context, both folder names will be empty strings.
        // ------------------------------------------------------------------
        $coursename = '';
        $assignname = '';
        if ($contextid > 0) {
            try {
                $modctx = context::instance_by_id($contextid, IGNORE_MISSING);
                if ($modctx && $modctx->contextlevel == CONTEXT_MODULE) {
                    $row = $DB->get_record_sql(
                        "SELECT a.name AS assignname, c.shortname AS courseshortname
                           FROM {course_modules} cm
                           JOIN {assign} a ON a.id = cm.instance
                           JOIN {course} c ON c.id = cm.course
                          WHERE cm.id = :cmid",
                        ['cmid' => $modctx->instanceid],
                        IGNORE_MISSING
                    );
                    if ($row) {
                        $coursename = $row->courseshortname;
                        $assignname = $row->assignname;
                    }
                }
            } catch (Exception $e) {
                // Context not found — leave folders empty.
            }
        }

        $allfolders = [
            clean_param($SITE->shortname . ' (id ' . $SITE->id . ')', PARAM_PATH),
            'webservice_uploads',
            $userfolder,
            clean_param($coursename, PARAM_PATH),
            clean_param($assignname, PARAM_PATH),
        ];



        // Walk / create the folder tree on Drive.
        $cache    = cache::make('repository_googledocs', 'folder');
        $parentid = 'root';
        $fullpath = 'root';
        foreach ($allfolders as $foldername) {
            if (empty($foldername)) {
                continue; // Skip empty slots (e.g. course/assign not resolved).
            }
            $fullpath .= '/' . $foldername;
            $folderid  = $cache->get($fullpath);

            if (empty($folderid)) {
                $folderid = self::folder_exists_in_drive($systemservice, $foldername, $parentid);
            }

            if ($folderid !== false) {
                $cache->set($fullpath, $folderid);
                $parentid = $folderid;
            } else {
                // Create the folder.
                $parentid = self::create_folder_in_drive($systemservice, $foldername, $parentid);
                $cache->set($fullpath, $parentid);
            }
        }

        // ----------------------------------------------------------------
        // 4. Upload the file to Google Drive.
        // ----------------------------------------------------------------
        $tmppath  = $_FILES[$elname]['tmp_name'];
        $mimetype = mime_content_type($tmppath) ?: 'application/octet-stream';

        // We use repository_googledocs::upload_file() which is declared public.
        $uploaded = $gdrepo->upload_file($systemservice, $tmppath, $filename, $mimetype, $parentid);

        if (empty($uploaded->id)) {
            throw new repository_exception(
                get_string('uploaderror', 'repository_gdrive_upload', 'No file ID returned by Google Drive API')
            );
        }

        // Set sharing: anyone with link can read.
        self::set_file_sharing_anyone_can_read($systemservice, $uploaded->id);

        // Fetch the web view / download link.
        $fileinfo = self::get_drive_file_summary($systemservice, $uploaded->id);
        $link     = !empty($fileinfo->webViewLink) ? $fileinfo->webViewLink : '';
        if (empty($link) && !empty($fileinfo->webContentLink)) {
            $link = $fileinfo->webContentLink;
        }

        // ----------------------------------------------------------------
        // 5. Store a FILE_CONTROLLED_LINK draft record in Moodle.
        // ----------------------------------------------------------------
        $reference = json_encode([
            'id'         => $uploaded->id,
            'name'       => $filename,
            'link'       => $link,
            'exportformat'=> 'download',
            'usesystem'  => true,
        ]);

        $usercontext = context_user::instance($USER->id);

        $record            = new stdClass();
        $record->filearea  = 'draft';
        $record->component = 'user';
        $record->filepath  = ($savepath !== '/') ? file_correct_filepath($savepath) : '/';
        $record->itemid    = $itemid;
        $record->license   = $license;
        $record->author    = $author;
        $record->filename  = $filename;
        $record->contextid = $usercontext->id;
        $record->userid    = $USER->id;
        $record->source    = self::build_source_field($filename);

        // Find the correct repository instance ID for googledocs so the
        // stored_file reference points to the right repository.
        $gdrepoid = $gdrepo->id;

        $fs = get_file_storage();

        // Handle duplicate filenames in the draft area.
        if (repository::draftfile_exists($record->itemid, $record->filepath, $record->filename)) {
            $unusedname  = repository::get_unused_filename($record->itemid, $record->filepath, $record->filename);
            $existingname = $record->filename;
            $record->filename = $unusedname;

            $storedfile = $fs->create_file_from_reference($record, $gdrepoid, $reference);

            if ($overwrite) {
                repository::overwrite_existing_draftfile(
                    $record->itemid, $record->filepath, $existingname,
                    $record->filepath, $record->filename
                );
                $record->filename = $existingname;
            }
        } else {
            $storedfile = $fs->create_file_from_reference($record, $gdrepoid, $reference);
        }

        // Fire the draft_file_added event.
        $logevent = \core\event\draft_file_added::create([
            'objectid' => $storedfile->get_id(),
            'context'  => $usercontext,
            'other'    => [
                'itemid'      => $record->itemid,
                'filename'    => $record->filename,
                'filesize'    => $filesize,
                'filepath'    => $record->filepath,
                'contenthash' => $storedfile->get_contenthash(),
            ],
        ]);
        $logevent->trigger();

        return [
            'url'  => moodle_url::make_draftfile_url(
                $record->itemid, $record->filepath, $record->filename
            )->out(false),
            'id'   => $record->itemid,
            'file' => $record->filename,
        ];
    }

    // ----------------------------------------------------------------
    // Helper methods (mirrors protected methods in repository_googledocs)
    // ----------------------------------------------------------------

    /**
     * Check whether a folder exists in a Google Drive parent.
     *
     * @param repository_googledocs\rest $client
     * @param string $foldername
     * @param string $parentid
     * @return string|false  Folder ID or false if not found.
     */
    private static function folder_exists_in_drive(
        repository_googledocs\rest $client,
        string $foldername,
        string $parentid
    ) {
        $q      = '\'' . addslashes($parentid) . '\' in parents and trashed = false and name = \'' . addslashes($foldername) . '\'';
        $params = ['q' => $q, 'fields' => 'files(id, name)'];
        $resp   = $client->call('list', $params);
        foreach ($resp->files as $child) {
            if ($child->name == $foldername) {
                return $child->id;
            }
        }
        return false;
    }

    /**
     * Create a folder inside a Google Drive parent.
     *
     * @param repository_googledocs\rest $client
     * @param string $foldername
     * @param string $parentid
     * @return string  New folder ID.
     */
    private static function create_folder_in_drive(
        repository_googledocs\rest $client,
        string $foldername,
        string $parentid
    ): string {
        $params  = ['fields' => 'id'];
        $folder  = [
            'mimeType' => 'application/vnd.google-apps.folder',
            'name'     => $foldername,
            'parents'  => [$parentid],
        ];
        $created = $client->call('create', $params, json_encode($folder));
        if (empty($created->id)) {
            throw new repository_exception(
                'errorwhilecommunicatingwith', 'repository', '', 'Cannot create Drive folder: ' . $foldername
            );
        }
        return $created->id;
    }

    /**
     * Set a Google Drive file so "anyone with the link" can read it.
     *
     * @param repository_googledocs\rest $client
     * @param string $fileid
     */
    private static function set_file_sharing_anyone_can_read(
        repository_googledocs\rest $client,
        string $fileid
    ): void {
        $permission = [
            'type'               => 'anyone',
            'role'               => 'reader',
            'allowFileDiscovery' => 'false',
        ];
        $params = ['fileid' => $fileid, 'supportsAllDrives' => 'true'];
        $client->call('create_permission', $params, json_encode($permission));
    }

    /**
     * Get file metadata (id, name, webViewLink, webContentLink, owners).
     *
     * @param repository_googledocs\rest $client
     * @param string $fileid
     * @return stdClass
     */
    private static function get_drive_file_summary(
        repository_googledocs\rest $client,
        string $fileid
    ): stdClass {
        $params = [
            'fileid' => $fileid,
            'fields' => 'id,name,owners,webContentLink,webViewLink',
        ];
        return $client->call('get', $params);
    }

    // ----------------------------------------------------------------
    // Standard repository API
    // ----------------------------------------------------------------

    /**
     * This repository supports FILE_CONTROLLED_LINK only.
     * Files are stored in Google Drive, not on the Moodle server.
     *
     * @return int
     */
    public function supported_returntypes() {
        return FILE_INTERNAL | FILE_CONTROLLED_LINK;
    }

    /**
     * What kind of files does this repository support?
     *
     * @return string|array
     */
    public function supported_filetypes() {
        return '*';
    }

    /**
     * This repository does not access private data in a way that requires
     * individual user credentials for the upload itself.
     *
     * @return bool
     */
    public function contains_private_data() {
        return false;
    }

    /**
     * Admin settings form — lets admins optionally choose a different
     * OAuth 2 issuer if the site has more than one Google issuer.
     *
     * @param MoodleQuickForm $mform
     * @param string          $classname
     */
    public static function type_config_form($mform, $classname = 'repository') {
        parent::type_config_form($mform, $classname);

        $options  = [];
        $issuers  = \core\oauth2\api::get_all_issuers();
        foreach ($issuers as $issuer) {
            $options[$issuer->get('id')] = s($issuer->get('name'));
        }

        if (!empty($options)) {
            $mform->addElement('select', 'issuerid',
                get_string('issuerid', 'repository_gdrive_upload'), $options);
            $mform->addHelpButton('issuerid', 'issuerid', 'repository_gdrive_upload');
        } else {
            $mform->addElement('static', null, '',
                get_string('nogoogledocsrepo', 'repository_gdrive_upload'));
        }
    }

    /**
     * Names of general (type-level) options stored in the DB.
     *
     * @return array
     */
    public static function get_type_option_names() {
        return ['issuerid', 'pluginname'];
    }
}