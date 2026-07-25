<?php
/**
 * Table for managing colleges.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

class admin_colleges extends \table_sql {
    
    private $search;
    
    /**
     * Constructor.
     * @param string $uniqueid
     * @param string $search
     */
    public function __construct($uniqueid, $search = '') {
        parent::__construct($uniqueid);
        $this->search = $search;
        
        $this->define_columns([
            'shortname',
            'collegename',
            'degrees',
            'sortorder',
            'actions'
        ]);
        
        $this->define_headers([
            get_string('collegeshortname', 'local_studentprofile'),
            get_string('collegename', 'local_studentprofile'),
            get_string('degrees', 'local_studentprofile'),
            get_string('sortorder', 'local_studentprofile'),
            get_string('edit') . ' / ' . get_string('delete')
        ]);
        
        $this->no_sorting('actions');
        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->set_attribute('class', 'admintable generaltable');
    }
    
    /**
     * Setup the SQL query.
     */
    public function setup() {
        global $DB;
        $sqlwhere = '1=1';
        $params = [];
        
        if (!empty($this->search)) {
            $sqlwhere = $DB->sql_like('collegename', ':search1', false) . ' OR ' . $DB->sql_like('shortname', ':search2', false);
            $params['search1'] = '%' . $this->search . '%';
            $params['search2'] = '%' . $this->search . '%';
        }
        
        $this->set_sql('*', '{local_studentprofile_colleges}', $sqlwhere, $params);
        parent::setup();
    }
    
    /**
     * Format the shortname column.
     * @param \stdClass $row
     * @return string
     */
    public function col_shortname($row) {
        $short = s($row->shortname);
        if ($row->sortorder == 0) {
            return '<span class="badge bg-warning text-dark me-1" title="User added custom college">Custom</span> ' . $short;
        }
        return $short;
    }
    
    /**
     * Format the collegename column.
     * @param \stdClass $row
     * @return string
     */
    public function col_collegename($row) {
        $name = s($row->collegename);
        if ($row->sortorder == 0) {
            return '<strong class="text-primary">' . $name . '</strong>';
        }
        return $name;
    }
    
    /**
     * Format the degrees column.
     * @param \stdClass $row
     * @return string
     */
    public function col_degrees($row) {
        return !empty($row->degrees) ? s($row->degrees) : '<em>' . s(get_string('all')) . '</em>';
    }
    
    /**
     * Format the actions column.
     * @param \stdClass $row
     * @return string
     */
    public function col_actions($row) {
        $editurl = new \moodle_url('/local/studentprofile/admin_colleges.php', [
            'action' => 'edit',
            'id' => $row->id,
            'sesskey' => sesskey()
        ]);
        
        $deleteurl = new \moodle_url('/local/studentprofile/admin_colleges.php', [
            'action' => 'delete',
            'id' => $row->id,
            'sesskey' => sesskey()
        ]);
        
        $editlink = \html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-sm btn-secondary me-2']);
        $deletelink = \html_writer::link($deleteurl, get_string('delete'), [
            'class' => 'btn btn-sm btn-danger',
            'onclick' => 'return confirm(' . json_encode(get_string('confirmdelete', 'local_studentprofile')) . ')'
        ]);
        
        return $editlink . $deletelink;
    }
}
