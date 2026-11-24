<?php
$userId = (int)($_SESSION['user']['id'] ?? 0);

$profile = [];
$stmt = $conn->prepare("
    SELECT u.name, u.village, u.district,
           fp.land_area, fp.soil_type,
           COALESCE(fp.village, u.village) AS farm_village,
           COALESCE(fp.district, u.district) AS farm_district
    FROM users u
    LEFT JOIN farmer_profiles fp ON fp.user_id = u.id
    WHERE u.id = ? AND u.role = 'farmer'
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

if (!$profile) {
    http_response_code(403);
    exit('Farmer account not found.');
}

$displayName = trim($profile['name'] ?? 'Farmer');
$district = trim($profile['farm_district'] ?? '');
$village = trim($profile['farm_village'] ?? '');

$totalCrops = 0;
$cropStmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM crop_records WHERE user_id = ?"
);
$cropStmt->bind_param("i", $userId);
$cropStmt->execute();
$totalCrops = (int)$cropStmt->get_result()->fetch_assoc()['total'];
$cropStmt->close();

$latestCrop = null;
$cropStmt = $conn->prepare("
    SELECT crop_name, crop_type, soil_type, planting_date,
           fertilizer, description
    FROM crop_records
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$cropStmt->bind_param("i", $userId);
$cropStmt->execute();
$latestCrop = $cropStmt->get_result()->fetch_assoc() ?: null;
$cropStmt->close();

$totalListings = 0;
$activeListings = 0;
$soldListings = 0;
$expectedRevenue = 0.0;

$listStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'active') AS active,
        SUM(status = 'sold') AS sold,
        COALESCE(SUM(
            CASE WHEN status = 'active'
            THEN quantity * expected_price ELSE 0 END
        ), 0) AS revenue
    FROM crop_listings
    WHERE farmer_id = ?
");
$listStmt->bind_param("i", $userId);
$listStmt->execute();
$listStats = $listStmt->get_result()->fetch_assoc();
$listStmt->close();

$totalListings = (int)($listStats['total'] ?? 0);
$activeListings = (int)($listStats['active'] ?? 0);
$soldListings = (int)($listStats['sold'] ?? 0);
$expectedRevenue = (float)($listStats['revenue'] ?? 0);

$recommendationCount = 0;
$recStmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM recommendation_history WHERE user_id = ?"
);
$recStmt->bind_param("i", $userId);
$recStmt->execute();
$recommendationCount = (int)$recStmt->get_result()->fetch_assoc()['total'];
$recStmt->close();

$landArea = (float)($profile['land_area'] ?? 0);
$soilType = $profile['soil_type'] ?? 'Not specified';


$marketLabels = [];
$marketDatasets = [];
$marketRows = [];

$marketStmt = $conn->prepare("
    SELECT arrival_date, crop_name, AVG(modal_price) AS average_price
    FROM market_prices
    WHERE district = ?
      AND arrival_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY arrival_date, crop_name
    ORDER BY arrival_date ASC
    LIMIT 100
");
$marketStmt->bind_param("s", $district);
$marketStmt->execute();
$marketResult = $marketStmt->get_result();

$marketDateSet = [];
$marketCropSet = [];

while ($row = $marketResult->fetch_assoc()) {
    $date = $row['arrival_date'];
    $crop = $row['crop_name'];

    $marketDateSet[$date] = date('d M', strtotime($date));
    $marketCropSet[$crop] = true;
    $marketRows[$crop][$date] = (float)$row['average_price'];
}
$marketStmt->close();

$marketDates = array_keys($marketDateSet);
$marketLabels = array_values($marketDateSet);

$marketPalette = ['#258044', '#2878B5', '#D18B27', '#8056A8', '#D05C58'];
$paletteIndex = 0;

foreach (array_slice(array_keys($marketCropSet), 0, 5) as $crop) {
    $values = [];
    foreach ($marketDates as $date) {
        $values[] = $marketRows[$crop][$date] ?? null;
    }

    $marketDatasets[] = [
        'label' => $crop,
        'data' => $values,
        'borderColor' => $marketPalette[$paletteIndex % count($marketPalette)],
        'backgroundColor' => $marketPalette[$paletteIndex % count($marketPalette)],
        'borderWidth' => 2,
        'tension' => 0.3,
        'spanGaps' => true
    ];
    $paletteIndex++;
}
$recentCrops = [];
$recentStmt = $conn->prepare("
    SELECT crop_name, crop_type, planting_date, soil_type
    FROM crop_records
    WHERE user_id = ?
    ORDER BY planting_date DESC
    LIMIT 5
");
$recentStmt->bind_param("i", $userId);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();

while ($row = $recentResult->fetch_assoc()) {
    $recentCrops[] = $row;
}
$recentStmt->close();


$upcomingHarvests = [];
$harvestStmt = $conn->prepare("
    SELECT crop_name, harvest_date, quantity, quantity_unit
    FROM crop_listings
    WHERE farmer_id = ?
      AND harvest_date IS NOT NULL
      AND harvest_date >= CURDATE()
      AND status = 'active'
    ORDER BY harvest_date ASC
    LIMIT 5
");
$harvestStmt->bind_param("i", $userId);
$harvestStmt->execute();
$harvestResult = $harvestStmt->get_result();

while ($row = $harvestResult->fetch_assoc()) {
    $upcomingHarvests[] = $row;
}
$harvestStmt->close();

$organicRecommendations = [];
$fertStmt = $conn->prepare("
    SELECT DISTINCT fertilizer_name, crop_name, application_method
    FROM fertilizer_recommendations
    WHERE fertilizer_type = 'Organic'
      AND (crop_name = ? OR crop_name = 'All')
    LIMIT 5
");
$cropName = $latestCrop['crop_name'] ?? '';
$fertStmt->bind_param("s", $cropName);
$fertStmt->execute();
$fertResult = $fertStmt->get_result();

while ($row = $fertResult->fetch_assoc()) {
    $organicRecommendations[] = $row;
}
$fertStmt->close();

$dashboardLocation = trim($village . ', ' . $district, ', ');
$weatherLocation = $district !== ''
    ? $district . ', Andhra Pradesh, India'
    : ($village !== '' ? $village . ', Andhra Pradesh, India' : 'Nellore, Andhra Pradesh, India');
