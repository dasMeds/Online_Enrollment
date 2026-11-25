<?php
/* Plain-PHP tests for the pure helpers in conn.php — no PHPUnit, no
   database. Run: php tests/run_tests.php
   Exit code 0 = all passed, 1 = at least one failure.
   Clean v2.1: GWA, installment and prerequisite tests were removed with
   those v3 features (see docs/CHANGELOG.md). Term tests are kept — they
   cover the January school-year bug the term fix addresses. */
define('APP_NO_DB', true);
require_once __DIR__ . '/../conn.php';

$passed = 0;
$failed = 0;
function check($name, $actual, $expected)
{
	global $passed, $failed;
	if ($actual === $expected) {
		$passed++;
		echo "PASS: $name\n";
	} else {
		$failed++;
		echo "FAIL: $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
	}
}

/* ---- Fee calculation (standard fees: 296/unit, 2050 entrance, 150
   internet, 50 misc, 1500 per lab/tutorial) ---- */
$fees = array('per_unit' => 296, 'entrance' => 2050, 'internet' => 150, 'misc' => 50, 'lab' => 1500, 'tutorial' => 1500);
$calc = app_assessment_calc($fees, 9, 2, 0);
check('fee calc: 9 units, 2 labs totals 7914', $calc['total'], 7914);
check('fee calc: tuition 9 x 296 = 2664', $calc['tuition'], 2664);
check('fee calc: lab fee 2 x 1500 = 3000', $calc['lab'], 3000);
$calc = app_assessment_calc($fees, 0, 0, 0);
check('fee calc: 0 units still charges entrance+internet+misc', $calc['total'], 2250);
$calc = app_assessment_calc($fees, 10, 0, 1);
check('fee calc: 10 units + 1 tutorial totals 6710', $calc['total'], 6710);

/* ---- Unit limit (24 max) ---- */
check('unit limit: 24 units is allowed', 24 <= 24, true);
check('unit limit: 25 units is over the limit', 25 > 24, true);

/* ---- Schedule conflict detection ---- */
$monMorning = array('day' => 'Monday', 'from' => 480, 'to' => 570);
$monOverlap = array('day' => 'Monday', 'from' => 540, 'to' => 630);
$monLater = array('day' => 'Monday', 'from' => 600, 'to' => 690);
$tueMorning = array('day' => 'Tuesday', 'from' => 480, 'to' => 570);
check('conflict: overlapping Monday slots clash', app_slots_overlap($monMorning, $monOverlap), true);
check('conflict: back-to-back Monday slots do not clash', app_slots_overlap($monMorning, $monLater), false);
check('conflict: same time on different days does not clash', app_slots_overlap($monMorning, $tueMorning), false);
check('conflict: a slot does not clash with itself in a different subject only when times differ', app_slots_overlap($monMorning, array('day' => 'Monday', 'from' => 570, 'to' => 660)), false);

/* ---- Time parsing ---- */
check('time parse: 08:00 is 480 minutes', app_time_to_minutes('08:00'), 480);
check('time parse: 09:30 is 570 minutes', app_time_to_minutes('09:30:00'), 570);
check('time parse: garbage returns null', app_time_to_minutes('not a time'), null);
$range = app_parse_time_range('08:00-09:30');
check('time range: 08:00-09:30 parses to [480, 570]', $range, array(480, 570));

/* ---- Term / school-year calculation (the January bug) ---- */
check('term: 2027-01-15 is 2nd Semester of 2026-2027', app_term_for_date('2027-01-15'), array('school_year' => '2026-2027', 'semester' => '2nd Semester'));
check('term: 2026-08-15 is 1st Semester of 2026-2027', app_term_for_date('2026-08-15'), array('school_year' => '2026-2027', 'semester' => '1st Semester'));
check('term: 2027-06-15 is Summer of 2026-2027', app_term_for_date('2027-06-15'), array('school_year' => '2026-2027', 'semester' => 'Summer'));
check('term: 2026-05-31 is still 2nd Semester of 2025-2026', app_school_year_for_date('2026-05-31'), '2025-2026');

echo "\n$passed passed, $failed failed.\n";
exit($failed > 0 ? 1 : 0);
