<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../auth/bootstrap.php';

$user = current_user();

if (!$user || empty($user['id'])) {
    redirect('auth/login.php');
}

$userId = (int)$user['id'];
$pdo = db();

/* =========================================================
   WEATHER API HELPER
========================================================= */

function weatherHttpGet(string $url): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'OrganicFarmingManagementSystem/1.0'
        ]);

        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false || $status !== 200) {
            throw new RuntimeException(
                'Weather service request failed. ' . $error
            );
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 20,
                'header' => "User-Agent: OrganicFarmingManagementSystem/1.0\r\n"
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new RuntimeException(
                'Could not connect to the weather service. Enable PHP cURL or allow_url_fopen.'
            );
        }
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new RuntimeException('Invalid response from weather service.');
    }

    return $data;
}

/* =========================================================
   WEATHER DESCRIPTION
========================================================= */

function weatherDescription(int $code): string
{
    $descriptions = [
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
        71 => 'Slight snow',
        73 => 'Moderate snow',
        75 => 'Heavy snow',
        80 => 'Rain showers',
        81 => 'Moderate rain showers',
        82 => 'Violent rain showers',
        95 => 'Thunderstorm',
        96 => 'Thunderstorm with hail',
        99 => 'Thunderstorm with heavy hail'
    ];

    return $descriptions[$code] ?? 'Weather conditions unavailable';
}

/* =========================================================
   LOCATION SEARCH AND WEATHER FETCHING
========================================================= */

function getWeatherForLocation(
    string $village,
    string $district,
    string $state
): array {

    $village = trim($village);
    $district = trim($district);
    $state = trim($state);

    if ($village === '' && $district === '') {
        throw new RuntimeException('Please enter a village or city and district.');
    }

    if ($state === '') {
        throw new RuntimeException('Please enter the state.');
    }

    // Try the village/city first, then the district and state.
    $searchLocations = [];

    if ($village !== '') {
        $searchLocations[] = $village . ', ' . $district . ', ' . $state;
        $searchLocations[] = $village . ', ' . $state;
        $searchLocations[] = $village;
    }

    if ($district !== '') {
        $searchLocations[] = $district . ', ' . $state;
    }

    $place = null;

    foreach ($searchLocations as $searchName) {

        $geoUrl = 'https://geocoding-api.open-meteo.com/v1/search?' .
            http_build_query([
                'name' => $searchName,
                'count' => 10,
                'language' => 'en',
                'format' => 'json',
                'countryCode' => 'IN'
            ]);

        $geoData = weatherHttpGet($geoUrl);

        foreach (($geoData['results'] ?? []) as $result) {

            if (
                isset($result['latitude'], $result['longitude']) &&
                strtoupper($result['country_code'] ?? '') === 'IN'
            ) {
                $place = $result;
                break 2;
            }
        }
    }

    if (!$place) {
        throw new RuntimeException(
            'Location not found. Please check the village, district and state names.'
        );
    }

    $latitude = (float)$place['latitude'];
    $longitude = (float)$place['longitude'];

    // Fetch current weather and seven-day forecast.
    $forecastUrl = 'https://api.open-meteo.com/v1/forecast?' .
        http_build_query([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,relative_humidity_2m,precipitation,weather_code,wind_speed_10m',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,wind_speed_10m_max',
            'forecast_days' => 7,
            'timezone' => 'auto',
            'temperature_unit' => 'celsius',
            'wind_speed_unit' => 'kmh',
            'precipitation_unit' => 'mm'
        ]);

    $weather = weatherHttpGet($forecastUrl);

    if (!isset($weather['current'], $weather['daily'])) {
        throw new RuntimeException('Forecast data is unavailable.');
    }

    return [
        'place_name' => $place['name'] ?? $village,
        'admin_area' => $place['admin1'] ?? $district,
        'current' => $weather['current'],
        'daily' => $weather['daily']
    ];
}

/* =========================================================
   SAVE WEATHER ALERTS FOR REGISTERED CROPS
========================================================= */

function saveWeatherAlert(
    PDO $pdo,
    int $userId,
    int $cropId,
    string $type,
    string $severity,
    string $title,
    string $message,
    string $date
): void {

    $check = $pdo->prepare(
        "SELECT id
         FROM weather_alerts
         WHERE user_id = ?
           AND crop_id = ?
           AND alert_type = ?
           AND alert_date = ?
         LIMIT 1"
    );

    $check->execute([$userId, $cropId, $type, $date]);

    if ($check->fetch()) {
        return;
    }

    $insert = $pdo->prepare(
        "INSERT INTO weather_alerts
         (user_id, crop_id, alert_type, severity,
          title, message, alert_date)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    $insert->execute([
        $userId,
        $cropId,
        $type,
        $severity,
        $title,
        $message,
        $date
    ]);
}

/* =========================================================
   GENERATE GENERAL WEATHER ALERTS
========================================================= */

function generateCropAlerts(
    PDO $pdo,
    int $userId,
    int $cropId,
    array $daily
): void {

    $dates = $daily['time'] ?? [];

    foreach ($dates as $index => $date) {

        $rain = (float)($daily['precipitation_sum'][$index] ?? 0);
        $rainChance = (float)($daily['precipitation_probability_max'][$index] ?? 0);
        $maxTemp = (float)($daily['temperature_2m_max'][$index] ?? 0);
        $maxWind = (float)($daily['wind_speed_10m_max'][$index] ?? 0);
        $code = (int)($daily['weather_code'][$index] ?? 0);

        if (
            $rain >= 50 ||
            in_array($code, [65, 82, 95, 96, 99], true)
        ) {

            saveWeatherAlert(
                $pdo,
                $userId,
                $cropId,
                'heavy_rain',
                'danger',
                'Heavy Rain Warning',
                'Forecast indicates potentially heavy rainfall (' .
                    round($rain, 1) .
                    ' mm). Check field drainage and avoid unnecessary field operations.',
                $date
            );

        } elseif ($rainChance >= 70 && $rain >= 10) {
    saveWeatherAlert(
        $pdo,
        $userId,
        $cropId,
        'rain_forecast',
        'warning',
        'Rain Expected',
        'Rain probability is ' . round($rainChance) .
            '% with forecast rainfall of ' . round($rain, 1) .
            ' mm. Plan field activities accordingly.',
        $date
    );
} elseif ($rainChance >= 70 && $rain >= 2) {
    saveWeatherAlert(
        $pdo,
        $userId,
        $cropId,
        'moderate_rain_watch',
        'info',
        'Moderate Rain Watch',
        'Rain probability is ' . round($rainChance) .
            '% with forecast rainfall of ' . round($rain, 1) .
            ' mm. Monitor field conditions and plan farming activities accordingly.',
        $date
    );
}

        if ($maxTemp >= 40) {

            saveWeatherAlert(
                $pdo,
                $userId,
                $cropId,
                'high_temperature',
                'danger',
                'High Temperature Alert',
                'Maximum temperature may reach ' . round($maxTemp, 1) .
                    '°C. Monitor soil moisture and crop heat stress.',
                $date
            );
        }

        if ($maxWind >= 40) {

            saveWeatherAlert(
                $pdo,
                $userId,
                $cropId,
                'strong_wind',
                'warning',
                'Strong Wind Alert',
                'Maximum wind speed may reach ' . round($maxWind, 1) .
                    ' km/h. Check crop support structures and avoid unsafe spraying.',
                $date
            );
        }
    }
}

/* =========================================================
   LOAD REGISTERED CROPS
========================================================= */

$errors = [];
$weatherResults = [];
$manualResult = null;

$cropStmt = $pdo->prepare(
    "SELECT
        id,
        crop_name,
        planting_date,
        field_name,
        field_village,
        field_district,
        field_state
     FROM crop_records
     WHERE user_id = ?
     ORDER BY id DESC"
);

$cropStmt->execute([$userId]);
$crops = $cropStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   WEATHER FOR SAVED CROPS
========================================================= */

foreach ($crops as $crop) {

    $cropId = (int)$crop['id'];

    $village = trim((string)($crop['field_village'] ?? ''));
    $district = trim((string)($crop['field_district'] ?? ''));
    $state = trim((string)($crop['field_state'] ?? ''));

    $locationParts = array_filter([
        $village,
        $district,
        $state,
        'India'
    ]);

    $location = implode(', ', $locationParts);

    if ($village === '' || $district === '' || $state === '') {

        $errors[] = $crop['crop_name'] .
            ': Field location is incomplete. Edit this crop and add its village, district and state.';

        continue;
    }

    try {

        $weatherData = getWeatherForLocation(
            $village,
            $district,
            $state
        );

        $weatherResults[] = [
            'crop' => $crop,
            'location' => $location,
            'place_name' => $weatherData['place_name'],
            'current' => $weatherData['current'],
            'daily' => $weatherData['daily']
        ];

        // Generate alerts only for saved crops.
        generateCropAlerts(
            $pdo,
            $userId,
            $cropId,
            $weatherData['daily']
        );

    } catch (Throwable $exception) {

        $errors[] = $crop['crop_name'] . ' (' . $location . '): ' .
            $exception->getMessage();
    }
}

/* =========================================================
   MANUAL WEATHER SEARCH
   This does not create a crop record or save alerts.
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf();

    $manualCrop = trim((string)($_POST['manual_crop'] ?? ''));
    $manualVillage = trim((string)($_POST['manual_village'] ?? ''));
    $manualDistrict = trim((string)($_POST['manual_district'] ?? ''));
    $manualState = trim((string)($_POST['manual_state'] ?? ''));

    if ($manualCrop === '') {
        $errors[] = 'Please enter a crop name for manual weather search.';
    } elseif ($manualVillage === '' || $manualDistrict === '' || $manualState === '') {
        $errors[] = 'Please enter the village/city, district and state.';
    } else {

        try {

            $manualWeather = getWeatherForLocation(
                $manualVillage,
                $manualDistrict,
                $manualState
            );

            $manualResult = [
                'crop_name' => $manualCrop,
                'location' => implode(', ', [
                    $manualVillage,
                    $manualDistrict,
                    $manualState,
                    'India'
                ]),
                'place_name' => $manualWeather['place_name'],
                'current' => $manualWeather['current'],
                'daily' => $manualWeather['daily']
            ];

        } catch (Throwable $exception) {

            $errors[] = 'Manual search: ' . $exception->getMessage();
        }
    }
}

/* =========================================================
   LOAD LATEST SAVED ALERTS
========================================================= */

$alertStmt = $pdo->prepare(
    "SELECT
        a.*,
        c.crop_name,
        c.field_name,
        c.field_village,
        c.field_district
     FROM weather_alerts a
     INNER JOIN crop_records c ON c.id = a.crop_id
     WHERE a.user_id = ?
       AND c.user_id = ?
     ORDER BY a.alert_date DESC, a.id DESC
     LIMIT 50"
);

$alertStmt->execute([$userId, $userId]);
$alerts = $alertStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   BROWSER NOTIFICATION DATA
========================================================= */

$notificationAlerts = [];

foreach ($alerts as $alert) {

    if (
        (int)$alert['is_read'] === 0 &&
        $alert['alert_date'] >= date('Y-m-d')
    ) {

        $notificationAlerts[] = [
            'title' => $alert['title'],
            'message' => $alert['crop_name'] . ': ' . $alert['message']
        ];
    }
}

$notificationJson = json_encode(
    $notificationAlerts,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);

$lastUpdatedAt = date('d M Y, h:i A');

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Weather & Alerts | Organic Farming</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #f3f7f1;
    color: #263b2a;
}

/* HEADER */

.header {
    background: #194d30;
    color: white;
    padding: 24px 6%;
}

.header h2 {
    margin-bottom: 12px;
}

.header a {
    color: white;
    text-decoration: none;
}

/* MAIN CONTAINER */

.container {
    width: 92%;
    max-width: 1150px;
    margin: 32px auto;
}

.page-title {
    color: #194d30;
    font-size: 29px;
    margin-bottom: 8px;
}

.subtitle {
    color: #68796b;
    margin-bottom: 22px;
    line-height: 1.5;
}

/* CARDS */

.card {
    background: white;
    padding: 23px;
    border-radius: 13px;
    margin-bottom: 22px;
    border: 1px solid #e4ebe2;
    box-shadow: 0 4px 16px rgba(30,70,40,.05);
}

.card h2 {
    color: #245c37;
    margin-bottom: 15px;
    font-size: 21px;
}

/* MANUAL SEARCH FORM */

.search-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-top: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: #315b3b;
    font-size: 14px;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #d6e1d3;
    border-radius: 8px;
    outline: none;
    font-size: 14px;
}

.form-group input:focus {
    border-color: #287a43;
    box-shadow: 0 0 0 2px rgba(40,122,67,.10);
}

.search-button {
    margin-top: 18px;
}

/* BUTTONS */

button {
    border: 0;
    border-radius: 8px;
    background: #287a43;
    color: white;
    padding: 12px 17px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
}

button:hover {
    background: #1d6135;
}

.secondary-button {
    background: #456b50;
}

.secondary-button:hover {
    background: #34583e;
}

/* WEATHER HEADING */

.crop-heading {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 15px;
}

.crop-heading h2 {
    margin: 0;
}

.location {
    color: #718072;
    font-size: 14px;
    margin-top: 6px;
    line-height: 1.7;
}

/* CURRENT WEATHER */

.current-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin: 18px 0;
}

.metric {
    background: #f1f7ef;
    padding: 16px;
    border-radius: 10px;
}

.metric small {
    display: block;
    color: #68796b;
    margin-bottom: 9px;
}

.metric strong {
    font-size: 21px;
    color: #245c37;
}

/* FORECAST */

.forecast-wrap {
    overflow-x: auto;
}

.forecast {
    display: grid;
    grid-template-columns: repeat(7, minmax(105px, 1fr));
    gap: 9px;
    min-width: 760px;
}

.day {
    background: #f7faf6;
    border: 1px solid #e5ede3;
    padding: 13px 9px;
    border-radius: 9px;
    text-align: center;
    line-height: 1.7;
}

.day strong {
    display: block;
    color: #245c37;
}

.day small {
    color: #68796b;
}

/* ALERTS */

.alert {
    padding: 16px;
    border-radius: 9px;
    margin-bottom: 12px;
    border-left: 5px solid #8b9b8b;
    background: #f4f7f3;
}

.alert.warning {
    border-left-color: #d99a20;
    background: #fff8e9;
}

.alert.danger {
    border-left-color: #c83f3f;
    background: #fff0f0;
}

.alert.info {
    border-left-color: #3884b8;
    background: #eef7ff;
}

.alert h3 {
    font-size: 16px;
    margin-bottom: 6px;
}

.alert p {
    line-height: 1.5;
    font-size: 14px;
}

.alert small {
    display: block;
    margin-top: 8px;
    color: #68796b;
}

/* MESSAGES */

.notice {
    background: #fff8e9;
    color: #805b16;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 16px;
    line-height: 1.5;
}

.error {
    background: #fff0f0;
    color: #a12b2b;
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 12px;
    line-height: 1.5;
}

.success-info {
    background: #eef7ef;
    color: #245c37;
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 16px;
    line-height: 1.5;
}

.empty {
    color: #718072;
    padding: 15px 0;
}

/* REFRESH TOOLBAR */

.weather-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin: 20px 0;
}

.update-info {
    color: #245c37;
    font-size: 14px;
    line-height: 1.8;
}

.update-info span {
    display: block;
    color: #718072;
    font-size: 12px;
}

#enableNotifications {
    margin-bottom: 20px;
}

/* RESPONSIVE */

@media(max-width: 750px) {

    .current-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .search-grid {
        grid-template-columns: 1fr;
    }

    .page-title {
        font-size: 24px;
    }

    .card {
        padding: 17px;
    }

    .container {
        width: 94%;
        margin: 22px auto;
    }

    .metric strong {
        font-size: 19px;
    }
}

</style>

<link rel="stylesheet" href="/organic-farming/assets/css/mobile-responsive.css">
</head>

<body>

<header class="header">

    <h2>🌱 Organic Farming Management</h2>

    <a href="../dashboard.php">← Back to Dashboard</a>

</header>


<main class="container">

    <h1 class="page-title">🌦️ Weather & Alerts</h1>

    <p class="subtitle">
        Check live weather conditions and seven-day forecasts
        for your registered crops or any manually entered location.
    </p>


    <!-- =================================================
         REFRESH AND NOTIFICATIONS
    ================================================== -->

    <div class="weather-toolbar">

        <div class="update-info">

            <strong>Last updated:</strong>
            <?= e($lastUpdatedAt) ?>

            <span>Refresh the page to get the latest forecast.</span>

        </div>

        <button type="button" id="refreshWeather">
            ↻ Refresh Weather
        </button>

    </div>


    <button type="button" id="enableNotifications">
        🔔 Enable Browser Notifications
    </button>


    <!-- =================================================
         DISPLAY ERRORS
    ================================================== -->

    <?php foreach ($errors as $error): ?>

        <div class="error">
            <?= e($error) ?>
        </div>

    <?php endforeach; ?>


    <!-- =================================================
         MANUAL WEATHER SEARCH
    ================================================== -->

    <section class="card">

        <h2> Crop Weather Search</h2>

        <p class="subtitle">
            Enter a crop and location to check weather conditions.
            This search does not add a crop to Crop Management.
        </p>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf()) ?>"
            >

            <div class="search-grid">

                <div class="form-group">

                    <label for="manual_crop">
                        Crop Name
                    </label>

                    <input
                        type="text"
                        id="manual_crop"
                        name="manual_crop"
                        placeholder="Example: Paddy, Cotton, Groundnut"
                        value="<?= e($_POST['manual_crop'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="manual_village">
                        Village / City
                    </label>

                    <input
                        type="text"
                        id="manual_village"
                        name="manual_village"
                        placeholder="Enter village or city"
                        value="<?= e($_POST['manual_village'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="manual_district">
                        District
                    </label>

                    <input
                        type="text"
                        id="manual_district"
                        name="manual_district"
                        placeholder="Enter district"
                        value="<?= e($_POST['manual_district'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="manual_state">
                        State
                    </label>

                    <input
                        type="text"
                        id="manual_state"
                        name="manual_state"
                        placeholder="Enter state"
                        value="<?= e($_POST['manual_state'] ?? '') ?>"
                        required
                    >

                </div>

            </div>

            <button type="submit" class="search-button">
                Search Weather
            </button>

        </form>

    </section>


    <!-- =================================================
         MANUAL SEARCH RESULT
    ================================================== -->

    <?php if ($manualResult): ?>

        <?php
            $current = $manualResult['current'];
            $daily = $manualResult['daily'];
            $currentCode = (int)($current['weather_code'] ?? 0);
        ?>

        <section class="card">

            <div class="success-info">
                Manual weather search completed successfully.
                This search has not created a crop record.
            </div>

            <div class="crop-heading">

                <div>

                    <h2>
                        🌾 <?= e($manualResult['crop_name']) ?>
                    </h2>

                    <p class="location">

                        <strong>Entered location:</strong>
                        <?= e($manualResult['location']) ?>

                        <br>

                        <strong>Weather forecast area:</strong>
                        <?= e($manualResult['place_name']) ?>

                    </p>

                </div>

            </div>


            <h3>Current Weather</h3>

            <p style="margin-top:8px;color:#68796b">
                <?= e(weatherDescription($currentCode)) ?>
            </p>


            <div class="current-grid">

                <div class="metric">

                    <small>Temperature</small>

                    <strong>
                        <?= round((float)$current['temperature_2m'], 1) ?> °C
                    </strong>

                </div>


                <div class="metric">

                    <small>Humidity</small>

                    <strong>
                        <?= round((float)$current['relative_humidity_2m']) ?>%
                    </strong>

                </div>


                <div class="metric">

                    <small>Rainfall</small>

                    <strong>
                        <?= round((float)$current['precipitation'], 1) ?> mm
                    </strong>

                </div>


                <div class="metric">

                    <small>Wind Speed</small>

                    <strong>
                        <?= round((float)$current['wind_speed_10m'], 1) ?> km/h
                    </strong>

                </div>

            </div>


            <h3 style="margin:20px 0 12px">
                7-Day Forecast
            </h3>


            <div class="forecast-wrap">

                <div class="forecast">

                    <?php foreach (($daily['time'] ?? []) as $i => $date): ?>

                        <div class="day">

                            <strong>
                                <?= date('D, d M', strtotime($date)) ?>
                            </strong>

                            <small>
                                <?= e(weatherDescription(
                                    (int)($daily['weather_code'][$i] ?? 0)
                                )) ?>
                            </small>

                            <div>
                                <?= round((float)($daily['temperature_2m_max'][$i] ?? 0)) ?>° /
                                <?= round((float)($daily['temperature_2m_min'][$i] ?? 0)) ?>°
                            </div>

                            <small>
                                Rain:
                                <?= round((float)($daily['precipitation_sum'][$i] ?? 0), 1) ?> mm
                            </small>

                            <small>
                                Chance:
                                <?= round((float)($daily['precipitation_probability_max'][$i] ?? 0)) ?>%
                            </small>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </section>

    <?php endif; ?>


    <!-- =================================================
         SAVED CROP WEATHER
    ================================================== -->

    <section class="card">

        <h2>🌱 Weather for Your Saved Crops</h2>

        <p class="subtitle">
            Weather reports are fetched using the field locations
            saved in your Crop Management section.
        </p>


        <?php if (empty($crops)): ?>

            <p class="empty">
                No registered crops were found in your account.
                Add crops in Crop Management to see their weather reports.
            </p>

            <a href="../crops.php">
                Open Crop Management
            </a>

        <?php elseif (empty($weatherResults)): ?>

            <p class="empty">
                Weather information is not available for your saved crops.
                Check the field location details in Crop Management.
            </p>

        <?php endif; ?>


        <?php foreach ($weatherResults as $item): ?>

            <?php
                $crop = $item['crop'];
                $current = $item['current'];
                $daily = $item['daily'];
                $currentCode = (int)($current['weather_code'] ?? 0);
            ?>

            <div style="border-top:1px solid #e5ede3;padding-top:22px;margin-top:22px;">

                <div class="crop-heading">

                    <div>

                        <h2>
                            🌾 <?= e($crop['crop_name']) ?>
                        </h2>

                        <p class="location">

                            <strong>Field:</strong>
                            <?= e($crop['field_name'] ?? 'Not specified') ?>

                            <br>

                            <strong>Field location:</strong>
                            <?= e($item['location']) ?>

                            <br>

                            <strong>Weather forecast area:</strong>
                            <?= e($item['place_name']) ?>

                        </p>

                    </div>

                </div>


                <h3>Current Weather</h3>

                <p style="margin-top:8px;color:#68796b">
                    <?= e(weatherDescription($currentCode)) ?>
                </p>


                <div class="current-grid">

                    <div class="metric">

                        <small>Temperature</small>

                        <strong>
                            <?= round((float)$current['temperature_2m'], 1) ?> °C
                        </strong>

                    </div>


                    <div class="metric">

                        <small>Humidity</small>

                        <strong>
                            <?= round((float)$current['relative_humidity_2m']) ?>%
                        </strong>

                    </div>


                    <div class="metric">

                        <small>Rainfall</small>

                        <strong>
                            <?= round((float)$current['precipitation'], 1) ?> mm
                        </strong>

                    </div>


                    <div class="metric">

                        <small>Wind Speed</small>

                        <strong>
                            <?= round((float)$current['wind_speed_10m'], 1) ?> km/h
                        </strong>

                    </div>

                </div>


                <h3 style="margin:20px 0 12px">
                    7-Day Forecast
                </h3>


                <div class="forecast-wrap">

                    <div class="forecast">

                        <?php foreach (($daily['time'] ?? []) as $i => $date): ?>

                            <div class="day">

                                <strong>
                                    <?= date('D, d M', strtotime($date)) ?>
                                </strong>

                                <small>
                                    <?= e(weatherDescription(
                                        (int)($daily['weather_code'][$i] ?? 0)
                                    )) ?>
                                </small>

                                <div>
                                    <?= round((float)($daily['temperature_2m_max'][$i] ?? 0)) ?>° /
                                    <?= round((float)($daily['temperature_2m_min'][$i] ?? 0)) ?>°
                                </div>

                                <small>
                                    Rain:
                                    <?= round((float)($daily['precipitation_sum'][$i] ?? 0), 1) ?> mm
                                </small>

                                <small>
                                    Chance:
                                    <?= round((float)($daily['precipitation_probability_max'][$i] ?? 0)) ?>%
                                </small>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </section>


    <!-- =================================================
         WEATHER ALERTS
    ================================================== -->

    <section class="card">

        <h2>🔔 Weather Alerts</h2>

        <p class="subtitle">
            Alerts generated from the latest forecast for your registered crops.
        </p>

        <div class="notice">

            Weather thresholds are general precautionary indicators,
            not crop-specific scientific diagnoses. Check local agricultural
            advisories before taking major farming decisions.

            Manual searches do not save alerts in the database.

        </div>


        <?php if (empty($alerts)): ?>

            <p class="empty">
                No weather alerts have been generated yet.
            </p>

        <?php else: ?>

            <?php foreach ($alerts as $alert): ?>

                <div class="alert <?= e($alert['severity']) ?>">

                    <h3>
                        <?= e($alert['title']) ?>
                    </h3>

                    <p>
                        <?= e($alert['message']) ?>
                    </p>

                    <small>

                        Crop: <?= e($alert['crop_name']) ?>

                        |

                        Field: <?= e($alert['field_name'] ?? 'Not specified') ?>

                        |

                        Date: <?= e($alert['alert_date']) ?>

                    </small>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>


<script>

/* =========================================================
   BROWSER NOTIFICATIONS
========================================================= */

const weatherAlerts = <?= $notificationJson ?: '[]' ?>;

document.getElementById('enableNotifications')
    .addEventListener('click', async function () {

        if (!('Notification' in window)) {

            alert('Your browser does not support notifications.');
            return;
        }

        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {

            alert('Notification permission was not granted.');
            return;
        }

        if (weatherAlerts.length === 0) {

            alert('Notifications enabled. There are no unread upcoming alerts.');
            return;
        }

        weatherAlerts.slice(0, 5).forEach(function (alertItem) {

            new Notification(alertItem.title, {
                body: alertItem.message
            });

        });

        alert('Browser notifications enabled.');
    });


/* =========================================================
   MANUAL WEATHER REFRESH
========================================================= */

document.getElementById('refreshWeather')
    .addEventListener('click', function () {

        window.location.reload();

    });

</script>

</body>
</html>