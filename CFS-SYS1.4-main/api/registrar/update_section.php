<?php
/**
 * POST /api/registrar/update_section.php   (JSON)
 * Body: { "application_id": 12, "section": "St. Joseph" }
 *       send "section": "" (or null) to set the student back to Unassigned.
 *
 * Registrar/admin only. Saves the section on the student's APPROVED
 * application (so it belongs to that student in that school year). It only
 * updates one existing row: no student or application is ever created.
 * The registrar types the school's real section names; nothing is invented.
 */

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../config/helpers.php';
require __DIR__ . '/../../config/auth.php';
require __DIR__ . '/../../config/registrar.php';

requireMethod('POST');
$staff = requireStaff();
$input = readJsonBody();

$applicationId = requireIntField($input, 'application_id');

$raw = $input['section'] ?? null;
if ($raw !== null && !is_string($raw)) {
    jsonError(400, 'section must be text.');
}
$section = $raw === null ? '' : preg_replace('/\s+/u', ' ', trim($raw));
if ($section === '') {
    $section = null;                                   // Unassigned
} else {
    if (textLength($section) > 50) {
        jsonError(400, 'section must be at most 50 characters.');
    }
    if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} .\'\-]*$/u', $section)) {
        jsonError(400, "section may only contain letters, numbers, spaces and . ' -");
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT id, application_no, school_year_id, status, section_name
         FROM enrollment_applications WHERE id = :id FOR UPDATE'
    );
    $stmt->execute(['id' => $applicationId]);
    $app = $stmt->fetch();
    if (!$app) {
        abortWith($pdo, 404, 'Application not found.');
    }
    if ($app['status'] !== 'approved') {
        abortWith($pdo, 409, 'Sections can only be assigned to approved students. This application is ' . $app['status'] . '.');
    }

    // If the same section already exists this school year (the database
    // ignores upper/lower case), reuse its spelling so "faith" and "Faith"
    // never become two different sections.
    if ($section !== null) {
        $stmt = $pdo->prepare(
            'SELECT section_name FROM enrollment_applications
             WHERE school_year_id = :sy AND section_name = :s LIMIT 1'
        );
        $stmt->execute(['sy' => $app['school_year_id'], 's' => $section]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            $section = $existing;
        }
    }

    if ($section !== $app['section_name']) {
        $stmt = $pdo->prepare('UPDATE enrollment_applications SET section_name = :s WHERE id = :id');
        $stmt->execute(['s' => $section, 'id' => $applicationId]);

        writeAudit($pdo, $staff['id'], 'section.assigned', 'application', $applicationId, [
            'application_no' => $app['application_no'],
            'previous'       => $app['section_name'],
            'section'        => $section,
        ]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('update_section failed: ' . $e->getMessage());
    // Most likely cause on a fresh copy: the migration has not been run yet.
    jsonError(500, 'Could not save the section. Has database/migrations/001_add_section_to_applications.sql been run?');
}

jsonResponse(200, ['application' => [
    'id' => $applicationId, 'application_no' => $app['application_no'], 'section_name' => $section,
]]);
