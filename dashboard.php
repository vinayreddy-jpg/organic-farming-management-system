<?php
declare(strict_types=1);

// ==========================================
// AUTHENTICATION AND DATABASE
// ==========================================

require_once __DIR__ . '/auth/bootstrap.php';
require_once __DIR__ . '/config/database.php';

// Check login
if (empty($_SESSION['user']['id'])) {
    redirect('auth/login.php');
}

// Logged-in user
$loggedInUser = $_SESSION['user'];

$displayName = trim(
    (string)($loggedInUser['username'] ?? '')
);

if ($displayName === '') {
    $displayName = trim(
        (string)($loggedInUser['name'] ?? 'Farmer')
    );
}

// Escape output safely
function dashboardEscape($value): string {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

require_once __DIR__ . '/dashboard-farmer-data.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Organic Farming | Dashboard</title>

<style>

/* ==========================================
   GLOBAL STYLES
========================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f7f2;
    color: #263b2a;
    overflow-x: hidden;
}

button {
    font-family: inherit;
}

.layout {
    display: flex;
    min-height: 100vh;
}

/* ==========================================
   SIDEBAR
========================================== */

.sidebar {
    width: 270px;
    min-width: 270px;
    background: #194d30;
    color: white;
    padding: 28px 18px;

    position: fixed;
    top: 0;
    left: 0;

    height: 100vh;
    overflow-y: auto;

    z-index: 2000;

    transition: transform 0.3s ease;
}

.logo {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 40px;
    line-height: 1.4;
}

.logo span {
    color: #a9e6a9;
}

.sidebar h4 {
    color: #b8d1bd;
    font-size: 13px;
    letter-spacing: 1.5px;
    margin: 25px 12px 12px;
}

.sidebar a {
    display: block;
    padding: 14px 12px;

    color: #e3eee5;
    text-decoration: none;

    border-radius: 8px;
    margin-bottom: 7px;

    font-size: 15px;
    font-weight: 600;
    line-height: 1.5;

    transition: 0.2s ease;
}

.sidebar a:hover,
.sidebar a.active {
    background: #32834a;
    color: white;
}

/* ==========================================
   MOBILE SIDEBAR OVERLAY
========================================== */

.sidebar-overlay {
    display: none;
}

/* ==========================================
   MAIN CONTENT
========================================== */

.main {
    margin-left: 270px;
    width: calc(100% - 270px);
    min-width: 0;

    padding: 30px 35px;
}

/* ==========================================
   TOP HEADER
========================================== */

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 20px;
    margin-bottom: 35px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.topbar h1 {
    font-size: 27px;
    color: #194d30;
}

.topbar p {
    color: #78877b;
    margin-top: 7px;
    font-size: 14px;
}

/* Hamburger button */

.menu-toggle {
    display: none;

    width: 44px;
    height: 44px;

    border: none;
    border-radius: 9px;

    background: #194d30;
    color: white;

    font-size: 25px;
    cursor: pointer;

    flex-shrink: 0;
}

/* ==========================================
   PROFILE DROPDOWN
========================================== */

.profile {
    position: relative;
    z-index: 1001;
    flex-shrink: 0;
}

.profile-btn {
    display: flex;
    align-items: center;
    gap: 11px;

    background: #ffffff;
    color: #194d30;

    border: 1px solid #dce9de;

    padding: 9px 16px 9px 10px;

    border-radius: 12px;

    font-size: 15px;
    font-weight: 600;

    cursor: pointer;

    box-shadow: 0 3px 12px rgba(25,77,48,0.07);

    transition: 0.25s ease;
}

.profile-btn .user-icon {
    width: 36px;
    height: 36px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;
    background: #e8f4e9;

    font-size: 18px;
}

.profile-btn:hover {
    background: #f3f8f3;
    border-color: #9fc8a7;
}

.profile-dropdown {
    display: none;

    position: absolute;
    right: 0;
    top: calc(100% + 12px);

    width: 255px;

    background: #ffffff;
    border: 1px solid #e2ebe3;

    border-radius: 14px;
    padding: 9px;

    z-index: 2100;

    box-shadow: 0 12px 35px rgba(25,77,48,0.15);
}

.profile-dropdown.show {
    display: block;
}

.profile-dropdown a {
    display: flex;
    align-items: center;
    gap: 12px;

    padding: 13px 12px;

    color: #344b39;
    text-decoration: none;

    border-radius: 9px;

    font-size: 14px;
    font-weight: 500;
}

.profile-dropdown a:hover {
    background: #edf6ee;
    color: #194d30;
}

.profile-dropdown .logout {
    color: #d94343;
}

.profile-dropdown .logout:hover {
    background: #fff0f0;
    color: #c52e2e;
}

.profile-divider {
    height: 1px;
    background: #e8eee8;
    margin: 6px 4px;
}

/* ==========================================
   WELCOME SECTION
========================================== */

.welcome {
    background: linear-gradient(
        120deg,
        #194d30,
        #087b45
    );

    padding: 35px;
    color: white;

    border-radius: 16px;
    margin-bottom: 32px;
}

.welcome h3 {
    font-size: 17px;
    margin-bottom: 10px;
}

.welcome h2 {
    font-size: 27px;
    margin-bottom: 12px;
    line-height: 1.4;
}

.welcome p {
    color: #e0f0e3;
    line-height: 1.7;
    max-width: 650px;
    font-size: 15px;
}

/* ==========================================
   FARM OVERVIEW
========================================== */

.section-title {
    font-size: 22px;
    color: #194d30;
    margin-bottom: 20px;
}

.stats {
    display: grid;

    grid-template-columns: repeat(4, minmax(0, 1fr));

    gap: 18px;
    margin-bottom: 35px;
}

.stat-card {
    background: white;

    padding: 23px;

    border-radius: 14px;

    box-shadow: 0 3px 12px #00000008;

    border: 1px solid #edf1eb;

    min-width: 0;
}

.stat-icon {
    font-size: 28px;
    margin-bottom: 15px;
}

.stat-card h3 {
    color: #718073;
    font-size: 13px;
    font-weight: normal;
    margin-bottom: 10px;
}

.stat-card strong {
    font-size: 29px;
    color: #194d30;
    overflow-wrap: anywhere;
}

/* ==========================================
   DASHBOARD INFORMATION GRID
========================================== */

.dashboard-grid {
    display: grid;

    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 20px;
    margin-bottom: 25px;
}

.info-card {
    background: #ffffff;

    border: 1px solid #e3ece3;

    border-radius: 14px;
    padding: 23px;

    box-shadow: 0 3px 12px #00000008;

    min-width: 0;
}

.info-card h3 {
    color: #194d30;
    font-size: 19px;
    margin-bottom: 20px;
}

.crop-details h2 {
    color: #194d30;
    font-size: 21px;
    margin-bottom: 16px;
    overflow-wrap: anywhere;
}

.crop-details p,
.farmer-details p {
    margin: 12px 0;
    color: #536456;
    font-size: 14px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.crop-details strong,
.farmer-details strong {
    color: #194d30;
}

.crop-status {
    display: inline-block;

    padding: 5px 12px;

    background: #e3f5e6;
    color: #21833b;

    border-radius: 20px;

    font-size: 12px;
    font-weight: bold;
    vertical-align: middle;
}

.info-card a {
    display: inline-block;
    margin-top: 12px;

    color: #258044;

    font-size: 14px;
    font-weight: 600;

    text-decoration: none;
}

.info-card a:hover {
    color: #194d30;
    text-decoration: underline;
}

/* ==========================================
   FARMER STATISTICS
========================================== */

.farmer-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.farmer-stat {
    background: #f2f8f2;

    padding: 16px 10px;

    text-align: center;
    border-radius: 10px;

    min-width: 0;
}

.farmer-stat strong {
    display: block;

    font-size: 25px;
    color: #194d30;

    margin-top: 8px;
}

.farmer-stat span {
    font-size: 12px;
    color: #667568;
}

/* ==========================================
   FOOTER
========================================== */

footer {
    margin-top: 40px;
    padding: 18px 5px;

    text-align: center;

    color: #879187;
    font-size: 12px;
    line-height: 1.6;
}

/* ==========================================
   TABLET RESPONSIVE
========================================== */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .main {
        padding: 25px;
    }
}

/* ==========================================
   MOBILE RESPONSIVE
========================================== */

@media (max-width: 700px) {

    html,
    body {
        width: 100%;
        overflow-x: hidden;
    }

    .layout {
        display: block;
        width: 100%;
    }

    /* Slide-out sidebar */

    .sidebar {
        width: 280px;
        min-width: 0;
        max-width: 85vw;

        height: 100vh;

        position: fixed;
        top: 0;
        left: 0;

        padding: 25px 18px;

        overflow-y: auto;

        transform: translateX(-100%);

        transition: transform 0.3s ease;

        z-index: 2000;
    }

    .sidebar.open {
        transform: translateX(0);
    }

    .sidebar .logo {
        font-size: 23px;
        margin-bottom: 35px;
    }

    .sidebar h4 {
        display: block;
        font-size: 12px;
    }

    .sidebar a {
        display: block;
        font-size: 15px;
        padding: 14px 12px;
        white-space: normal;
    }

    /* Dark overlay */

    .sidebar-overlay.show {
        display: block;

        position: fixed;
        inset: 0;

        background: rgba(0,0,0,0.45);

        z-index: 1999;
    }

    /* Main content */

    .main {
        margin-left: 0;

        width: 100%;
        min-width: 0;

        padding: 18px 14px;
    }

    /* Header */

    .topbar {
        display: flex;
        flex-wrap: wrap;

        align-items: center;
        justify-content: space-between;

        gap: 12px;

        margin-bottom: 25px;
    }

    .header-left {
        flex: 1;
        gap: 10px;
    }

    .menu-toggle {
        display: block;
    }

    .topbar h1 {
        font-size: 22px;
    }

    .topbar p {
        font-size: 12px;
        line-height: 1.4;
    }

    .profile {
        margin-left: auto;
    }

    .profile-btn {
        padding: 7px 10px;
        gap: 7px;
        font-size: 13px;
    }

    .profile-btn .user-icon {
        width: 31px;
        height: 31px;
        font-size: 16px;
    }

    .profile-dropdown {
        width: min(255px, calc(100vw - 30px));
    }

    /* Welcome */

    .welcome {
        padding: 24px 19px;
        margin-bottom: 25px;
        border-radius: 13px;
    }

    .welcome h3 {
        font-size: 15px;
    }

    .welcome h2 {
        font-size: 21px;
        line-height: 1.4;
    }

    .welcome p {
        font-size: 14px;
        line-height: 1.7;
    }

    /* Statistics */

    .section-title {
        font-size: 20px;
    }

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .stat-card {
        padding: 17px 14px;
    }

    .stat-icon {
        font-size: 24px;
        margin-bottom: 12px;
    }

    .stat-card h3 {
        font-size: 12px;
        line-height: 1.5;
    }

    .stat-card strong {
        font-size: 25px;
    }

    /* Information cards */

    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .info-card {
        padding: 19px 16px;
    }

    .info-card h3 {
        font-size: 18px;
    }

    .crop-details h2 {
        font-size: 19px;
        line-height: 1.5;
    }

    .crop-details p,
    .farmer-details p {
        font-size: 13px;
    }

    .farmer-stat-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 7px;
    }

    .farmer-stat {
        padding: 13px 5px;
    }

    .farmer-stat span {
        font-size: 10px;
    }

    .farmer-stat strong {
        font-size: 21px;
    }

    footer {
        margin-top: 25px;
    }
}

/* ==========================================
   SMALL MOBILE PHONES
========================================== */

@media (max-width: 380px) {

    .main {
        padding: 14px 10px;
    }

    .topbar h1 {
        font-size: 19px;
    }

    .topbar p {
        font-size: 11px;
    }

    .menu-toggle {
        width: 40px;
        height: 40px;
        font-size: 22px;
    }

    .profile-btn {
        gap: 5px;
        padding: 6px 8px;
        font-size: 12px;
    }

    .welcome {
        padding: 20px 15px;
    }

    .welcome h2 {
        font-size: 19px;
    }

    .stats {
        gap: 9px;
    }

    .stat-card {
        padding: 14px 11px;
    }

    .stat-card strong {
        font-size: 22px;
    }
}

/* ==========================================
   DESKTOP SIDEBAR SCROLLBAR
========================================== */

.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: #43865a;
    border-radius: 10px;
}


/* FARMER DASHBOARD ADDITIONS */
.wide-card { grid-column: 1 / -1; }
.card-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.card-heading h3 { margin-bottom:7px; }
.muted { color:#718073; font-size:13px; line-height:1.6; }
.small-note { font-size:11px; margin-top:12px; }
.chart-wrap { position:relative; width:100%; height:290px; margin-top:12px; }
.weather-loading { padding:22px 0; color:#718073; font-size:13px; }
.weather-main { display:flex; align-items:center; justify-content:space-between; padding:20px 0; }
.weather-temperature { font-size:38px; font-weight:700; color:#194d30; }
.weather-icon { font-size:48px; }
.weather-details { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
.weather-details div { background:#f2f8f2; padding:12px 8px; border-radius:9px; text-align:center; }
.weather-details span { display:block; color:#718073; font-size:11px; margin-bottom:7px; }
.weather-details strong { color:#194d30; font-size:14px; }
.weather-advice { background:#f2f8f2; border-left:3px solid #258044; padding:12px; margin-top:14px; border-radius:5px; font-size:13px; line-height:1.6; }
.crop-highlight { background:#f4f8f3; padding:16px; border-radius:10px; }
.report-label { font-size:11px; color:#718073; text-transform:uppercase; letter-spacing:.5px; }
.crop-highlight h2 { color:#194d30; margin:9px 0 13px; }
.crop-highlight p,.profile-report p { font-size:13px; color:#536456; line-height:1.8; overflow-wrap:anywhere; }
.crop-highlight p strong,.profile-report strong { color:#194d30; }
.report-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
.report-stats div { background:#f2f8f2; border-radius:9px; padding:13px 8px; text-align:center; }
.report-stats span { display:block; font-size:11px; color:#718073; }
.report-stats strong { display:block; color:#194d30; font-size:22px; margin-top:7px; }
.revenue-box { background:#e8f5e9; padding:15px; margin-top:14px; border-radius:9px; }
.revenue-box span { display:block; color:#536456; font-size:12px; }
.revenue-box strong { display:block; color:#194d30; font-size:24px; margin-top:7px; overflow-wrap:anywhere; }
.recommendation-list { display:grid; gap:12px; }
.recommendation-item { border-left:3px solid #64a875; padding:5px 0 5px 12px; }
.recommendation-item strong { color:#194d30; font-size:14px; }
.recommendation-item p { color:#718073; font-size:12px; line-height:1.6; margin-top:5px; overflow-wrap:anywhere; }
.empty-note { color:#718073; font-size:13px; line-height:1.7; padding:15px 0; }
.health-note { background:#f2f8f2; padding:15px; border-radius:9px; margin-top:15px; }
.health-note strong { color:#194d30; }
.health-note p { color:#536456; font-size:13px; line-height:1.6; margin-top:7px; }
.table-scroll { overflow-x:auto; }
.crop-table { width:100%; border-collapse:collapse; min-width:500px; }
.crop-table th,.crop-table td { padding:13px 12px; text-align:left; border-bottom:1px solid #edf1eb; font-size:13px; }
.crop-table th { background:#f2f8f2; color:#194d30; }
.crop-table td { color:#536456; }
@media(max-width:700px) {
    .wide-card { grid-column:auto; }
    .chart-wrap { height:240px; }
    .weather-details { gap:5px; }
    .weather-details div { padding:10px 4px; }
    .weather-details strong { font-size:12px; }
}


/* COMPACT DASHBOARD CARD LAYOUT */
.dashboard-grid {
    align-items: start !important;
    grid-auto-rows: min-content;
    gap: 18px;
}

.info-card {
    align-self: start;
    height: fit-content;
    min-height: 0;
    padding: 21px;
}

.wide-card {
    grid-column: 1 / -1;
}

.info-card h3 {
    margin-bottom: 14px;
}

.weather-loading,
.empty-note {
    padding: 10px 0;
}

.chart-wrap {
    height: 270px;
}

.recommendation-list {
    gap: 9px;
}

.recommendation-item {
    padding-top: 3px;
    padding-bottom: 3px;
}

@media (max-width: 700px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .wide-card {
        grid-column: auto;
    }

    .info-card {
        padding: 17px;
    }

    .chart-wrap {
        height: 230px;
    }
}


/* QUICK ACTIONS */
.quick-actions { margin-bottom: 28px; }
.quick-action-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}
.quick-action-grid a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 17px;
    background: #fff;
    border: 1px solid #e3ece3;
    border-radius: 12px;
    color: #194d30;
    text-decoration: none;
    box-shadow: 0 3px 12px #00000008;
    transition: transform .2s, border-color .2s;
}
.quick-action-grid a:hover {
    transform: translateY(-2px);
    border-color: #8abf96;
}
.quick-action-grid span { font-size: 25px; }
.quick-action-grid strong { font-size: 14px; }
@media(max-width:900px) {
    .quick-action-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media(max-width:480px) {
    .quick-action-grid { gap: 9px; }
    .quick-action-grid a { padding: 13px 10px; gap: 8px; }
    .quick-action-grid strong { font-size: 12px; }
}

</style>

<link rel="stylesheet" href="/organic-farming/assets/css/mobile-responsive.css">
</head>

<body>

<div class="layout">

    <!-- ======================================
         SIDEBAR
    ======================================= -->

    <aside class="sidebar" id="sidebar">

        <div class="logo">
            🌱 Organic <span>Farming</span>
        </div>

        <h4>MAIN MENU</h4>

        <a href="dashboard.php" class="active">
            🏠 Dashboard
        </a>

        <a href="crops.php">
            🌾 Crop Management
        </a>

        <a href="/organic-farming/farmer/fertilizers.php">
            🌱 Fertilizer Management
        </a>

        <a href="farmer/pest-management.php">
            🐛 Pest Management
        </a>

        <a href="farmer/market-management.php">
            📊 Market
        </a>

        <a href="farmer/weather-alerts.php">
            🌦️ Weather & Alerts
        </a>

        <a href="farmer/farmer-records.php">
            🧑‍🌾 Farmer Records
        </a>

        <a href="farmer/about.php">
            ℹ️ About Project
        </a>

    </aside>

    <!-- MOBILE SIDEBAR OVERLAY -->

    <div
        class="sidebar-overlay"
        id="sidebarOverlay">
    </div>


    <!-- ======================================
         MAIN DASHBOARD
    ======================================= -->

    <main class="main">

        <!-- HEADER -->

        <div class="topbar">

            <div class="header-left">

                <button
                    type="button"
                    class="menu-toggle"
                    id="menuToggle"
                    aria-label="Open navigation menu"
                    aria-expanded="false">

                    ☰

                </button>

                <div>

                    <h1>Dashboard</h1>

                    <p>
                        Organic Farming Management System
                    </p>

                </div>

            </div>


            <!-- PROFILE DROPDOWN -->

            <div class="profile">

                <button
                    type="button"
                    class="profile-btn"
                    id="profileBtn"
                    aria-expanded="false">

                    <span class="user-icon">🙎‍♂️</span>

                    Profile

                    <span>⌄</span>

                </button>


                <div
                    class="profile-dropdown"
                    id="profileDropdown">

                    <a href="farmer/profile.php">

                        <span>👤</span>

                        My Profile

                    </a>

                    <a
                        href="#"
                        onclick="return false;">

                        <span>⚙️</span>

                        Account Settings

                    </a>

                    <div class="profile-divider"></div>

                    <a
                        href="auth/logout.php"
                        class="logout">

                        <span>↪</span>

                        Logout

                    </a>

                </div>

            </div>

        </div>


        <!-- ==================================
             WELCOME SECTION
        =================================== -->

        <section class="welcome">

            <h3>
                Welcome,
                <?= dashboardEscape($displayName) ?>!
            </h3>

            <h2>
                Welcome to Organic Farming 🌱
            </h2>

            <p>

                Manage your farming activities,
                monitor crops and discover
                sustainable agricultural practices
                in one place.

            </p>

        </section>


        
<!-- FARMER OVERVIEW -->
<h2 class="section-title">My Farm Overview</h2>

<section class="stats">
    <div class="stat-card">
        <div class="stat-icon">🌾</div>
        <h3>My Registered Crops</h3>
        <strong><?= (int)$totalCrops ?></strong>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🚜</div>
        <h3>My Farm Area</h3>
        <strong><?= $landArea > 0 ? number_format($landArea, 2) . " acres" : "Not added" ?></strong>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🛒</div>
        <h3>Active Crop Listings</h3>
        <strong><?= (int)$activeListings ?></strong>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <h3>Expected Revenue</h3>
        <strong>₹<?= number_format($expectedRevenue, 0) ?></strong>
    </div>

</section>

<!-- QUICK FARM ACTIONS -->
<section class="quick-actions">
    <h2 class="section-title">Quick Farm Actions</h2>
    <div class="quick-action-grid">
        <a href="crops.php"><span>🌱</span><strong>Add Crop</strong></a>
        <a href="farmer/market-management.php"><span>🛒</span><strong>Sell Crop</strong></a>
        <a href="farmer/pest-management.php"><span>🐛</span><strong>Report Pest</strong></a>
        <a href="farmer/weather-alerts.php"><span>🌦️</span><strong>Check Weather</strong></a>
    </div>
</section>

<section class="dashboard-grid">
    <!-- MARKET PRICE GRAPH -->
    <div class="info-card wide-card">
        <div class="card-heading">
            <div>
                <h3>📈 Market Price Trends</h3>
                <p class="muted">
                    Average modal prices in <?= dashboardEscape($district ?: 'your district') ?>
                </p>
            </div>
            <a href="farmer/market-management.php">View Market →</a>
        </div>
        <div class="chart-wrap">
            <canvas id="marketPriceChart"></canvas>
        </div>
        <?php if (empty($marketValues)): ?>
            <p class="empty-note">
                No recent market price records found for your district.
            </p>
        <?php else: ?>
            <p class="muted small-note">
                Based on market price records from the last 30 days. Values are average modal prices per quintal.
            </p>
        <?php endif; ?>
    </div>

    <!-- WEATHER -->
    <div class="info-card">
        <h3>🌦️ Weather & Farm Alerts</h3>
        <p class="muted"><?= dashboardEscape($dashboardLocation ?: 'Farm location not set') ?></p>
        <div id="weatherLoading" class="weather-loading">Checking local forecast...</div>
        <div id="weatherContent" class="weather-content" hidden>
            <div class="weather-main">
                <div>
                    <div id="weatherTemperature" class="weather-temperature">--°C</div>
                    <div id="weatherDescription" class="muted">Loading...</div>
                </div>
                <div id="weatherIcon" class="weather-icon">🌤️</div>
            </div>
            <div class="weather-details">
                <div><span>Rain chance</span><strong id="weatherRain">--%</strong></div>
                <div><span>Humidity</span><strong id="weatherHumidity">--%</strong></div>
                <div><span>Wind</span><strong id="weatherWind">-- km/h</strong></div>
            </div>
            <p id="weatherAdvice" class="weather-advice"></p>
        </div>
        <div id="weatherError" class="empty-note" hidden>
            Weather data is unavailable. Check your internet connection or farm location.
        </div>
        <a href="farmer/weather-alerts.php">View Weather Alerts →</a>
    </div>

    <!-- CROP REPORT -->
    <div class="info-card">
        <h3>🌱 My Crop Report</h3>
        <?php if ($latestCrop): ?>
            <div class="crop-highlight">
                <span class="report-label">Latest registered crop</span>
                <h2><?= dashboardEscape($latestCrop['crop_name']) ?></h2>
                <p><strong>Crop type:</strong> <?= dashboardEscape($latestCrop['crop_type'] ?: 'Not specified') ?></p>
                <p><strong>Soil:</strong> <?= dashboardEscape($latestCrop['soil_type'] ?: 'Not specified') ?></p>
                <p><strong>Planting date:</strong> <?= dashboardEscape($latestCrop['planting_date'] ?: 'Not specified') ?></p>
                <?php if (!empty($latestCrop['planting_date'])):
                    $plantDate = new DateTime($latestCrop['planting_date']);
                    $today = new DateTime('today');
                    $cropAge = $plantDate <= $today ? $plantDate->diff($today)->days : null;
                ?>
                    <p><strong>Days since planting:</strong>
                        <?= $cropAge === null ? 'Planting date is in the future' : (int)$cropAge . ' days' ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="empty-note">You have not registered any crops yet.</p>
        <?php endif; ?>
        <a href="crops.php">Manage My Crops →</a>
    </div>


    <!-- UPCOMING HARVEST -->
    <div class="info-card">
        <h3>🗓️ Upcoming Harvests</h3>
        <?php if ($upcomingHarvests): ?>
            <div class="recommendation-list">
                <?php foreach ($upcomingHarvests as $harvest): ?>
                    <div class="recommendation-item">
                        <strong><?= dashboardEscape($harvest['crop_name']) ?></strong>
                        <p>
                            Harvest date:
                            <?= dashboardEscape(date('d M Y', strtotime($harvest['harvest_date']))) ?>
                        </p>
                        <p>
                            Quantity:
                            <?= dashboardEscape($harvest['quantity']) ?>
                            <?= dashboardEscape($harvest['quantity_unit']) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-note">
                No upcoming harvest dates are recorded in your active crop listings.
                Add a harvest date when listing a crop to see it here.
            </p>
        <?php endif; ?>
        <a href="farmer/market-management.php">Manage Harvest Listings →</a>
    </div>

    <!-- MY SALES -->
    <div class="info-card">
        <h3>🛍️ My Crop Sales Report</h3>
        <div class="report-stats">
            <div><span>Total listings</span><strong><?= $totalListings ?></strong></div>
            <div><span>Active listings</span><strong><?= $activeListings ?></strong></div>
            <div><span>Sold listings</span><strong><?= $soldListings ?></strong></div>
        </div>
        <div class="revenue-box">
            <span>Expected revenue from active listings</span>
            <strong>₹<?= number_format($expectedRevenue, 2) ?></strong>
        </div>
        <p class="muted small-note">
            Revenue is calculated from active listing quantity × expected price.
        </p>
        <a href="farmer/market-management.php">Manage My Listings →</a>
    </div>

    <!-- ORGANIC FERTILIZER -->
    <div class="info-card">
        <h3>🍃 Organic Fertilizer Suggestions</h3>
        <?php if ($organicRecommendations): ?>
            <div class="recommendation-list">
                <?php foreach ($organicRecommendations as $recommendation): ?>
                    <div class="recommendation-item">
                        <strong><?= dashboardEscape($recommendation['fertilizer_name']) ?></strong>
                        <p><?= dashboardEscape($recommendation['application_method'] ?: 'See fertilizer management for application guidance.') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-note">
                No matching organic fertilizer recommendations are available for your latest crop yet.
            </p>
        <?php endif; ?>
        <a href="farmer/fertilizers.php">View Organic Fertilizer Management →</a>
    </div>

    <!-- FARM PROFILE -->
    <div class="info-card">
        <h3>👨‍🌾 My Farm Details</h3>
        <div class="profile-report">
            <p><strong>Farmer:</strong> <?= dashboardEscape($displayName) ?></p>
            <p><strong>Village:</strong> <?= dashboardEscape($village ?: 'Not provided') ?></p>
            <p><strong>District:</strong> <?= dashboardEscape($district ?: 'Not provided') ?></p>
            <p><strong>Soil type:</strong> <?= dashboardEscape($soilType) ?></p>
            <p><strong>Land area:</strong> <?= $landArea > 0 ? number_format($landArea, 2) . " acres" : "Not added" ?></p>
            <p><strong>Saved crop recommendations:</strong> <?= (int)$recommendationCount ?></p>
        </div>
        <a href="farmer/farmer-records.php">View My Farm Records →</a>
    </div>

    <!-- RECENT CROPS -->
    <div class="info-card wide-card">
        <h3>📋 My Recent Crop Records</h3>
        <?php if ($recentCrops): ?>
            <div class="table-scroll">
                <table class="crop-table">
                    <thead>
                        <tr>
                            <th>Crop</th>
                            <th>Type</th>
                            <th>Planting Date</th>
                            <th>Soil Type</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentCrops as $crop): ?>
                        <tr>
                            <td><?= dashboardEscape($crop['crop_name']) ?></td>
                            <td><?= dashboardEscape($crop['crop_type'] ?: '—') ?></td>
                            <td><?= dashboardEscape($crop['planting_date'] ?: '—') ?></td>
                            <td><?= dashboardEscape($crop['soil_type'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty-note">No crop records to display.</p>
        <?php endif; ?>
        <a href="crops.php">View All Crop Records →</a>
    </div>

    <!-- PEST MANAGEMENT -->
    <div class="info-card">
        <h3>🐛 Crop Health & Pest Management</h3>
        <p class="muted">
            Keep track of crop symptoms, pest reports and prevention activities.
        </p>
        <div class="health-note">
            <strong>Reminder</strong>
            <p>Inspect your crops regularly for leaf damage, spots, wilting and unusual insect activity.</p>
        </div>
        <a href="farmer/pest-management.php">Open Pest Management →</a>
    </div>

    <!-- FARM ACTIVITIES -->
    <div class="info-card">
        <h3>🗓️ Farm Activity Reminders</h3>
        <div class="recommendation-list">
            <div class="recommendation-item">
                <strong>Crop inspection</strong>
                <p>Check leaves, stems and soil moisture regularly.</p>
            </div>
            <div class="recommendation-item">
                <strong>Organic nutrient management</strong>
                <p>Follow the recommended organic fertilizer schedule for your crop stage.</p>
            </div>
            <div class="recommendation-item">
                <strong>Harvest planning</strong>
                <p>Review crop maturity and plan your expected harvest date.</p>
            </div>
        </div>
    </div>
</section>

<!-- ==================================
             FOOTER
        =================================== -->

        <footer>

            Organic Farming Management System

            <br>

            Community Service Project

        </footer>

    </main>

</div>


<!-- ==========================================
     JAVASCRIPT
========================================== -->

<script>

document.addEventListener("DOMContentLoaded", function () {

    // ======================================
    // MOBILE SIDEBAR
    // ======================================

    const menuToggle = document.getElementById("menuToggle");

    const sidebar = document.getElementById("sidebar");

    const sidebarOverlay =
        document.getElementById("sidebarOverlay");


    function openSidebar() {

        sidebar.classList.add("open");

        sidebarOverlay.classList.add("show");

        menuToggle.setAttribute(
            "aria-expanded",
            "true"
        );

    }


    function closeSidebar() {

        sidebar.classList.remove("open");

        sidebarOverlay.classList.remove("show");

        menuToggle.setAttribute(
            "aria-expanded",
            "false"
        );

    }


    menuToggle.addEventListener("click", function () {

        if (sidebar.classList.contains("open")) {

            closeSidebar();

        } else {

            openSidebar();

        }

    });


    sidebarOverlay.addEventListener(
        "click",
        closeSidebar
    );


    // Close sidebar after clicking a menu link

    sidebar.querySelectorAll("a").forEach(function (link) {

        link.addEventListener("click", function () {

            closeSidebar();

        });

    });


    // Close menu with Escape key

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {

            closeSidebar();

            profileDropdown.classList.remove("show");

            profileBtn.setAttribute(
                "aria-expanded",
                "false"
            );

        }

    });


    // ======================================
    // PROFILE DROPDOWN
    // ======================================

    const profileBtn =
        document.getElementById("profileBtn");

    const profileDropdown =
        document.getElementById("profileDropdown");


    profileBtn.addEventListener("click", function (event) {

        event.stopPropagation();

        const isOpen =
            profileDropdown.classList.toggle("show");

        profileBtn.setAttribute(
            "aria-expanded",
            String(isOpen)
        );

    });


    // Close profile dropdown when clicking outside

    document.addEventListener("click", function (event) {

        if (
            !profileBtn.contains(event.target) &&
            !profileDropdown.contains(event.target)
        ) {

            profileDropdown.classList.remove("show");

            profileBtn.setAttribute(
                "aria-expanded",
                "false"
            );

        }

    });


    // Close profile dropdown after selecting an option

    profileDropdown.querySelectorAll("a").forEach(
        function (link) {

            link.addEventListener("click", function () {

                profileDropdown.classList.remove("show");

                profileBtn.setAttribute(
                    "aria-expanded",
                    "false"
                );

            });

        }
    );


    // Close mobile sidebar when returning to desktop width

    window.addEventListener("resize", function () {

        if (window.innerWidth > 700) {

            closeSidebar();

        }

    });

});

</script>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const labels = <?= json_encode($marketLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const datasets = <?= json_encode($marketDatasets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const canvas = document.getElementById("marketPriceChart");

    if (canvas && window.Chart && labels.length && datasets.length) {
        new Chart(canvas, {
            type: "line",
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: value => "₹" + Number(value).toLocaleString("en-IN")
                        }
                    }
                }
            }
        });
    } else if (canvas) {
        const parent = canvas.parentElement;
        parent.innerHTML = '<p class="empty-note">No recent market price data is available for your district.</p>';
    }

    // Weather forecast through Open-Meteo (no API key required).
    const location = <?= json_encode($weatherLocation, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const loading = document.getElementById("weatherLoading");
    const content = document.getElementById("weatherContent");
    const error = document.getElementById("weatherError");

    function weatherDescription(code) {
        if (code === 0) return ["Clear sky", "☀️"];
        if ([1, 2].includes(code)) return ["Partly cloudy", "🌤️"];
        if (code === 3) return ["Overcast", "☁️"];
        if ([45, 48].includes(code)) return ["Fog", "🌫️"];
        if ([51, 53, 55, 61, 63, 65, 80, 81, 82].includes(code)) return ["Rain expected", "🌧️"];
        if ([95, 96, 99].includes(code)) return ["Thunderstorm", "⛈️"];
        return ["Variable conditions", "🌥️"];
    }

    async function loadWeather() {
        if (!location || !location.trim()) throw new Error("No farm location");

        const geoUrl = "https://geocoding-api.open-meteo.com/v1/search?name=" +
            encodeURIComponent(location) + "&count=1&language=en&format=json";
        const geoResponse = await fetch(geoUrl);
        if (!geoResponse.ok) throw new Error("Location lookup failed");
        const geo = await geoResponse.json();
        if (!geo.results || !geo.results.length) throw new Error("Location not found");

        const place = geo.results[0];
        const forecastUrl = "https://api.open-meteo.com/v1/forecast?latitude=" +
            place.latitude + "&longitude=" + place.longitude +
            "&current=temperature_2m,relative_humidity_2m,precipitation,weather_code,wind_speed_10m" +
            "&daily=precipitation_probability_max&forecast_days=1&timezone=auto";

        const response = await fetch(forecastUrl);
        if (!response.ok) throw new Error("Forecast unavailable");
        const data = await response.json();
        const current = data.current;
        const description = weatherDescription(current.weather_code);

        document.getElementById("weatherTemperature").textContent =
            Math.round(current.temperature_2m) + "°C";
        document.getElementById("weatherDescription").textContent = description[0];
        document.getElementById("weatherIcon").textContent = description[1];
        document.getElementById("weatherRain").textContent =
            (data.daily?.precipitation_probability_max?.[0] ?? "--") + "%";
        document.getElementById("weatherHumidity").textContent =
            current.relative_humidity_2m + "%";
        document.getElementById("weatherWind").textContent =
            Math.round(current.wind_speed_10m) + " km/h";

        let advice = "Check soil moisture and local field conditions before irrigation.";
        if ((data.daily?.precipitation_probability_max?.[0] ?? 0) >= 60) {
            advice = "Rain is likely. Check drainage and consider postponing irrigation.";
        } else if (current.temperature_2m >= 35) {
            advice = "High temperature: monitor soil moisture and avoid unnecessary midday field work.";
        }
        document.getElementById("weatherAdvice").textContent = advice;

        loading.hidden = true;
        content.hidden = false;
    }

    loadWeather().catch(function () {
        loading.hidden = true;
        error.hidden = false;
    });
});
</script>

</body>
</html>
<style>
/* MOBILE RESPONSIVE FIX */
@media screen and (max-width: 700px) {
    html {
        width: 100%;
        font-size: 14px;
        -webkit-text-size-adjust: 100%;
    }

    body {
        width: 100%;
        min-width: 0;
        overflow-x: hidden;
    }

    .layout {
        display: block;
        width: 100%;
        min-width: 0;
    }

    .main {
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100%;
        min-width: 0;
        padding: 14px 12px !important;
    }

    .topbar {
        gap: 8px;
        margin-bottom: 18px;
    }

    .topbar h1 {
        font-size: 20px;
    }

    .topbar p {
        font-size: 11px;
    }

    .welcome {
        padding: 20px 16px !important;
        margin-bottom: 22px;
    }

    .welcome h2 {
        font-size: 20px;
    }

    .welcome p {
        font-size: 13px;
    }

    .section-title {
        font-size: 18px;
        margin-bottom: 13px;
    }

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px;
        margin-bottom: 22px;
    }

    .stat-card {
        padding: 14px 11px !important;
        border-radius: 11px;
    }

    .stat-icon {
        font-size: 22px;
        margin-bottom: 8px;
    }

    .stat-card h3 {
        font-size: 11px;
    }

    .stat-card strong {
        font-size: 20px;
    }

    .quick-action-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 9px;
    }

    .quick-action-grid a {
        padding: 12px 9px;
        font-size: 12px;
    }

    .dashboard-grid {
        grid-template-columns: minmax(0, 1fr) !important;
        gap: 13px;
    }

    .wide-card {
        grid-column: auto !important;
    }

    .info-card {
        width: 100%;
        min-width: 0;
        padding: 16px 13px !important;
        border-radius: 11px;
    }

    .info-card h3 {
        font-size: 16px;
    }

    .chart-wrap {
        height: 220px !important;
        max-width: 100%;
    }

    .crop-table {
        min-width: 450px;
    }

    footer {
        font-size: 11px;
        margin-top: 22px;
    }
}
</style>

