<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'organic_farming_db');
define('DB_USER', 'root');
define('DB_PASS', '');

function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST .
               ';dbname=' . DB_NAME .
               ';charset=utf8mb4';

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }

    return $pdo;
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never {

    $baseUrl = "/organic-farming/";

    header("Location: " . $baseUrl . ltrim($path, "/"));

    exit;
}

function flash(string $type, string $message): void {
    $_SESSION['flashes'][] = [$type, $message];
}

function pull_flashes(): array {
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}


function require_csrf(): void {
    $token = $_POST['csrf'] ?? $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !isset($_SESSION['csrf']) ||
        !hash_equals($_SESSION['csrf'], $token)
    ) {
        fail('Invalid security token. Please try again.');
    }
}


function fail(string $message): never {

    flash('error', $message);

    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');

    if ($script === 'register-submit.php') {
        redirect('auth/register.php');
    }

    redirect('auth/login.php');
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}