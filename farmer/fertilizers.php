<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../auth/bootstrap.php';
/* AUTHENTICATION */
$loggedInUser = current_user();

if (!$loggedInUser || empty($loggedInUser['id'])) {
    redirect('auth/login.php');
}

$userId = (int)$loggedInUser['id'];

/* PDO DATABASE CONNECTION */
$pdo = db();

/* CSRF TOKEN */
if (empty($_SESSION['fertilizer_csrf'])) {
    $_SESSION['fertilizer_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['fertilizer_csrf'];

/* CROP CATALOGUE */
$cropGroups = [
    'Cereals & Millets' => [
        'Paddy', 'Wheat', 'Maize', 'Barley', 'Sorghum (Jowar)',
        'Pearl Millet (Bajra)', 'Finger Millet (Ragi)',
        'Foxtail Millet', 'Little Millet', 'Kodo Millet',
        'Barnyard Millet', 'Proso Millet'
    ],
    'Pulses' => [
        'Red Gram (Pigeon Pea)', 'Green Gram', 'Black Gram',
        'Bengal Gram (Chickpea)', 'Cowpea', 'Horse Gram',
        'Field Pea', 'Lentil', 'Moth Bean', 'Lablab Bean'
    ],
    'Oilseeds' => [
        'Groundnut', 'Sunflower', 'Sesame', 'Mustard', 'Soybean',
        'Castor', 'Safflower', 'Linseed', 'Niger Seed', 'Coconut'
    ],
    'Commercial & Fibre Crops' => [
        'Cotton', 'Sugarcane', 'Tobacco', 'Jute', 'Mesta',
        'Tea', 'Coffee', 'Rubber'
    ],
    'Vegetables' => [
        'Tomato', 'Brinjal', 'Chilli', 'Capsicum', 'Potato',
        'Sweet Potato', 'Onion', 'Garlic', 'Cabbage', 'Cauliflower',
        'Carrot', 'Radish', 'Beetroot', 'Okra', 'Cucumber',
        'Bottle Gourd', 'Bitter Gourd', 'Ridge Gourd', 'Snake Gourd',
        'Pumpkin', 'Ash Gourd', 'Drumstick', 'Spinach',
        'Amaranthus', 'Beans', 'Peas', 'Cluster Beans',
        'Broad Beans', 'Knol Khol', 'Turnip', 'Lettuce',
        'Broccoli', 'Zucchini', 'Mushroom'
    ],
    'Fruits' => [
        'Mango', 'Banana', 'Papaya', 'Guava', 'Pomegranate',
        'Grapes', 'Orange', 'Sweet Lime', 'Lemon', 'Watermelon',
        'Muskmelon', 'Pineapple', 'Sapota', 'Custard Apple',
        'Jackfruit', 'Amla', 'Apple', 'Pear', 'Peach', 'Plum',
        'Strawberry', 'Dragon Fruit', 'Avocado', 'Litchi', 'Ber'
    ],
    'Spices & Plantation Crops' => [
        'Turmeric', 'Ginger', 'Black Pepper', 'Cardamom',
        'Clove', 'Cinnamon', 'Coriander', 'Cumin', 'Fenugreek',
        'Fennel', 'Ajwain', 'Tamarind', 'Vanilla', 'Arecanut',
        'Betel Leaf'
    ],
    'Fodder & Other Crops' => [
        'Napier Grass', 'Lucerne', 'Berseem', 'Fodder Sorghum',
        'Fodder Maize', 'Fodder Cowpea', 'Azolla', 'Indigo',
        'Marigold', 'Jasmine', 'Rose', 'Chrysanthemum'
    ]
];

$allCrops = [];

foreach ($cropGroups as $names) {
    foreach ($names as $name) {
        $allCrops[] = $name;
    }
}

$soilTypes = [
    'Alluvial Soil', 'Black Soil', 'Red Soil', 'Sandy Soil',
    'Clay Soil', 'Loamy Soil', 'Laterite Soil', 'Silty Soil', 'Other'
];

$allowedStages = [
    'Planting / Sowing',
    'Seedling Stage',
    'Vegetative Stage',
    'Flowering Stage',
    'Fruiting / Grain Formation Stage',
    'Maturity / Harvesting Stage'
];

$allowedSeasons = ['Kharif', 'Rabi', 'Both'];
$allowedWater = ['Low', 'Moderate', 'High'];

/* PAGE VARIABLES */
$message = '';
$messageType = 'info';

$recommendations = [];
$history = [];
$savedCrops = [];

$cropName = '';
$selectedSoil = '';
$cropStage = '';
$area = '';
$season = '';
$waterAvailability = '';
$previousCrop = '';

/* LOAD SAVED CROPS */
$stmt = $pdo->prepare("
    SELECT id, crop_name, soil_type
    FROM crop_records
    WHERE user_id = :user_id
    ORDER BY crop_name ASC
");

$stmt->execute(['user_id' => $userId]);
$savedCrops = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* HANDLE POST REQUESTS */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        $message = 'Invalid security token. Refresh the page and try again.';
        $messageType = 'error';

    } else {

        $action = $_POST['action'] ?? '';

        /* DELETE HISTORY */
        if ($action === 'delete_history') {

            $historyId = filter_var(
                $_POST['history_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$historyId || $historyId < 1) {
                $message = 'Invalid history record.';
                $messageType = 'error';

            } else {

                $stmt = $pdo->prepare("
                    DELETE FROM recommendation_history
                    WHERE id = :id AND user_id = :user_id
                ");

                $stmt->execute([
                    'id' => $historyId,
                    'user_id' => $userId
                ]);

                if ($stmt->rowCount() > 0) {
                    $message = 'History deleted successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Record not found or already deleted.';
                    $messageType = 'error';
                }
            }

        /* GET RECOMMENDATIONS */
        } elseif ($action === 'get_recommendations') {

            $cropChoice = trim((string)($_POST['crop_choice'] ?? ''));
            $manualCropName = trim((string)($_POST['manual_crop_name'] ?? ''));

            $selectedSoil = trim((string)($_POST['soil_type'] ?? ''));
            $cropStage = trim((string)($_POST['crop_stage'] ?? ''));
            $area = trim((string)($_POST['land_area'] ?? ''));
            $season = trim((string)($_POST['season'] ?? ''));
            $waterAvailability = trim((string)($_POST['water_availability'] ?? ''));
            $previousCrop = trim((string)($_POST['previous_crop'] ?? ''));

            $validCrop = false;

            /* SAVED CROP */
            if (strpos($cropChoice, 'saved:') === 0) {

                $savedCropId = (int)substr($cropChoice, 6);

                $stmt = $pdo->prepare("
                    SELECT crop_name, soil_type
                    FROM crop_records
                    WHERE id = :id AND user_id = :user_id
                    LIMIT 1
                ");

                $stmt->execute([
                    'id' => $savedCropId,
                    'user_id' => $userId
                ]);

                $savedCrop = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($savedCrop) {
                    $cropName = trim($savedCrop['crop_name']);
                    $validCrop = true;

                    if ($selectedSoil === '') {
                        $selectedSoil = trim($savedCrop['soil_type'] ?? '');
                    }
                }

            /* CATALOGUE CROP */
            } elseif (strpos($cropChoice, 'catalog:') === 0) {

                $catalogCropName = trim(substr($cropChoice, 8));

                if (in_array($catalogCropName, $allCrops, true)) {
                    $cropName = $catalogCropName;
                    $validCrop = true;
                }

            /* MANUAL CROP */
            } elseif ($cropChoice === 'manual') {

                $cropName = $manualCropName;

                if ($cropName !== '' && strlen($cropName) <= 100) {
                    $validCrop = true;
                }
            }

            /* VALIDATE INPUTS */
            if (!$validCrop) {
                $message = 'Please select a valid crop or enter a crop name.';
                $messageType = 'error';

            } elseif (!in_array($selectedSoil, $soilTypes, true)) {
                $message = 'Please select a valid soil type.';
                $messageType = 'error';

            } elseif (!in_array($cropStage, $allowedStages, true)) {
                $message = 'Please select a valid crop growth stage.';
                $messageType = 'error';

            } elseif (
                !is_numeric($area) ||
                (float)$area <= 0 ||
                (float)$area > 100000
            ) {
                $message = 'Please enter a valid land area.';
                $messageType = 'error';

            } elseif (!in_array($season, $allowedSeasons, true)) {
                $message = 'Please select a valid season.';
                $messageType = 'error';

            } elseif (!in_array($waterAvailability, $allowedWater, true)) {
                $message = 'Please select water availability.';
                $messageType = 'error';

            } else {

                /*
                 * RECOMMENDATION MATCHING
                 * Exact soil and stage matches are ranked first.
                 * General soil and All Stages records are fallbacks.
                 * Only verified organic records are selected.
                 */

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        crop_name,
                        soil_type,
                        crop_stage,
                        fertilizer_name,
                        application_method,
                        source_name,
                        source_url,

                        CASE
                            WHEN soil_type IS NOT NULL
                             AND TRIM(soil_type) <> ''
                             AND LOWER(TRIM(soil_type)) = LOWER(TRIM(:soil_label))
                            THEN 'Soil-specific'
                            ELSE 'General soil guidance'
                        END AS soil_match_label,

                        CASE
                            WHEN LOWER(TRIM(crop_stage)) = LOWER(TRIM(:stage_label))
                            THEN 'Growth-stage specific'
                            ELSE 'All-stage guidance'
                        END AS stage_match_label

                    FROM fertilizer_recommendations

                    WHERE LOWER(TRIM(crop_name)) = LOWER(TRIM(:crop_name))
                      AND LOWER(TRIM(fertilizer_type)) = 'organic'
                      AND is_verified = 1

                      AND (
                          (
                              soil_type IS NOT NULL
                              AND TRIM(soil_type) <> ''
                              AND LOWER(TRIM(soil_type)) = LOWER(TRIM(:soil_filter))
                          )
                          OR soil_type IS NULL
                          OR TRIM(soil_type) = ''
                      )

                      AND (
                          LOWER(TRIM(crop_stage)) = LOWER(TRIM(:stage_filter))
                          OR LOWER(TRIM(crop_stage)) = 'all stages'
                          OR crop_stage IS NULL
                          OR TRIM(crop_stage) = ''
                      )

                    ORDER BY
                        CASE
                            WHEN soil_type IS NOT NULL
                             AND TRIM(soil_type) <> ''
                             AND LOWER(TRIM(soil_type)) = LOWER(TRIM(:soil_order))
                            THEN 0
                            ELSE 1
                        END,

                        CASE
                            WHEN LOWER(TRIM(crop_stage)) = LOWER(TRIM(:stage_order))
                            THEN 0
                            ELSE 1
                        END,

                        fertilizer_name ASC,
                        id ASC
                ");

                $stmt->execute([
                    'soil_label' => $selectedSoil,
                    'stage_label' => $cropStage,
                    'crop_name' => $cropName,
                    'soil_filter' => $selectedSoil,
                    'stage_filter' => $cropStage,
                    'soil_order' => $selectedSoil,
                    'stage_order' => $cropStage
                ]);

                $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);

                /* SAVE RECOMMENDATION HISTORY */
                $historyData = json_encode(
                    $recommendations,
                    JSON_UNESCAPED_UNICODE
                );

                if ($historyData === false) {
                    $historyData = '[]';
                }

                $historyStmt = $pdo->prepare("
                    INSERT INTO recommendation_history
                    (
                        user_id,
                        crop_name,
                        village,
                        district,
                        soil_type,
                        crop_stage,
                        season,
                        water_availability,
                        land_area,
                        previous_crop,
                        recommended_crops
                    )
                    VALUES
                    (
                        :user_id,
                        :crop_name,
                        :village,
                        :district,
                        :soil_type,
                        :crop_stage,
                        :season,
                        :water,
                        :area,
                        :previous_crop,
                        :recommendations
                    )
                ");

                $historyStmt->execute([
                    'user_id' => $userId,
                    'crop_name' => $cropName,
                    'village' => '',
                    'district' => '',
                    'soil_type' => $selectedSoil,
                    'crop_stage' => $cropStage,
                    'season' => $season,
                    'water' => $waterAvailability,
                    'area' => (float)$area,
                    'previous_crop' => $previousCrop,
                    'recommendations' => $historyData
                ]);

                if (count($recommendations) > 0) {
                    $message = 'Verified organic fertilizer recommendations found.';
                    $messageType = 'success';
                } else {
                    $message = 'No verified organic fertilizer recommendations are available for this crop, soil and growth stage yet.';
                    $messageType = 'info';
                }
            }
        }
    }
}

/* LOAD HISTORY */
$historyStmt = $pdo->prepare("
    SELECT
        id,
        crop_name,
        soil_type,
        crop_stage,
        season,
        water_availability,
        land_area,
        previous_crop,
        created_at
    FROM recommendation_history
    WHERE user_id = :user_id
    ORDER BY id DESC
    LIMIT 20
");

$historyStmt->execute(['user_id' => $userId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Organic Fertilizer Management</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f8f4;
    color: #24352a;
}

.header {
    background: #194d30;
    color: white;
    padding: 22px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.header h1 {
    font-size: 23px;
}

.header a {
    color: white;
    text-decoration: none;
    background: #286b43;
    padding: 10px 16px;
    border-radius: 7px;
}

.container {
    width: 92%;
    max-width: 1200px;
    margin: 30px auto;
}

.intro {
    margin-bottom: 22px;
}

.intro h2 {
    color: #194d30;
    margin-bottom: 8px;
}

.card {
    background: white;
    padding: 25px;
    margin-bottom: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 14px rgba(0,0,0,0.06);
}

.card h3 {
    color: #194d30;
    margin-bottom: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

label {
    font-weight: 600;
    font-size: 14px;
}

input, select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccd8ce;
    border-radius: 7px;
    font-size: 14px;
    background: white;
}

input:focus, select:focus {
    outline: none;
    border-color: #27834b;
}

button {
    border: none;
    border-radius: 7px;
    padding: 13px 20px;
    background: #247744;
    color: white;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #185b32;
}

.submit-row {
    margin-top: 22px;
}

.alert {
    padding: 14px 17px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert.success {
    background: #e4f5e9;
    color: #176333;
}

.alert.error {
    background: #ffe9e9;
    color: #a12222;
}

.alert.info {
    background: #eaf2ff;
    color: #244f83;
}

.recommendation {
    border: 1px solid #dce9de;
    border-left: 5px solid #268047;
    padding: 18px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.recommendation h4 {
    color: #194d30;
    margin-bottom: 10px;
    font-size: 17px;
}

.recommendation p {
    margin: 8px 0;
    line-height: 1.5;
}

.tag {
    display: inline-block;
    background: #e5f5e9;
    color: #216b3a;
    padding: 5px 9px;
    border-radius: 5px;
    font-size: 12px;
    margin-bottom: 10px;
}

.match-tag {
    display: inline-block;
    background: #f0f4f1;
    color: #52645a;
    padding: 5px 9px;
    border-radius: 5px;
    font-size: 12px;
    margin: 0 0 10px 5px;
}

.source-link {
    color: #176baf;
    text-decoration: none;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th, td {
    padding: 13px;
    border-bottom: 1px solid #e4e9e4;
    text-align: left;
    font-size: 14px;
}

th {
    background: #edf5ef;
    color: #194d30;
}

.delete-btn {
    background: #c83c3c;
    padding: 8px 12px;
}

.delete-btn:hover {
    background: #9e2525;
}

.empty {
    color: #66736a;
    padding: 15px 0;
}

.hidden {
    display: none;
}

@media(max-width: 700px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 18px;
    }

    .header h1 {
        font-size: 19px;
    }
}
</style>
<link rel="stylesheet" href="/organic-farming/assets/css/mobile-responsive.css">
</head>

<body>

<header class="header">
    <h1>Organic Fertilizer Management</h1>
    <a href="../dashboard.php">Back to Dashboard</a>
</header>

<main class="container">

    <section class="intro">
        <h2>Organic Fertilizer Recommendations</h2>
        <p>
            Find organic fertilizer information based on your crop,
            soil type and crop growth stage.
        </p>
    </section>

    <?php if ($message !== ''): ?>
        <div class="alert <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <h3>Find Fertilizer Recommendations</h3>

        <form method="POST" action="fertilizers.php">

            <input type="hidden" name="csrf_token"
                   value="<?= e($csrfToken) ?>">

            <input type="hidden" name="action"
                   value="get_recommendations">

            <div class="form-grid">

                <div class="form-group">
                    <label for="crop_choice">Select Crop</label>

                    <select name="crop_choice" id="crop_choice" required>
                        <option value="">Choose a crop</option>

                        <?php if ($savedCrops): ?>
                            <optgroup label="My Saved Crops">
                                <?php foreach ($savedCrops as $crop): ?>
                                    <option
                                        value="saved:<?= (int)$crop['id'] ?>"
                                        data-soil="<?= e($crop['soil_type'] ?? '') ?>"
                                    >
                                        <?= e($crop['crop_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>

                        <?php foreach ($cropGroups as $group => $names): ?>
                            <optgroup label="<?= e($group) ?>">
                                <?php foreach ($names as $name): ?>
                                    <option value="catalog:<?= e($name) ?>">
                                        <?= e($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>

                        <option value="manual">Enter Another Crop</option>
                    </select>
                </div>

                <div class="form-group hidden" id="manualCropGroup">
                    <label for="manual_crop_name">Enter Crop Name</label>
                    <input
                        type="text"
                        name="manual_crop_name"
                        id="manual_crop_name"
                        maxlength="100"
                        placeholder="Enter crop name"
                    >
                </div>

                <div class="form-group">
                    <label for="soil_type">Soil Type</label>
                    <select name="soil_type" id="soil_type" required>
                        <option value="">Select soil type</option>

                        <?php foreach ($soilTypes as $soil): ?>
                            <option value="<?= e($soil) ?>">
                                <?= e($soil) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="crop_stage">Crop Growth Stage</label>
                    <select name="crop_stage" id="crop_stage" required>
                        <option value="">Select growth stage</option>

                        <?php foreach ($allowedStages as $stage): ?>
                            <option value="<?= e($stage) ?>">
                                <?= e($stage) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="land_area">Land Area (Acres)</label>
                    <input
                        type="number"
                        name="land_area"
                        id="land_area"
                        min="0.01"
                        step="0.01"
                        placeholder="Example: 2"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="season">Season</label>
                    <select name="season" id="season" required>
                        <option value="">Select season</option>

                        <?php foreach ($allowedSeasons as $item): ?>
                            <option value="<?= e($item) ?>">
                                <?= e($item) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="water_availability">Water Availability</label>
                    <select name="water_availability"
                            id="water_availability" required>
                        <option value="">Select water availability</option>

                        <?php foreach ($allowedWater as $item): ?>
                            <option value="<?= e($item) ?>">
                                <?= e($item) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="previous_crop">Previous Crop</label>
                    <input
                        type="text"
                        name="previous_crop"
                        id="previous_crop"
                        maxlength="100"
                        placeholder="Enter previous crop (optional)"
                    >
                </div>

            </div>

            <div class="submit-row">
                <button type="submit">
                    Get Organic Recommendations
                </button>
            </div>

        </form>
    </section>

    <?php if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        ($_POST['action'] ?? '') === 'get_recommendations'
    ): ?>

        <section class="card">
            <h3>Recommendation Results</h3>

            <?php if ($recommendations): ?>

                <?php foreach ($recommendations as $item): ?>
                    <article class="recommendation">

                        <span class="tag">
                            Verified Organic Fertilizer
                        </span>

                        <span class="match-tag">
                            <?= e($item['soil_match_label']) ?> ·
                            <?= e($item['stage_match_label']) ?>
                        </span>

                        <h4><?= e($item['fertilizer_name']) ?></h4>

                        <p>
                            <strong>Crop:</strong>
                            <?= e($item['crop_name']) ?>
                        </p>

                        <p>
                            <strong>Soil:</strong>
                            <?= e(
                                $item['soil_type']
                                ?: 'General recommendation — no soil-specific record available'
                            ) ?>
                        </p>

                        <p>
                            <strong>Growth Stage:</strong>
                            <?= e($item['crop_stage'] ?: 'All Stages') ?>
                        </p>

                        <p>
                            <strong>Application Method:</strong><br>
                            <?= nl2br(e(
                                $item['application_method']
                                ?: 'Details not provided.'
                            )) ?>
                        </p>

                        <p>
                            <strong>Information Source:</strong>
                            <?= e($item['source_name'] ?: 'Not specified') ?>
                        </p>

                        <?php if (!empty($item['source_url'])): ?>
                            <p>
                                <a
                                    class="source-link"
                                    href="<?= e($item['source_url']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    View Source
                                </a>
                            </p>
                        <?php endif; ?>

                    </article>
                <?php endforeach; ?>

                <p class="empty">
                    Exact soil and growth-stage matches are shown first.
                    General records are labelled when no specific record
                    is available. Application quantities are not calculated
                    unless verified dosage information is available.
                </p>

            <?php else: ?>

                <p class="empty">
                    No matching verified organic fertilizer recommendation
                    was found. Check the crop name, soil type or growth stage.
                    No chemical fertilizer suggestions are displayed.
                </p>

            <?php endif; ?>
        </section>

    <?php endif; ?>

    <section class="card">
        <h3>Recommendation History</h3>

        <?php if ($history): ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Crop</th>
                            <th>Soil</th>
                            <th>Growth Stage</th>
                            <th>Season</th>
                            <th>Water</th>
                            <th>Area (Acres)</th>
                            <th>Previous Crop</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($history as $record): ?>
                            <tr>
                                <td><?= e($record['crop_name']) ?></td>
                                <td><?= e($record['soil_type']) ?></td>
                                <td><?= e($record['crop_stage']) ?></td>
                                <td><?= e($record['season']) ?></td>
                                <td><?= e($record['water_availability']) ?></td>
                                <td><?= e((string)$record['land_area']) ?></td>
                                <td><?= e($record['previous_crop'] ?: '-') ?></td>
                                <td><?= e($record['created_at']) ?></td>

                                <td>
                                    <form
                                        method="POST"
                                        action="fertilizers.php"
                                        onsubmit="return confirm('Delete this history record?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($csrfToken) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete_history"
                                        >

                                        <input
                                            type="hidden"
                                            name="history_id"
                                            value="<?= (int)$record['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="delete-btn"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <p class="empty">
                No recommendation history available yet.
            </p>

        <?php endif; ?>
    </section>

</main>

<script>
const cropChoice = document.getElementById('crop_choice');
const manualCropGroup = document.getElementById('manualCropGroup');
const manualCropInput = document.getElementById('manual_crop_name');
const soilSelect = document.getElementById('soil_type');

cropChoice.addEventListener('change', function () {

    const selectedOption = this.options[this.selectedIndex];

    if (this.value === 'manual') {
        manualCropGroup.classList.remove('hidden');
        manualCropInput.required = true;
        manualCropInput.focus();
    } else {
        manualCropGroup.classList.add('hidden');
        manualCropInput.required = false;
        manualCropInput.value = '';
    }

    const savedSoil = selectedOption.dataset.soil || '';

    if (
        savedSoil &&
        soilSelect.querySelector(
            'option[value="' + CSS.escape(savedSoil) + '"]'
        )
    ) {
        soilSelect.value = savedSoil;
    }
});
</script>

</body>
</html>
