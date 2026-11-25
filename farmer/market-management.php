<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

$pdo = db();

// ========================================
// ACTIVE FARMER CROP LISTINGS
// ========================================

$listingSearch = trim($_GET['listing_search'] ?? '');

$listingSQL = "
    SELECT
        cl.*,
        u.name AS farmer_name,
        u.mobile AS farmer_mobile
    FROM crop_listings cl
    INNER JOIN users u ON u.id = cl.farmer_id
    WHERE cl.status = 'active'
";

$listingParams = [];

if ($listingSearch !== '') {
    $listingSQL .= "
        AND (
            cl.crop_name LIKE :search_crop
            OR cl.district LIKE :search_district
            OR cl.market_location LIKE :search_market
        )
    ";

    $searchValue = '%' . $listingSearch . '%';

    $listingParams = [
        'search_crop' => $searchValue,
        'search_district' => $searchValue,
        'search_market' => $searchValue
    ];
}

$listingSQL .= " ORDER BY cl.created_at DESC LIMIT 100";

$listingStmt = $pdo->prepare($listingSQL);
$listingStmt->execute($listingParams);
$farmerListings = $listingStmt->fetchAll(PDO::FETCH_ASSOC);


// ========================================
// FILTERS
// ========================================

$cropFilter = trim((string)($_GET['crop'] ?? ''));
$districtFilter = trim((string)($_GET['district'] ?? ''));
$marketFilter = trim((string)($_GET['market'] ?? ''));

$where = [];
$params = [];

if ($cropFilter !== '') {
    $where[] = 'crop_name = :crop';
    $params['crop'] = $cropFilter;
}

if ($districtFilter !== '') {
    $where[] = 'district = :district';
    $params['district'] = $districtFilter;
}

if ($marketFilter !== '') {
    $where[] = 'market_name = :market';
    $params['market'] = $marketFilter;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';


// ========================================
// DASHBOARD STATISTICS
// ========================================

$totalRecords = (int)$pdo
    ->query("SELECT COUNT(*) FROM market_prices")
    ->fetchColumn();

$latestDate = $pdo
    ->query("SELECT MAX(arrival_date) FROM market_prices")
    ->fetchColumn();

$latestDateDisplay = $latestDate
    ? date('d M Y', strtotime((string)$latestDate))
    : 'No records yet';


// ========================================
// FILTER OPTIONS
// ========================================

$cropOptions = $pdo->query("
    SELECT DISTINCT crop_name
    FROM market_prices
    WHERE crop_name IS NOT NULL AND crop_name <> ''
    ORDER BY crop_name
")->fetchAll(PDO::FETCH_COLUMN);

$districtOptions = $pdo->query("
    SELECT DISTINCT district
    FROM market_prices
    WHERE district IS NOT NULL AND district <> ''
    ORDER BY district
")->fetchAll(PDO::FETCH_COLUMN);

$marketOptions = $pdo->query("
    SELECT DISTINCT market_name
    FROM market_prices
    WHERE market_name IS NOT NULL AND market_name <> ''
    ORDER BY market_name
")->fetchAll(PDO::FETCH_COLUMN);


// ========================================
// MARKET RECORDS
// ========================================

$sql = "
    SELECT
        id,
        crop_name,
        variety,
        market_name,
        district,
        state,
        min_price,
        max_price,
        modal_price,
        arrival_date,
        source_name
    FROM market_prices
    $whereSQL
    ORDER BY arrival_date DESC, crop_name ASC
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$marketRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ========================================
// MARKET COMPARISON
// ========================================

$comparisonSQL = "
    SELECT
        crop_name,
        market_name,
        district,
        MIN(modal_price) AS min_modal,
        MAX(modal_price) AS max_modal,
        AVG(modal_price) AS average_modal
    FROM market_prices
    $whereSQL
    GROUP BY crop_name, market_name, district
    ORDER BY crop_name, average_modal DESC
    LIMIT 200
";

$comparisonStmt = $pdo->prepare($comparisonSQL);
$comparisonStmt->execute($params);

$comparisonRecords = $comparisonStmt->fetchAll(PDO::FETCH_ASSOC);


// ========================================
// ANDHRA PRADESH MARKETS
// ========================================

$apStmt = $pdo->query("
    SELECT
        market_name,
        district,
        state,
        crop_name,
        min_price,
        max_price,
        modal_price,
        arrival_date
    FROM market_prices
    WHERE TRIM(state) = 'Andhra Pradesh'
    ORDER BY district, market_name, crop_name
    LIMIT 200
");

$apRecords = $apStmt->fetchAll(PDO::FETCH_ASSOC);


// ========================================
// HELPER FUNCTIONS
// ========================================

function marketMoney($amount): string
{
    if ($amount === null || $amount === '') {
        return '—';
    }

    return '₹' . number_format((float)$amount, 2);
}

function marketDate($date): string
{
    if (!$date) {
        return '—';
    }

    $timestamp = strtotime((string)$date);

    return $timestamp
        ? date('d M Y', $timestamp)
        : '—';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Market Management | Organic Farming</title>

<style>

/* ========================================
   GLOBAL
======================================== */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f3f7f2;
    color: #20352a;
    font-family: Arial, Helvetica, sans-serif;
}

.app-layout {
    display: block;
    min-height: 100vh;
}

.main-content {
    flex: 1;
    min-width: 0;
    padding: 28px 25px 45px;
}

.page-container {
    max-width: 1500px;
    margin: auto;
}


/* ========================================
   WELCOME BANNER
======================================== */

.welcome-banner {
    background: linear-gradient(115deg, #174b31, #28764a);
    color: white;
    padding: 34px 30px;
    border-radius: 16px;
    margin-bottom: 22px;
    position: relative;
    overflow: hidden;
}

.welcome-banner h1 {
    font-size: 30px;
    margin: 0 0 12px;
}

.welcome-banner p {
    max-width: 650px;
    line-height: 1.7;
    margin: 0;
    color: #e4f1e7;
}

.banner-decoration {
    position: absolute;
    right: 35px;
    top: 12px;
    font-size: 100px;
    opacity: .15;
}


/* ========================================
   STATISTICS
======================================== */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 28px;
}

.stat-card {
    background: white;
    border: 1px solid #e6eee6;
    border-radius: 14px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 3px 12px rgba(25, 65, 40, .03);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #eaf4ec;
    display: grid;
    place-items: center;
    font-size: 23px;
}

.stat-card strong {
    display: block;
    font-size: 20px;
    color: #174f32;
    margin-bottom: 4px;
}

.stat-card small {
    color: #748176;
}


/* ========================================
   SECTION HEADING
======================================== */

.section-heading {
    margin: 28px 0 15px;
}

.section-heading h2 {
    font-size: 21px;
    color: #174f32;
    margin: 0 0 5px;
}

.section-heading p {
    color: #758177;
    font-size: 13px;
    margin: 0;
}


/* ========================================
   FEATURE CARDS
======================================== */

.feature-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
}

.feature-card {
    display: block;
    text-decoration: none;
    color: inherit;
    background: white;
    border: 1px solid #e7eee7;
    border-radius: 15px;
    padding: 22px;
    min-height: 160px;
    transition: .2s ease;
    box-shadow: 0 3px 12px rgba(25, 65, 40, .025);
}

.feature-card:hover,
.feature-card:focus {
    transform: translateY(-3px);
    border-color: #a8cdb1;
    box-shadow: 0 8px 22px rgba(25, 65, 40, .08);
    outline: none;
}

.feature-card.is-selected {
    border: 2px solid #28764a;
    background: #f4faf5;
}

.feature-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    background: #eaf4ec;
    border-radius: 12px;
    font-size: 22px;
    margin-bottom: 13px;
}

.feature-card h3 {
    color: #174f32;
    font-size: 16px;
    margin: 0 0 9px;
}

.feature-card p {
    font-size: 12px;
    line-height: 1.6;
    color: #718075;
    margin: 0 0 15px;
}

.feature-link {
    font-size: 12px;
    font-weight: bold;
    color: #21834a;
}


/* ========================================
   CONTENT PANELS
======================================== */

.panel {
    display: none;
    background: white;
    border: 1px solid #e3ece3;
    border-radius: 15px;
    padding: 23px;
    margin-top: 22px;
    box-shadow: 0 3px 12px rgba(25, 65, 40, .025);
}

.panel.is-active {
    display: block;
}

.panel h2 {
    font-size: 21px;
    color: #174f32;
    margin: 0 0 18px;
}

.panel h3 {
    color: #174f32;
}

.panel-intro {
    color: #718075;
    font-size: 13px;
    line-height: 1.6;
}


/* ========================================
   FILTER FORM
======================================== */

.filter-form {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr)) auto auto;
    gap: 12px;
    align-items: end;
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 8px;
    color: #355a41;
}

.form-group select,
.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #d5e4d7;
    border-radius: 8px;
    background: white;
    color: #263b2d;
    font-size: 14px;
}


/* ========================================
   BUTTONS
======================================== */

.btn {
    display: inline-block;
    border: none;
    border-radius: 8px;
    padding: 12px 18px;
    cursor: pointer;
    font-weight: bold;
    font-size: 13px;
    text-decoration: none;
    text-align: center;
}

.btn-primary {
    background: #247345;
    color: white;
}

.btn-secondary {
    background: #eaf0eb;
    color: #28563a;
}


/* ========================================
   TABLES
======================================== */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th {
    background: #e5f0e5;
    color: #28563a;
    text-align: left;
    padding: 14px 12px;
    font-size: 13px;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid #edf1ed;
    font-size: 13px;
}

tbody tr:hover {
    background: #f7faf7;
}

.price {
    color: #1c7540;
    font-weight: bold;
}


/* ========================================
   SELL ESTIMATOR
======================================== */

.estimator-grid {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 14px;
    align-items: end;
}

.estimate-result {
    display: none;
    background: #eaf5ec;
    border: 1px solid #cfe4d2;
    border-radius: 10px;
    padding: 18px;
    margin-top: 18px;
    color: #174f32;
}


/* ========================================
   NOTICE AND EMPTY STATE
======================================== */

.notice {
    background: #fff7e7;
    border: 1px solid #f3e5c5;
    color: #76521a;
    border-radius: 10px;
    padding: 16px;
    font-size: 13px;
    line-height: 1.7;
}

.empty-state {
    padding: 25px;
    text-align: center;
    color: #778379;
    background: #f8faf8;
    border-radius: 10px;
}


/* ========================================
   FOOTER
======================================== */

.page-footer {
    margin-top: 24px;
    color: #718075;
    font-size: 12px;
    text-align: center;
}


/* ========================================
   RESPONSIVE DESIGN
======================================== */

@media (max-width: 1100px) {

    .feature-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .filter-form {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

}

@media (max-width: 768px) {

    .app-layout {
        display: block;
    }

    .main-content {
        padding: 18px 13px 30px;
    }

    .stats-grid,
    .feature-grid,
    .estimator-grid,
    .filter-form {
        grid-template-columns: 1fr;
    }

    .welcome-banner h1 {
        font-size: 24px;
    }

    .banner-decoration {
        right: 15px;
        font-size: 65px;
    }

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



/* =========================================
   COMPACT MOBILE RESPONSIVE DESIGN
========================================= */
@media (max-width: 768px) {

    html {
        font-size: 14px;
        -webkit-text-size-adjust: 100%;
    }

    body {
        overflow-x: hidden;
    }

    .main-content,
    .main,
    .content,
    .page-content {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        padding: 12px !important;
    }

    .page-layout,
    .app-layout,
    .layout {
        display: block;
        width: 100%;
        min-width: 0;
    }

    h1 {
        font-size: 23px !important;
        line-height: 1.3;
    }

    h2 {
        font-size: 19px !important;
        line-height: 1.35;
    }

    h3 {
        font-size: 16px !important;
        line-height: 1.4;
    }

    h4 {
        font-size: 14px !important;
    }

    p, label, input, select, textarea, button {
        font-size: 13px;
        line-height: 1.5;
    }

    .hero,
    .welcome,
    .page-header,
    .market-hero {
        padding: 19px 15px !important;
        margin-bottom: 18px !important;
    }

    .hero-icon,
    .large-icon,
    .icon-large {
        font-size: 38px !important;
    }

    .card,
    .market-card,
    .panel,
    .content-card {
        padding: 15px !important;
        margin-bottom: 13px !important;
        border-radius: 13px;
        min-width: 0;
    }

    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 9px !important;
    }

    .stats-grid > * {
        padding: 12px !important;
        min-width: 0;
    }

    .form-grid,
    .form-row {
        grid-template-columns: 1fr !important;
        gap: 10px !important;
    }

    .form-group {
        width: 100%;
        max-width: 100% !important;
        margin: 10px 0 !important;
    }

    input, select, textarea {
        width: 100%;
        max-width: 100%;
        min-height: 42px;
        box-sizing: border-box;
    }

    button,
    .btn,
    .button {
        min-height: 40px;
        padding: 10px 14px !important;
        max-width: 100%;
    }

    .tabs,
    .tab-buttons,
    .panel-tabs {
        display: flex;
        gap: 7px;
        overflow-x: auto;
        white-space: nowrap;
        padding-bottom: 8px;
        -webkit-overflow-scrolling: touch;
    }

    .tabs > *,
    .tab-buttons > *,
    .panel-tabs > * {
        flex: 0 0 auto;
    }

    table {
        min-width: 520px;
    }

    .table-responsive,
    .table-scroll,
    .table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    img, canvas {
        max-width: 100%;
        height: auto;
    }

    .dashboard-back-link,
    .back-link {
        font-size: 13px;
        margin-bottom: 14px;
    }

    hr {
        margin: 18px 0 !important;
    }
}

@media (max-width: 380px) {
    .stats-grid {
        grid-template-columns: 1fr !important;
    }

    .main-content,
    .main,
    .content,
    .page-content {
        padding: 10px !important;
    }
}

</style>

<link rel="stylesheet" href="../assets/css/mobile-responsive.css">
</head>

<body>

<div class="app-layout">



<main class="main-content">

<a href="../dashboard.php" class="dashboard-back-link">← Back to Dashboard</a>

<div class="page-container">
    <!-- ====================================
     WELCOME BANNER
===================================== -->

<section class="welcome-banner">

    <div class="banner-decoration">🌾</div>

    <h1>🌾 Market Management</h1>

    <p>
        Explore crop prices, compare markets and make
        better buying and selling decisions using
        the market records stored in your application.
    </p>

</section>


<!-- ====================================
     STATISTICS
===================================== -->

<section class="stats-grid">

    <div class="stat-card">

        <div class="stat-icon">📊</div>

        <div>
            <strong><?= number_format($totalRecords) ?></strong>
            <small>Stored Market Records</small>
        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon">📅</div>

        <div>
            <strong><?= e($latestDateDisplay) ?></strong>
            <small>Latest Recorded Arrival Date</small>
        </div>

    </div>

</section>


<!-- ====================================
     FEATURE CARDS
===================================== -->

<div class="section-heading">

    <h2>What would you like to do?</h2>

    <p>
        Select an option. Only the information
        for your selected option will appear below.
    </p>

</div>


<section class="feature-grid">

    <a href="#sell-crop"
       class="feature-card"
       data-panel="sell-crop">

        <div class="feature-icon">🌾</div>

        <h3>Sell My Crop</h3>

        <p>
            List your crop and estimate its value
            using stored modal market prices.
        </p>

        <span class="feature-link">
            Sell My Crop →
        </span>

    </a>


    <a href="#market-records"
       class="feature-card"
       data-panel="market-records">

        <div class="feature-icon">🛒</div>

        <h3>Buy Crops</h3>

        <p>
            Explore available farmer crop listings
            and contact farmers directly.
        </p>

        <span class="feature-link">
            Explore Crops →
        </span>

    </a>


    <a href="#compare-markets"
       class="feature-card"
       data-panel="compare-markets">

        <div class="feature-icon">⚖️</div>

        <h3>Compare Markets</h3>

        <p>
            Compare recorded crop prices across
            different agricultural markets and districts.
        </p>

        <span class="feature-link">
            Market Comparison →
        </span>

    </a>


    <a href="#price-trends"
       class="feature-card"
       data-panel="price-trends">

        <div class="feature-icon">📈</div>

        <h3>Price Trends</h3>

        <p>
            Review recorded crop prices and arrival
            dates to understand price history.
        </p>

        <span class="feature-link">
            View Price History →
        </span>

    </a>


    <a href="#msp"
       class="feature-card"
       data-panel="msp">

        <div class="feature-icon">🏛️</div>

        <h3>MSP Information</h3>

        <p>
            View published seasonal Minimum Support Prices
            for notified agricultural crops.
        </p>

        <span class="feature-link">
            Check MSP Information →
        </span>

    </a>


    <a href="#ap-markets"
       class="feature-card"
       data-panel="ap-markets">

        <div class="feature-icon">📍</div>

        <h3>AP Markets</h3>

        <p>
            Explore stored agricultural market
            records district-wise across Andhra Pradesh.
        </p>

        <span class="feature-link">
            Explore Markets →
        </span>

    </a>

</section>


<!-- ====================================
     SELL MY CROP
===================================== -->

<section class="panel" id="sell-crop">

    <h2>🌾 Sell My Crop</h2>

    <p class="panel-intro">
        List your harvested crops for buyers. Your listing
        will appear in the Buy Crops workspace after submission.
    </p>

    <?php
    $listingStatus = $_GET['status'] ?? '';

    $statusMessages = [
        'success' => 'Your crop listing has been published successfully!',
        'invalid-data' => 'Please check all required fields.',
        'invalid-date' => 'Please enter a valid harvest date.',
        'invalid-image' => 'Upload a valid JPG, PNG or WEBP image.',
        'image-too-large' => 'Image size must not exceed 5 MB.',
        'image-error' => 'There was a problem uploading your image.',
        'security-error' => 'Security verification failed. Please try again.',
        'save-error' => 'Unable to save your listing. Please try again.',
        'missing-location' => 'Please enter your district.',
        'upload-folder-error' => 'Unable to prepare the image upload folder.'
    ];
    ?>

    <?php if (isset($statusMessages[$listingStatus])): ?>
        <div class="estimate-result"
             style="display:block; margin-bottom:20px;">
            <?= e($statusMessages[$listingStatus]) ?>
        </div>
    <?php endif; ?>

    <form
        action="sell-crop-submit.php"
        method="POST"
        enctype="multipart/form-data"
        class="listing-form"
    >

        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">

        <div class="estimator-grid">

            <div class="form-group">
                <label for="listingCrop">Crop Name *</label>

                <select name="crop_name" id="listingCrop" required>
                    <option value="">Select crop</option>

                    <?php foreach ($cropOptions as $crop): ?>
                        <option
                            value="<?= e((string)$crop) ?>"
                            <?= (($_GET['price_crop'] ?? '') === (string)$crop) ? 'selected' : '' ?>
                        >
                            <?= e((string)$crop) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>


            <div class="form-group">
                <label for="variety">Variety</label>

                <input type="text"
                       name="variety"
                       id="variety"
                       maxlength="150"
                       placeholder="e.g. BPT 5204">
            </div>


            <div class="form-group">
                <label for="listingQuantity">Quantity *</label>

                <input type="number"
                       name="quantity"
                       id="listingQuantity"
                       min="0.01"
                       step="0.01"
                       required
                       placeholder="Enter quantity">
            </div>


            <div class="form-group">
                <label for="quantityUnit">Unit *</label>

                <select name="quantity_unit" id="quantityUnit" required>
                    <option value="">Select unit</option>
                    <option value="kg">Kilograms (kg)</option>
                    <option value="quintal">Quintals</option>
                    <option value="tonne">Tonnes</option>
                </select>
            </div>


            <div class="form-group">
                <label for="expectedPrice">
                    Expected Price (₹ per unit) *
                </label>

                <input type="number"
                       name="expected_price"
                       id="expectedPrice"
                       min="0.01"
                       step="0.01"
                       required
                       placeholder="Enter expected price">
            </div>


            <div class="form-group">
                <label for="marketLocation">Market Location *</label>

                <input type="text"
                       name="market_location"
                       id="marketLocation"
                       maxlength="150"
                       required
                       placeholder="e.g. Nellore Agricultural Market">
            </div>


            <div class="form-group">
                <label for="village">Village</label>

                <input type="text"
                       name="village"
                       id="village"
                       maxlength="150"
                       placeholder="Enter village">
            </div>


            <div class="form-group">
                <label for="district">District *</label>

                <input type="text"
                       name="district"
                       id="district"
                       maxlength="100"
                       required
                       placeholder="Enter district">
            </div>


            <div class="form-group">
                <label for="harvestDate">Harvest Date</label>

                <input type="date"
                       name="harvest_date"
                       id="harvestDate">
            </div>


            <div class="form-group">
                <label for="cropQuality">Crop Quality *</label>

                <select name="crop_quality" id="cropQuality" required>
                    <option value="">Select quality</option>
                    <option value="Premium">Premium</option>
                    <option value="Good">Good</option>
                    <option value="Average">Average</option>
                </select>
            </div>


            <div class="form-group">
                <label for="cropImage">Crop Image</label>

                <input type="file"
                       name="crop_image"
                       id="cropImage"
                       accept="image/jpeg,image/png,image/webp">

                <small>JPG, PNG or WEBP. Maximum 5 MB.</small>
            </div>


            <div class="form-group">
                <label for="description">Crop Description</label>

                <textarea name="description"
                          id="description"
                          rows="4"
                          placeholder="Describe your crop..."></textarea>
            </div>

        </div>

        <button type="submit" class="btn btn-primary">
            Publish Crop Listing
        </button>

    </form>


    <!-- MATCHING MARKET PRICES -->

    <div class="market-price-results"
         id="cropMarketResults"
         style="margin-top:25px;">

        <h3>📊 Market Prices for Your Crop</h3>

        <p id="cropMarketMessage">
            Select a crop to view its recorded market prices.
        </p>

        <div id="cropAveragePrice"
             style="display:none; margin:15px 0; padding:18px;
                    background:#eaf5ed; border-radius:10px;">
        </div>

        <div style="overflow-x:auto;">

            <table class="market-table"
                   id="cropMarketTable"
                   style="display:none; width:100%;">

                <thead>
                    <tr>
                        <th>Crop</th>
                        <th>Market</th>
                        <th>District</th>
                        <th>Minimum Price</th>
                        <th>Maximum Price</th>
                        <th>Modal Price</th>
                        <th>Arrival Date</th>
                    </tr>
                </thead>

                <tbody id="cropMarketTableBody"></tbody>

            </table>

        </div>

    </div>

</section>


<!-- ====================================
     BUY CROPS / MARKET RECORDS
===================================== -->

<section class="panel" id="market-records">
        <h2>🛒 Available Market Prices</h2>

    <div style="margin:25px 0;">

        <h2>🛍️ Farmer Marketplace – Buy Crops</h2>

        <p class="panel-intro">
            Explore crops listed by farmers and contact them directly.
        </p>

        <form method="GET"
              action="market-management.php"
              class="filter-form">

            <input type="hidden"
                   name="section"
                   value="market-records">

            <div class="form-group">
                <label for="listingSearch">Search Crop or Location</label>

                <input
                    type="text"
                    name="listing_search"
                    id="listingSearch"
                    value="<?= e($listingSearch) ?>"
                    placeholder="Search Paddy, Banana, Nellore..."
                >
            </div>

            <button type="submit" class="btn btn-primary">
                Search Crops
            </button>

            <a href="market-management.php?section=market-records"
               class="btn btn-secondary">
                Clear
            </a>

        </form>

        <h3 style="margin:25px 0 15px;">
            Available Farmer Listings (<?= count($farmerListings) ?>)
        </h3>

        <?php if (!$farmerListings): ?>

            <p>No active farmer crop listings found.</p>

        <?php else: ?>

            <div style="
                display:grid;
                grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
                gap:20px;
            ">

                <?php foreach ($farmerListings as $listing): ?>

                    <div style="
                        background:#fff;
                        border:1px solid #dce9df;
                        border-radius:12px;
                        padding:16px;
                    ">

                        <h3 style="color:#195b36;margin-top:0;">
                            <?= e($listing['crop_name']) ?>
                        </h3>

                        <p>
                            <strong>Variety:</strong>
                            <?= e($listing['variety'] ?: 'Not specified') ?>
                        </p>

                        <p>
                            <strong>Quantity:</strong>
                            <?= number_format((float)$listing['quantity'], 2) ?>
                            <?= e($listing['quantity_unit']) ?>
                        </p>

                        <p>
                            <strong>Expected Price:</strong>
                            ₹<?= number_format((float)$listing['expected_price'], 2) ?>
                            / <?= e($listing['quantity_unit']) ?>
                        </p>

                        <p>
                            <strong>Quality:</strong>
                            <?= e($listing['crop_quality'] ?: 'Not specified') ?>
                        </p>

                        <p>
                            <strong>Location:</strong>
                            <?= e($listing['village'] ?: '—') ?>,
                            <?= e($listing['district']) ?>
                        </p>

                        <p>
                            <strong>Market:</strong>
                            <?= e($listing['market_location']) ?>
                        </p>

                        <p>
                            <strong>Farmer:</strong>
                            <?= e($listing['farmer_name']) ?>
                        </p>

                        <?php if (!empty($listing['description'])): ?>
                            <p><?= e($listing['description']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($listing['farmer_mobile'])): ?>

                            <a
                                href="tel:<?= e($listing['farmer_mobile']) ?>"
                                class="btn btn-primary"
                                style="display:inline-block;text-decoration:none;"
                            >
                                Contact Farmer
                            </a>

                        <?php else: ?>

                            <p>Contact number not available.</p>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>


    <hr style="margin:30px 0;">

    <h2>📊 Reference Market Prices</h2>

    <p>
        Compare farmer asking prices with recorded market prices below.
    </p>


    <form
        method="GET"
        action="market-management.php"
        class="filter-form">

        <input
            type="hidden"
            name="section"
            value="market-records"
        >

        <div class="form-group">

            <label for="crop">Crop Name</label>

            <select name="crop" id="crop">

                <option value="">All Crops</option>

                <?php foreach ($cropOptions as $crop): ?>

                    <option
                        value="<?= e((string)$crop) ?>"
                        <?= $cropFilter === $crop ? 'selected' : '' ?>>

                        <?= e((string)$crop) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="form-group">

            <label for="district">District</label>

            <select name="district" id="district">

                <option value="">All Districts</option>

                <?php foreach ($districtOptions as $district): ?>

                    <option
                        value="<?= e((string)$district) ?>"
                        <?= $districtFilter === $district ? 'selected' : '' ?>>

                        <?= e((string)$district) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="form-group">

            <label for="market">Market Name</label>

            <select name="market" id="market">

                <option value="">All Markets</option>

                <?php foreach ($marketOptions as $market): ?>

                    <option
                        value="<?= e((string)$market) ?>"
                        <?= $marketFilter === $market ? 'selected' : '' ?>>

                        <?= e((string)$market) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <button type="submit" class="btn btn-primary">
            Apply Filters
        </button>


        <a
            href="market-management.php#market-records"
            class="btn btn-secondary">

            Clear

        </a>

    </form>


    <div class="table-wrapper">

        <table>

            <thead>

                <tr>
                    <th>Crop</th>
                    <th>Variety</th>
                    <th>Market</th>
                    <th>District</th>
                    <th>Min Price</th>
                    <th>Max Price</th>
                    <th>Modal Price</th>
                    <th>Arrival Date</th>
                </tr>

            </thead>


            <tbody>

                <?php if (!$marketRecords): ?>

                    <tr>

                        <td colspan="8">

                            <div class="empty-state">
                                No market records found.
                                Try changing your filters.
                            </div>

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($marketRecords as $record): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= e((string)$record['crop_name']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= e((string)($record['variety'] ?? '—')) ?>
                            </td>

                            <td>
                                <?= e((string)$record['market_name']) ?>
                            </td>

                            <td>
                                <?= e((string)$record['district']) ?>
                            </td>

                            <td>
                                <?= marketMoney($record['min_price']) ?>
                            </td>

                            <td>
                                <?= marketMoney($record['max_price']) ?>
                            </td>

                            <td class="price">
                                <?= marketMoney($record['modal_price']) ?>
                            </td>

                            <td>
                                <?= marketDate($record['arrival_date']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>


    <p class="panel-intro">
        Showing up to 200 matching stored records.
    </p>

</section>


<!-- ====================================
     COMPARE MARKETS
===================================== -->

<section class="panel" id="compare-markets">

    <h2>⚖️ Market Price Comparison</h2>

    <p class="panel-intro">
        Compare recorded modal prices for the same
        crop across markets.
    </p>


    <div class="table-wrapper">

        <table>

            <thead>

                <tr>
                    <th>Crop</th>
                    <th>Market</th>
                    <th>District</th>
                    <th>Lowest Recorded Modal Price</th>
                    <th>Highest Recorded Modal Price</th>
                    <th>Average Modal Price</th>
                </tr>

            </thead>


            <tbody>

                <?php if (!$comparisonRecords): ?>

                    <tr>

                        <td colspan="6">

                            <div class="empty-state">
                                No comparison records available.
                            </div>

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($comparisonRecords as $item): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= e((string)$item['crop_name']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= e((string)$item['market_name']) ?>
                            </td>

                            <td>
                                <?= e((string)$item['district']) ?>
                            </td>

                            <td>
                                <?= marketMoney($item['min_modal']) ?>
                            </td>

                            <td>
                                <?= marketMoney($item['max_modal']) ?>
                            </td>

                            <td class="price">
                                <?= marketMoney($item['average_modal']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- ====================================
     PRICE TRENDS
===================================== -->

<section class="panel" id="price-trends">

    <h2>📈 Recorded Price History</h2>

    <p class="panel-intro">
        Recent stored market arrivals and their
        modal prices. This table shows recorded
        history, not a live price feed.
    </p>


    <div class="table-wrapper">

        <table>

            <thead>

                <tr>
                    <th>Crop</th>
                    <th>Market</th>
                    <th>District</th>
                    <th>Modal Price</th>
                    <th>Arrival Date</th>
                    <th>Source</th>
                </tr>

            </thead>


            <tbody>

                <?php if (!$marketRecords): ?>

                    <tr>

                        <td colspan="6">

                            <div class="empty-state">
                                No price history available.
                            </div>

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($marketRecords as $record): ?>

                        <tr>

                            <td>
                                <?= e((string)$record['crop_name']) ?>
                            </td>

                            <td>
                                <?= e((string)$record['market_name']) ?>
                            </td>

                            <td>
                                <?= e((string)$record['district']) ?>
                            </td>

                            <td class="price">
                                <?= marketMoney($record['modal_price']) ?>
                            </td>

                            <td>
                                <?= marketDate($record['arrival_date']) ?>
                            </td>

                            <td>
                                <?= e((string)($record['source_name'] ?? 'Not specified')) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- ====================================
     MSP INFORMATION
===================================== -->

<section class="panel" id="msp">

    <h2>🏛️ Minimum Support Price (MSP)</h2>

    <p class="panel-intro">
        Government of India Minimum Support Prices for notified
        agricultural crops. All prices are per quintal.
    </p>

    <div class="notice">

        <strong>What is MSP?</strong>

        <p>
            Minimum Support Price is the price announced by the
            Government of India for notified crops to protect
            farmers against a sharp fall in market prices.
        </p>

        <p>
            MSP is not an automatic guaranteed price from every
            private trader. Procurement depends on the applicable
            government scheme, crop quality, registration and
            procurement arrangements.
        </p>

    </div>


    <div class="stats-grid" style="margin:20px 0;">

        <div class="stat-card">

            <div class="stat-icon">🌾</div>

            <div>
                <strong>Kharif 2026–27</strong>
                <small>14 notified crop groups</small>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">🌱</div>

            <div>
                <strong>Rabi 2027–28</strong>
                <small>6 notified crops</small>
            </div>

        </div>

    </div>


    <div class="form-group" style="max-width:450px;margin:20px 0;">

        <label for="mspSearch">Search Crop</label>

        <input
            type="search"
            id="mspSearch"
            placeholder="Search Paddy, Maize, Groundnut..."
        >

    </div>


    <h3>🌧️ Kharif Marketing Season 2026–27</h3>

    <div class="table-wrapper">

        <table class="msp-table">

            <thead>
                <tr>
                    <th>Crop</th>
                    <th>MSP (₹/Quintal)</th>
                </tr>
            </thead>

            <tbody>
                <tr><td>Paddy (Common)</td><td>₹2,441</td></tr>
                <tr><td>Paddy (Grade A)</td><td>₹2,461</td></tr>
                <tr><td>Jowar (Hybrid)</td><td>₹4,023</td></tr>
                <tr><td>Jowar (Maldandi)</td><td>₹4,073</td></tr>
                <tr><td>Bajra</td><td>₹2,900</td></tr>
                <tr><td>Maize</td><td>₹2,410</td></tr>
                <tr><td>Ragi</td><td>₹5,205</td></tr>
                <tr><td>Tur (Arhar)</td><td>₹8,450</td></tr>
                <tr><td>Moong</td><td>₹8,780</td></tr>
                <tr><td>Urad</td><td>₹8,200</td></tr>
                <tr><td>Groundnut (in shell)</td><td>₹7,517</td></tr>
                <tr><td>Soyabean (Yellow)</td><td>₹5,708</td></tr>
                <tr><td>Sunflower Seed</td><td>₹8,343</td></tr>
                <tr><td>Sesamum</td><td>₹10,346</td></tr>
                <tr><td>Nigerseed</td><td>₹10,052</td></tr>
                <tr><td>Cotton (Medium Staple)</td><td>₹8,267</td></tr>
                <tr><td>Cotton (Long Staple)</td><td>₹8,667</td></tr>
            </tbody>

        </table>

    </div>


    <h3 style="margin-top:30px;">
        🌱 Rabi Marketing Season 2027–28
    </h3>

    <div class="table-wrapper">

        <table class="msp-table">

            <thead>
                <tr>
                    <th>Crop</th>
                    <th>MSP (₹/Quintal)</th>
                </tr>
            </thead>

            <tbody>
                <tr><td>Wheat</td><td>₹2,610</td></tr>
                <tr><td>Barley</td><td>₹2,286</td></tr>
                <tr><td>Gram</td><td>₹5,958</td></tr>
                <tr><td>Lentil (Masur)</td><td>₹7,390</td></tr>
                <tr><td>Rapeseed & Mustard</td><td>₹6,613</td></tr>
                <tr><td>Safflower</td><td>₹7,215</td></tr>
            </tbody>

        </table>

    </div>


    <div class="notice" style="margin-top:25px;">

        <strong>Important information for farmers</strong>

        <ul>
            <li>MSP rates are announced for specific marketing seasons.</li>
            <li>Prices are for notified crop quality and grades.</li>
            <li>Actual procurement is subject to government guidelines.</li>
            <li>Confirm procurement dates and centres with the relevant authorities.</li>
        </ul>

    </div>


    <h3 style="margin-top:25px;">Official Government Sources</h3>

    <ul>

        <li>
            <a href="https://desagri.gov.in/msp-session/2027-28/"
               target="_blank" rel="noopener">
                Government MSP notifications – Rabi 2027–28
            </a>
        </li>

        <li>
            <a href="https://desagri.gov.in/notification-type/kharif/"
               target="_blank" rel="noopener">
                Government MSP notifications – Kharif 2026–27
            </a>
        </li>

        <li>
            <a href="https://www.pib.gov.in/PressReleasePage.aspx?PRID=2260618"
               target="_blank" rel="noopener">
                PIB – Kharif MSP announcement
            </a>
        </li>

    </ul>

</section>


<!-- ====================================
     ANDHRA PRADESH MARKETS
===================================== -->

<?php
$apTotalRecords = count($apRecords);
$apDistricts = array_unique(array_filter(array_column($apRecords, 'district')));
$apMarkets = array_unique(array_filter(array_column($apRecords, 'market_name')));
?>

<section class="panel" id="ap-markets">

    <h2>📍 Andhra Pradesh Agricultural Markets</h2>

    <p class="panel-intro">
        Explore stored crop prices and market information across Andhra Pradesh.
    </p>


    <div class="stats-grid"
         style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin:20px 0;">

        <div class="stat-card">

            <div class="stat-icon">📊</div>

            <div>
                <strong><?= number_format($apTotalRecords) ?></strong>
                <small>Total Price Records</small>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">🏘️</div>

            <div>
                <strong><?= number_format(count($apDistricts)) ?></strong>
                <small>Districts Covered</small>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">🏪</div>

            <div>
                <strong><?= number_format(count($apMarkets)) ?></strong>
                <small>Markets Covered</small>
            </div>

        </div>

    </div>


    <div class="form-group" style="max-width:520px;margin:20px 0;">

        <label for="apMarketSearch">
            Search Andhra Pradesh Markets
        </label>

        <input
            type="search"
            id="apMarketSearch"
            placeholder="Search crop, market or district..."
            autocomplete="off"
        >

    </div>


    <div class="table-wrapper">

        <table id="apMarketTable">

            <thead>
                <tr>
                    <th>Market</th>
                    <th>District</th>
                    <th>State</th>
                    <th>Crop</th>
                    <th>Minimum Price</th>
                    <th>Maximum Price</th>
                    <th>Modal Price</th>
                    <th>Arrival Date</th>
                </tr>
            </thead>

            <tbody>

                <?php if (!$apRecords): ?>

                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                No Andhra Pradesh market records found.
                                Check that the state column contains
                                "Andhra Pradesh".
                            </div>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($apRecords as $record): ?>

                        <tr>
                            <td><?= e((string)$record['market_name']) ?></td>
                            <td><?= e((string)$record['district']) ?></td>
                            <td><?= e((string)$record['state']) ?></td>
                            <td><?= e((string)$record['crop_name']) ?></td>
                            <td><?= marketMoney($record['min_price']) ?></td>
                            <td><?= marketMoney($record['max_price']) ?></td>
                            <td class="price"><?= marketMoney($record['modal_price']) ?></td>
                            <td><?= marketDate($record['arrival_date']) ?></td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>


        <p id="apNoResults"
           class="empty-state"
           style="display:none;">
            No matching Andhra Pradesh market records found.
        </p>

    </div>


    <p class="panel-intro" style="margin-top:15px;">
        Showing up to 200 stored Andhra Pradesh market records.
        Prices are database records, not a live feed.
    </p>

</section>
<!-- ====================================
     MARKET NOTICE
===================================== -->

<div class="notice" style="margin-top:22px">

    <strong>Market Information Notice</strong>

    <br>

    Market prices depend on records stored in
    the database.

    Demonstration prices must not be treated
    as actual daily market quotations.

    Live market integration is not configured.

</div>


<footer class="page-footer">

    Organic Farming Management System |
    Market Management

</footer>

</div>

</main>

</div>


<!-- ====================================
     JAVASCRIPT
===================================== -->

<script>

'use strict';


// ========================================
// MARKET PRICE DATA
// ========================================

const marketPrices = <?= json_encode(
    array_map(
        static function ($record) {
            return [
                'crop' => (string)$record['crop_name'],
                'price' => (float)$record['modal_price']
            ];
        },
        $marketRecords
    ),
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
) ?>;


// ========================================
// HTML ESCAPE FUNCTION
// ========================================

function escapeHTML(value) {

    return String(value).replace(
        /[&<>"']/g,
        character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[character])
    );

}


// ========================================
// SELL CROP PRICE ESTIMATOR
// ========================================

function calculateEstimate() {

    const crop = document.getElementById('estimateCrop').value;

    const quantity = Number.parseFloat(
        document.getElementById('quantity').value
    );

    const result = document.getElementById('estimateResult');


    if (!crop) {
        alert('Please select a crop.');
        return;
    }


    if (!Number.isFinite(quantity) || quantity <= 0) {
        alert('Please enter a valid quantity.');
        return;
    }


    const matchingRecords = marketPrices.filter(function(item) {
        return item.crop === crop && item.price > 0;
    });


    result.style.display = 'block';


    if (matchingRecords.length === 0) {

        result.textContent =
            'No matching modal price is available in the currently loaded records. Clear filters or add a market record for this crop.';

        return;
    }


    const averagePrice = matchingRecords.reduce(
        function(total, item) {
            return total + item.price;
        },
        0
    ) / matchingRecords.length;


    const estimatedValue = averagePrice * quantity;


    const formatINR = value => value.toLocaleString(
        'en-IN',
        { maximumFractionDigits: 2 }
    );


    result.innerHTML =

        '<h3>Estimated Crop Value</h3>' +

        '<p>Crop: <strong>' +
        escapeHTML(crop) +
        '</strong></p>' +

        '<p>Quantity: <strong>' +
        quantity.toFixed(2) +
        ' quintals</strong></p>' +

        '<p>Average stored modal price: <strong>₹' +
        formatINR(averagePrice) +
        '</strong></p>' +

        '<h2>Estimated Total: ₹' +
        formatINR(estimatedValue) +
        '</h2>' +

        '<small>Estimate based on loaded stored records, not a guaranteed selling price.</small>';

}


// ========================================
// MARKET MANAGEMENT PANEL SWITCHING
// ========================================

document.addEventListener('DOMContentLoaded', function() {

    const cards = Array.from(
        document.querySelectorAll('.feature-card[data-panel]')
    );

    const panels = Array.from(
        document.querySelectorAll('.panel[id]')
    );

    const validIds = new Set(
        panels.map(panel => panel.id)
    );


    function showPanel(id, updateAddress = true) {

        if (!validIds.has(id)) {
            return;
        }


        panels.forEach(panel => {

            panel.classList.toggle(
                'is-active',
                panel.id === id
            );

        });


        cards.forEach(card => {

            const selected = card.dataset.panel === id;

            card.classList.toggle(
                'is-selected',
                selected
            );

            card.setAttribute(
                'aria-current',
                selected ? 'true' : 'false'
            );

        });


        if (updateAddress) {

            const url = new URL(window.location.href);

            url.hash = id;

            history.replaceState(
                null,
                '',
                url.pathname + url.search + url.hash
            );

        }


        const activePanel = document.getElementById(id);

        if (activePanel) {

            activePanel.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        }

    }


    // FEATURE CARD CLICK EVENTS

    cards.forEach(card => {

        card.addEventListener('click', function(event) {

            event.preventDefault();

            showPanel(this.dataset.panel, true);

        });

    });


    // RESTORE PANEL FROM URL

    const querySection = new URLSearchParams(
        window.location.search
    ).get('section');

    const hashSection = window.location.hash.replace(/^#/, '');

    const initialSection = validIds.has(hashSection)
        ? hashSection
        : querySection;

    if (initialSection && validIds.has(initialSection)) {

        showPanel(initialSection, false);

    }


    // BACK AND FORWARD NAVIGATION

    window.addEventListener('hashchange', function() {

        const id = window.location.hash.replace(/^#/, '');

        if (validIds.has(id)) {
            showPanel(id, false);
        }

    });


    // CALCULATOR BUTTON

    document.getElementById('calculateButton')
        ?.addEventListener('click', calculateEstimate);

});

</script>


<!-- ====================================
     CROP MARKET PRICE MATCHING
===================================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const cropSelect = document.getElementById('listingCrop');
    const message = document.getElementById('cropMarketMessage');
    const averageBox = document.getElementById('cropAveragePrice');
    const table = document.getElementById('cropMarketTable');
    const tableBody = document.getElementById('cropMarketTableBody');

    if (!cropSelect) return;


    function money(value) {

        if (value === null || value === '') return '—';

        return '₹' + Number(value).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

    }


    async function loadCropPrices(crop = '') {

        table.style.display = 'none';
        averageBox.style.display = 'none';
        tableBody.innerHTML = '';

        message.textContent = 'Loading market prices...';


        try {

            const response = await fetch(
                'get-crop-market-prices.php?crop=' +
                encodeURIComponent(crop)
            );

            const data = await response.json();


            if (!response.ok || data.error) {
                throw new Error(data.error || 'Unable to load prices.');
            }


            if (!data.records || data.records.length === 0) {

                message.textContent = crop
                    ? 'No market records found for ' + crop + '.'
                    : 'No market price records are available yet.';

                return;
            }


            message.textContent = crop
                ? 'Market prices for ' + crop
                : 'Showing all available crop market prices (latest 100 records).';


            if (data.average_modal !== null) {

                averageBox.textContent =
                    (crop
                        ? 'Average Modal Price for ' + crop + ': '
                        : 'Average Modal Price across stored records: '
                    ) + money(data.average_modal);

                averageBox.style.display = 'block';

            }


            data.records.forEach(function (record) {

                const row = document.createElement('tr');

                [
                    record.crop_name,
                    record.market_name,
                    record.district,
                    money(record.min_price),
                    money(record.max_price),
                    money(record.modal_price),
                    record.arrival_date
                ].forEach(function (value) {

                    const cell = document.createElement('td');

                    cell.textContent = value ?? '—';

                    row.appendChild(cell);

                });

                tableBody.appendChild(row);

            });


            table.style.display = 'table';

        } catch (error) {

            message.textContent =
                error.message || 'Unable to retrieve market prices.';

        }

    }


    cropSelect.addEventListener('change', function () {

        loadCropPrices(this.value);

    });


    // LOAD MATCHING PRICES AFTER SUBMISSION

    const submittedCrop = new URLSearchParams(
        window.location.search
    ).get('price_crop');


    if (submittedCrop) {

        cropSelect.value = submittedCrop;

        loadCropPrices(submittedCrop);

    } else {

        loadCropPrices('');

    }

});

</script>


<!-- ====================================
     MSP SEARCH
===================================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const search = document.getElementById('mspSearch');

    if (!search) return;


    search.addEventListener('input', function () {

        const query = this.value.toLowerCase().trim();

        document.querySelectorAll('.msp-table tbody tr')
            .forEach(function (row) {

                row.style.display =
                    row.textContent.toLowerCase().includes(query)
                    ? ''
                    : 'none';

            });

    });

});

</script>


<!-- ====================================
     ANDHRA PRADESH MARKET SEARCH
===================================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const search = document.getElementById('apMarketSearch');

    const table = document.getElementById('apMarketTable');

    const noResults = document.getElementById('apNoResults');


    if (!search || !table) return;


    search.addEventListener('input', function () {

        const query = this.value.toLowerCase().trim();

        const rows = table.querySelectorAll('tbody tr');

        let visibleRows = 0;


        rows.forEach(function (row) {

            const matches = row.textContent
                .toLowerCase()
                .includes(query);

            row.style.display = matches ? '' : 'none';

            if (matches) {
                visibleRows++;
            }

        });


        if (noResults) {

            noResults.style.display =
                visibleRows === 0 ? 'block' : 'none';

        }

    });

});

</script>

</body>
</html>