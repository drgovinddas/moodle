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

namespace format_buttons\output\courseformat\content;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_courseformat\output\local\content\sectionnavigation as core_sectionnavigation;

class sectionnavigation extends core_sectionnavigation
{
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     * @throws \coding_exception
     */
    public function export_for_template(\renderer_base $output): \stdClass
    {
        global $USER;

        if ($this->data !== null) {
            return $this->data;
        }

        $format = $this->format;
        $course = $format->get_course();
        $context = context_course::instance($course->id);

        $modinfo = $this->format->get_modinfo();
        $sections = $modinfo->get_section_info_all();

        // FIXME: This is really evil and should by using the navigation API.
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context, $USER);

        $data = (object)[
            'previousurl' => '',
            'nexturl' => '',
            'larrow' => $output->larrow(),
            'rarrow' => $output->rarrow(),
            'currentsection' => $this->sectionno,
        ];

        $back = $this->sectionno - 1;
        while ($back >= 0 && empty($data->previousurl)) {
            //Drop subsections
            $sectiondelegated = $sections[$back]->get_component_instance();
            if ($sectiondelegated !== null && $sectiondelegated->get_parent_section() !== null) {
                $back++;
                continue;
            }
            if ($canviewhidden || $sections[$back]->uservisible) {
                if (!$sections[$back]->visible) {
                    $data->previoushidden = true;
                }
                $data->previousname = get_section_name($course, $sections[$back]);
                $data->previousurl = course_get_url($course, $back, ['navigation' => true]);
                $data->hasprevious = true;
            }
            $back--;
        }

        $forward = $this->sectionno + 1;
        $numsections = course_get_format($course)->get_last_section_number();
        while ($forward <= $numsections and empty($data->nexturl)) {
            //Drop subsections
            $sectiondelegated = $sections[$forward]->get_component_instance();
            if ($sectiondelegated !== null && $sectiondelegated->get_parent_section() !== null) {
                $forward++;
                continue;
            }

            if ($canviewhidden || $sections[$forward]->uservisible) {
                if (!$sections[$forward]->visible) {
                    $data->nexthidden = true;
                }
                $data->nextname = get_section_name($course, $sections[$forward]);
                $data->nexturl = course_get_url($course, $forward, ['navigation' => true]);
                $data->hasnext = true;
            }
            $forward++;
        }

        $this->data = $data;
        return $data;
    }
}