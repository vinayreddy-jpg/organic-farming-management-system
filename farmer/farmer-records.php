<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

$user = current_user();

if (!$user || ($user['role'] ?? '') !== 'farmer') {
    redirect('auth/login.php');
}

$userId = (int)($user['id'] ?? 0);

if ($userId <= 0) {
    redirect('auth/login.php');
}

$pdo = db();

/* =========================================
   FARMER PROFILE
========================================= */

$profileStmt = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.username,
        u.email,
        u.mobile,
        u.district AS user_district,
        u.village AS user_village,
        u.status,
        u.created_at,
        fp.phone,
        fp.village AS profile_village,
        fp.district AS profile_district,
        fp.land_area,
        fp.soil_type
    FROM users u
    LEFT JOIN farmer_profiles fp
        ON fp.user_id = u.id
    WHERE u.id = :user_id
      AND u.role = 'farmer'
    LIMIT 1
");

$profileStmt->execute(['user_id' => $userId]);
$farmer = $profileStmt->fetch();

if (!$farmer) {
    http_response_code(404);
    exit('Farmer profile not found.');
}

$phone = $farmer['phone'] ?: $farmer['mobile'];
$village = $farmer['profile_village'] ?: $farmer['user_village'];
$district = $farmer['profile_district'] ?: $farmer['user_district'];

/* =========================================
   FARMER CROP RECORDS
========================================= */

$cropStmt = $pdo->prepare("
    SELECT
        id,
        crop_name,
        crop_type,
        soil_type,
        planting_date,
        fertilizer,
        description,
        field_name,
        field_village,
        field_district,
        field_state,
        created_at
    FROM crop_records
    WHERE user_id = :user_id
    ORDER BY created_at DESC
");

$cropStmt->execute(['user_id' => $userId]);
$crops = $cropStmt->fetchAll();

/* =========================================
   FARMER CROP LISTINGS
========================================= */

$listingStmt = $pdo->prepare("
    SELECT
        id,
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
        status,
        created_at
    FROM crop_listings
    WHERE farmer_id = :farmer_id
    ORDER BY created_at DESC
");

$listingStmt->execute(['farmer_id' => $userId]);
$listings = $listingStmt->fetchAll();

/* =========================================
   SUMMARY STATISTICS
========================================= */

$totalCrops = count($crops);
$totalListings = count($listings);

$activeListings = 0;
$soldListings = 0;

foreach ($listings as $listing) {
    if ($listing['status'] === 'active') {
        $activeListings++;
    }

    if ($listing['status'] === 'sold') {
        $soldListings++;
    }
}

function recordDate($date): string
{
    if (!$date) {
        return 'Not provided';
    }

    $timestamp = strtotime((string)$date);

    return $timestamp
        ? date('d M Y', $timestamp)
        : 'Not provided';
}

function recordMoney($amount): string
{
    if ($amount === null || $amount === '') {
        return 'Not provided';
    }

    return '₹' . number_format((float)$amount, 2);
}

function recordText($value): string
{
    return ($value !== null && trim((string)$value) !== '')
        ? e((string)$value)
        : 'Not provided';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Farmer Records | Organic Farming</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f3f7f2;
    color: #26382c;
}

.page {
    width: 100%;
    min-height: 100vh;
    padding: 28px 4%;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.brand {
    color: #194d30;
    font-size: 23px;
    font-weight: 800;
}

.back-link {
    display: inline-block;
    background: #194d30;
    color: white;
    text-decoration: none;
    padding: 11px 18px;
    border-radius: 9px;
    font-weight: 600;
}

.back-link:hover {
    background: #276b43;
}

.welcome {
    background: linear-gradient(120deg, #194d30, #347b4d);
    color: white;
    padding: 30px;
    border-radius: 18px;
    margin-bottom: 25px;
}

.welcome h1 {
    margin: 0 0 10px;
    font-size: 30px;
}

.welcome p {
    margin: 0;
    line-height: 1.6;
    color: #e2f0e5;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}

.stat-card {
    background: white;
    padding: 22px;
    border-radius: 14px;
    box-shadow: 0 3px 14px rgba(0,0,0,0.05);
    border: 1px solid #e5ece5;
}

.stat-card .icon {
    font-size: 27px;
}

.stat-card h2 {
    color: #194d30;
    font-size: 28px;
    margin: 12px 0 5px;
}

.stat-card p {
    color: #748176;
    margin: 0;
    font-size: 14px;
}

.section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 3px 14px rgba(0,0,0,0.045);
    border: 1px solid #e5ece5;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.section-heading h2 {
    color: #194d30;
    font-size: 21px;
    margin: 0;
}

.profile-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.detail {
    background: #f7faf6;
    padding: 16px;
    border-radius: 10px;
    border: 1px solid #e8eee7;
    min-width: 0;
}

.detail label {
    display: block;
    color: #778579;
    font-size: 12px;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: .4px;
}

.detail strong {
    color: #26382c;
    font-size: 15px;
    overflow-wrap: anywhere;
}

.status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
}

.status.active {
    color: #176b36;
    background: #e2f5e7;
}

.status.inactive {
    color: #a33b32;
    background: #fde9e7;
}

.status.sold {
    color: #245c9c;
    background: #e6f0ff;
}

.status.removed {
    color: #777;
    background: #eeeeee;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th {
    background: #edf4ed;
    color: #194d30;
    text-align: left;
    padding: 14px;
    font-size: 13px;
    white-space: nowrap;
}

td {
    padding: 14px;
    border-bottom: 1px solid #edf0ed;
    font-size: 13px;
    vertical-align: top;
}

tr:hover td {
    background: #fbfdfb;
}

.empty {
    padding: 30px 15px;
    text-align: center;
    color: #78847a;
    background: #f8faf7;
    border-radius: 10px;
    line-height: 1.7;
}

.footer {
    text-align: center;
    color: #89958a;
    font-size: 13px;
    padding: 15px;
}

@media(max-width: 900px) {
    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .profile-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 560px) {
    .page {
        padding: 18px 4%;
    }

    .welcome {
        padding: 22px;
    }

    .welcome h1 {
        font-size: 24px;
    }

    .stats {
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-card h2 {
        font-size: 23px;
    }

    .profile-grid {
        grid-template-columns: 1fr;
    }

    .section {
        padding: 17px;
    }
}

</style>

<link rel="stylesheet" href="/organic-farming/assets/css/mobile-responsive.css">
</head>

<body>

<div class="page">

    <div class="topbar">

        <div class="brand">
            🌱 Organic Farming
        </div>

        <a href="../dashboard.php" class="back-link">
            ← Back to Dashboard
        </a>

    </div>


    <section class="welcome">

        <h1>👨‍🌾 My Farmer Records</h1>

        <p>
            Welcome, <?= e($farmer['name']) ?>.
            View your personal details, farm information,
            registered crops and crop listings in one place.
        </p>

    </section>


    <!-- SUMMARY CARDS -->

    <div class="stats">

        <div class="stat-card">
            <div class="icon">🌾</div>
            <h2><?= $totalCrops ?></h2>
            <p>My Registered Crops</p>
        </div>

        <div class="stat-card">
            <div class="icon">🛒</div>
            <h2><?= $totalListings ?></h2>
            <p>My Crop Listings</p>
        </div>

        <div class="stat-card">
            <div class="icon">🟢</div>
            <h2><?= $activeListings ?></h2>
            <p>Active Listings</p>
        </div>

        <div class="stat-card">
            <div class="icon">✅</div>
            <h2><?= $soldListings ?></h2>
            <p>Sold Listings</p>
        </div>

    </div>


    <!-- PERSONAL DETAILS -->

    <section class="section">

        <div class="section-heading">
            <h2>👤 Personal Information</h2>
        </div>

        <div class="profile-grid">

            <div class="detail">
                <label>Farmer Name</label>
                <strong><?= recordText($farmer['name']) ?></strong>
            </div>

            <div class="detail">
                <label>Username</label>
                <strong><?= recordText($farmer['username']) ?></strong>
            </div>

            <div class="detail">
                <label>Mobile Number</label>
                <strong><?= recordText($phone) ?></strong>
            </div>

            <div class="detail">
                <label>Email Address</label>
                <strong><?= recordText($farmer['email']) ?></strong>
            </div>

            <div class="detail">
                <label>Account Status</label>
                <strong>
                    <span class="status <?= e($farmer['status']) ?>">
                        <?= e($farmer['status']) ?>
                    </span>
                </strong>
            </div>

            <div class="detail">
                <label>Registration Date</label>
                <strong><?= recordDate($farmer['created_at']) ?></strong>
            </div>

        </div>

    </section>


    <!-- FARM DETAILS -->

    <section class="section">

        <div class="section-heading">
            <h2>🌱 My Farm Information</h2>
        </div>

        <div class="profile-grid">

            <div class="detail">
                <label>Village</label>
                <strong><?= recordText($village) ?></strong>
            </div>

            <div class="detail">
                <label>District</label>
                <strong><?= recordText($district) ?></strong>
            </div>

            <div class="detail">
                <label>Land Area</label>
                <strong>
                    <?= $farmer['land_area'] !== null
                        ? e((string)$farmer['land_area']) . ' acres'
                        : 'Not provided' ?>
                </strong>
            </div>

            <div class="detail">
                <label>Soil Type</label>
                <strong><?= recordText($farmer['soil_type']) ?></strong>
            </div>

            <div class="detail">
                <label>State</label>
                <strong>Andhra Pradesh</strong>
            </div>

        </div>

    </section>


    <!-- REGISTERED CROPS -->

    <section class="section">

        <div class="section-heading">
            <h2>🌾 My Registered Crop Records</h2>
        </div>

        <?php if (!$crops): ?>

            <div class="empty">
                <h3>No crop records found</h3>
                Your registered crops will appear here
                when you add them through Crop Management.
            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Crop Name</th>
                            <th>Crop Type</th>
                            <th>Field Name</th>
                            <th>Soil Type</th>
                            <th>Planting Date</th>
                            <th>Fertilizer</th>
                            <th>Location</th>
                            <th>Added On</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($crops as $crop): ?>

                        <tr>

                            <td><strong><?= recordText($crop['crop_name']) ?></strong></td>

                            <td><?= recordText($crop['crop_type']) ?></td>

                            <td><?= recordText($crop['field_name']) ?></td>

                            <td><?= recordText($crop['soil_type']) ?></td>

                            <td><?= recordDate($crop['planting_date']) ?></td>

                            <td><?= recordText($crop['fertilizer']) ?></td>

                            <td>
                                <?= recordText($crop['field_village']) ?>,
                                <?= recordText($crop['field_district']) ?>
                            </td>

                            <td><?= recordDate($crop['created_at']) ?></td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>


    <!-- CROP LISTINGS -->

    <section class="section">

        <div class="section-heading">
            <h2>🛒 My Crop Listings</h2>
        </div>

        <?php if (!$listings): ?>

            <div class="empty">
                <h3>No crop listings found</h3>
                Crops you list for sale through Market Management
                will appear here.
            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Crop</th>
                            <th>Variety</th>
                            <th>Quantity</th>
                            <th>Expected Price</th>
                            <th>Market</th>
                            <th>Harvest Date</th>
                            <th>Quality</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($listings as $listing): ?>

                        <tr>

                            <td><strong><?= recordText($listing['crop_name']) ?></strong></td>

                            <td><?= recordText($listing['variety']) ?></td>

                            <td>
                                <?= number_format((float)$listing['quantity'], 2) ?>
                                <?= e($listing['quantity_unit']) ?>
                            </td>

                            <td><?= recordMoney($listing['expected_price']) ?></td>

                            <td>
                                <?= recordText($listing['market_location']) ?>,
                                <?= recordText($listing['district']) ?>
                            </td>

                            <td><?= recordDate($listing['harvest_date']) ?></td>

                            <td><?= recordText($listing['crop_quality']) ?></td>

                            <td>
                                <span class="status <?= e($listing['status']) ?>">
                                    <?= e($listing['status']) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>


    <div class="footer">
        Organic Farming Management System |
        My Farmer Records
    </div>

</div>

</body>
</html>
