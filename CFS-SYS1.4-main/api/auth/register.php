<?php
/** Guardian self-registration. */
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../config/helpers.php';
require __DIR__ . '/../../config/auth.php';

requireMethod('POST');
$input = readJsonBody();
$missing = missingFields($input, ['email', 'password', 'confirm_password', 'full_name']);
if (!empty($missing)) {
    jsonError(400, 'Missing required fields: ' . implode(', ', $missing));
}
$email = trim(strtolower($input['email']));
$password = $input['password'];
$confirmPassword = $input['confirm_password'];
$fullName = trim($input['full_name']);
$mobile = isset($input['mobile']) ? trim($input['mobile']) : null;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError(400, 'Invalid email address.');
}
if (strlen($password) < 8) {
    jsonError(400, 'Password must be at least 8 characters.');
}
if (!preg_match('/[A-Z]/', $password)) {
    jsonError(400, 'Password must contain at least one uppercase letter.');
}
if (!preg_match('/[a-z]/', $password)) {
    jsonError(400, 'Password must contain at least one lowercase letter.');
}
if (!preg_match('/[0-9]/', $password)) {
    jsonError(400, 'Password must contain at least one number.');
}
if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
    jsonError(400, 'Password must contain at least one special character.');
}
if ($password !== $confirmPassword) {
    jsonError(400, 'Passwords do not match.');
}
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    jsonError(409, 'An account with this email already exists.');
}
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare(
    'INSERT INTO users (email, password_hash, full_name, mobile, role)
     VALUES (:email, :password_hash, :full_name, :mobile, "guardian")'
);
$stmt->execute([
    'email' => $email,
    'password_hash' => $passwordHash,
    'full_name' => $fullName,
    'mobile' => $mobile,
]);
$userId = (int) $pdo->lastInsertId();
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['role'] = 'guardian';
$_SESSION['email'] = $email;
jsonResponse(201, [
    'id' => $userId,
    'email' => $email,
    'full_name' => $fullName,
    'role' => 'guardian',
]);
