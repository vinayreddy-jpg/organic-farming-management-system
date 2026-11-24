<?php
$current_page = basename($_SERVER['PHP_SELF']);
$base_path = '/organic-farming/';

$menu_items = [
    ['icon' => '🏠', 'name' => 'Dashboard', 'url' => 'dashboard.php'],
    ['icon' => '🌾', 'name' => 'Crop Management', 'url' => 'crops.php'],
    ['icon' => '🌿', 'name' => 'Fertilizers', 'url' => 'farmer/fertilizers.php'],
    ['icon' => '🐛', 'name' => 'Pest Management', 'url' => 'farmer/pest-management.php'],
    ['icon' => '🌦️', 'name' => 'Weather & Alerts', 'url' => 'farmer/weather-alerts.php'],
    ['icon' => '📊', 'name' => 'Market', 'url' => 'farmer/market-management.php'],
    ['icon' => 'ℹ️', 'name' => 'About Project', 'url' => 'farmer/about.php']
];
?>

<aside class="sidebar">

    <div class="sidebar-brand">
        <span>🌱</span>
        <h2>Organic Farm</h2>
    </div>

    <nav class="sidebar-menu">

        <?php foreach ($menu_items as $item): ?>

            <a href="<?php echo $base_path . htmlspecialchars($item['url']); ?>"
   class="sidebar-link <?php echo ($current_page === basename($item['url'])) ? 'active' : ''; ?>">

                <span class="sidebar-icon">
                    <?php echo $item['icon']; ?>
                </span>

                <span>
                    <?php echo htmlspecialchars($item['name']); ?>
                </span>

            </a>

        <?php endforeach; ?>

    </nav>

</aside>

<style>

.sidebar {
    position: sticky;
    top: 0;
    align-self: flex-start;
    width: 316px;
    min-width: 316px;
    height: 100vh;
    box-sizing: border-box;
    background: #174f32;
    padding: 30px 18px;
    color: white;
    flex-shrink: 0;
    overflow-y: auto;

    /* Consistent font on every page */
    font-family: Arial, Helvetica, sans-serif !important;
}

.sidebar,
.sidebar * {
    font-family: Arial, Helvetica, sans-serif !important;
    font-style: normal;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    margin-bottom: 55px;
}

.sidebar-brand span {
    font-size: 32px;
}

.sidebar-brand h2 {
    font-size: 27px;
    color: white;
    margin: 0;
    white-space: nowrap;
    font-weight: 700;
}

.sidebar-menu {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 20px 18px;
    min-height: 70px;
    box-sizing: border-box;
    color: white !important;
    text-decoration: none !important;
    font-size: 20px;
    font-weight: 500;
    border-radius: 13px;
    transition: background 0.2s ease;
}

.sidebar-icon {
    width: 34px;
    text-align: center;
    font-size: 25px;
    flex-shrink: 0;
}

.sidebar-link:hover,
.sidebar-link.active {
    background: #377d50;
    color: white !important;
}

@media (max-width: 768px) {
    .sidebar {
        position: static;
        width: 100%;
        min-width: 0;
        height: auto;
        min-height: auto;
        padding: 18px;
        overflow-y: visible;
    }

    .sidebar-brand {
        margin-bottom: 15px;
    }

    .sidebar-menu {
        gap: 5px;
    }

    .sidebar-link {
        min-height: 50px;
        padding: 12px;
        font-size: 16px;
    }
}


</style>
