<?php
/**
 * Table class for listing rules.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

class admin_rules extends \table_sql {

    /**
     * Constructor
     * @param string $uniqueid
     */
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);

        $this->define_columns([
            'priority',
            'rulename',
            'status',
            'courses',
            'stopprocessing',
            'actions'
        ]);

        $this->define_headers([
            get_string('priority', 'local_studentprofile'),
            get_string('rulename', 'local_studentprofile'),
            get_string('status', 'local_studentprofile'),
            get_string('courses', 'local_studentprofile'),
            get_string('stopprocessing', 'local_studentprofile'),
            get_string('actions')
        ]);

        $this->sortable(false);
        $this->collapsible(false);
        $this->set_attribute('class', 'generaltable w-100');
    }

    /**
     * Setup the SQL for this table.
     */
    public function setup() {
        $this->set_sql('*', "{local_studentprofile_rules}", "1=1", []);
        parent::setup();
    }

    public function col_priority($row) {
        return $row->priority;
    }

    public function col_rulename($row) {
        return format_string($row->rulename);
    }

    public function col_status($row) {
        return $row->status 
            ? '<span class="badge badge-success bg-success">Active</span>' 
            : '<span class="badge badge-secondary bg-secondary">Inactive</span>';
    }

    public function col_courses($row) {
        $courses = @json_decode($row->courses, true);
        $coursecount = is_array($courses) ? count($courses) : 0;
        return $coursecount . ' course(s)';
    }

    public function col_stopprocessing($row) {
        return $row->stopprocessing 
            ? '<span class="badge badge-danger bg-danger">Yes</span>' 
            : 'No';
    }

    public function col_actions($row) {
        $editurl = new \moodle_url('/local/studentprofile/admin_rule_edit.php', ['id' => $row->id]);
        $deleteurl = new \moodle_url('/local/studentprofile/admin_rules.php', ['action' => 'delete', 'id' => $row->id, 'sesskey' => sesskey()]);
        
        $actions = \html_writer::link($editurl, 'Edit', ['class' => 'btn btn-sm btn-primary']);
        $actions .= ' ' . \html_writer::link($deleteurl, 'Delete', [
            'class' => 'btn btn-sm btn-danger',
            'onclick' => 'return confirm("Are you sure you want to delete this rule?");'
        ]);
        return $actions;
    }
}
