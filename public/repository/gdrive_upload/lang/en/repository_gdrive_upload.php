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
 * Language strings for repository_gdrive_upload.
 *
 * @package    repository_gdrive_upload
 * @copyright  2026 medi-wiki.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname']      = 'Drive Upload';
$string['configplugin']    = 'Google Drive Upload Repository Configuration';
$string['pluginname_help'] = 'Upload files from your device directly to Google Drive.';
$string['privacy:metadata']= 'The Google Drive Upload repository does not store any personal data itself. Uploaded files are stored in Google Drive under the configured system account.';
$string['nogoogledocsrepo']= 'No active Google Docs repository instance found. Please ask your administrator to enable and configure the Google Docs repository.';
$string['nosystemaccount'] = 'The Google Docs repository system account is not connected. Please ask your administrator to connect the system account (Admin → Site Administration → Server → OAuth2 services).';
$string['uploaderror']     = 'Error uploading file to Google Drive: {$a}';
$string['issuerid']        = 'OAuth 2 service';
$string['issuerid_help']   = 'Select the OAuth 2 service that will be used to connect to Google Drive. This should match the service used by the Google Docs repository.';
