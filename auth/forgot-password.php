<?php
require_once __DIR__ . '/bootstrap.php';

$error = '';
$verified = false;

// Clear previous verification when opening the page.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    unset(
        $_SESSION['password_reset_user_id'],
        $_SESSION['password_reset_time']
    );
}

// Verify username and mobile number.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim($_POST['username'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');

    if (
        $username === '' ||
        !preg_match('/^[6-9][0-9]{9}$/', $mobile)
    ) {
        $error = 'Please enter a valid username and mobile number.';
    } else {
        try {
            $stmt = db()->prepare(
                "SELECT id FROM users
                 WHERE username = ?
                 AND mobile = ?
                 AND role = 'farmer'
                 AND status = 'active'
                 LIMIT 1"
            );

            $stmt->execute([$username, $mobile]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                session_regenerate_id(true);

                $_SESSION['password_reset_user_id'] = (int)$user['id'];
                $_SESSION['password_reset_time'] = time();

                $verified = true;
            } else {
                $error = 'Username and mobile number do not match.';
            }

        } catch (PDOException $e) {
            error_log('Forgot password error: ' . $e->getMessage());
            $error = 'Unable to verify your details. Please try again.';
        }
    }
}

// Keep verified state for the current reset session.
if (
    !$verified &&
    !empty($_SESSION['password_reset_user_id']) &&
    !empty($_SESSION['password_reset_time']) &&
    time() - $_SESSION['password_reset_time'] <= 600
) {
    $verified = true;
}

// Expire old verification.
if (
    !empty($_SESSION['password_reset_time']) &&
    time() - $_SESSION['password_reset_time'] > 600
) {
    unset(
        $_SESSION['password_reset_user_id'],
        $_SESSION['password_reset_time']
    );
    $verified = false;
}

$flashes = pull_flashes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Forgot Password | Organic Farming</title>

    <link rel="stylesheet" href="../assests/auth.css?v=6">
</head>

<body>

<main class="registration-page">

    <header class="welcome-section">
        <h1>🔐 Forgot Password</h1>
        <p class="farm-quote">🌱 Grow Naturally. Live Sustainably.</p>
        <div class="welcome-line"></div>
    </header>

    <section class="registration-card">

        <?php foreach ($flashes as [$type, $message]): ?>
            <p class="<?= e($type) ?>"><?= e($message) ?></p>
        <?php endforeach; ?>

        <?php if ($error): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($verified): ?>

            <p class="form-description">
                Account verified! Create your new password.
            </p>

            <form action="reset-password-submit.php" method="POST">

                <input type="hidden" name="csrf"
                       value="<?= e(csrf()) ?>">

                <div class="form-grid">

                    <div class="field">
                        <label>New Password</label>
                        <input type="password"
                               name="password"
                               placeholder="Enter your new password"
                               minlength="8"
                               autocomplete="new-password"
                               required>
                    </div>

                    <div class="field">
                        <label>Confirm Password</label>
                        <input type="password"
                               name="confirm_password"
                               placeholder="Confirm your new password"
                               minlength="8"
                               autocomplete="new-password"
                               required>
                    </div>

                </div>

                <button type="submit" class="register-button">
                    Update Password <span>→</span>
                </button>

            </form>

        <?php else: ?>

            <p class="form-description">
                Enter your registered username and mobile number.
            </p>

            <form action="forgot-password.php" method="POST">

                <input type="hidden" name="csrf"
                       value="<?= e(csrf()) ?>">

                <div class="form-grid">

                    <div class="field">
                        <label>Username</label>
                        <input type="text"
                               name="username"
                               placeholder="Enter your username"
                               autocomplete="username"
                               required>
                    </div>

                    <div class="field">
                        <label>Mobile Number</label>
                        <input type="tel"
                               name="mobile"
                               placeholder="Enter your 10-digit mobile number"
                               pattern="[6-9][0-9]{9}"
                               maxlength="10"
                               inputmode="numeric"
                               autocomplete="tel"
                               required>
                    </div>

                </div>

                <button type="submit" class="register-button">
                    Verify Account <span>→</span>
                </button>

            </form>

        <?php endif; ?>

        <div class="auth-links">
            <a href="login.php" class="login-link">
                ← Back to Login
            </a>
        </div>

        <footer class="panel-footer">
            🌱 Empowering Farmers Through Technology
        </footer>

    </section>

</main>

</body>
</html>
