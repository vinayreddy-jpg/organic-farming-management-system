<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

function returnToSell(string $status): never
{
    header(
        'Location: market-management.php?section=sell-crop&status=' .
        urlencode($status)
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnToSell('invalid-request');
}

$user = current_user();

if (!$user || ($user['role'] ?? '') !== 'farmer') {
    redirect('auth/login.php');
}

// Verify CSRF token
$token = $_POST['csrf'] ?? '';

if (
    !is_string($token) ||
    empty($_SESSION['csrf']) ||
    !hash_equals($_SESSION['csrf'], $token)
) {
    returnToSell('security-error');
}

$farmerId = (int)($user['id'] ?? 0);

$cropName = trim($_POST['crop_name'] ?? '');
$variety = trim($_POST['variety'] ?? '');
$quantity = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_FLOAT);
$unit = trim($_POST['quantity_unit'] ?? '');
$price = filter_var($_POST['expected_price'] ?? '', FILTER_VALIDATE_FLOAT);
$market = trim($_POST['market_location'] ?? '');
$village = trim($_POST['village'] ?? '');
$district = trim($_POST['district'] ?? '');
$harvestDate = trim($_POST['harvest_date'] ?? '');
$quality = trim($_POST['crop_quality'] ?? '');
$description = trim($_POST['description'] ?? '');

$allowedUnits = ['kg', 'quintal', 'tonne'];
$allowedQualities = ['Premium', 'Good', 'Average'];

if (
    $farmerId <= 0 ||
    $cropName === '' ||
    strlen($cropName) > 150 ||
    $quantity === false ||
    $quantity <= 0 ||
    !in_array($unit, $allowedUnits, true) ||
    $price === false ||
    $price <= 0 ||
    $market === '' ||
    strlen($market) > 150 ||
    $district === '' ||
    strlen($district) > 100 ||
    strlen($variety) > 150 ||
    strlen($village) > 150 ||
    !in_array($quality, $allowedQualities, true)
) {
    returnToSell('invalid-data');
}

if ($harvestDate !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $harvestDate);

    if (!$date || $date->format('Y-m-d') !== $harvestDate) {
        returnToSell('invalid-date');
    }
} else {
    $harvestDate = null;
}

$imagePath = null;
$absoluteImagePath = null;

try {
    $pdo = db();

    // Use the farmer's profile location as a fallback.
    $profileStmt = $pdo->prepare(
        'SELECT village, district FROM farmer_profiles WHERE user_id = ?'
    );
    $profileStmt->execute([$farmerId]);
    $profile = $profileStmt->fetch() ?: [];

    if ($village === '') {
        $village = (string)($profile['village'] ?? '');
    }

    if ($district === '') {
        $district = (string)($profile['district'] ?? '');
    }

    if ($district === '') {
        returnToSell('missing-location');
    }

    // Optional image upload
    if (
        isset($_FILES['crop_image']) &&
        $_FILES['crop_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES['crop_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            returnToSell('image-error');
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            returnToSell('image-too-large');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            returnToSell('image-error');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        $allowedImages = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedImages[$mime])) {
            returnToSell('invalid-image');
        }

        if (@getimagesize($file['tmp_name']) === false) {
            returnToSell('invalid-image');
        }

        $uploadDir = __DIR__ . '/../uploads/crop-listings/';

        if (!is_dir($uploadDir) &&
            !mkdir($uploadDir, 0755, true) &&
            !is_dir($uploadDir)
        ) {
            returnToSell('upload-folder-error');
        }

        $filename = bin2hex(random_bytes(16)) .
            '.' . $allowedImages[$mime];

        $absoluteImagePath = $uploadDir . $filename;

        if (!move_uploaded_file(
            $file['tmp_name'],
            $absoluteImagePath
        )) {
            returnToSell('image-error');
        }

        $imagePath = 'uploads/crop-listings/' . $filename;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO crop_listings (
            farmer_id,
            crop_name,
            variety,
            quantity,
            quantity_unit,
            expected_price,
            market_location,
            village,
            district,
            state,
            harvest_date,
            crop_quality,
            description,
            image_path
        ) VALUES (
            :farmer_id,
            :crop_name,
            :variety,
            :quantity,
            :unit,
            :price,
            :market,
            :village,
            :district,
            :state,
            :harvest_date,
            :quality,
            :description,
            :image_path
        )'
    );

    $stmt->execute([
        'farmer_id' => $farmerId,
        'crop_name' => $cropName,
        'variety' => $variety !== '' ? $variety : null,
        'quantity' => $quantity,
        'unit' => $unit,
        'price' => $price,
        'market' => $market,
        'village' => $village !== '' ? $village : null,
        'district' => $district,
        'state' => 'Andhra Pradesh',
        'harvest_date' => $harvestDate,
        'quality' => $quality,
        'description' => $description !== '' ? $description : null,
        'image_path' => $imagePath
    ]);

   header(
    'Location: market-management.php?' .
    http_build_query([
        'section' => 'sell-crop',
        'status' => 'success',
        'price_crop' => $cropName
    ])
);
exit;

} catch (Throwable $e) {
    if ($absoluteImagePath && is_file($absoluteImagePath)) {
        unlink($absoluteImagePath);
    }

    error_log('Crop listing error: ' . $e->getMessage());
    header(
    'Location: market-management.php?' .
    http_build_query([
        'section' => 'sell-crop',
        'status' => 'success',
        'price_crop' => $cropName
    ])
);
exit;
}
