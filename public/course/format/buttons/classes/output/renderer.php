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
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     format_buttons
 * @category    upgrade
 * @copyright   2023 Jhon Rangel <jrangelardila@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_buttons\output;

defined('MOODLE_INTERNAL') || die();

use core_courseformat\output\section_renderer as core_section_renderer;
use core_courseformat\base as course_format;
use section_info;


class renderer extends core_section_renderer
{

    /**
     * Course update section
     *
     * @param course_format $format
     * @param section_info $section
     * @return string
     * @throws \core\exception\coding_exception
     */
    public function course_section_updated(course_format $format, section_info $section): string
    {
        $classname = $format->get_output_classname('content\\section');
        $renderable = new $classname($format, $section);
        return $this->render($renderable);
    }
}

