<?php
/**
 * Participants table for local_studentprofile.
 *
 * Customisations:
 *  - Removes the Email address column from the grid.
 *  - Reformats the name column as: {college_shortname}{last2ofyear}-{firstname}
 *    e.g.  hns24-Ravi
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\table;

defined('MOODLE_INTERNAL') || die();

class participants extends \core_user\table\participants {

    /** @var string College filter value. */
    protected $college = '';

    /** @var string Degree filter value. */
    protected $degree = '';

    /** @var string Degree type filter value (UG/PG). */
    protected $degreetype = '';

    /** @var int Admission year filter value. */
    protected $year = 0;

    /** @var string Keyword search value. */
    protected $search = '';

    /**
     * Student profile data keyed by Moodle userid.
     * Populated in query_db() to avoid N+1 queries in col_fullname().
     *
     * @var array
     */
    protected $studentprofiles = [];

    // -----------------------------------------------------------------------
    // Column customisation
    // -----------------------------------------------------------------------

    /**
     * Override setup to remove the email column after the parent sets up columns.
     */
    public function setup() {
        parent::setup();
        $this->remove_column('email');
        
        // Add rollno column
        if (!isset($this->columns['rollno'])) {
            $this->columns['rollno'] = count($this->columns);
            $this->headers[] = 'Roll No';
        }
    }

    /**
     * Remove a column by name from the table definition.
     * Works for both the legacy $this->columns array and the modern column API.
     *
     * @param string $colname
     */
    protected function remove_column(string $colname): void {
        // Modern Moodle 4.x column objects API.
        if (method_exists($this, 'get_columns')) {
            try {
                $cols = $this->get_columns();
                foreach ($cols as $col) {
                    if (method_exists($col, 'get_column_type') && $col->get_column_type() === $colname) {
                        // No public remove API — fall through to legacy below.
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore — fall through to legacy.
            }
        }

        // Legacy flexible_table approach: $this->columns is an associative
        // array of colname => index, $this->headers is a numerically keyed array.
        if (!isset($this->columns[$colname])) {
            return;
        }

        $idx = $this->columns[$colname];

        // Remove from headers (preserve numeric order).
        if (isset($this->headers[$idx])) {
            array_splice($this->headers, $idx, 1);
        }

        // Remove from columns map and re-index positions of later columns.
        unset($this->columns[$colname]);
        foreach ($this->columns as $name => $pos) {
            if ($pos > $idx) {
                $this->columns[$name] = $pos - 1;
            }
        }

        // Remove any sorting/class metadata that reference the column.
        unset($this->column_style[$colname]);
        unset($this->column_class[$colname]);
        if (isset($this->sortable_columns[$colname])) {
            unset($this->sortable_columns[$colname]);
        }
    }

    // -----------------------------------------------------------------------
    // Custom fullname column renderer
    // -----------------------------------------------------------------------

    /**
     * Render the fullname column as:
     *   {college_shortname}{last 2 digits of admission year}-{firstname}
     * e.g.  hns24-Ravi
     *
     * Falls back to the default Moodle fullname if no profile record exists.
     *
     * @param  \stdClass $row  The user row.
     * @return string   Escaped HTML for the table cell.
     */
    public function col_fullname($row): string {
        $profile = $this->studentprofiles[$row->id] ?? null;

        if ($profile && !empty($profile->shortname)) {
            $shortname = strtolower(trim($profile->shortname));
            $year2     = substr((string)($profile->admissionyear ?? ''), -2);
            $rollnostatus = (int)($profile->rollnostatus ?? 0);
            $rollno    = (!empty($profile->rollno) && $rollnostatus === 1) ? '-' . trim($profile->rollno) : '';
            // Use first name stored in plugin table if available; else Moodle user row.
            $firstname = trim($profile->firstname ?: $row->firstname);

            $display = $shortname . $year2 . $rollno . '-' . $firstname;

            // Wrap in a profile link just as the parent does, but use our text.
            global $OUTPUT;
            $url = new \moodle_url('/user/view.php', [
                'id'     => $row->id,
                'course' => $this->course->id,
            ]);
            return \html_writer::link($url, s($display), ['class' => 'sp-student-name']);
        }

        // Fallback — standard Moodle fullname + link.
        return parent::col_fullname($row);
    }

    /**
     * Return an empty string for the email column so even if it sneaks
     * through the column removal it renders nothing.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_email($row): string {
        return '';
    }

    public function col_rollno($row): string {
        $profile = $this->studentprofiles[$row->id] ?? null;
        $current_rollno = $profile ? trim($profile->rollno ?? '') : '';
        $current_type = $profile ? trim($profile->rollnotype ?? '') : 'permanent';
        
        if ($current_rollno) {
            $type_label = ($current_type === 'temporary') ? ' (Temp)' : '';
            return s($current_rollno) . $type_label;
        }
        return '-';
    }

    // -----------------------------------------------------------------------
    // Remaining existing overrides (filters, query_db, etc.)
    // -----------------------------------------------------------------------

    /**
     * Set the custom student profile filters.
     *
     * @param string $college
     * @param string $degree
     * @param int    $year
     * @param string $search
     * @param string $degreetype
     */
    public function set_studentprofile_filters($college, $degree, $year, $search, $degreetype = '') {
        $this->college    = $college;
        $this->degree     = $degree;
        $this->year       = (int)$year;
        $this->search     = $search;
        $this->degreetype = $degreetype;
    }

    /**
     * Guess the base url for the participants table.
     */
    public function guess_base_url(): void {
        $this->baseurl = new \moodle_url('/local/studentprofile/participants.php', ['id' => $this->courseid]);
    }

    /**
     * Query the database to fetch participants.
     *
     * @param int  $pagesize
     * @param bool $useinitialsbar
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        // 1. Populate custom filters from filterset if available.
        if ($this->filterset) {
            $this->college = $this->filterset->has_filter('college')
                ? (reset($this->filterset->get_filter('college')->get_filter_values()) ?: '')
                : '';

            $this->degree = $this->filterset->has_filter('degree')
                ? (reset($this->filterset->get_filter('degree')->get_filter_values()) ?: '')
                : '';

            $this->degreetype = $this->filterset->has_filter('degreetype')
                ? (reset($this->filterset->get_filter('degreetype')->get_filter_values()) ?: '')
                : '';

            $this->year = $this->filterset->has_filter('year')
                ? (int)(reset($this->filterset->get_filter('year')->get_filter_values()) ?: 0)
                : 0;

            $this->search = $this->filterset->has_filter('keywords')
                ? (implode(' ', $this->filterset->get_filter('keywords')->get_filter_values()) ?: '')
                : '';
        }

        // 2. Clone filterset and strip keywords before passing to base search.
        $searchfilterset = $this->filterset;
        if ($this->filterset && $this->filterset->has_filter('keywords')) {
            $searchfilterset = clone $this->filterset;
            $searchfilterset->remove_filter('keywords');
        }

        // 3. Get basic WHERE conditions (e.g. initial bars).
        list($twhere, $tparams) = $this->get_sql_where();

        // 4. Determine JOIN type.
        $jointype = $this->filterset
            ? $this->filterset->get_join_type()
            : \core_table\local\filter\filterset::JOINTYPE_ALL;
        $joinstr = ($jointype === \core_table\local\filter\filterset::JOINTYPE_ANY) ? ' OR ' : ' AND ';

        $subparams = [];

        // 5. Custom student profile filter conditions.
        $sp_conditions = [];

        if (!empty($this->college)) {
            $sp_conditions[] = "collegename = :sp_college";
            $subparams['sp_college'] = $this->college;
        }
        if (!empty($this->degree)) {
            $sp_conditions[] = "degree = :sp_degree";
            $subparams['sp_degree'] = $this->degree;
        }
        // Validate degreetype allow-list to prevent injection.
        if (!empty($this->degreetype) && in_array($this->degreetype, ['UG', 'PG'])) {
            $sp_conditions[] = "degreetype = :sp_degreetype";
            $subparams['sp_degreetype'] = $this->degreetype;
        }
        if ($this->year > 0) {
            $sp_conditions[] = "admissionyear = :sp_year";
            $subparams['sp_year'] = $this->year;
        }

        $sp_subsql = "";
        if (!empty($sp_conditions)) {
            $sp_subsql = "udistinct.id IN (SELECT userid FROM {local_studentprofile_data} WHERE "
                . implode($joinstr, $sp_conditions) . ")";
        }

        // 6. Keyword search conditions.
        $kw_sql = "";
        if (!empty($this->search)) {
            $userfields_sql = [];
            $canviewfullnames = has_capability('moodle/site:viewfullnames', $this->context);
            list($fullname_sql, $fullnameparams) = \core_user\fields::get_sql_fullname('udistinct', $canviewfullnames);
            $userfields_sql[] = $DB->sql_like($fullname_sql, ':sp_kw_fullname', false, false);
            $subparams['sp_kw_fullname'] = '%' . $this->search . '%';
            $subparams = array_merge($subparams, $fullnameparams);

            $userfields_sql[] = $DB->sql_like('udistinct.email', ':sp_kw_email', false, false);
            $subparams['sp_kw_email'] = '%' . $this->search . '%';

            $profilefields_sql = [];
            $profilefields_sql[] = $DB->sql_like('spd.collegename', ':sp_kw_coll', false, false);
            $subparams['sp_kw_coll'] = '%' . $this->search . '%';

            $profilefields_sql[] = $DB->sql_like('spd.degree', ':sp_kw_deg', false, false);
            $subparams['sp_kw_deg'] = '%' . $this->search . '%';

            $profilefields_sql[] = $DB->sql_like('spd.fullname', ':sp_kw_spfn', false, false);
            $subparams['sp_kw_spfn'] = '%' . $this->search . '%';

            $profilefields_sql[] = $DB->sql_like('spd.shortname', ':sp_kw_spsn', false, false);
            $subparams['sp_kw_spsn'] = '%' . $this->search . '%';

            if (is_numeric($this->search)) {
                $profilefields_sql[] = "spd.admissionyear = :sp_kw_yr";
                $subparams['sp_kw_yr'] = (int)$this->search;
            }

            $kw_sql = "(" . implode(' OR ', $userfields_sql) .
                " OR udistinct.id IN (SELECT userid FROM {local_studentprofile_data} spd WHERE " .
                implode(' OR ', $profilefields_sql) . "))";
        }

        // 7. Combine all WHERE clauses.
        $all_wheres = [];
        if (!empty($twhere))    { $all_wheres[] = "($twhere)"; }
        if (!empty($sp_subsql)) { $all_wheres[] = "($sp_subsql)"; }
        if (!empty($kw_sql))    { $all_wheres[] = "($kw_sql)"; }

        $twhere  = !empty($all_wheres) ? implode($joinstr, $all_wheres) : "";
        $tparams = array_merge($tparams, $subparams);

        // 8. Execute via participants_search.
        $psearch = new \core_user\table\participants_search($this->course, $this->context, $searchfilterset);
        $sort    = $this->get_sql_sort();

        $this->use_pages = true;
        $rawdata = $psearch->get_participants($twhere, $tparams, $sort, $this->get_page_start(), $this->get_page_size());
        $total   = $rawdata->current()->fullcount ?? 0;
        $this->pagesize($pagesize, $total);

        $this->rawdata = [];
        foreach ($rawdata as $user) {
            $this->rawdata[$user->id] = $user;
        }
        $rawdata->close();

        // 9. Pre-fetch student profile records for all visible users (single query).
        $this->studentprofiles = [];
        if (!empty($this->rawdata)) {
            list($insql, $inparams) = $DB->get_in_or_equal(
                array_keys($this->rawdata),
                SQL_PARAMS_NAMED,
                'sp_uid'
            );
            $profiles = $DB->get_records_sql(
                "SELECT userid, shortname, admissionyear, firstname, draft, rollno, rollnotype
                   FROM {local_studentprofile_data}
                  WHERE userid $insql
                    AND draft = 0",
                $inparams
            );
            foreach ($profiles as $p) {
                $this->studentprofiles[$p->userid] = $p;
            }
        }

        if ($this->rawdata) {
            $this->allroleassignments = get_users_roles(
                $this->context,
                array_keys($this->rawdata),
                true,
                'c.contextlevel DESC, r.sortorder ASC'
            );
        } else {
            $this->allroleassignments = [];
        }

        if ($useinitialsbar) {
            $this->initialbars(true);
        }
    }
}
