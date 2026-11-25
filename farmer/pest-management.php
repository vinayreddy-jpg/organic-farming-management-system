<?php
require_once __DIR__ . '/../auth/bootstrap.php';

/* =========================================================
   AUTHENTICATION
========================================================= */

$user = current_user();

if (!$user || empty($user['id'])) {
    redirect('auth/login.php');
}

$userId = (int)$user['id'];
$pdo = db();

/* =========================================================
   HELPERS
========================================================= */

function pm_tokens(string $text): array
{
    $parts = preg_split(
        '/[^a-z0-9]+/',
        strtolower($text),
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    $ignored = [
        'the', 'and', 'with', 'from', 'this', 'that',
        'have', 'has', 'are', 'was', 'were', 'for',
        'your', 'crop', 'plant', 'other', 'visible'
    ];

    $tokens = [];

    foreach ($parts as $part) {
        if (
            strlen($part) >= 3 &&
            !in_array($part, $ignored, true)
        ) {
            $tokens[] = $part;
        }
    }

    return array_values(array_unique($tokens));
}

function pm_valid_url(?string $url): bool
{
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    return in_array(
        strtolower((string)parse_url($url, PHP_URL_SCHEME)),
        ['http', 'https'],
        true
    );
}

/* =========================================================
   INITIAL VALUES
========================================================= */

$success = '';
$error = '';
$recommendationMessage = '';

$crops = [];
$cropOptions = [];
$cropOptionMap = [];
$recommendations = [];

$selectedCatalogId = 0;
$selectedPart = '';
$problemDescription = '';
$selectedSymptoms = [];
$selectedSoilType = '';

$selectedFertilizers = [];

$allowedSoilTypes = [
    'Red Soil',
    'Black Soil',
    'Sandy Soil',
    'Clay Soil',
    'Loamy Soil',
    'Alluvial Soil',
    'Laterite Soil',
    'Other',
    'Not Sure'
];

$allowedFertilizers = [
    'Farmyard Manure (FYM)',
    'Vermicompost',
    'Compost',
    'Neem Cake',
    'Panchagavya',
    'Jeevamrutham',
    'Other',
    'Not Using Any Fertilizer'
];

$allowedSymptoms = [
    'Yellow Leaves',
    'Brown Spots',
    'Holes in Leaves',
    'Wilting',
    'Insects Visible',
    'Leaf Curling',
    'Root Damage',
    'Fruit Damage',
    'Flower Dropping',
    'Stunted Growth',
    'White Powder on Leaves',
    'Other'
];

$plantParts = [
    'Leaves',
    'Stem',
    'Roots',
    'Fruits',
    'Flowers',
    'Seeds',
    'Whole Plant'
];

/* =========================================================
   LOAD REGISTERED CROPS AND COMPLETE CROP CATALOGUE
========================================================= */

/* Registered crops belonging to the signed-in farmer */

$cropStmt = $pdo->prepare("
    SELECT
        id,
        crop_name,
        crop_type,
        soil_type,
        planting_date,
        fertilizer,
        field_name,
        field_village,
        field_district
    FROM crop_records
    WHERE user_id = :user_id
    ORDER BY id DESC
");

$cropStmt->execute([
    ':user_id' => $userId
]);

$crops = $cropStmt->fetchAll(PDO::FETCH_ASSOC);

/* Index registered records by crop name */

$registeredCropMap = [];

foreach ($crops as $registeredCrop) {

    $cropKey = mb_strtolower(
        trim((string)$registeredCrop['crop_name'])
    );

    if (!isset($registeredCropMap[$cropKey])) {
        $registeredCropMap[$cropKey] = $registeredCrop;
    }
}

/* Load every crop/season entry from the catalogue */

$catalogStmt = $pdo->query("
    SELECT
        MIN(id) AS id,
        crop_name,
        category,
        season
    FROM crop_catalog
    GROUP BY crop_name, category, season
    ORDER BY category ASC, crop_name ASC, season ASC
");

$catalogCrops = $catalogStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($catalogCrops as $catalogCrop) {

    $cropKey = mb_strtolower(
        trim((string)$catalogCrop['crop_name'])
    );

    $registeredCrop = $registeredCropMap[$cropKey] ?? [];

    $option = [
        'id' => (int)$catalogCrop['id'],
        'crop_name' => $catalogCrop['crop_name'],
        'season' => $catalogCrop['season'],
        'category' => $catalogCrop['category'],

        'crop_record_id' => isset($registeredCrop['id'])
            ? (int)$registeredCrop['id']
            : null,

        'crop_type' => $registeredCrop['crop_type']
            ?? $catalogCrop['category']
            ?? '',

        'soil_type' => $registeredCrop['soil_type'] ?? '',
        'planting_date' => $registeredCrop['planting_date'] ?? '',
        'fertilizer' => $registeredCrop['fertilizer'] ?? '',
        'field_name' => $registeredCrop['field_name'] ?? '',
        'field_village' => $registeredCrop['field_village'] ?? '',
        'field_district' => $registeredCrop['field_district'] ?? ''
    ];

    $cropOptions[] = $option;

    $cropOptionMap[(int)$catalogCrop['id']] = $option;
}

/* =========================================================
   PROCESS CROP PROBLEM REPORT
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        require_csrf();

        $selectedCatalogId = (int)(
            $_POST['crop_catalog_id'] ?? 0
        );

        $selectedPart = trim(
            (string)($_POST['affected_part'] ?? '')
        );
        /* Get soil type */

$selectedSoilType = trim(
    (string)($_POST['soil_type'] ?? '')
);

if (
    $selectedSoilType !== '' &&
    !in_array($selectedSoilType, $allowedSoilTypes, true)
) {
    throw new RuntimeException('Please select a valid soil type.');
}

/* Get organic fertilizers used */

$postedFertilizers = $_POST['fertilizers_used'] ?? [];

if (!is_array($postedFertilizers)) {
    $postedFertilizers = [];
}

$selectedFertilizers = array_values(
    array_intersect(
        $allowedFertilizers,
        $postedFertilizers
    )
);

/* Prevent selecting "None" with other fertilizers */

if (
    in_array('Not Using Any Fertilizer', $selectedFertilizers, true) &&
    count($selectedFertilizers) > 1
) {
    throw new RuntimeException(
        'Select Not Using Any Fertilizer or choose the fertilizers used.'
    );
}

$fertilizersUsed = implode(', ', $selectedFertilizers);

        $problemDescription = trim(
            (string)($_POST['problem_description'] ?? '')
        );

        $postedSymptoms = $_POST['symptoms'] ?? [];

        if (!is_array($postedSymptoms)) {
            $postedSymptoms = [];
        }

        $selectedSymptoms = array_values(
            array_intersect(
                $allowedSymptoms,
                $postedSymptoms
            )
        );

        /* Validate affected part */

        if (
            $selectedPart !== '' &&
            !in_array($selectedPart, $plantParts, true)
        ) {
            throw new RuntimeException(
                'Please select a valid affected plant part.'
            );
        }

        /* Validate selected crop against catalogue */

        if (!isset($cropOptionMap[$selectedCatalogId])) {

            throw new RuntimeException(
                'Please select a valid crop from the crop catalogue.'
            );
        }

        $selectedCrop = $cropOptionMap[$selectedCatalogId];

        $cropName = $selectedCrop['crop_name'];

        /*
         * Registered crops retain their crop_records ID.
         * Unregistered catalogue crops are stored with NULL.
         */

        $cropRecordId = !empty($selectedCrop['crop_record_id'])
            ? (int)$selectedCrop['crop_record_id']
            : null;

        /* Build observed symptoms */

        $observed = [];

        if ($selectedPart !== '') {

            $observed[] = 'Affected plant part: ' . $selectedPart;
        }

        if (!empty($selectedSymptoms)) {

            $observed[] = 'Symptoms: ' .
                implode(', ', $selectedSymptoms);
        }

        $observedSymptoms = implode("\n", $observed);

        /* =================================================
           MATCH POSSIBLE PESTS AND DISEASES
        ================================================= */

        $issueStmt = $pdo->prepare("
            SELECT
                id,
                crop_name,
                issue_type,
                issue_name,
                symptoms,
                causes,
                prevention_tips
            FROM pest_disease_master
            WHERE crop_name = :crop_name
              AND is_active = 1
            ORDER BY issue_name
        ");

        $issueStmt->execute([
            ':crop_name' => $cropName
        ]);

        $issues = $issueStmt->fetchAll(PDO::FETCH_ASSOC);

        $farmerText = implode(' ', [
            $problemDescription,
            $selectedPart,
            implode(' ', $selectedSymptoms)
        ]);

        $farmerTokens = pm_tokens($farmerText);

        foreach ($issues as &$issue) {

            $issueText = implode(' ', [
                $issue['issue_name'] ?? '',
                $issue['symptoms'] ?? '',
                $issue['causes'] ?? ''
            ]);

            $issueTokens = pm_tokens($issueText);

            $score = 0;

            foreach ($farmerTokens as $token) {

                if (in_array($token, $issueTokens, true)) {
                    $score++;
                }
            }

            $issue['match_score'] = $score;
        }

        unset($issue);

        usort(
            $issues,
            function ($a, $b) {
                return $b['match_score'] <=> $a['match_score'];
            }
        );

        
/* =================================================
   SAVE REPORT
================================================= */

$insertReport = $pdo->prepare("
    INSERT INTO crop_problem_reports
    (
        user_id,
        crop_record_id,
        issue_id,
        crop_name,
        soil_type,
        fertilizers_used,
        problem_description,
        observed_symptoms,
        image_path,
        status
    )
    VALUES
    (
        :user_id,
        :crop_record_id,
        NULL,
        :crop_name,
        :soil_type,
        :fertilizers_used,
        :problem_description,
        :observed_symptoms,
        NULL,
        'Pending'
    )
");

$insertReport->execute([
    ':user_id' => $userId,
    ':crop_record_id' => $cropRecordId,
    ':crop_name' => $cropName,
    ':soil_type' => $selectedSoilType ?: null,
    ':fertilizers_used' => $fertilizersUsed ?: null,
    ':problem_description' => $problemDescription,
    ':observed_symptoms' => $observedSymptoms
]);

$success = 'Your crop problem report was submitted successfully.';


        $success = 'Your crop problem report was submitted successfully.';

        /* =================================================
           LOAD ORGANIC TREATMENTS
        ================================================= */

        foreach ($issues as $issue) {

            $treatmentStmt = $pdo->prepare("
                SELECT
                    id,
                    issue_id,
                    treatment_name,
                    ingredients,
                    preparation,
                    application_method,
                    frequency,
                    safety_notes,
                    source_name,
                    source_url,
                    is_verified
                FROM organic_pest_treatments
                WHERE issue_id = :issue_id
                ORDER BY is_verified DESC, treatment_name
            ");

            $treatmentStmt->execute([
                ':issue_id' => (int)$issue['id']
            ]);

            $issue['treatments'] = $treatmentStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

            $recommendations[] = $issue;
        }

        /* =================================================
           RECOMMENDATION MESSAGE
        ================================================= */

        if (empty($recommendations)) {

            $recommendationMessage =
                'No pest or disease records are currently available for ' .
                $cropName .
                '. Please consult your local KVK or agricultural officer.';

        } elseif (empty($farmerTokens)) {

            $recommendationMessage =
                'These are recorded pest and disease possibilities for ' .
                $cropName .
                '. Enter symptoms to improve matching. These are not confirmed diagnoses.';

        } elseif ($recommendations[0]['match_score'] === 0) {

            $recommendationMessage =
                'No strong symptom match was found. The following are possible issues, not confirmed diagnoses.';

        } else {

            $recommendationMessage =
                'Possible matches are ranked using your reported symptoms. These are preliminary suggestions, not confirmed diagnoses.';
        }

    } catch (Throwable $exception) {

        $error = $exception->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Pest & Disease Management</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f4f7f4;
    color: #26352a;
    font-family: Arial, sans-serif;
}

.layout {
    display: block;
    min-height: 100vh;
}

.main-content {
    flex: 1;
    min-width: 0;
    padding: 30px;
}

.page-header {
    max-width: 1050px;
    margin: 0 auto 25px;
}

.page-header h1 {
    color: #194d30;
    font-size: 28px;
    margin-bottom: 8px;
}

.page-header p {
    color: #718075;
    line-height: 1.6;
}

.form-card,
.results-card,
.info-card {
    max-width: 1050px;
    margin: 0 auto 25px;
    padding: 30px;
    background: white;
    border: 1px solid #e1ebe2;
    border-radius: 15px;
    box-shadow: 0 5px 22px rgba(25,77,48,.05);
}

h2 {
    color: #194d30;
    margin-top: 0;
}

.step-indicator {
    display: flex;
    gap: 12px;
    margin: 25px 0 30px;
}

.step-indicator span {
    flex: 1;
    padding: 13px;
    text-align: center;
    background: #edf1ed;
    color: #778178;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
}

.step-indicator span.active {
    background: #e2f3e6;
    color: #246b3c;
    border: 1px solid #b9dabe;
}

.step-indicator span.completed {
    background: #246b3c;
    color: white;
}

.form-step {
    display: none;
}

.form-step.active {
    display: block;
}

.section-heading {
    color: #194d30;
    font-size: 18px;
    margin: 20px 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5eee6;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.full-width {
    grid-column: 1 / -1;
}

label {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 9px;
    color: #294a32;
}

input,
select,
textarea {
    width: 100%;
    padding: 14px;
    border: 1px solid #d7e3d8;
    border-radius: 9px;
    background: white;
    font-size: 14px;
    color: #26352a;
}

input:focus,
select:focus,
textarea:focus {
    outline: none;
    border-color: #36834c;
    box-shadow: 0 0 0 3px rgba(54,131,76,.10);
}

textarea {
    min-height: 110px;
    resize: vertical;
}

.help-text {
    font-size: 12px;
    color: #718075;
    margin-top: 7px;
    line-height: 1.5;
}

.crop-details {
    background: #f4faf5;
    border: 1px solid #dceade;
    border-radius: 10px;
    padding: 15px;
    margin-top: 20px;
    display: none;
}

.crop-details p {
    margin: 8px 0;
    font-size: 13px;
    line-height: 1.6;
}

.symptom-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.symptom-option {
    display: flex;
    align-items: center;
    gap: 9px;
    background: #f8fbf8;
    padding: 13px;
    border: 1px solid #e0e9e1;
    border-radius: 9px;
    cursor: pointer;
    margin: 0;
    font-size: 13px;
}

.symptom-option input {
    width: auto;
    accent-color: #287a43;
}

.step-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 30px;
}

button {
    padding: 13px 22px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.btn-primary,
.btn-next {
    background: #246b3c;
    color: white;
}

.btn-primary:hover,
.btn-next:hover {
    background: #194d30;
}

.btn-back,
.btn-secondary {
    background: #e9eee9;
    color: #294a32;
}

.message,
.error,
.notice {
    max-width: 1050px;
    margin: 0 auto 20px;
    padding: 15px;
    border-radius: 9px;
    line-height: 1.6;
}

.message {
    background: #e5f6e9;
    color: #216b35;
}

.error {
    background: #ffe9e9;
    color: #b42318;
}

.notice {
    background: #fff7e6;
    color: #805500;
}

.issue-card {
    background: #f9fcf9;
    border: 1px solid #dce8dd;
    border-radius: 12px;
    padding: 22px;
    margin-top: 20px;
}

.issue-card h3 {
    color: #194d30;
    margin-top: 0;
    font-size: 20px;
}

.issue-meta {
    font-size: 13px;
    color: #667568;
    margin-bottom: 15px;
}

.issue-card p {
    font-size: 14px;
    line-height: 1.7;
}

.treatment-heading {
    color: #246b3c;
    margin-top: 22px;
}

.treatment-card {
    background: white;
    border: 1px solid #e1ebe2;
    padding: 20px;
    border-radius: 10px;
    margin: 14px 0;
}

.treatment-card h4 {
    color: #246b3c;
    margin-top: 0;
}

.treatment-card p {
    font-size: 14px;
    line-height: 1.7;
}

.treatment-label {
    color: #194d30;
    font-weight: 700;
}

.source-link {
    color: #216b35;
    font-weight: 600;
}

.no-treatment {
    background: #fff7e6;
    padding: 14px;
    border-radius: 8px;
    color: #805500;
    line-height: 1.6;
}

.info-card p {
    color: #647468;
    line-height: 1.8;
    font-size: 14px;
}

@media(max-width: 900px) {
    .symptom-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 768px) {
    .main-content {
        padding: 16px;
    }

    .form-card,
    .results-card,
    .info-card {
        padding: 20px;
    }

    .form-grid,
    .symptom-grid {
        grid-template-columns: 1fr;
    }

    .step-indicator {
        flex-direction: column;
    }

    .step-actions {
        flex-wrap: wrap;
    }
}

/* Single-page Pest Management form */

.step-indicator {
    display: none;
}

.form-step,
.form-step.active {
    display: block;
}

#step1 .step-actions {
    display: none;
}

#step2 .btn-back {
    display: none;
}

.step-actions {
    justify-content: flex-end;
}


.dashboard-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #194d30;
    color: #ffffff !important;
    text-decoration: none !important;
    padding: 11px 18px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 22px;
    transition: background 0.2s ease;
}

.dashboard-back-link:hover {
    background: #2d7045;
    color: #ffffff !important;
}
.pest-page-header {
    background: linear-gradient(120deg, #194d30, #087c49);
    padding: 30px;
    border-radius: 16px;
    margin: 25px 0;
    color: #ffffff;
    box-shadow: 0 6px 18px rgba(25, 77, 48, 0.14);
}

.pest-header-content {
    display: flex;
    align-items: center;
    gap: 18px;
}

.pest-header-icon {
    font-size: 42px;
    flex-shrink: 0;
}



.pest-page-header {
    width: 100%;
    box-sizing: border-box;
    margin: 25px 0 22px;
    padding: 30px 40px;
    background: linear-gradient(120deg, #194d30, #087c49);
    border-radius: 16px;
    color: #ffffff;
    box-shadow: 0 6px 18px rgba(25, 77, 48, 0.14);
}

.pest-header-content {
    display: flex;
    align-items: center;
    gap: 18px;
}

.pest-header-icon {
    font-size: 42px;
    flex-shrink: 0;
}

.pest-header-content h1 {
    color: #ffffff;
    font-size: 28px;
    margin: 0 0 10px;
}

.pest-header-content p {
    color: #e5f3e9;
    font-size: 15px;
    line-height: 1.6;
    margin: 0;
}

@media (max-width: 600px) {
    .pest-page-header {
        padding: 22px 18px;
    }

    .pest-header-content h1 {
        font-size: 21px;
    }

    .pest-header-icon {
        font-size: 32px;
    }
}

/* SOIL AND FERTILIZER SECTION */

.soil-fertilizer-grid {
    display: grid;
    grid-template-columns: minmax(220px, 0.85fr) minmax(0, 1.5fr);
    gap: 24px;
    align-items: start;
    margin: 20px 0 28px;
}

.soil-fertilizer-grid .form-group {
    min-width: 0;
}

.soil-fertilizer-grid select {
    width: 100%;
    min-height: 46px;
    box-sizing: border-box;
}

.soil-fertilizer-grid label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #244b32;
}

.fertilizer-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.fertilizer-option {
    display: flex !important;
    align-items: center;
    gap: 10px;
    min-height: 46px;
    padding: 10px 12px;
    margin: 0 !important;
    background: #f7faf7;
    border: 1px solid #dce9df;
    border-radius: 9px;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
    box-sizing: border-box;
}

.fertilizer-option:hover {
    background: #edf6ef;
    border-color: #75a986;
}

.fertilizer-option input {
    width: 17px;
    height: 17px;
    flex-shrink: 0;
    accent-color: #21834b;
    margin: 0;
}

.fertilizer-option span {
    font-size: 13px;
    line-height: 1.4;
    font-weight: 500;
    color: #294b34;
}

.fertilizer-field .help-text {
    display: block;
    margin-top: 10px;
    color: #718176;
    font-size: 12px;
}

@media (max-width: 700px) {
    .soil-fertilizer-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

@media (max-width: 420px) {
    .fertilizer-grid {
        grid-template-columns: 1fr;
    }
}


</style>

<link rel="stylesheet" href="../assets/css/mobile-responsive.css">
</head>

<body>

<div class="layout">



<main class="main-content">

    <a href="../dashboard.php" class="dashboard-back-link">← Back to Dashboard</a>
</header>
    <div class="pest-page-header">
    <div class="pest-header-content">
        <span class="pest-header-icon">🌿</span>
        <div>
            <h1>Pest & Disease Management</h1>
            <p>
                Identify crop problems and explore possible pest
                and disease matches with organic treatment guidance.
            </p>
        </div>
    </div>
</div>

    </header>

    <?php if ($success !== ''): ?>

        <div class="message">
            <?= e($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <!-- CROP PROBLEM FORM -->

    <section class="form-card">

        <h2>Report a Crop Problem</h2>

        <p class="help-text">
            Follow these steps to submit your crop problem.
        </p>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf"
                value="<?= e(csrf()) ?>"
            >

            <!-- STEP 1 -->

            <div class="form-step active" id="step1">

                <h3 class="section-heading">
                    Select Your Crop
                </h3>

                <?php if (empty($cropOptions)): ?>

                    <div class="notice">
                        No crops are currently available in the crop catalogue.
                        Please contact the project administrator.
                    </div>

                <?php else: ?>

                    <div class="form-grid">

                        <div class="form-group full-width">

                            <label for="crop_record_id">Select Crop *</label>

<select
    name="crop_catalog_id"
    id="crop_record_id"
    required
>
    <option value="">Select Crop Name</option>

    <?php
    $currentCategory = null;

    foreach ($cropOptions as $crop):

        $category = $crop['category'] ?: 'Other';

        if ($category !== $currentCategory):

            if ($currentCategory !== null):
                ?>
                </optgroup>
                <?php
            endif;

            $currentCategory = $category;
            ?>

            <optgroup label="<?= e($currentCategory) ?>">

        <?php endif; ?>

        <option
            value="<?= (int)$crop['id'] ?>"
            <?= $selectedCatalogId === (int)$crop['id'] ? 'selected' : '' ?>
        >
            <?= e($crop['crop_name']) ?>
        </option>

    <?php endforeach; ?>

    <?php if ($currentCategory !== null): ?>
        </optgroup>
    <?php endif; ?>

</select>

                            <span class="help-text">
                                All catalogue crops are available. You can report a problem
                                for a registered or unregistered crop.
                            </span>

                        </div>

                    </div>

                    <div class="crop-details" id="cropDetails">

                        <h3>Selected Crop Information</h3>

                        <p>
                            <strong>Crop Category:</strong>
                            <span id="cropType">-</span>
                        </p>

                        <p>
                            <strong>Soil Type:</strong>
                            <span id="soilType">-</span>
                        </p>

                        <p>
                            <strong>Planting Date:</strong>
                            <span id="plantingDate">-</span>
                        </p>

                        <p>
                            <strong>Fertilizer:</strong>
                            <span id="fertilizer">-</span>
                        </p>

                        <p>
                            <strong>Field:</strong>
                            <span id="fieldName">-</span>
                        </p>

                        <p>
                            <strong>Location:</strong>
                            <span id="fieldLocation">-</span>
                        </p>

                    </div>

                <?php endif; ?>

                <div class="step-actions">

                    <span></span>

                    <button
                        type="button"
                        class="btn-next"
                        onclick="nextStep()"
                        <?= empty($cropOptions) ? 'disabled' : '' ?>
                    >
                        Continue to Symptoms →
                    </button>

                </div>

            </div>

            <!-- STEP 2 -->

            <div class="form-step" id="step2">

                <h3 class="section-heading">
                    Describe the Crop Problem
                </h3>


<h3 class="section-heading">
    Soil & Fertilizer Details
</h3>

<div class="soil-fertilizer-grid">

    <div class="form-group soil-field">
        <label for="soil_type">Soil Type</label>

        <select name="soil_type" id="soil_type">
            <option value="">Select Soil Type</option>

            <?php foreach ($allowedSoilTypes as $soil): ?>
                <option
                    value="<?= e($soil) ?>"
                    <?= $selectedSoilType === $soil ? 'selected' : '' ?>
                >
                    <?= e($soil) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group fertilizer-field">
        <label>Organic Fertilizers Used</label>

        <div class="fertilizer-grid">
            <?php foreach ($allowedFertilizers as $fertilizer): ?>
                <label class="fertilizer-option">
                    <input
                        type="checkbox"
                        name="fertilizers_used[]"
                        value="<?= e($fertilizer) ?>"
                        <?= in_array(
                            $fertilizer,
                            $selectedFertilizers,
                            true
                        ) ? 'checked' : '' ?>
                    >
                    <span><?= e($fertilizer) ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <small class="help-text">
            Select all organic fertilizers currently used.
        </small>
    </div>

</div>



                <div class="form-grid">

                    <div class="form-group">

                        <label for="affected_part">
                            Affected Plant Part
                        </label>

                        <select
                            name="affected_part"
                            id="affected_part"
                        >

                            <option value="">
                                -- Select Plant Part --
                            </option>

                            <?php foreach ($plantParts as $part): ?>

                                <option
                                    value="<?= e($part) ?>"
                                    <?= $selectedPart === $part ? 'selected' : '' ?>
                                >
                                    <?= e($part) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="problem_description">
                            Problem Description
                        </label>

                        <textarea
                            name="problem_description"
                            id="problem_description"
                            placeholder="Describe what is happening to your crop..."
                        ><?= e($problemDescription) ?></textarea>

                    </div>

                    <div class="form-group full-width">

                        <label>
                            Select Visible Symptoms
                        </label>

                        <div class="symptom-grid">

                            <?php foreach ($allowedSymptoms as $symptom): ?>

                                <label class="symptom-option">

                                    <input
                                        type="checkbox"
                                        name="symptoms[]"
                                        value="<?= e($symptom) ?>"
                                        <?= in_array($symptom, $selectedSymptoms, true) ? 'checked' : '' ?>
                                    >

                                    <?= e($symptom) ?>

                                </label>

                            <?php endforeach; ?>

                        </div>

                        <span class="help-text">
                            Select all symptoms that you can observe.
                        </span>

                    </div>

                </div>

                <div class="step-actions">

                    <button
                        type="button"
                        class="btn-back"
                        onclick="previousStep()"
                    >
                        ← Back
                    </button>

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        Submit Crop Problem
                    </button>

                    <button
                        type="reset"
                        class="btn-secondary"
                        id="resetButton"
                    >
                        Clear Form
                    </button>

                </div>

            </div>

        </form>

    </section>

    <!-- RECOMMENDATIONS -->

    <?php if ($success !== ''): ?>

        <section class="results-card" id="recommendations">

            <h2>🌱 Possible Pest & Disease Matches</h2>

            <div class="notice">
                <?= e($recommendationMessage) ?>
            </div>

            <?php foreach ($recommendations as $issue): ?>

                <article class="issue-card">

                    <h3>
                        <?= e($issue['issue_name']) ?>
                    </h3>

                    <div class="issue-meta">

                        <strong>Type:</strong>
                        <?= e($issue['issue_type']) ?>

                        &nbsp; | &nbsp;

                        <strong>Symptom matches:</strong>
                        <?= (int)$issue['match_score'] ?>

                    </div>

                    <p>
                        <strong>Symptoms:</strong><br>
                        <?= nl2br(e($issue['symptoms'] ?: 'Not specified.')) ?>
                    </p>

                    <p>
                        <strong>Possible Causes:</strong><br>
                        <?= nl2br(e($issue['causes'] ?: 'Not specified.')) ?>
                    </p>

                    <p>
                        <strong>Prevention Tips:</strong><br>
                        <?= nl2br(e($issue['prevention_tips'] ?: 'Not specified.')) ?>
                    </p>

                    <h4 class="treatment-heading">
                        🌿 Organic Treatment Recommendations
                    </h4>

                    <?php if (!empty($issue['treatments'])): ?>

                        <?php foreach ($issue['treatments'] as $treatment): ?>

                            <div class="treatment-card">

                                <h4>
                                    <?= e($treatment['treatment_name']) ?>

                                    <?php if ((int)$treatment['is_verified'] === 1): ?>

                                        <span style="font-size:11px;color:#287a43;">
                                            ✓ Verified
                                        </span>

                                    <?php endif; ?>

                                </h4>

                                <p>
                                    <span class="treatment-label">
                                        Ingredients:
                                    </span><br>

                                    <?= nl2br(e($treatment['ingredients'] ?: 'Not specified.')) ?>
                                </p>

                                <p>
                                    <span class="treatment-label">
                                        Preparation:
                                    </span><br>

                                    <?= nl2br(e($treatment['preparation'] ?: 'Not specified.')) ?>
                                </p>

                                <p>
                                    <span class="treatment-label">
                                        Application Method:
                                    </span><br>

                                    <?= nl2br(e($treatment['application_method'] ?: 'Not specified.')) ?>
                                </p>

                                <p>
                                    <span class="treatment-label">
                                        Frequency:
                                    </span><br>

                                    <?= nl2br(e($treatment['frequency'] ?: 'Not specified.')) ?>
                                </p>

                                <p>
                                    <span class="treatment-label">
                                        Safety Precautions:
                                    </span><br>

                                    <?= nl2br(e($treatment['safety_notes'] ?: 'Follow appropriate local agricultural guidance.')) ?>
                                </p>

                                <?php if (!empty($treatment['source_name'])): ?>

                                    <p>

                                        <span class="treatment-label">
                                            Source:
                                        </span>

                                        <?php if (pm_valid_url($treatment['source_url'])): ?>

                                            <a
                                                class="source-link"
                                                href="<?= e($treatment['source_url']) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <?= e($treatment['source_name']) ?>
                                            </a>

                                        <?php else: ?>

                                            <?= e($treatment['source_name']) ?>

                                        <?php endif; ?>

                                    </p>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="no-treatment">

                            No organic treatment is currently recorded
                            for this issue.

                            Please consult your local KVK or agricultural officer.

                        </div>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        </section>

    <?php endif; ?>

    <!-- INFORMATION -->

    <section class="info-card">

        <h2>How This Module Works</h2>

        <p>
            Pest Management uses all crops available in the crop catalogue.
            Registered crop details are displayed when available. It compares
            reported symptoms with crop-specific pest and disease records.
        </p>

        <p>
            The system ranks possible matches using symptom keywords.
            These results are preliminary guidance, not confirmed diagnoses.
        </p>

        <p>
            Organic treatment information is retrieved from your
            organic pest treatment database.
        </p>

    </section>

</main>

</div>

<script>

/* =========================================================
   CROP INFORMATION
========================================================= */

const cropRecords = <?= json_encode(
    array_values($cropOptions),
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
) ?>;

const cropSelect = document.getElementById('crop_record_id');

const cropDetails = document.getElementById('cropDetails');

function updateCropDetails() {

    if (!cropSelect || !cropDetails) {
        return;
    }

    const selectedId = parseInt(cropSelect.value, 10);

    const crop = cropRecords.find(function(item) {
        return parseInt(item.id, 10) === selectedId;
    });

    if (!crop) {

        cropDetails.style.display = 'none';
        return;
    }

    cropDetails.style.display = 'block';

    document.getElementById('cropType').textContent =
        crop.crop_type || crop.category || '-';

    document.getElementById('soilType').textContent =
        crop.soil_type || '-';

    document.getElementById('plantingDate').textContent =
        crop.planting_date || '-';

    document.getElementById('fertilizer').textContent =
        crop.fertilizer || '-';

    document.getElementById('fieldName').textContent =
        crop.field_name || '-';

    document.getElementById('fieldLocation').textContent =
        [
            crop.field_village,
            crop.field_district
        ].filter(Boolean).join(', ') || '-';
}

if (cropSelect) {

    cropSelect.addEventListener('change', updateCropDetails);

    updateCropDetails();
}

/* =========================================================
   STEP NAVIGATION
========================================================= */

function showStep(step) {

    document.querySelectorAll('.form-step').forEach(function(element) {

        element.classList.remove('active');

    });

    document.getElementById('step' + step)
        .classList.add('active');

    for (let i = 1; i <= 2; i++) {

        const indicator =
            document.getElementById('indicator' + i);

        if (!indicator) {
            continue;
        }

        indicator.classList.remove('active', 'completed');

        if (i < step) {
            indicator.classList.add('completed');
        }

        if (i === step) {
            indicator.classList.add('active');
        }
    }

    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

function nextStep() {

    if (!cropSelect || !cropSelect.value) {

        alert('Please select a crop from the catalogue first.');

        if (cropSelect) {
            cropSelect.focus();
        }

        return;
    }

    showStep(2);
}

function previousStep() {

    showStep(1);
}

/* =========================================================
   RESET
========================================================= */

const resetButton = document.getElementById('resetButton');

if (resetButton) {

    resetButton.addEventListener('click', function() {

        setTimeout(function() {

            showStep(1);

            updateCropDetails();

        }, 0);

    });

}

/* =========================================================
   AFTER SUBMISSION
========================================================= */

<?php if ($success !== ''): ?>

window.addEventListener('load', function() {

    const results =
        document.getElementById('recommendations');

    if (results) {

        results.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

});

<?php elseif ($error !== ''): ?>

window.addEventListener('load', function() {

    showStep(2);

});

<?php endif; ?>

</script>

</body>
</html>