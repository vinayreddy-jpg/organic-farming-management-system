<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('forgot-password.php');
}

require_csrf();

$userId = $_SESSION['password_reset_user_id'] ?? null;
$verifiedAt = $_SESSION['password_reset_time'] ?? 0;

if (
    !$userId ||
    !$verifiedAt ||
    (time() - $verifiedAt) > 600
) {
    unset($_SESSION['password_reset_user_id']);
    unset($_SESSION['password_reset_time']);

    fail('Your verification session expired. Please verify your details again.', 'forgot-password.php');
}

$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (strlen($password) < 8) {
    fail('Password must contain at least 8 characters.', 'forgot-password.php');
}

if ($password !== $confirmPassword) {
    fail('Passwords do not match.', 'forgot-password.php');
}

try {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = db()->prepare(
       "UPDATE users
 SET password_hash = ?
 WHERE id = ?
 AND role = 'farmer'
 AND status = 'active'"
    );

    $stmt->execute([$passwordHash, $userId]);

    if ($stmt->rowCount() !== 1) {
        fail('Unable to update your password. Please try again.', 'forgot-password.php');
    }

    unset($_SESSION['password_reset_user_id']);
    unset($_SESSION['password_reset_time']);

    session_regenerate_id(true);

    flash('success', 'Your password has been changed successfully. Please log in.');

    redirect('login.php');

} catch (PDOException $e) {
    error_log('Password update error: ' . $e->getMessage());
    fail('Unable to update your password. Please try again.', 'forgot-password.php');
}
