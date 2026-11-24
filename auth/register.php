<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// If already logged in, go to the existing dashboard
if (current_user()) {
    redirect('../dashboard.php');
}

$flashes = pull_flashes();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Farmer Registration | Organic Farming</title>

    <link rel="stylesheet" href="../assests/auth.css?v=5">
</head>

<body>

<main class="registration-page">

    <!-- Welcome Heading -->
    <header class="welcome-section">

        <h1>🌱 Welcome to Organic Farming</h1>

        <p class="farm-quote">
            🌱 Grow Naturally. Live Sustainably.
        </p>

        <div class="welcome-line"></div>

    </header>


    <!-- Registration Form -->
    <section class="registration-card">

        <?php foreach ($flashes as [$type, $message]): ?>
            <p class="<?= e($type) ?>">
                <?= e($message) ?>
            </p>
        <?php endforeach; ?>


        <form action="register-submit.php" method="POST">

            <input
                type="hidden"
                name="csrf"
                value="<?= e(csrf()) ?>"
            >

            <div class="form-grid">

                <!-- Username -->
                <div class="field">
                    <label for="username">Username</label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter your username"
                        minlength="3"
                        maxlength="50"
                        pattern="[a-zA-Z0-9_]+"
                        autocomplete="username"
                        required
                    >
                </div>


                <!-- Mobile Number -->
                <div class="field">
                    <label for="mobile">Mobile Number</label>

                    <input
                        type="tel"
                        id="mobile"
                        name="mobile"
                        placeholder="Enter your 10-digit mobile number"
                        pattern="[6-9][0-9]{9}"
                        maxlength="10"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        required
                    >
                </div>


                <!-- District -->
                <div class="field">
                    <label for="district">District</label>

                    <input
                        type="text"
                        id="district"
                        name="district"
                        placeholder="Enter your district"
                        maxlength="100"
                        required
                    >
                </div>


                <!-- Village -->
                <div class="field">
                    <label for="village">Village</label>

                    <input
                        type="text"
                        id="village"
                        name="village"
                        placeholder="Enter your village"
                        maxlength="150"
                        required
                    >
                </div>


                <!-- Password -->
                <div class="field">
                    <label for="password">Create Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Minimum 8 characters"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>


                <!-- Confirm Password -->
                <div class="field">
                    <label for="confirm_password">Confirm Password</label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Re-enter your password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>

            </div>


            <!-- Create Account Button -->
            <button type="submit" class="register-button">
                Create Account
                <span>→</span>
            </button>

        </form>


       <div class="auth-links">
    <span>Already have an account?</span>
    <a href="login.php" class="login-link">Login Here →</a>
</div>

        <!-- Footer -->
        <footer class="panel-footer">
            🌱 Empowering Farmers Through Technology
        </footer>

    </section>

</main>

</body>
</html>
