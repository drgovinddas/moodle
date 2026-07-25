<?php
/**
 * Upgrade code for local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_studentprofile_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024051900) {
        // Define table local_studentprofile_data to be created.
        $table = new xmldb_table('local_studentprofile_data');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('collegename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fullname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('admissionyear', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('degree', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('collegename', XMLDB_INDEX_NOTUNIQUE, ['collegename']);
        $table->add_index('admissionyear', XMLDB_INDEX_NOTUNIQUE, ['admissionyear']);
        $table->add_index('degree', XMLDB_INDEX_NOTUNIQUE, ['degree']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2024051900, 'local', 'studentprofile');
    }

    if ($oldversion < 2024051901) {
        // Disable requireconfirmation for Google OAuth2 issuer.
        if ($DB->record_exists('oauth2_issuer', ['servicetype' => 'google', 'requireconfirmation' => 1])) {
            $DB->set_field('oauth2_issuer', 'requireconfirmation', 0, ['servicetype' => 'google']);
        }
        upgrade_plugin_savepoint(true, 2024051901, 'local', 'studentprofile');
    }

    // -----------------------------------------------------------------------
    // v2.0 upgrades
    // -----------------------------------------------------------------------

    if ($oldversion < 2026060601) {
        // Create local_studentprofile_colleges table.
        $table = new xmldb_table('local_studentprofile_colleges');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('shortname', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('collegename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('longname', XMLDB_TYPE_CHAR, '500', null, null, null, null);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('shortname', XMLDB_INDEX_UNIQUE, ['shortname']);
            $table->add_index('sortorder', XMLDB_INDEX_NOTUNIQUE, ['sortorder']);
            $dbman->create_table($table);
        }

        // Create local_studentprofile_degrees table.
        $dtable = new xmldb_table('local_studentprofile_degrees');
        if (!$dbman->table_exists($dtable)) {
            $dtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $dtable->add_field('degreename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $dtable->add_field('degreetype', XMLDB_TYPE_CHAR, '2', null, XMLDB_NOTNULL, null, 'UG');
            $dtable->add_field('sortorder', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
            $dtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $dtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $dtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dtable->add_index('degreetype', XMLDB_INDEX_NOTUNIQUE, ['degreetype']);
            $dtable->add_index('sortorder', XMLDB_INDEX_NOTUNIQUE, ['sortorder']);
            $dbman->create_table($dtable);

            // Seed default degrees from old config if available.
            $rawdegrees = get_config('local_studentprofile', 'degrees');
            if ($rawdegrees) {
                $rows = explode("\n", str_replace("\r", "", $rawdegrees));
                $i = 0;
                foreach ($rows as $row) {
                    $row = trim($row);
                    if (empty($row)) {
                        continue;
                    }
                    // Heuristic: B.*, BBA, BE = UG; else PG.
                    $type = (preg_match('/^(B\.|BA|BE|BBA|BCA|B\.Com)/i', $row)) ? 'UG' : 'PG';
                    $DB->insert_record('local_studentprofile_degrees', (object)[
                        'degreename'   => $row,
                        'degreetype'   => $type,
                        'sortorder'    => $i++,
                        'timecreated'  => time(),
                        'timemodified' => time(),
                    ]);
                }
            }

            // Seed colleges from old allowedcolleges config if available.
            $rawcolleges = get_config('local_studentprofile', 'allowedcolleges');
            if ($rawcolleges) {
                $rows = explode("\n", str_replace("\r", "", $rawcolleges));
                $i = 0;
                foreach ($rows as $row) {
                    $row = trim($row);
                    if (empty($row)) {
                        continue;
                    }
                    // Generate a simple short name from initials.
                    $words = explode(' ', $row);
                    $short = strtoupper(implode('', array_map(fn($w) => substr($w, 0, 1), $words)));
                    $short = substr($short, 0, 10);
                    $DB->insert_record('local_studentprofile_colleges', (object)[
                        'shortname'    => $short . $i,
                        'collegename'  => $row,
                        'longname'     => '',
                        'sortorder'    => $i++,
                        'timecreated'  => time(),
                        'timemodified' => time(),
                    ]);
                }
            }
        }

        // Add new columns to local_studentprofile_data.
        $datatable = new xmldb_table('local_studentprofile_data');

        $field = new xmldb_field('firstname', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'userid');
        if (!$dbman->field_exists($datatable, $field)) {
            $dbman->add_field($datatable, $field);
        }

        $field = new xmldb_field('middlename', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'firstname');
        if (!$dbman->field_exists($datatable, $field)) {
            $dbman->add_field($datatable, $field);
        }

        $field = new xmldb_field('lastname', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'middlename');
        if (!$dbman->field_exists($datatable, $field)) {
            $dbman->add_field($datatable, $field);
        }

        $field = new xmldb_field('collegeid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'lastname');
        if (!$dbman->field_exists($datatable, $field)) {
            $dbman->add_field($datatable, $field);
        }

        $field = new xmldb_field('longname', XMLDB_TYPE_CHAR, '500', null, null, null, null, 'shortname');
        if (!$dbman->field_exists($datatable, $field)) {
            $dbman->add_field($datatable, $field);
        }

        $field = new xmldb_field('degreetype', XMLDB_TYPE_CHAR, '2', null, null, null, 'UG', 'degree');
        if (!$dbman->field_exists($datatable, $field)) {
            $dbman->add_field($datatable, $field);
        }

        $field = new xmldb_field('draft', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'degreetype');
        if (!$dbman->field_exists($datatable, $field)) {
            $dbman->add_field($datatable, $field);
        }

        $field = new xmldb_field('draftdata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'draft');
        if (!$dbman->field_exists($datatable, $field)) {
            $dbman->add_field($datatable, $field);
        }

        // Add indexes for new columns.
        $index = new xmldb_index('degreetype', XMLDB_INDEX_NOTUNIQUE, ['degreetype']);
        if (!$dbman->index_exists($datatable, $index)) {
            $dbman->add_index($datatable, $index);
        }
        $index = new xmldb_index('draft', XMLDB_INDEX_NOTUNIQUE, ['draft']);
        if (!$dbman->index_exists($datatable, $index)) {
            $dbman->add_index($datatable, $index);
        }

        upgrade_plugin_savepoint(true, 2026060601, 'local', 'studentprofile');
    }

    if ($oldversion < 2026060602) {
        // Seed default Gujarat Homoeopathic Medical Council colleges.
        $now = time();
        $defaultcolleges = [
            ['shortname' => 'GHMC_SAVLI',          'collegename' => 'Gujarat Homoeopathic Medical College',                       'longname' => 'Gujarat Homoeopathic Medical College, Savli, Vadodara',                               'sortorder' => 1],
            ['shortname' => 'VHDMC_ANAND',         'collegename' => 'Dr. V.H. Dave Homoeopathic Medical College',                 'longname' => 'Dr. V.H. Dave Homoeopathic Medical College, Anand',                                  'sortorder' => 2],
            ['shortname' => 'AHMC_ANAND',          'collegename' => 'Anand Homoeopathic Medical College & Research Institute',    'longname' => 'Anand Homoeopathic Medical College & Research Institute, Anand',                      'sortorder' => 3],
            ['shortname' => 'AJSHMC_MEHSANA',      'collegename' => 'Smt A.J. Savla Homoeopathic Medical College & R.I.',         'longname' => 'Smt A.J. Savla Homoeopathic Medical College & Research Institute, Mehsana',           'sortorder' => 4],
            ['shortname' => 'CDPHMC_SURAT',        'collegename' => 'C.D. Pachchigar Homoeopathic Medical College',               'longname' => 'C.D. Pachchigar Homoeopathic Medical College, Surat',                                'sortorder' => 5],
            ['shortname' => 'BHMC_VADODARA',       'collegename' => 'Baroda Homoeopathic Medical College',                        'longname' => 'Baroda Homoeopathic Medical College, Vadodara',                                       'sortorder' => 6],
            ['shortname' => 'AHMC_AHMEDABAD',      'collegename' => 'Ahmedabad Homoeopathic Medical College',                     'longname' => 'Ahmedabad Homoeopathic Medical College, Ghuma, Ahmedabad',                            'sortorder' => 7],
            ['shortname' => 'SSHMC_GODHRA',        'collegename' => 'Shri Shamlaji Homoeopathic Medical College',                 'longname' => 'Shri Shamlaji Homoeopathic Medical College, Godhra',                                 'sortorder' => 8],
            ['shortname' => 'BGGHMC_RAJKOT',       'collegename' => 'Shri B.G. Garaiya Homoeopathic Medical College',             'longname' => 'Shri B.G. Garaiya Homoeopathic Medical College, Rajkot',                            'sortorder' => 9],
            ['shortname' => 'VNVHMC_AMRELI',       'collegename' => 'Smt. Vasantben N. Vyas Homoeopathic Medical College',        'longname' => 'Smt. Vasantben N. Vyas Homoeopathic Medical College, Amreli',                       'sortorder' => 10],
            ['shortname' => 'RHMC_RAJKOT',         'collegename' => 'Rajkot Homoeopathic Medical College',                        'longname' => 'Rajkot Homoeopathic Medical College, Rajkot',                                        'sortorder' => 11],
            ['shortname' => 'SVHMC_BHAVNAGAR',     'collegename' => 'Swami Vivekanand Homoeopathic Medical College and Hospital', 'longname' => 'Swami Vivekanand Homoeopathic Medical College and Hospital, Bhavnagar',              'sortorder' => 12],
            ['shortname' => 'SMMHMC_VADODARA',     'collegename' => 'Shri Mahalaxmiji Mahila Homoeopathic Medical College',       'longname' => 'Shri Mahalaxmiji Mahila Homoeopathic Medical College, Vadodara',                    'sortorder' => 13],
            ['shortname' => 'BADHMC_RAJKOT',       'collegename' => 'Shri B.A. Dangar Homoeopathic Medical College',              'longname' => 'Shri B.A. Dangar Homoeopathic Medical College, Rajkot',                             'sortorder' => 14],
            ['shortname' => 'PHMC_VADODARA',       'collegename' => 'Pioneer Homoeopathic Medical College',                       'longname' => 'Pioneer Homoeopathic Medical College, Vadodara',                                     'sortorder' => 15],
            ['shortname' => 'JNHMC_VADODARA',      'collegename' => 'Jawaharlal Nehru Homoeopathic Medical College',              'longname' => 'Jawaharlal Nehru Homoeopathic Medical College, Limda, Vadodara',                    'sortorder' => 16],
            ['shortname' => 'CNKHMC_VYARA',        'collegename' => 'Shri C.N. Kothari Homoeopathic Medical College',             'longname' => 'Shri C.N. Kothari Homoeopathic Medical College, Vyara',                            'sortorder' => 17],
            ['shortname' => 'MSHMC_KARJAN',        'collegename' => 'Smt. Malinikishor Sanghavi Homoeopathic Medical College',    'longname' => 'Smt. Malinikishor Sanghavi Homoeopathic Medical College, Karjan',                   'sortorder' => 18],
            ['shortname' => 'HNSHMC_RAJKOT',       'collegename' => 'Shree H.N. Shukla Homoeopathic College & Hospital',          'longname' => 'Shree H.N. Shukla Homoeopathic College & Hospital, Rajkot',                        'sortorder' => 19],
            ['shortname' => 'JJHMC_SHAHERA',       'collegename' => 'Jay Jalaram Homoeopathic Medical College',                   'longname' => 'Jay Jalaram Homoeopathic Medical College, Shahera',                                  'sortorder' => 20],
            ['shortname' => 'PIHR_VADODARA',       'collegename' => 'Parul Institute of Homoeopathy & Research',                  'longname' => 'Parul Institute of Homoeopathy & Research, Vadodara',                               'sortorder' => 21],
            ['shortname' => 'SSAHMC_NAVSARI',      'collegename' => 'S.S. Agrawal Homoeopathic Medical College',                  'longname' => 'S.S. Agrawal Homoeopathic Medical College, Navsari',                                'sortorder' => 22],
            ['shortname' => 'KHMRC_RAJKOT',        'collegename' => 'Kamdar Homoeopathic Medical & Research Centre',              'longname' => 'Kamdar Homoeopathic Medical & Research Centre, Rajkot',                             'sortorder' => 23],
            ['shortname' => 'AVHMC_RAJKOT',        'collegename' => 'Aarya-Veer Homoeopathy Medical College',                     'longname' => 'Aarya-Veer Homoeopathy Medical College, Rajkot',                                    'sortorder' => 24],
            ['shortname' => 'AHMCRI_GANDHINAGAR',  'collegename' => 'Arihant Homoeopathic Medical College & Research Institute',  'longname' => 'Arihant Homoeopathic Medical College & Research Institute, Gandhinagar',            'sortorder' => 25],
            ['shortname' => 'GHMC_GANDHINAGAR',    'collegename' => 'Gandhinagar Homoeopathy Medical College',                    'longname' => 'Gandhinagar Homoeopathy Medical College, Gandhinagar',                               'sortorder' => 26],
            ['shortname' => 'NHCRI_JUNAGADH',      'collegename' => 'Noble Homoeopathic College & Research Institute',            'longname' => 'Noble Homoeopathic College & Research Institute, Junagadh',                        'sortorder' => 27],
            ['shortname' => 'VHMCRC_SURAT',        'collegename' => 'Vidhyadeep Homoeopathic Medical College & Research Centre',  'longname' => 'Vidhyadeep Homoeopathic Medical College & Research Centre, Surat',                 'sortorder' => 28],
            ['shortname' => 'SSHMC_KALOL',         'collegename' => 'Shree Swaminarayan Homoeopathic Medical College',            'longname' => 'Shree Swaminarayan Homoeopathic Medical College, Kalol',                           'sortorder' => 29],
            ['shortname' => 'BHMC_BORSAD',         'collegename' => 'Bhargava Homoeopathic Medical College',                      'longname' => 'Bhargava Homoeopathic Medical College, Borsad',                                      'sortorder' => 30],
            ['shortname' => 'ACH_KALOL',           'collegename' => 'Ananya College of Homoeopathy',                              'longname' => 'Ananya College of Homoeopathy, Kalol',                                               'sortorder' => 31],
            ['shortname' => 'LRSHC_RAJKOT',        'collegename' => 'L.R. Shah Homoeopathy College',                             'longname' => 'L.R. Shah Homoeopathy College, Rajkot',                                             'sortorder' => 32],
            ['shortname' => 'LHIRC_MEHSANA',       'collegename' => 'Laxmiben Homoeopathy Institute & Research Centre',           'longname' => 'Laxmiben Homoeopathy Institute & Research Centre, Mehsana',                        'sortorder' => 33],
            ['shortname' => 'MHMC_MEHSANA',        'collegename' => 'Merchant Homoeopathic Medical College',                      'longname' => 'Merchant Homoeopathic Medical College, Mehsana',                                    'sortorder' => 34],
            ['shortname' => 'GHMC_DETHALI',        'collegename' => 'Government Homoeopathic Medical College',                    'longname' => 'Government Homoeopathic Medical College, Dethali, Mehsana',                         'sortorder' => 35],
            ['shortname' => 'LHMCH_SURENDRANAGAR', 'collegename' => 'Limbdi Homoeopathic Medical College & Hospital',             'longname' => 'Limbdi Homoeopathic Medical College & Hospital, Surendranagar',                    'sortorder' => 36],
            ['shortname' => 'PPSHMC_SURAT',        'collegename' => 'P.P. Savani Homoeopathy Medical College & Hospital',         'longname' => 'P.P. Savani Homoeopathy Medical College & Hospital, Surat',                       'sortorder' => 37],
            ['shortname' => 'AHMCH_SABARKANTHA',   'collegename' => 'Arrdeta Homoeopathic Medical College & Hospital',            'longname' => 'Arrdeta Homoeopathic Medical College & Hospital, Sabarkantha',                     'sortorder' => 38],
            ['shortname' => 'SAHMC_MORBI',         'collegename' => 'Shri Aryatej Homoeopathic Medical College',                  'longname' => 'Shri Aryatej Homoeopathic Medical College, Morbi',                                  'sortorder' => 39],
            ['shortname' => 'GFGHMC_PATAN',        'collegename' => 'Gokul Foundation Gokul Homoeopathy Medical College',         'longname' => 'Gokul Foundation Gokul Homoeopathy Medical College, Patan',                       'sortorder' => 40],
            ['shortname' => 'VHMCH_VADODARA',      'collegename' => 'Valan Homoeopathic Medical College and Hospital',            'longname' => 'Valan Homoeopathic Medical College and Hospital, Vadodara',                        'sortorder' => 41],
            ['shortname' => 'SSPMHMC_RAJKOT',      'collegename' => 'Shree Sardar Patel Mahila Homoeopathic Medical College',     'longname' => 'Shree Sardar Patel Mahila Homoeopathic Medical College, Rajkot',                  'sortorder' => 42],
            ['shortname' => 'NHMC_VISNAGAR',       'collegename' => 'Nootan Homoeopathic Medical College',                        'longname' => 'Nootan Homoeopathic Medical College, Visnagar',                                     'sortorder' => 43],
            ['shortname' => 'MHMCH_AHMEDABAD',     'collegename' => 'Monark Homoeopathic Medical College and Hospital',           'longname' => 'Monark Homoeopathic Medical College and Hospital, Ahmedabad',                      'sortorder' => 44],
            ['shortname' => 'SSHC_JAMNAGAR',       'collegename' => 'Shree Swaminarayan Homoeopathic College',                    'longname' => 'Shree Swaminarayan Homoeopathic College, Jamnagar',                                'sortorder' => 45],
            ['shortname' => 'SHMCH_VADODARA',      'collegename' => 'Sumandeep Homoeopathic Medical College and Hospital',        'longname' => 'Sumandeep Homoeopathic Medical College and Hospital, Vadodara',                    'sortorder' => 46],
        ];

        foreach ($defaultcolleges as $college) {
            if (!$DB->record_exists('local_studentprofile_colleges', ['shortname' => $college['shortname']])) {
                $DB->insert_record('local_studentprofile_colleges', (object)[
                    'shortname'    => $college['shortname'],
                    'collegename'  => $college['collegename'],
                    'longname'     => $college['longname'],
                    'sortorder'    => $college['sortorder'],
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        upgrade_plugin_savepoint(true, 2026060602, 'local', 'studentprofile');
    }

    if ($oldversion < 2026060603) {
        // Seed default degree list for all medical / homoeopathic programmes.
        $now = time();
        $defaultdegrees = [
            // ---- UG Medical ----
            ['degreename' => 'MBBS',                                    'degreetype' => 'UG', 'sortorder' => 1],
            ['degreename' => 'BDS',                                     'degreetype' => 'UG', 'sortorder' => 2],
            ['degreename' => 'BSc Nursing',                             'degreetype' => 'UG', 'sortorder' => 3],
            ['degreename' => 'BPT',                                     'degreetype' => 'UG', 'sortorder' => 4],
            ['degreename' => 'BOT',                                     'degreetype' => 'UG', 'sortorder' => 5],
            ['degreename' => 'BASLP',                                   'degreetype' => 'UG', 'sortorder' => 6],
            ['degreename' => 'BSc Medical Laboratory Technology',       'degreetype' => 'UG', 'sortorder' => 7],
            ['degreename' => 'BSc Radiology',                           'degreetype' => 'UG', 'sortorder' => 8],
            ['degreename' => 'BSc Operation Theatre Technology',        'degreetype' => 'UG', 'sortorder' => 9],
            ['degreename' => 'BSc Optometry',                           'degreetype' => 'UG', 'sortorder' => 10],
            ['degreename' => 'BSc Emergency Medicine',                  'degreetype' => 'UG', 'sortorder' => 11],
            // ---- PG Medical (broad) ----
            ['degreename' => 'MD',                                      'degreetype' => 'PG', 'sortorder' => 12],
            ['degreename' => 'MS',                                      'degreetype' => 'PG', 'sortorder' => 13],
            ['degreename' => 'DNB',                                     'degreetype' => 'PG', 'sortorder' => 14],
            ['degreename' => 'MSc Nursing',                             'degreetype' => 'PG', 'sortorder' => 15],
            ['degreename' => 'MPT',                                     'degreetype' => 'PG', 'sortorder' => 16],
            ['degreename' => 'MHA',                                     'degreetype' => 'PG', 'sortorder' => 17],
            ['degreename' => 'MPH',                                     'degreetype' => 'PG', 'sortorder' => 18],
            // ---- PG MD specialisations ----
            ['degreename' => 'MD General Medicine',                     'degreetype' => 'PG', 'sortorder' => 19],
            ['degreename' => 'MD Pediatrics',                           'degreetype' => 'PG', 'sortorder' => 20],
            ['degreename' => 'MD Dermatology',                          'degreetype' => 'PG', 'sortorder' => 21],
            ['degreename' => 'MD Psychiatry',                           'degreetype' => 'PG', 'sortorder' => 22],
            ['degreename' => 'MD Radiology',                            'degreetype' => 'PG', 'sortorder' => 23],
            ['degreename' => 'MD Anesthesiology',                       'degreetype' => 'PG', 'sortorder' => 24],
            ['degreename' => 'MD Pathology',                            'degreetype' => 'PG', 'sortorder' => 25],
            ['degreename' => 'MD Pharmacology',                         'degreetype' => 'PG', 'sortorder' => 26],
            ['degreename' => 'MD Microbiology',                         'degreetype' => 'PG', 'sortorder' => 27],
            ['degreename' => 'MD Community Medicine',                   'degreetype' => 'PG', 'sortorder' => 28],
            ['degreename' => 'MD Forensic Medicine',                    'degreetype' => 'PG', 'sortorder' => 29],
            ['degreename' => 'MD Emergency Medicine',                   'degreetype' => 'PG', 'sortorder' => 30],
            ['degreename' => 'MD Respiratory Medicine',                 'degreetype' => 'PG', 'sortorder' => 31],
            // ---- PG MS specialisations ----
            ['degreename' => 'MS General Surgery',                      'degreetype' => 'PG', 'sortorder' => 32],
            ['degreename' => 'MS Orthopedics',                          'degreetype' => 'PG', 'sortorder' => 33],
            ['degreename' => 'MS ENT',                                  'degreetype' => 'PG', 'sortorder' => 34],
            ['degreename' => 'MS Ophthalmology',                        'degreetype' => 'PG', 'sortorder' => 35],
            ['degreename' => 'MS Obstetrics and Gynecology',            'degreetype' => 'PG', 'sortorder' => 36],
            // ---- Super-speciality DM / MCh ----
            ['degreename' => 'DM Cardiology',                           'degreetype' => 'PG', 'sortorder' => 37],
            ['degreename' => 'DM Neurology',                            'degreetype' => 'PG', 'sortorder' => 38],
            ['degreename' => 'DM Nephrology',                           'degreetype' => 'PG', 'sortorder' => 39],
            ['degreename' => 'DM Gastroenterology',                     'degreetype' => 'PG', 'sortorder' => 40],
            ['degreename' => 'DM Endocrinology',                        'degreetype' => 'PG', 'sortorder' => 41],
            ['degreename' => 'DM Medical Oncology',                     'degreetype' => 'PG', 'sortorder' => 42],
            ['degreename' => 'DM Critical Care Medicine',               'degreetype' => 'PG', 'sortorder' => 43],
            ['degreename' => 'MCh Neurosurgery',                        'degreetype' => 'PG', 'sortorder' => 44],
            ['degreename' => 'MCh Urology',                             'degreetype' => 'PG', 'sortorder' => 45],
            ['degreename' => 'MCh Plastic Surgery',                     'degreetype' => 'PG', 'sortorder' => 46],
            ['degreename' => 'MCh Surgical Oncology',                   'degreetype' => 'PG', 'sortorder' => 47],
            ['degreename' => 'MCh Pediatric Surgery',                   'degreetype' => 'PG', 'sortorder' => 48],
            ['degreename' => 'MCh Cardiothoracic Surgery',              'degreetype' => 'PG', 'sortorder' => 49],
            // ---- UG Homoeopathy ----
            ['degreename' => 'BHMS',                                    'degreetype' => 'UG', 'sortorder' => 50],
            // ---- PG Homoeopathy MD (Hom) ----
            ['degreename' => 'MD (Hom) Organon of Medicine',            'degreetype' => 'PG', 'sortorder' => 51],
            ['degreename' => 'MD (Hom) Materia Medica',                 'degreetype' => 'PG', 'sortorder' => 52],
            ['degreename' => 'MD (Hom) Repertory',                      'degreetype' => 'PG', 'sortorder' => 53],
            ['degreename' => 'MD (Hom) Practice of Medicine',           'degreetype' => 'PG', 'sortorder' => 54],
            ['degreename' => 'MD (Hom) Pharmacy',                       'degreetype' => 'PG', 'sortorder' => 55],
            ['degreename' => 'MD (Hom) Pediatrics',                     'degreetype' => 'PG', 'sortorder' => 56],
            ['degreename' => 'MD (Hom) Psychiatry',                     'degreetype' => 'PG', 'sortorder' => 57],
            // ---- Doctoral Homoeopathy ----
            ['degreename' => 'PhD in Homeopathy',                       'degreetype' => 'PG', 'sortorder' => 58],
        ];

        foreach ($defaultdegrees as $degree) {
            if (!$DB->record_exists('local_studentprofile_degrees', ['degreename' => $degree['degreename']])) {
                $DB->insert_record('local_studentprofile_degrees', (object)[
                    'degreename'   => $degree['degreename'],
                    'degreetype'   => $degree['degreetype'],
                    'sortorder'    => $degree['sortorder'],
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        upgrade_plugin_savepoint(true, 2026060603, 'local', 'studentprofile');
    }

    if ($oldversion < 2026060604) {
        // Add rollno and rollnotype to local_studentprofile_data.
        $datatable = new xmldb_table('local_studentprofile_data');

        $field = new xmldb_field('rollno', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'draftdata');
        if (!$dbman->field_exists($datatable, $field)) {
            $dbman->add_field($datatable, $field);
        }

        $field = new xmldb_field('rollnotype', XMLDB_TYPE_CHAR, '20', null, null, null, 'permanent', 'rollno');
        if (!$dbman->field_exists($datatable, $field)) {
            $dbman->add_field($datatable, $field);
        }

        upgrade_plugin_savepoint(true, 2026060604, 'local', 'studentprofile');
    }

    if ($oldversion < 2026062700) {
        $table = new xmldb_table('local_studentprofile_rules');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('rulename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('priority', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('stopprocessing', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('conditions', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('courses', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('status_priority', XMLDB_INDEX_NOTUNIQUE, ['status', 'priority']);
            
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026062700, 'local', 'studentprofile');
    }

    if ($oldversion < 2026070500) {
        $table = new xmldb_table('local_studentprofile_data');
        $field = new xmldb_field('rollnostatus', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'rollnotype');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026070500, 'local', 'studentprofile');
    }

    if ($oldversion < 2026071800) {
        $table = new xmldb_table('local_studentprofile_data');
        $field = new xmldb_field('mobileno', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'rollno');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026071800, 'local', 'studentprofile');
    }

    if ($oldversion < 2026072500) {
        $table = new xmldb_table('local_studentprofile_colleges');
        $field = new xmldb_field('degrees', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'longname');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026072500, 'local', 'studentprofile');
    }

    return true;
}
