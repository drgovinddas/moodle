<?php
/**
 * Participants filterset for local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\table;

defined('MOODLE_INTERNAL') || die();

use core_table\local\filter\integer_filter;
use core_table\local\filter\string_filter;

class participants_filterset extends \core_user\table\participants_filterset {
    /**
     * Get the optional filters — adds college, degree, degreetype and admissionyear.
     *
     * @return array
     */
    public function get_optional_filters(): array {
        $filters = parent::get_optional_filters();
        $filters['college']    = string_filter::class;
        $filters['degree']     = string_filter::class;
        $filters['degreetype'] = string_filter::class;
        $filters['year']       = integer_filter::class;
        return $filters;
    }

    /**
     * Remove a filter by name.
     *
     * @param string $filtername
     */
    public function remove_filter(string $filtername): void {
        unset($this->filters[$filtername]);
    }
}
