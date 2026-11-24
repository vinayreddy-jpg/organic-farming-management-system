<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Allow POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('auth/login.php');
}

// Verify CSRF token
require_csrf();

// Get login details
$username = trim($_POST['username'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$password = $_POST['password'] ?? '';

// Validate empty fields
if ($username === '' || $mobile === '' || $password === '') {
    fail('Please enter all login details.');
}

// Validate mobile number
if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
    fail('Enter a valid 10-digit mobile number.');
}

try {

    // Connect to database
    $pdo = db();

    // Find user
    $stmt = $pdo->prepare(
        'SELECT
            id,
            name,
            role,
            username,
            mobile,
            password_hash,
            status
         FROM users
         WHERE username = ? AND mobile = ?
         LIMIT 1'
    );

    $stmt->execute([$username, $mobile]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify username, mobile and password
    if (
        !$user ||
        !password_verify($password, $user['password_hash'])
    ) {
        fail('Invalid username, mobile number or password.');
    }

    // Check account status
    if ($user['status'] !== 'active') {
        fail('Your account is inactive. Please contact the administrator.');
    }

    // Validate user role
    if (!in_array($user['role'], ['admin', 'farmer'], true)) {
        fail('Invalid account type. Please contact the administrator.');
    }

    // Regenerate session ID for security
    session_regenerate_id(true);

    // Store logged-in user details
    $_SESSION['user'] = [
        'id'       => (int) $user['id'],
        'name'     => $user['name'],
        'role'     => $user['role'],
        'username' => $user['username'],
        'mobile'   => $user['mobile']
    ];

    // Clear old CSRF token
    unset($_SESSION['csrf']);

    // Redirect to dashboard
    redirect('dashboard.php');

} catch (PDOException $e) {

    // Log database error
    error_log('Login error: ' . $e->getMessage());

    fail('Unable to log in right now. Please try again.');
}