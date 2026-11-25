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
    flash('error', 'Please enter all login details.');
    redirect('auth/login.php');
}

// Validate mobile number
if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
    flash('error', 'Enter a valid 10-digit mobile number.');
    redirect('auth/login.php');
}

try {
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

    // Verify credentials
    if (
        !$user ||
        !password_verify($password, $user['password_hash'])
    ) {
        flash('error', 'Invalid username, mobile number or password.');
        redirect('auth/login.php');
    }

    // Check account status
    if ($user['status'] !== 'active') {
        flash('error', 'Your account is inactive. Please contact the administrator.');
        redirect('auth/login.php');
    }

    // Validate user role
    if (!in_array($user['role'], ['admin', 'farmer'], true)) {
        flash('error', 'Invalid account type. Please contact the administrator.');
        redirect('auth/login.php');
    }

    // Regenerate session ID
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
    unset($_SESSION['csrf_token']);

    // Redirect to dashboard
    redirect('dashboard.php');

} catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());

    flash('error', 'Unable to log in right now. Please try again.');
    redirect('auth/login.php');
}
