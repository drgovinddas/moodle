<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

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

global $DB;

$inserted = 0;
foreach ($defaultdegrees as $degree) {
    if (!$DB->record_exists('local_studentprofile_degrees', ['degreename' => $degree['degreename']])) {
        $DB->insert_record('local_studentprofile_degrees', (object)[
            'degreename'   => $degree['degreename'],
            'degreetype'   => $degree['degreetype'],
            'sortorder'    => $degree['sortorder'],
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $inserted++;
    }
}
echo "Degrees inserted: $inserted\n";
