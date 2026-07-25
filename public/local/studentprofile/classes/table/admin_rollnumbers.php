<?php
/**
 * Table for admin roll number assignment.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

class admin_rollnumbers extends \table_sql {

    /**
     * Constructor.
     * @param string $uniqueid
     */
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);

        $this->define_columns([
            'fullname',
            'email',
            'collegename',
            'degree',
            'admissionyear',
            'rollno',
            'edit'
        ]);

        $this->define_headers([
            get_string('fullnameuser'),
            get_string('email'),
            get_string('collegename', 'local_studentprofile'),
            get_string('degree', 'local_studentprofile'),
            get_string('admissionyear', 'local_studentprofile'),
            'Roll No',
            'Edit'
        ]);

        $this->sortable(true, 'firstname');
        $this->no_sorting('rollno');
        $this->no_sorting('edit');
        $this->collapsible(false);
        
        $this->set_attribute('class', 'generaltable w-100');
    }

    /**
     * Setup the SQL for this table.
     */
    public function setup() {
        global $DB;
        
        $sql_fields = "u.id, u.firstname, u.lastname, u.email, spd.collegename, spd.degree, spd.admissionyear, spd.shortname, spd.rollno, spd.rollnotype, spd.rollnostatus";
        $sql_from = "{user} u JOIN {local_studentprofile_data} spd ON spd.userid = u.id";
        $sql_where = "u.deleted = 0 AND spd.draft = 0";
        
        $this->set_sql($sql_fields, $sql_from, $sql_where, []);
        
        parent::setup();
    }

    /**
     * Render fullname with our custom format if available.
     */
    public function col_fullname($row) {
        global $OUTPUT;
        
        $shortname = strtolower(trim($row->shortname ?? ''));
        $year2     = substr((string)($row->admissionyear ?? ''), -2);
        $rollnostatus = (int)($row->rollnostatus ?? 0);
        $rollno    = (!empty($row->rollno) && $rollnostatus === 1) ? '-' . trim($row->rollno) : '';
        $firstname = trim($row->firstname);

        $display = $shortname . $year2 . $rollno . '-' . $firstname;
        
        $url = new \moodle_url('/user/profile.php', ['id' => $row->id]);
        return \html_writer::link($url, s($display));
    }

    public function col_rollno($row) {
        $current_rollno = trim($row->rollno ?? '');
        $current_type = trim($row->rollnotype ?? 'permanent');
        $current_status = (int)($row->rollnostatus ?? 0);
        
        if (is_siteadmin()) {
            $temp_selected = ($current_type === 'temporary') ? 'selected' : '';
            $perm_selected = ($current_type === 'permanent') ? 'selected' : '';
            
            $pending_selected = ($current_status === 0) ? 'selected' : '';
            $approved_selected = ($current_status === 1) ? 'selected' : '';
            $disapproved_selected = ($current_status === 2) ? 'selected' : '';
            
            $html = '<div class="rollno-edit-container d-flex flex-column gap-1" data-userid="'.$row->id.'">';
            $html .= '<input type="text" class="form-control form-control-sm rollno-input" value="'.s($current_rollno).'" placeholder="Roll No">';
            
            $html .= '<select class="form-select form-select-sm rollnotype-select">';
            $html .= '<option value="temporary" '.$temp_selected.'>Temporary</option>';
            $html .= '<option value="permanent" '.$perm_selected.'>Permanent</option>';
            $html .= '</select>';

            $html .= '<select class="form-select form-select-sm rollnostatus-select">';
            $html .= '<option value="0" '.$pending_selected.'>Pending</option>';
            $html .= '<option value="1" '.$approved_selected.'>Approved</option>';
            $html .= '<option value="2" '.$disapproved_selected.'>Disapproved</option>';
            $html .= '</select>';
            
            $html .= '<button class="btn btn-sm btn-primary save-rollno-btn">Save</button>';
            $html .= '</div>';
            return $html;
        } else {
            if ($current_rollno) {
                $type_label = ($current_type === 'temporary') ? ' (Temp)' : '';
                $status_label = '';
                if ($current_status === 0) {
                    $status_label = ' <span class="badge bg-warning">Pending</span>';
                } elseif ($current_status === 1) {
                    $status_label = ' <span class="badge bg-success">Approved</span>';
                } elseif ($current_status === 2) {
                    $status_label = ' <span class="badge bg-danger">Disapproved</span>';
                }
                return s($current_rollno) . $type_label . $status_label;
            }
            return '-';
        }
    }

    public function col_edit($row) {
        if (is_siteadmin()) {
            $url = new \moodle_url('/local/studentprofile/admin_edit_profile.php', ['userid' => $row->id]);
            return \html_writer::link($url, get_string('editprofile', 'local_studentprofile'), ['class' => 'btn btn-sm btn-outline-secondary']);
        }
        return '';
    }
}
