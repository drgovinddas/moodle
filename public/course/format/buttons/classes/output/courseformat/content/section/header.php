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

namespace format_buttons\classes\output\courseformat\content\section;

defined('MOODLE_INTERNAL') || die();

use core_courseformat\base as course_format;
use core_courseformat\output\local\content\section\header as class_header;
use section_info;
use stdClass;

class header extends class_header
{
    /**
     * Config to the sectioname
     *
     * @var bool
     */
    var bool $sectioname_return = true;

    /**
     * Constructor
     *
     * @param course_format $format
     * @param section_info $section
     */
    public function __construct(course_format $format, section_info $section)
    {
        parent::__construct($format, $section);
        if ($this->format->get_course()->title_section_view == 0) {
            $this->sectioname_return = false;
        }
    }

    /**
     * export data for the template
     *
     * @param \renderer_base $output
     * @return stdClass
     */
    public function export_for_template(\renderer_base $output): stdClass
    {
        $data = parent::export_for_template($output);
        //Verified, if the config can sent sectioname
        if (!$this->sectioname_return) {
            $data->title = " ";
        }

        return $data;
    }

    /**
     * template name
     *
     * @param \renderer_base $renderer
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string
    {
        return 'format_buttons/local/content/headers';
    }

}