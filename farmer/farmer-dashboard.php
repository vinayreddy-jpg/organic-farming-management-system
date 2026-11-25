<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

// Check farmer login
if (!current_user()) {
    redirect('../auth/login.php');
}

$pdo = db();
$userId = (int) current_user()['id'];

// Fetch logged-in farmer
$stmt = $pdo->prepare(
    "SELECT id, name, username, mobile, district, village, role, status
     FROM users
     WHERE id = ?
     LIMIT 1"
);
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$user ||
    $user['role'] !== 'farmer' ||
    $user['status'] !== 'active'
) {
    session_unset();
    session_destroy();
    redirect('../auth/login.php');
}

$username = $user['username'] ?: $user['name'];

// --------------------------------------------------
// FARMER-SPECIFIC CROP STATISTICS
// --------------------------------------------------

$cropCount = 0;
$latestCrop = null;
$catalogCount = 0;

try {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM crop_records
         WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $cropCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT id, crop_name, crop_type, soil_type,
                planting_date, fertilizer, description,
                field_name, field_village, field_district
         FROM crop_records
         WHERE user_id = ?
         ORDER BY planting_date DESC, id DESC
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $latestCrop = $stmt->fetch(PDO::FETCH_ASSOC);

    $catalogCount = (int) $pdo
        ->query("SELECT COUNT(*) FROM crop_catalog")
        ->fetchColumn();

} catch (PDOException $e) {
    error_log("Farmer dashboard crop error: " . $e->getMessage());
}

// --------------------------------------------------
// CROP AGE CALCULATION
// --------------------------------------------------

$cropAge = null;
$plantingDateFormatted = 'Not available';

if ($latestCrop && !empty($latestCrop['planting_date'])) {
    try {
        $plantDate = new DateTime($latestCrop['planting_date']);
        $today = new DateTime('today');

        if ($plantDate <= $today) {
            $cropAge = $plantDate->diff($today)->days;
        }

        $plantingDateFormatted = $plantDate->format('d M Y');

    } catch (Exception $e) {
        $cropAge = null;
    }
}

// --------------------------------------------------
// WEATHER REPORT USING OPEN-METEO API
// No API key required.
// --------------------------------------------------

function fetchWeatherJson(string $url): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "User-Agent: OrganicFarmingDashboard/1.0\r\n"
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);

    return is_array($data) ? $data : null;
}

function getWeatherDescription(int $code): string
{
    $conditions = [
        0 => 'Clear sky',
        1 => 'Mainly clear',
        2 => 'Partly cloudy',
        3 => 'Overcast',
        45 => 'Fog',
        48 => 'Depositing rime fog',
        51 => 'Light drizzle',
        53 => 'Moderate drizzle',
        55 => 'Dense drizzle',
        61 => 'Slight rain',
        63 => 'Moderate rain',
        65 => 'Heavy rain',
        71 => 'Slight snowfall',
        73 => 'Moderate snowfall',
        75 => 'Heavy snowfall',
        80 => 'Rain showers',
        81 => 'Moderate rain showers',
        82 => 'Heavy rain showers',
        95 => 'Thunderstorm'
    ];

    return $conditions[$code] ?? 'Weather information available';
}

$weather = null;
$weatherLocation = trim(
    ($user['village'] ?? '') . ', ' .
    ($user['district'] ?? '') . ', Andhra Pradesh, India',
    ' ,'
);

if (
    !empty($user['village']) ||
    !empty($user['district'])
) {
    try {
        // Find the farmer's location
        $searchLocation = !empty($user['village'])
            ? $user['village'] . ', ' . $user['district'] . ', Andhra Pradesh, India'
            : $user['district'] . ', Andhra Pradesh, India';

        $geoUrl = 'https://geocoding-api.open-meteo.com/v1/search?name=' .
            urlencode($searchLocation) .
            '&count=1&language=en&format=json';

        $geoData = fetchWeatherJson($geoUrl);

        // If village lookup fails, try district
        if (empty($geoData['results'][0]) && !empty($user['district'])) {
            $geoUrl = 'https://geocoding-api.open-meteo.com/v1/search?name=' .
                urlencode($user['district'] . ', Andhra Pradesh, India') .
                '&count=1&language=en&format=json';

            $geoData = fetchWeatherJson($geoUrl);
        }

        if (!empty($geoData['results'][0])) {
            $location = $geoData['results'][0];

            $latitude = (float) $location['latitude'];
            $longitude = (float) $location['longitude'];

            $weatherUrl =
                'https://api.open-meteo.com/v1/forecast?' .
                'latitude=' . $latitude .
                '&longitude=' . $longitude .
                '&current=temperature_2m,relative_humidity_2m,precipitation,wind_speed_10m,weather_code' .
                '&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max' .
                '&forecast_days=1&timezone=auto';

            $weatherData = fetchWeatherJson($weatherUrl);

            if (!empty($weatherData['current'])) {
                $current = $weatherData['current'];

                $weatherCode = (int) ($current['weather_code'] ?? -1);

                $weather = [
                    'temperature' => $current['temperature_2m'] ?? null,
                    'humidity' => $current['relative_humidity_2m'] ?? null,
                    'rainfall' => $current['precipitation'] ?? null,
                    'wind' => $current['wind_speed_10m'] ?? null,
                    'condition' => getWeatherDescription($weatherCode),
                    'max_temp' => $weatherData['daily']['temperature_2m_max'][0] ?? null,
                    'min_temp' => $weatherData['daily']['temperature_2m_min'][0] ?? null,
                    'rain_probability' => $weatherData['daily']['precipitation_probability_max'][0] ?? null,
                    'location' => $location['name'] ?? $weatherLocation
                ];
            }
        }

    } catch (Throwable $e) {
        error_log("Weather API error: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Farmer Dashboard | Organic Farming</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    background: #f3f7f2;
    color: #263b2d;
}

/* SIDEBAR */

.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: #194d30;
    color: white;
    padding: 25px 18px;
    overflow-y: auto;
}

.logo {
    font-size: 23px;
    font-weight: bold;
    margin-bottom: 35px;
}

.logo span {
    color: #9cdb91;
}

.sidebar h4 {
    color: #b9d5c0;
    font-size: 12px;
    margin: 20px 10px 12px;
    text-transform: uppercase;
}

.sidebar a {
    display: block;
    color: white;
    text-decoration: none;
    padding: 13px 14px;
    margin: 5px 0;
    border-radius: 7px;
    font-size: 14px;
    transition: .2s;
}

.sidebar a:hover,
.sidebar a.active {
    background: #34754c;
}

/* MAIN CONTENT */

.main {
    margin-left: 250px;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
    padding: 20px 25px;
    border-radius: 12px;
    margin-bottom: 28px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.topbar h2 {
    color: #194d30;
}

.profile {
    font-size: 14px;
    color: #526456;
}

/* WELCOME */

.welcome {
    background: linear-gradient(120deg, #194d30, #347b4b);
    color: white;
    padding: 30px;
    border-radius: 14px;
    margin-bottom: 28px;
}

.welcome h1 {
    font-size: 27px;
    margin-bottom: 10px;
}

.welcome p {
    color: #e0eee2;
    line-height: 1.6;
}

/* STATISTICS */

.cards {
    display: grid;
    grid-template-columns: repeat(2, minmax(200px, 1fr));
    gap: 20px;
}

.card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
    border-left: 5px solid #39834d;
}

.card h3 {
    font-size: 14px;
    color: #718075;
    margin-bottom: 15px;
}

.card .number {
    font-size: 30px;
    font-weight: bold;
    color: #194d30;
}

/* GENERAL SECTIONS */

.section {
    background: white;
    margin-top: 28px;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.035);
}

.section h2 {
    color: #194d30;
    margin-bottom: 20px;
    font-size: 20px;
}

.quick-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 15px;
}

.quick-links a {
    padding: 18px;
    border: 1px solid #dce9dd;
    border-radius: 9px;
    text-decoration: none;
    color: #194d30;
    font-weight: bold;
    background: #f8fbf7;
    transition: .2s;
}

.quick-links a:hover {
    background: #e8f4e8;
    transform: translateY(-2px);
}

/* WEATHER */

.weather-card {
    background: linear-gradient(120deg, #e8f4ed, #f7fbf7);
    border: 1px solid #d8e9dc;
    border-radius: 12px;
    padding: 24px;
}

.weather-location {
    color: #718075;
    font-size: 13px;
    margin-bottom: 15px;
}

.weather-main {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 22px;
}

.weather-temperature {
    font-size: 43px;
    font-weight: bold;
    color: #194d30;
}

.weather-condition {
    color: #526456;
    margin-top: 5px;
}

.weather-icon {
    font-size: 55px;
}

.weather-details {
    display: grid;
    grid-template-columns: repeat(4, minmax(100px, 1fr));
    gap: 12px;
}

.weather-item {
    background: white;
    padding: 15px;
    border-radius: 9px;
}

.weather-item span {
    display: block;
    font-size: 12px;
    color: #718075;
    margin-bottom: 8px;
}

.weather-item strong {
    color: #194d30;
    font-size: 16px;
}

.weather-forecast {
    margin-top: 18px;
    padding: 15px;
    background: white;
    border-radius: 9px;
    color: #526456;
    line-height: 1.8;
}

/* CROP STATUS */

.crop-status {
    border: 1px solid #dce9dd;
    border-radius: 12px;
    padding: 22px;
    background: #fbfdfb;
}

.crop-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}

.crop-header h3 {
    color: #194d30;
    font-size: 22px;
}

.status-badge {
    background: #fff2d9;
    color: #946300;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.crop-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(150px, 1fr));
    gap: 15px;
}

.crop-detail {
    padding: 14px;
    background: white;
    border-radius: 8px;
    border: 1px solid #edf1ed;
}

.crop-detail span {
    display: block;
    color: #718075;
    font-size: 12px;
    margin-bottom: 7px;
}

.crop-detail strong {
    color: #194d30;
    font-size: 14px;
}

.crop-note {
    margin-top: 17px;
    color: #718075;
    background: #f0f6f0;
    padding: 13px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.6;
}

.empty-state {
    text-align: center;
    padding: 25px;
    color: #718075;
    background: #f8fbf7;
    border-radius: 10px;
}

.empty-state a {
    display: inline-block;
    margin-top: 14px;
    background: #194d30;
    color: white;
    text-decoration: none;
    padding: 11px 18px;
    border-radius: 7px;
}

/* RESPONSIVE */

@media(max-width: 900px) {
    .weather-details {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 750px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }

    .main {
        margin-left: 0;
        padding: 15px;
    }

    .topbar {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .crop-details {
        grid-template-columns: 1fr;
    }

    .weather-main {
        align-items: flex-start;
    }
}

@media(max-width: 450px) {
    .weather-details {
        grid-template-columns: 1fr 1fr;
    }

    .weather-temperature {
        font-size: 34px;
    }

    .weather-icon {
        font-size: 42px;
    }
}
</style>
<link rel="stylesheet" href="../assets/css/mobile-responsive.css">
</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="logo">ORGANIC <span>FARM</span></div>

    <h4>Farmer Menu</h4>

    <a class="active" href="farmer-dashboard.php">🏠 Dashboard</a>
    <a href="recommendations.php">🌱 Smart Crop Recommendations</a>
    <a href="pest-management.php">🐛 Pest Management</a>
    <a href="fertilizers.php">🌿 Fertilizer Information</a>
    <a href="profile.php">👤 My Profile</a>

    <h4>Other</h4>

    <a href="../index.php">🏡 Home</a>
    <a href="../auth/logout.php">🚪 Logout</a>

</aside>

<!-- MAIN CONTENT -->

<main class="main">

    <header class="topbar">
        <h2>Farmer Dashboard</h2>

        <div class="profile">
            Welcome, <?= e($username) ?> 👋
        </div>
    </header>

    <!-- WELCOME -->

    <section class="welcome">
        <h1>Welcome to Organic Farming 🌱</h1>

        <p>
            Manage your farming activities, monitor your crops,
            check local weather conditions and discover sustainable
            agricultural practices in one place.
        </p>
    </section>

    <!-- FARM OVERVIEW -->

    <section class="cards">

        <div class="card">
            <h3>My Registered Crops</h3>
            <div class="number"><?= $cropCount ?></div>
        </div>

        <div class="card">
            <h3>Available Crop Catalog</h3>
            <div class="number"><?= $catalogCount ?></div>
        </div>

    </section>

    <!-- WEATHER REPORT -->

    <section class="section">

        <h2>🌤️ Weather Report</h2>

        <div class="weather-card">

            <div class="weather-location">
                Location: <?= e($weatherLocation) ?>
            </div>

            <?php if ($weather): ?>

                <div class="weather-main">

                    <div>
                        <div class="weather-temperature">
                            <?= $weather['temperature'] !== null
                                ? e((string) round((float) $weather['temperature'])) . '°C'
                                : '--' ?>
                        </div>

                        <div class="weather-condition">
                            <?= e($weather['condition']) ?>
                        </div>
                    </div>

                    <div class="weather-icon">🌤️</div>

                </div>

                <div class="weather-details">

                    <div class="weather-item">
                        <span>💧 Humidity</span>
                        <strong>
                            <?= $weather['humidity'] !== null
                                ? e((string) $weather['humidity']) . '%'
                                : '--' ?>
                        </strong>
                    </div>

                    <div class="weather-item">
                        <span>🌧️ Rainfall</span>
                        <strong>
                            <?= $weather['rainfall'] !== null
                                ? e((string) $weather['rainfall']) . ' mm'
                                : '--' ?>
                        </strong>
                    </div>

                    <div class="weather-item">
                        <span>💨 Wind Speed</span>
                        <strong>
                            <?= $weather['wind'] !== null
                                ? e((string) $weather['wind']) . ' km/h'
                                : '--' ?>
                        </strong>
                    </div>

                    <div class="weather-item">
                        <span>🌡️ Temperature</span>
                        <strong>
                            <?= $weather['min_temp'] !== null
                                ? e((string) round((float) $weather['min_temp'])) .
                                  '° - ' .
                                  e((string) round((float) $weather['max_temp'])) . '°'
                                : '--' ?>
                        </strong>
                    </div>

                </div>

                <div class="weather-forecast">
                    <strong>Today's Forecast</strong><br>

                    Rain probability:
                    <?= $weather['rain_probability'] !== null
                        ? e((string) $weather['rain_probability']) . '%'
                        : 'Not available' ?>
                </div>

            <?php else: ?>

                <div class="empty-state">
                    <h3>Weather information unavailable</h3>
                    <p>
                        We couldn't retrieve weather data for
                        this location. Please check your internet
                        connection or registered village and district.
                    </p>
                </div>

            <?php endif; ?>

        </div>

    </section>

    <!-- PRESENT CROP STATUS -->

    <section class="section">

        <h2>🌾 Present Crop Status</h2>

        <?php if ($latestCrop): ?>

            <div class="crop-status">

                <div class="crop-header">

                    <h3>
                        <?= e($latestCrop['crop_name']) ?>
                    </h3>

                    <span class="status-badge">
                        Status Not Recorded
                    </span>

                </div>

                <div class="crop-details">

                    <div class="crop-detail">
                        <span>Crop Type</span>
                        <strong>
                            <?= e($latestCrop['crop_type'] ?: 'Not specified') ?>
                        </strong>
                    </div>

                    <div class="crop-detail">
                        <span>Soil Type</span>
                        <strong>
                            <?= e($latestCrop['soil_type'] ?: 'Not specified') ?>
                        </strong>
                    </div>

                    <div class="crop-detail">
                        <span>Planting Date</span>
                        <strong>
                            <?= e($plantingDateFormatted) ?>
                        </strong>
                    </div>

                    <div class="crop-detail">
                        <span>Crop Age</span>
                        <strong>
                            <?= $cropAge !== null
                                ? e((string) $cropAge) . ' days'
                                : 'Not available' ?>
                        </strong>
                    </div>

                    <div class="crop-detail">
                        <span>Field Name</span>
                        <strong>
                            <?= e($latestCrop['field_name'] ?: 'Not specified') ?>
                        </strong>
                    </div>

                    <div class="crop-detail">
                        <span>Fertilizer Used</span>
                        <strong>
                            <?= e($latestCrop['fertilizer'] ?: 'Not specified') ?>
                        </strong>
                    </div>

                    <div class="crop-detail">
                        <span>Village</span>
                        <strong>
                            <?= e($latestCrop['field_village'] ?: $user['village']) ?>
                        </strong>
                    </div>

                    <div class="crop-detail">
                        <span>District</span>
                        <strong>
                            <?= e($latestCrop['field_district'] ?: $user['district']) ?>
                        </strong>
                    </div>

                </div>

                <div class="crop-note">
                    <strong>Crop monitoring note:</strong>
                    Your planting information is available.
                    Growth stage, crop health and expected harvest
                    have not yet been recorded in the database.
                    Please update these details after monitoring
                    your field.
                </div>

            </div>

        <?php else: ?>

            <div class="empty-state">
                <h3>No crop records found</h3>

                <p>
                    You haven't registered any crops yet.
                    Add your first crop to view its status here.
                </p>

                <a href="../crops.php">Add Crop Record</a>
            </div>

        <?php endif; ?>

    </section>

    <!-- QUICK ACCESS -->

    <section class="section">

        <h2>Quick Access</h2>

        <div class="quick-links">

            <a href="recommendations.php">
                🌱 Smart Crop Recommendations
            </a>

            <a href="pest-management.php">
                🐛 Pest Management
            </a>

            <a href="fertilizers.php">
                🌿 Organic Fertilizers
            </a>

            <a href="../crops.php">
                🌾 Crop Management
            </a>

        </div>

    </section>

</main>

</body>
</html>
