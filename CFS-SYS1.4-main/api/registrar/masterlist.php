<?php
/**
 * GET /api/registrar/masterlist.php
 * Registrar/admin only. The Student Masterlist = every APPROVED application
 * of a school year. Query params (all optional):
 *   school_year_id  defaults to the current school year
 *   grade           1-6
 *   section         a section name, or "__unassigned__" for students without one
 *   category        fully_paid | pending     (omit = both)
 *   search          student name, LRN, application no., grade or section
 *   page            default 1
 *   per_page        default 50, max 100
 *
 * Payment category is NEVER stored. It is worked out on every request from
 * the same data the billing module uses (fee_items + VERIFIED payments, via
 * v_application_balance):
 *   fully_paid = an invoice exists (fee_items > 0) AND outstanding <= 0
 *   pending    = everything else, including "approved but no invoice yet"
 * (no invoice is NOT "paid": the balance is only zero because nothing has
 * been assessed yet). Approved students stay on the list either way.
 */

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../config/helpers.php';
require __DIR__ . '/../../config/auth.php';
require __DIR__ . '/../../config/registrar.php';

requireMethod('GET');
requireStaff();

const UNASSIGNED_FILTER = '__unassigned__';

// ---- school year (default: current) ----
$syRaw = $_GET['school_year_id'] ?? '';
if ($syRaw !== '') {
    $syId = filter_var($syRaw, FILTER_VALIDATE_INT);
    if ($syId === false || $syId < 1) {
        jsonError(400, 'school_year_id must be a positive integer.');
    }
    $stmt = $pdo->prepare('SELECT id, label, is_current FROM school_years WHERE id = :id');
    $stmt->execute(['id' => $syId]);
} else {
    $stmt = $pdo->query('SELECT id, label, is_current FROM school_years WHERE is_current = 1 LIMIT 1');
}
$schoolYear = $stmt->fetch();
if (!$schoolYear) {
    jsonError(404, $syRaw !== '' ? 'School year not found.' : 'No current school year is set up.');
}

$where  = ["a.status = 'approved'", 'a.school_year_id = :sy'];
$params = ['sy' => (int) $schoolYear['id']];

// ---- grade ----
$grade = $_GET['grade'] ?? '';
if ($grade !== '') {
    $g = is_string($grade) ? filter_var($grade, FILTER_VALIDATE_INT) : false;
    if ($g === false || $g < 1 || $g > 6) {
        jsonError(400, 'grade must be between 1 and 6.');
    }
    $where[] = 'a.grade_level = :grade';
    $params['grade'] = $g;
}

// ---- section ----
$section = $_GET['section'] ?? '';
if (!is_string($section)) {
    jsonError(400, 'section must be text.');
}
$section = trim($section);
if (textLength($section) > 50) {
    jsonError(400, 'section must be at most 50 characters.');
}
if ($section === UNASSIGNED_FILTER) {
    $where[] = 'a.section_name IS NULL';
} elseif ($section !== '') {
    $where[] = 'a.section_name = :section';
    $params['section'] = $section;
}

// ---- search ----
$search = $_GET['search'] ?? '';
if (!is_string($search)) {
    jsonError(400, 'search must be text.');
}
$search = trim($search);
if (textLength($search) > 100) {
    jsonError(400, 'search must be at most 100 characters.');
}
if ($search !== '') {
    // '!' is the LIKE escape character so "%" and "_" are searched literally.
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
    $where[] = "(s.first_name LIKE :q1 ESCAPE '!'
              OR s.last_name LIKE :q2 ESCAPE '!'
              OR CONCAT(s.first_name, ' ', s.last_name) LIKE :q3 ESCAPE '!'
              OR CONCAT(s.last_name, ', ', s.first_name) LIKE :q4 ESCAPE '!'
              OR s.lrn LIKE :q5 ESCAPE '!'
              OR a.application_no LIKE :q6 ESCAPE '!'
              OR CONCAT('Grade ', a.grade_level) LIKE :q7 ESCAPE '!'
              OR a.section_name LIKE :q8 ESCAPE '!')";
    foreach (range(1, 8) as $i) {
        $params["q$i"] = $like;
    }
}

// ---- payment category ----
$category = $_GET['category'] ?? '';
if (!is_string($category) || !in_array($category, ['', 'fully_paid', 'pending'], true)) {
    jsonError(400, "category must be 'fully_paid' or 'pending'.");
}

// ---- pagination (validated ints, safe to place in the SQL) ----
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$perPage = filter_var($_GET['per_page'] ?? 50, FILTER_VALIDATE_INT);
if ($page === false || $page < 1) {
    jsonError(400, 'page must be a positive integer.');
}
if ($perPage === false || $perPage < 1 || $perPage > 100) {
    jsonError(400, 'per_page must be between 1 and 100.');
}
$offset = ($page - 1) * $perPage;

// One row per application (applications are unique per student + school year,
// and the view has one row per application), so the joins cannot duplicate students.
$fromSql = 'FROM enrollment_applications a
            JOIN students s ON s.id = a.student_id
            LEFT JOIN v_application_balance b ON b.application_id = a.id';
$feeCount = '(SELECT COUNT(*) FROM fee_items f WHERE f.application_id = a.id)';
$isPaid   = "($feeCount > 0 AND COALESCE(b.outstanding, 0) <= 0)";
$whereSql = 'WHERE ' . implode(' AND ', $where);

// Tab counts follow the other filters but ignore the category itself.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_all,
            COALESCE(SUM($isPaid), 0) AS total_paid_count
     $fromSql $whereSql"
);
$stmt->execute($params);
$c = $stmt->fetch();
$counts = [
    'all'        => (int) $c['total_all'],
    'fully_paid' => (int) $c['total_paid_count'],
    'pending'    => (int) $c['total_all'] - (int) $c['total_paid_count'],
];

$catSql = '';
if ($category === 'fully_paid') {
    $catSql = " AND $isPaid";
} elseif ($category === 'pending') {
    $catSql = " AND NOT $isPaid";
}
$total = $category === '' ? $counts['all'] : $counts[$category];

$stmt = $pdo->prepare(
    "SELECT a.id AS application_id, a.application_no, a.grade_level, a.section_name,
            s.id AS student_id, s.first_name, s.last_name, s.lrn,
            $feeCount AS fee_item_count,
            COALESCE(b.total_assessed, 0) AS total_assessed,
            COALESCE(b.total_paid, 0) AS total_paid,
            COALESCE(b.outstanding, 0) AS outstanding,
            CASE WHEN $isPaid THEN 'fully_paid' ELSE 'pending' END AS payment_category
     $fromSql
     $whereSql $catSql
     ORDER BY a.grade_level, s.last_name, s.first_name, a.id
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$students = $stmt->fetchAll();
foreach ($students as &$row) {
    $row['has_invoice'] = (int) $row['fee_item_count'] > 0;
}
unset($row);

// Filter dropdown helpers.
$stmt = $pdo->prepare(
    "SELECT DISTINCT grade_level, section_name FROM enrollment_applications
     WHERE school_year_id = :sy AND status = 'approved' AND section_name IS NOT NULL
     ORDER BY grade_level, section_name"
);
$stmt->execute(['sy' => (int) $schoolYear['id']]);
$sections = $stmt->fetchAll();

$schoolYears = $pdo->query('SELECT id, label, is_current FROM school_years ORDER BY starts_on DESC')->fetchAll();

jsonResponse(200, [
    'school_year'  => $schoolYear,
    'school_years' => $schoolYears,
    'sections'     => $sections,
    'counts'       => $counts,
    'students'     => $students,
    'pagination'   => [
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => (int) ceil($total / $perPage),
    ],
]);
