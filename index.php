
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organic Farming Management System</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f8f1;
            color: #263b2a;
        }

        header {
            background: #245c37;
            color: white;
            padding: 22px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h2 {
            font-size: 22px;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin-left: 22px;
        }

        .hero {
            padding: 100px 8%;
            text-align: center;
        }

        .hero h1 {
            font-size: 42px;
            margin-bottom: 20px;
        }

        .hero p {
            font-size: 18px;
            color: #637365;
            margin-bottom: 30px;
        }

        .btn {
            display: inline-block;
            background: #32834a;
            color: white;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 8px;
        }

        .features {
            padding: 30px 8% 70px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #00000010;
        }

        .card h3 {
            margin-bottom: 12px;
            color: #245c37;
        }

        footer {
            background: #245c37;
            color: white;
            text-align: center;
            padding: 20px;
        }
    </style>
<link rel="stylesheet" href="/organic-farming/assets/css/mobile-responsive.css">
</head>

<body>

<header>
    <h2>🌱 Organic Farming</h2>

    <nav>
        <a href="index.php">Home</a>
        <a href="#features">Features</a>
    </nav>
</header>

<section class="hero">

    <h1>Welcome to Organic Farming</h1>

    <p>
        Promoting sustainable agriculture
        and a healthier environment.
    </p>

    <a href="dashboard.php" class="btn">
    Explore Application
</a>

</section>

<section class="features" id="features">

    <div class="card">
        <h3>🌾 Crop Management</h3>
        <p>Manage crops and monitor farming activities.</p>
    </div>

    <div class="card">
        <h3>🌿 Organic Fertilizers</h3>
        <p>Learn about cow dung, compost and vermicompost.</p>
    </div>

    <div class="card">
        <h3>🐛 Pest Management</h3>
        <p>Explore natural pest management methods.</p>
    </div>

    <div class="card">
        <h3>👨‍🌾 Farmer Records</h3>
        <p>Maintain farmer information and feedback.</p>
    </div>

</section>

<footer>
    <p>Organic Farming Management System | Community Service Project</p>
</footer>

</body>
</html>
