<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>About Project | Organic Farming Management System</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f3f7f2;
    color: #29392e;
    line-height: 1.65;
}

.page {
    width: 100%;
    padding: 25px 5%;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.brand {
    color: #194d30;
    font-size: 23px;
    font-weight: 800;
}

.back-button {
    display: inline-block;
    background: #194d30;
    color: white;
    text-decoration: none;
    padding: 11px 19px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 700;
    transition: .2s;
}

.back-button:hover {
    background: #347b4d;
}

.hero {
    background: linear-gradient(120deg, #194d30, #398451);
    color: white;
    padding: 55px 35px;
    border-radius: 18px;
    margin-bottom: 28px;
}

.hero .tag {
    display: inline-block;
    background: rgba(255,255,255,.16);
    padding: 6px 13px;
    border-radius: 20px;
    font-size: 13px;
    margin-bottom: 15px;
}

.hero h1 {
    font-size: clamp(27px, 4vw, 40px);
    margin: 0 0 14px;
    line-height: 1.2;
}

.hero p {
    max-width: 850px;
    color: #e5f1e7;
    margin: 0;
    font-size: 16px;
}

.section {
    background: white;
    padding: 28px;
    border-radius: 15px;
    margin-bottom: 23px;
    border: 1px solid #e5ece5;
    box-shadow: 0 3px 14px rgba(0,0,0,.04);
}

.section h2 {
    color: #194d30;
    font-size: 22px;
    margin: 0 0 16px;
}

.section p {
    margin: 0 0 12px;
    color: #59675d;
}

.objectives {
    padding-left: 22px;
    margin: 0;
}

.objectives li {
    margin: 10px 0;
    color: #526157;
}

.module-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
}

.module-card {
    background: #f7faf6;
    border: 1px solid #e5ece5;
    border-radius: 12px;
    padding: 20px;
}

.module-card .icon {
    font-size: 28px;
    margin-bottom: 8px;
}

.module-card h3 {
    color: #194d30;
    font-size: 16px;
    margin: 0 0 8px;
}

.module-card p {
    font-size: 13px;
    margin: 0;
}

.tech-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 13px;
}

.tech {
    text-align: center;
    background: #f0f6ef;
    padding: 17px 10px;
    border-radius: 10px;
    color: #194d30;
    font-weight: 700;
}

.workflow {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
}

.workflow-item {
    text-align: center;
    padding: 20px 12px;
    background: #f7faf6;
    border-radius: 12px;
}

.workflow-item .number {
    display: inline-flex;
    width: 35px;
    height: 35px;
    align-items: center;
    justify-content: center;
    background: #194d30;
    color: white;
    border-radius: 50%;
    font-weight: 700;
    margin-bottom: 10px;
}

.workflow-item h3 {
    color: #194d30;
    font-size: 15px;
    margin: 0 0 5px;
}

.workflow-item p {
    font-size: 12px;
    margin: 0;
}

.highlight {
    background: #edf5ed;
    border-left: 5px solid #347b4d;
    padding: 20px;
    border-radius: 8px;
}

.highlight strong {
    color: #194d30;
}

.footer {
    text-align: center;
    color: #89958a;
    padding: 20px 10px;
    font-size: 13px;
}

@media(max-width: 850px) {
    .module-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .tech-grid,
    .workflow {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 550px) {
    .page {
        padding: 16px 4%;
    }

    .hero {
        padding: 30px 22px;
    }

    .section {
        padding: 20px;
    }

    .module-grid,
    .tech-grid,
    .workflow {
        grid-template-columns: 1fr;
    }
}
</style>
<link rel="stylesheet" href="/organic-farming/assets/css/mobile-responsive.css">
</head>

<body>

<div class="page">

    <header class="topbar">
        <div class="brand">🌱 Organic Farming</div>

        <a href="../dashboard.php" class="back-button">
            ← Back to Dashboard
        </a>
    </header>

    <!-- PROJECT INTRODUCTION -->

    <section class="hero">
        <span class="tag">COMMUNITY SERVICE PROJECT</span>

        <h1>Organic Farming Management System</h1>

        <p>
            A web-based platform designed to support farmers through
            digital farm management, organic farming recommendations,
            crop information, pest management, weather alerts and
            agricultural market information.
        </p>
    </section>

    <!-- PROJECT OVERVIEW -->

    <section class="section">
        <h2>🌿 Project Overview</h2>

        <p>
            The Organic Farming Management System is a web application
            developed to make important farming information and farm
            management activities easier to access.
        </p>

        <p>
            The application provides farmers with a centralized platform
            to maintain crop records, explore suitable crop recommendations,
            understand pest and disease management, access organic
            fertilizer guidance and view agricultural market information.
        </p>

        <p>
            The project focuses on using web technology to support
            informed farming decisions and improve the organization
            of agricultural information.
        </p>
    </section>

    <!-- OBJECTIVES -->

    <section class="section">
        <h2>🎯 Project Objectives</h2>

        <ul class="objectives">
            <li>Promote awareness and understanding of organic farming practices.</li>
            <li>Provide a digital platform for maintaining farmer and crop records.</li>
            <li>Help farmers explore suitable crops through crop recommendations.</li>
            <li>Provide guidance for identifying crop pests and diseases.</li>
            <li>Recommend organic fertilizers based on available crop and soil information.</li>
            <li>Provide weather alerts and agricultural market information.</li>
            <li>Make farming information easier to organize and access.</li>
        </ul>
    </section>

    <!-- APPLICATION MODULES -->

    <section class="section">
        <h2>🧩 Application Modules</h2>

        <div class="module-grid">

            <div class="module-card">
                <div class="icon">🌾</div>
                <h3>Crop Management</h3>
                <p>
                    Maintain crop information, planting details,
                    field information and registered crop records.
                </p>
            </div>

            <div class="module-card">
                <div class="icon">🌱</div>
                <h3>Organic Fertilizer Management</h3>
                <p>
                    Explore organic fertilizer recommendations
                    based on available crop and soil information.
                </p>
            </div>

            <div class="module-card">
                <div class="icon">🐛</div>
                <h3>Pest & Disease Management</h3>
                <p>
                    Explore possible pest and disease problems
                    with suitable organic treatment guidance.
                </p>
            </div>

            <div class="module-card">
                <div class="icon">🌦️</div>
                <h3>Weather & Alerts</h3>
                <p>
                    Access weather-related information and
                    alerts to support farming activities.
                </p>
            </div>

            <div class="module-card">
                <div class="icon">📊</div>
                <h3>Market Management</h3>
                <p>
                    View stored crop prices, compare markets,
                    explore MSP information and manage crop listings.
                </p>
            </div>

            <div class="module-card">
                <div class="icon">👨‍🌾</div>
                <h3>Farmer Records</h3>
                <p>
                    View personal information, farm details,
                    registered crops and the farmer's own crop listings.
                </p>
            </div>

            <div class="module-card">
                <div class="icon">💡</div>
                <h3>Smart Crop Recommendations</h3>
                <p>
                    Explore crop suggestions using available
                    soil, crop and suitability information.
                </p>
            </div>

            <div class="module-card">
                <div class="icon">🔐</div>
                <h3>User Authentication</h3>
                <p>
                    Provide registration and login functionality
                    for accessing the application.
                </p>
            </div>

        </div>
    </section>

    <!-- TECHNOLOGY STACK -->

    <section class="section">
        <h2>💻 Technologies Used</h2>

        <p>
            The application is developed using web technologies
            and a relational database.
        </p>

        <div class="tech-grid">
            <div class="tech">HTML5</div>
            <div class="tech">CSS3</div>
            <div class="tech">JavaScript</div>
            <div class="tech">PHP</div>
            <div class="tech">MySQL</div>
            <div class="tech">XAMPP</div>
            <div class="tech">VS Code</div>
            <div class="tech">phpMyAdmin</div>
        </div>
    </section>

    <!-- WORKFLOW -->

    <section class="section">
        <h2>⚙️ How the Application Works</h2>

        <div class="workflow">

            <div class="workflow-item">
                <div class="number">1</div>
                <h3>Register & Login</h3>
                <p>Farmers access the application using their account.</p>
            </div>

            <div class="workflow-item">
                <div class="number">2</div>
                <h3>Manage Farm</h3>
                <p>Maintain personal details and crop information.</p>
            </div>

            <div class="workflow-item">
                <div class="number">3</div>
                <h3>Explore Guidance</h3>
                <p>Access recommendations, pest guidance and alerts.</p>
            </div>

            <div class="workflow-item">
                <div class="number">4</div>
                <h3>View Market Data</h3>
                <p>Explore stored market prices and manage crop listings.</p>
            </div>

        </div>
    </section>

    <!-- PROJECT BENEFITS -->

    <section class="section">
        <h2>🌍 Benefits of the Project</h2>

        <ul class="objectives">
            <li>Centralized management of farmer and crop information.</li>
            <li>Improved access to organic farming guidance.</li>
            <li>Easy access to crop and soil-related information.</li>
            <li>Better awareness of pest and disease management.</li>
            <li>Convenient access to stored market price information.</li>
            <li>Support for organized and informed farm management.</li>
        </ul>
    </section>

    <!-- COMMUNITY SERVICE PROJECT -->

    <section class="section">
        <h2>🤝 Community Service Project</h2>

        <div class="highlight">
            <p>
                <strong>Project Title:</strong>
                Organic Farming Management System
            </p>

            <p>
                <strong>Project Location:</strong>
                Jonawada, Nellore District, Andhra Pradesh
            </p>

            <p>
                <strong>Project Type:</strong>
                Community Service Project (CSP)
            </p>

            <p>
                This project connects community-level agricultural
                awareness with a web-based application designed to
                organize farming information and provide useful
                digital resources.
            </p>
        </div>
    </section>

    <!-- CONCLUSION -->

    <section class="section">
        <h2>🌱 Conclusion</h2>

        <p>
            The Organic Farming Management System demonstrates how
            web technologies can be used to organize agricultural
            information and provide useful tools for farmers.
        </p>

        <p>
            By bringing crop management, organic fertilizer guidance,
            pest management, weather alerts and market information
            together, the application aims to make farming information
            more accessible and organized.
        </p>
    </section>

    <footer class="footer">
        Organic Farming Management System
        <br>
        Community Service Project | Jonawada, Nellore
    </footer>

</div>

</body>
</html>
