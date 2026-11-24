<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();

if (!$user || !in_array($user['role'] ?? '', ['farmer', 'admin'], true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in.']);
    exit;
}

$crop = trim($_GET['crop'] ?? '');

if (strlen($crop) > 150) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid crop name.']);
    exit;
}

try {
    $pdo = db();

    if ($crop !== '') {

        $stmt = $pdo->prepare(
            "SELECT
                crop_name,
                market_name,
                district,
                state,
                min_price,
                max_price,
                modal_price,
                arrival_date
             FROM market_prices
             WHERE crop_name = :crop
             ORDER BY arrival_date DESC
             LIMIT 100"
        );

        $stmt->execute(['crop' => $crop]);

        $averageStmt = $pdo->prepare(
            "SELECT AVG(modal_price)
             FROM market_prices
             WHERE crop_name = :crop
               AND modal_price > 0"
        );

        $averageStmt->execute(['crop' => $crop]);

    } else {

        // Show all available crop market records.
        $stmt = $pdo->query(
            "SELECT
                crop_name,
                market_name,
                district,
                state,
                min_price,
                max_price,
                modal_price,
                arrival_date
             FROM market_prices
             ORDER BY arrival_date DESC
             LIMIT 100"
        );

        $averageStmt = $pdo->query(
            "SELECT AVG(modal_price)
             FROM market_prices
             WHERE modal_price > 0"
        );
    }

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $average = $averageStmt->fetchColumn();

    echo json_encode([
        'records' => $records,
        'average_modal' => $average !== false && $average !== null
            ? (float)$average
            : null
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

} catch (Throwable $e) {
    error_log('Market price lookup: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'error' => 'Unable to retrieve market prices.'
    ]);
}
