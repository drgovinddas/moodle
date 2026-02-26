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
 * Install function for repository_gdrive_upload.
 *
 * Automatically creates a site-wide repository instance so that the
 * "Upload a file" tab appears in all file pickers immediately after install
 * — without any extra admin configuration.
 *
 * @package    repository_gdrive_upload
 * @copyright  2026 medi-wiki.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Called by Moodle during plugin installation.
 *
 * @return bool
 */
function xmldb_repository_gdrive_upload_install() {
    // Avoid creating a duplicate instance on reinstall.
    $existing = repository::get_instances(['type' => 'gdrive_upload', 'currentcontext' => context_system::instance()]);
    if (!empty($existing)) {
        return true;
    }

    // Create a single system-wide visible instance (onlyvisible = 1).
    $result = repository::static_function(
        'gdrive_upload',
        'create',
        'gdrive_upload',
        false,
        context_system::instance(),
        ['pluginname' => 'Upload a file'],
        1  // onlyvisible
    );

    return ($result !== false);
}
