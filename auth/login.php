<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$flashes = pull_flashes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Farmer Login | Organic Farming</title>

    <link rel="stylesheet" href="../assests/auth.css?v=5">
</head>

<body>

<main class="registration-page">

    <header class="welcome-section">
        <h1>🌱 Welcome Back</h1>
        <p class="farm-quote">
            🌱 Grow Naturally. Live Sustainably.
        </p>
        <div class="welcome-line"></div>
    </header>

    <section class="registration-card">

        <?php foreach ($flashes as [$type, $message]): ?>
            <p class="<?= e($type) ?>">
                <?= e($message) ?>
            </p>
        <?php endforeach; ?>

        <form action="login-submit.php" method="POST">

            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">

            <div class="form-grid">

                <div class="field">
                    <label>Username</label>
                    <input
                        type="text"
                        name="username"
                        placeholder="Enter your username"
                        autocomplete="username"
                        required>
                </div>

                <div class="field">
                    <label>Mobile Number</label>
                    <input
                        type="tel"
                        name="mobile"
                        placeholder="Enter your 10-digit mobile number"
                        pattern="[6-9][0-9]{9}"
                        maxlength="10"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        required>
                </div>

                <div class="field">
                    <label>Password</label>
                    <input
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required>
                </div>
                <div class="forgot-password">
    <a href="forgot-password.php">Forgot Password?</a>
</div>

            </div>

            <button type="submit" class="register-button">
                Login to Account <span>→</span>
            </button>

        </form>

        <div class="auth-links">
            <span>New farmer?</span>
            <a href="register.php" class="login-link">
                Create an Account →
            </a>
        </div>

        <footer class="panel-footer">
            🌱 Empowering Farmers Through Technology
        </footer>

    </section>

</main>

</body>
</html>