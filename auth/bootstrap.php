<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Environment and database configuration
|--------------------------------------------------------------------------
*/

$hostName = strtolower(
    explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]
);

$isProduction = ($hostName === 'organicfarming.great-site.net');

if ($isProduction) {
    $productionConfig = __DIR__ . '/../config/production.php';

    if (!file_exists($productionConfig)) {
        error_log('Production database configuration is missing.');
        http_response_code(500);
        exit('Application configuration error.');
    }

    $config = require $productionConfig;
    $APP_BASE_URL = '/';
} else {
    $config = [
        'host'     => 'localhost',
        'database' => 'organic_farming_db',
        'username' => 'root',
        'password' => ''
    ];

    $APP_BASE_URL = '/organic-farming/';
}

define('DB_HOST', $config['host']);
define('DB_NAME', $config['database']);
define('DB_USER', $config['username']);
define('DB_PASS', $config['password']);


/*
|--------------------------------------------------------------------------
| PDO database connection
|--------------------------------------------------------------------------
*/

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST .
               ';dbname=' . DB_NAME .
               ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false
            ]);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            http_response_code(500);
            exit('Database connection failed. Please try again later.');
        }
    }

    return $pdo;
}

/*
|--------------------------------------------------------------------------
| Application base URL
|--------------------------------------------------------------------------
*/

function redirect(string $path): never
{
    global $APP_BASE_URL;

    $url = $APP_BASE_URL . ltrim($path, '/');

    header('Location: ' . $url);
    exit;
}

/*
|--------------------------------------------------------------------------
| Output escaping
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Flash messages
|--------------------------------------------------------------------------
*/

function flash(string $type, string $message): void
{
    if (!isset($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }

    $_SESSION['flashes'][] = [
        'type'    => $type,
        'message' => $message
    ];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);

    return $messages;
}

/*
|--------------------------------------------------------------------------
| CSRF protection
|--------------------------------------------------------------------------
*/

function csrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


function require_csrf(): void
{
    $submittedToken = $_POST['csrf'] ?? $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and try again.');
    }
}


/*
|--------------------------------------------------------------------------
| Error response
|--------------------------------------------------------------------------
*/

function fail(string $message, int $statusCode = 400): never
{
    http_response_code($statusCode);
    exit($message);
}

/*
|--------------------------------------------------------------------------
| Logged-in user
|--------------------------------------------------------------------------
*/

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user'])
        ? $_SESSION['user']
        : null;
}
