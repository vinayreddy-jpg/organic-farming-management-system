<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . "/auth/bootstrap.php";
require_once __DIR__ . "/config/database.php";

$loggedInUser = current_user();

if (!$loggedInUser || empty($loggedInUser["id"])) {
    redirect("auth/login.php");
}

$userId = (int)$loggedInUser["id"];

$message = "";
$messageType = "success";

/* ---------------- CROP CATEGORIES ---------------- */

$cropGroups = [
    "Cereals & Millets" => [
        "Paddy", "Wheat", "Maize", "Barley", "Sorghum (Jowar)",
        "Pearl Millet (Bajra)", "Finger Millet (Ragi)",
        "Foxtail Millet", "Little Millet", "Kodo Millet",
        "Barnyard Millet", "Proso Millet"
    ],
    "Pulses" => [
        "Red Gram (Pigeon Pea)", "Green Gram", "Black Gram",
        "Bengal Gram (Chickpea)", "Cowpea", "Horse Gram",
        "Field Pea", "Lentil", "Moth Bean", "Lablab Bean"
    ],
    "Oilseeds" => [
        "Groundnut", "Sunflower", "Sesame", "Mustard", "Soybean",
        "Castor", "Safflower", "Linseed", "Niger Seed", "Coconut"
    ],
    "Commercial & Fibre Crops" => [
        "Cotton", "Sugarcane", "Tobacco", "Jute", "Mesta",
        "Tea", "Coffee", "Rubber"
    ],
    "Vegetables" => [
        "Tomato", "Brinjal", "Chilli", "Capsicum", "Potato",
        "Sweet Potato", "Onion", "Garlic", "Cabbage", "Cauliflower",
        "Carrot", "Radish", "Beetroot", "Okra", "Cucumber",
        "Bottle Gourd", "Bitter Gourd", "Ridge Gourd", "Snake Gourd",
        "Pumpkin", "Ash Gourd", "Drumstick", "Spinach",
        "Amaranthus", "Beans", "Peas", "Cluster Beans",
        "Broad Beans", "Knol Khol", "Turnip", "Lettuce",
        "Broccoli", "Zucchini", "Mushroom"
    ],
    "Fruits" => [
        "Mango", "Banana", "Papaya", "Guava", "Pomegranate",
        "Grapes", "Orange", "Sweet Lime", "Lemon", "Watermelon",
        "Muskmelon", "Pineapple", "Sapota", "Custard Apple",
        "Jackfruit", "Amla", "Apple", "Pear", "Peach", "Plum",
        "Strawberry", "Dragon Fruit", "Avocado", "Litchi", "Ber"
    ],
    "Spices & Plantation Crops" => [
        "Turmeric", "Ginger", "Black Pepper", "Cardamom",
        "Clove", "Cinnamon", "Coriander", "Cumin", "Fenugreek",
        "Fennel", "Ajwain", "Tamarind", "Vanilla", "Arecanut",
        "Betel Leaf"
    ],
    "Fodder & Other Crops" => [
        "Napier Grass", "Lucerne", "Berseem", "Fodder Sorghum",
        "Fodder Maize", "Fodder Cowpea", "Azolla", "Indigo",
        "Marigold", "Jasmine", "Rose", "Chrysanthemum"
    ]
];

$cropTypes = [
    "Cereal", "Millet", "Pulse", "Oilseed", "Cash Crop",
    "Fibre Crop", "Vegetable", "Fruit", "Spice",
    "Plantation Crop", "Fodder Crop", "Flower Crop", "Other"
];

$soilTypes = [
    "Alluvial Soil", "Black Soil", "Red Soil", "Sandy Soil",
    "Clay Soil", "Loamy Soil", "Laterite Soil", "Silty Soil", "Other"
];

/* ---------------- CROP TYPE SUGGESTIONS ---------------- */

$cropTypeMap = [
    "Paddy" => "Cereal",
    "Wheat" => "Cereal",
    "Maize" => "Cereal",
    "Barley" => "Cereal",

    "Sorghum (Jowar)" => "Millet",
    "Pearl Millet (Bajra)" => "Millet",
    "Finger Millet (Ragi)" => "Millet",
    "Foxtail Millet" => "Millet",
    "Little Millet" => "Millet",
    "Kodo Millet" => "Millet",
    "Barnyard Millet" => "Millet",
    "Proso Millet" => "Millet",

    "Red Gram (Pigeon Pea)" => "Pulse",
    "Green Gram" => "Pulse",
    "Black Gram" => "Pulse",
    "Bengal Gram (Chickpea)" => "Pulse",
    "Cowpea" => "Pulse",
    "Horse Gram" => "Pulse",
    "Field Pea" => "Pulse",
    "Lentil" => "Pulse",
    "Moth Bean" => "Pulse",
    "Lablab Bean" => "Pulse",

    "Groundnut" => "Oilseed",
    "Sunflower" => "Oilseed",
    "Sesame" => "Oilseed",
    "Mustard" => "Oilseed",
    "Soybean" => "Oilseed",
    "Castor" => "Oilseed",
    "Safflower" => "Oilseed",
    "Linseed" => "Oilseed",
    "Niger Seed" => "Oilseed",

    "Cotton" => "Fibre Crop",
    "Jute" => "Fibre Crop",
    "Mesta" => "Fibre Crop",

    "Sugarcane" => "Cash Crop",
    "Tobacco" => "Cash Crop",

    "Tea" => "Plantation Crop",
    "Coffee" => "Plantation Crop",
    "Rubber" => "Plantation Crop",
    "Coconut" => "Plantation Crop",
    "Arecanut" => "Plantation Crop",
    "Betel Leaf" => "Plantation Crop",

    "Tomato" => "Vegetable",
    "Brinjal" => "Vegetable",
    "Chilli" => "Vegetable",
    "Capsicum" => "Vegetable",
    "Potato" => "Vegetable",
    "Sweet Potato" => "Vegetable",
    "Onion" => "Vegetable",
    "Garlic" => "Vegetable",
    "Cabbage" => "Vegetable",
    "Cauliflower" => "Vegetable",
    "Carrot" => "Vegetable",
    "Radish" => "Vegetable",
    "Beetroot" => "Vegetable",
    "Okra" => "Vegetable",
    "Cucumber" => "Vegetable",
    "Bottle Gourd" => "Vegetable",
    "Bitter Gourd" => "Vegetable",
    "Ridge Gourd" => "Vegetable",
    "Snake Gourd" => "Vegetable",
    "Pumpkin" => "Vegetable",
    "Ash Gourd" => "Vegetable",
    "Drumstick" => "Vegetable",
    "Spinach" => "Vegetable",
    "Amaranthus" => "Vegetable",
    "Beans" => "Vegetable",
    "Peas" => "Vegetable",
    "Cluster Beans" => "Vegetable",
    "Broad Beans" => "Vegetable",
    "Knol Khol" => "Vegetable",
    "Turnip" => "Vegetable",
    "Lettuce" => "Vegetable",
    "Broccoli" => "Vegetable",
    "Zucchini" => "Vegetable",
    "Mushroom" => "Other",

    "Mango" => "Fruit",
    "Banana" => "Fruit",
    "Papaya" => "Fruit",
    "Guava" => "Fruit",
    "Pomegranate" => "Fruit",
    "Grapes" => "Fruit",
    "Orange" => "Fruit",
    "Sweet Lime" => "Fruit",
    "Lemon" => "Fruit",
    "Watermelon" => "Fruit",
    "Muskmelon" => "Fruit",
    "Pineapple" => "Fruit",
    "Sapota" => "Fruit",
    "Custard Apple" => "Fruit",
    "Jackfruit" => "Fruit",
    "Amla" => "Fruit",
    "Apple" => "Fruit",
    "Pear" => "Fruit",
    "Peach" => "Fruit",
    "Plum" => "Fruit",
    "Strawberry" => "Fruit",
    "Dragon Fruit" => "Fruit",
    "Avocado" => "Fruit",
    "Litchi" => "Fruit",
    "Ber" => "Fruit",

    "Turmeric" => "Spice",
    "Ginger" => "Spice",
    "Black Pepper" => "Spice",
    "Cardamom" => "Spice",
    "Clove" => "Spice",
    "Cinnamon" => "Spice",
    "Coriander" => "Spice",
    "Cumin" => "Spice",
    "Fenugreek" => "Spice",
    "Fennel" => "Spice",
    "Ajwain" => "Spice",
    "Tamarind" => "Spice",
    "Vanilla" => "Spice",

    "Napier Grass" => "Fodder Crop",
    "Lucerne" => "Fodder Crop",
    "Berseem" => "Fodder Crop",
    "Fodder Sorghum" => "Fodder Crop",
    "Fodder Maize" => "Fodder Crop",
    "Fodder Cowpea" => "Fodder Crop",
    "Azolla" => "Fodder Crop",

    "Marigold" => "Flower Crop",
    "Jasmine" => "Flower Crop",
    "Rose" => "Flower Crop",
    "Chrysanthemum" => "Flower Crop"
];

/* ---------------- FORM DEFAULTS ---------------- */

$formData = [
    "id" => "",
    "crop_name" => "",
    "crop_type" => "",
    "soil_type" => "",
    "planting_date" => "",
    "fertilizer" => "",
    "description" => "",
    "field_name" => "",
    "field_village" => "",
    "field_district" => "",
    "field_state" => ""
];

/* ---------------- ESCAPE FUNCTION ---------------- */

function h($value): string {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/* ---------------- HANDLE FORM SUBMISSION ---------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    require_csrf();

    $action = $_POST["action"] ?? "";

    /* DELETE CROP */

    if ($action === "delete") {

        $id = (int)($_POST["id"] ?? 0);

        if ($id > 0) {

            $stmt = $conn->prepare(
                "DELETE FROM crop_records
                 WHERE id = ? AND user_id = ?"
            );

            if (!$stmt) {
                $message = "Database error: " . $conn->error;
                $messageType = "error";
            } else {

                $stmt->bind_param("ii", $id, $userId);

                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = "Crop deleted successfully!";
                } else {
                    $message = "Crop could not be deleted.";
                    $messageType = "error";
                }

                $stmt->close();
            }

        } else {
            $message = "Invalid crop record.";
            $messageType = "error";
        }

    } elseif ($action === "add" || $action === "update") {

        /* CROP NAME - ONLY REQUIRED FIELD */

        $crop_name = trim($_POST["crop_name_manual"] ?? "");

        if (($_POST["crop_name"] ?? "") !== "Other") {
            $crop_name = trim($_POST["crop_name"] ?? "");
        }

        /* OPTIONAL CROP TYPE */

        $crop_type = trim($_POST["crop_type_manual"] ?? "");

        if (($_POST["crop_type"] ?? "") !== "Other") {
            $crop_type = trim($_POST["crop_type"] ?? "");
        }

        /* OPTIONAL SOIL TYPE */

        $soil_type = trim($_POST["soil_type_manual"] ?? "");

        if (($_POST["soil_type"] ?? "") !== "Other") {
            $soil_type = trim($_POST["soil_type"] ?? "");
        }

        /* OPTIONAL DETAILS */

        $planting_date = trim($_POST["planting_date"] ?? "");
        $fertilizer = trim($_POST["fertilizer"] ?? "");
        $description = trim($_POST["description"] ?? "");

        /* OPTIONAL FIELD LOCATION */

        $field_name = trim($_POST["field_name"] ?? "");
        $field_village = trim($_POST["field_village"] ?? "");
        $field_district = trim($_POST["field_district"] ?? "");
        $field_state = trim($_POST["field_state"] ?? "");

        /* VALIDATION: ONLY CROP NAME */

        if ($crop_name === "") {

            $message = "Please select or enter a crop name.";
            $messageType = "error";

            $formData = array_merge($formData, $_POST);

        } else {

            /* EMPTY DATE STORED AS NULL */

            $planting_date = ($planting_date === "")
                ? null
                : $planting_date;

            /* UPDATE EXISTING CROP */

            if ($action === "update") {

                $id = (int)($_POST["id"] ?? 0);

                if ($id <= 0) {

                    $message = "Invalid crop record.";
                    $messageType = "error";

                } else {

                    $stmt = $conn->prepare(
                        "UPDATE crop_records
                         SET crop_name = ?,
                             crop_type = ?,
                             soil_type = ?,
                             planting_date = ?,
                             fertilizer = ?,
                             description = ?,
                             field_name = ?,
                             field_village = ?,
                             field_district = ?,
                             field_state = ?
                         WHERE id = ? AND user_id = ?"
                    );

                    if (!$stmt) {

                        $message = "Database error: " . $conn->error;
                        $messageType = "error";

                    } else {

                        $stmt->bind_param(
                            "ssssssssssii",
                            $crop_name,
                            $crop_type,
                            $soil_type,
                            $planting_date,
                            $fertilizer,
                            $description,
                            $field_name,
                            $field_village,
                            $field_district,
                            $field_state,
                            $id,
                            $userId
                        );

                        if ($stmt->execute()) {
                            $message = "Crop updated successfully!";
                        } else {
                            $message = "Error updating crop: " . $stmt->error;
                            $messageType = "error";
                        }

                        $stmt->close();
                    }
                }

            } else {

                /* INSERT NEW CROP */

                $stmt = $conn->prepare(
                    "INSERT INTO crop_records
                    (
                        user_id,
                        crop_name,
                        crop_type,
                        soil_type,
                        planting_date,
                        fertilizer,
                        description,
                        field_name,
                        field_village,
                        field_district,
                        field_state
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$stmt) {

                    $message = "Database error: " . $conn->error;
                    $messageType = "error";

                } else {

                    $stmt->bind_param(
                        "issssssssss",
                        $userId,
                        $crop_name,
                        $crop_type,
                        $soil_type,
                        $planting_date,
                        $fertilizer,
                        $description,
                        $field_name,
                        $field_village,
                        $field_district,
                        $field_state
                    );

                    if ($stmt->execute()) {
                        $message = "Crop added successfully!";
                    } else {
                        $message = "Error adding crop: " . $stmt->error;
                        $messageType = "error";
                    }

                    $stmt->close();
                }
            }
        }

    } else {

        $message = "Invalid form action.";
        $messageType = "error";
    }
}

/* ---------------- LOAD CROP FOR EDITING ---------------- */

if (isset($_GET["edit"])) {

    $editId = (int)$_GET["edit"];

    if ($editId > 0) {

        $stmt = $conn->prepare(
            "SELECT *
             FROM crop_records
             WHERE id = ? AND user_id = ?"
        );

        if ($stmt) {

            $stmt->bind_param("ii", $editId, $userId);
            $stmt->execute();

            $editResult = $stmt->get_result();

            if ($editResult && $editResult->num_rows > 0) {
                $formData = array_merge(
                    $formData,
                    $editResult->fetch_assoc()
                );
            } else {
                $message = "Crop record not found in your account.";
                $messageType = "error";
            }

            $stmt->close();
        }
    }
}

/* ---------------- LOAD FARMER'S CROPS ---------------- */

$stmt = $conn->prepare(
    "SELECT *
     FROM crop_records
     WHERE user_id = ?
     ORDER BY id DESC"
);

if (!$stmt) {
    die("Database error: " . h($conn->error));
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Crop Management | Organic Farming</title>

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
    padding: 25px 6%;
}

.header h2 {
    font-size: 24px;
    margin-bottom: 18px;
}

.header a {
    color: white;
    text-decoration: none;
    font-size: 15px;
}

/* MAIN CONTAINER */

.container {
    max-width: 1100px;
    width: 92%;
    margin: 35px auto;
}

.page-title {
    color: #194d30;
    font-size: 30px;
    margin-bottom: 22px;
}

/* CARDS */

.card {
    background: white;
    padding: 28px 24px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(30, 70, 40, .07);
    border: 1px solid #e9efe8;
}

.card h2 {
    color: #245c37;
    font-size: 23px;
    margin-bottom: 22px;
}

/* FORM */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px 15px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field.full {
    grid-column: 1 / -1;
}

label {
    color: #385541;
    font-size: 14px;
    font-weight: 600;
}

.optional-label {
    color: #829084;
    font-size: 12px;
    font-weight: normal;
}

input,
select,
textarea {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #d5e2d5;
    border-radius: 8px;
    background: #fff;
    color: #263b2a;
    font-size: 14px;
    outline: none;
    transition: .2s;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #32834a;
    box-shadow: 0 0 0 3px rgba(50,131,74,.10);
}

textarea {
    min-height: 90px;
    resize: vertical;
}

.manual-field {
    display: none;
}

/* BUTTONS */

.button-row {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

button,
.cancel-btn {
    border: none;
    border-radius: 8px;
    padding: 13px 22px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    text-align: center;
}

.primary-btn {
    background: #287a43;
    color: white;
    min-width: 220px;
}

.primary-btn:hover {
    background: #1d6135;
}

.cancel-btn {
    background: #edf2ed;
    color: #34533b;
}

/* MESSAGE */

.message {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 17px;
    background: #e1f3e3;
    color: #205c30;
}

.message.error {
    background: #ffeded;
    color: #a12b2b;
}

/* TABLE */

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th {
    background: #e4efe3;
    color: #294b32;
    font-weight: 700;
}

th,
td {
    padding: 15px 13px;
    text-align: left;
    border-bottom: 1px solid #e7ece6;
    font-size: 14px;
}

tr:hover td {
    background: #fafcf9;
}

.action-cell {
    white-space: nowrap;
}

.edit-btn,
.delete-btn {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    border: none;
    cursor: pointer;
}

.edit-btn {
    background: #e4f1e6;
    color: #216438;
}

.delete-btn {
    background: #ffeded;
    color: #c03939;
}

.delete-form {
    display: inline;
}

.empty {
    padding: 20px;
    color: #758176;
}

.location-text {
    line-height: 1.6;
}

/* MOBILE RESPONSIVE */

@media(max-width: 650px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }

    .page-title {
        font-size: 25px;
    }

    .card {
        padding: 20px 16px;
    }

    .button-row {
        flex-direction: column;
    }

    .primary-btn,
    .cancel-btn {
        width: 100%;
    }

    .header {
        padding: 22px 5%;
    }

    .container {
        width: 94%;
        margin: 24px auto;
    }
}
/* SHARED SIDEBAR LAYOUT */

.page-layout {
    display: block;
    min-height: 100vh;
    width: 100%;
}

.main-content {
    flex: 1;
    min-width: 0;
    padding: 30px;
    background: #f3f7f1;
}

.container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

.header {
    display: none;
}

@media (max-width: 768px) {
    .page-layout {
        flex-direction: column;
    }

    .main-content {
        padding: 18px;
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

</style>
<link rel="stylesheet" href="assets/css/mobile-responsive.css">
</head>

<body>

<div class="page-layout">

    

    <main class="main-content">

    <a href="dashboard.php" class="dashboard-back-link">← Back to Dashboard</a>

        <div class="container">

<header class="header">

    <h2>🌱 Organic Farming Management</h2>

    <a href="dashboard.php">
        ← Back to Dashboard
    </a>

</header>

<main class="container">

    <h1 class="page-title">
        🌾 Crop Management
    </h1>

    <?php if ($message !== ""): ?>

        <div class="message <?= h($messageType) ?>">
            <?= h($message) ?>
        </div>

    <?php endif; ?>

    <!-- ADD / EDIT CROP FORM -->

    <section class="card">

        <h2>
            <?= !empty($formData["id"])
                ? "Edit Crop Details"
                : "Add New Crop" ?>
        </h2>

        <form method="POST" id="cropForm">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h(csrf()) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="<?= !empty($formData["id"]) ? "update" : "add" ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?= h($formData["id"]) ?>"
            >

            <div class="form-grid">

                <!-- CROP NAME -->

                <div class="field">

                    <label for="crop_name">
                        Crop Name *
                    </label>

                    <select name="crop_name" id="crop_name">

                        <option value="">Select Crop Name</option>

                        <?php foreach ($cropGroups as $group => $crops): ?>

                            <optgroup label="<?= h($group) ?>">

                                <?php foreach ($crops as $crop): ?>

                                    <option value="<?= h($crop) ?>">
                                        <?= h($crop) ?>
                                    </option>

                                <?php endforeach; ?>

                            </optgroup>

                        <?php endforeach; ?>

                        <option value="Other">
                            Other – Enter Manually
                        </option>

                    </select>

                    <input
                        class="manual-field"
                        type="text"
                        id="crop_name_manual"
                        name="crop_name_manual"
                        placeholder="Enter crop name manually"
                    >

                </div>

                <!-- CROP TYPE -->

                <div class="field">

                    <label for="crop_type">
                        Crop Type
                        <span class="optional-label">(Optional)</span>
                    </label>

                    <select name="crop_type" id="crop_type">

                        <option value="">Select Crop Type</option>

                        <?php foreach ($cropTypes as $type): ?>

                            <?php if ($type !== "Other"): ?>

                                <option value="<?= h($type) ?>">
                                    <?= h($type) ?>
                                </option>

                            <?php endif; ?>

                        <?php endforeach; ?>

                        <option value="Other">
                            Other – Enter Manually
                        </option>

                    </select>

                    <input
                        class="manual-field"
                        type="text"
                        id="crop_type_manual"
                        name="crop_type_manual"
                        placeholder="Enter crop type manually"
                    >

                </div>

                <!-- PLANTING DATE -->

                <div class="field">

                    <label for="planting_date">
                        Planting Date
                        <span class="optional-label">(Optional)</span>
                    </label>

                    <input
                        type="date"
                        name="planting_date"
                        id="planting_date"
                        value="<?= h($formData["planting_date"]) ?>"
                    >

                </div>

                <!-- FERTILIZER -->

                <div class="field">

                    <label for="fertilizer">
                        Fertilizer Used
                        <span class="optional-label">(Optional)</span>
                    </label>

                    <input
                        type="text"
                        name="fertilizer"
                        id="fertilizer"
                        placeholder="e.g. Vermicompost, Neem Cake"
                        value="<?= h($formData["fertilizer"]) ?>"
                    >

                </div>

                <!-- SOIL TYPE -->

                <div class="field">

                    <label for="soil_type">
                        Soil Type
                        <span class="optional-label">(Optional)</span>
                    </label>

                    <select name="soil_type" id="soil_type">

                        <option value="">Select Soil Type</option>

                        <?php foreach ($soilTypes as $soil): ?>

                            <?php if ($soil !== "Other"): ?>

                                <option value="<?= h($soil) ?>">
                                    <?= h($soil) ?>
                                </option>

                            <?php endif; ?>

                        <?php endforeach; ?>

                        <option value="Other">
                            Other – Enter Manually
                        </option>

                    </select>

                    <input
                        class="manual-field"
                        type="text"
                        id="soil_type_manual"
                        name="soil_type_manual"
                        placeholder="Enter soil type manually"
                    >

                </div>

                <!-- FIELD NAME -->

                <div class="field">

                    <label for="field_name">
                        Field Name
                        <span class="optional-label">(Optional)</span>
                    </label>

                    <input
                        type="text"
                        name="field_name"
                        id="field_name"
                        placeholder="e.g. North Paddy Field"
                        value="<?= h($formData["field_name"]) ?>"
                    >

                </div>

                <!-- VILLAGE -->

                <div class="field">

                    <label for="field_village">
                        Field Village
                        <span class="optional-label">(Optional)</span>
                    </label>

                    <input
                        type="text"
                        name="field_village"
                        id="field_village"
                        placeholder="Enter field village"
                        value="<?= h($formData["field_village"]) ?>"
                    >

                </div>

                <!-- DISTRICT -->

                <div class="field">

                    <label for="field_district">
                        Field District
                        <span class="optional-label">(Optional)</span>
                    </label>

                    <input
                        type="text"
                        name="field_district"
                        id="field_district"
                        placeholder="Enter field district"
                        value="<?= h($formData["field_district"]) ?>"
                    >

                </div>

                <!-- STATE -->

                <div class="field">

                    <label for="field_state">
                        Field State
                        <span class="optional-label">(Optional)</span>
                    </label>

                    <input
                        type="text"
                        name="field_state"
                        id="field_state"
                        placeholder="Enter state"
                        value="<?= h($formData["field_state"]) ?>"
                    >

                </div>

                <!-- DESCRIPTION -->

                <div class="field full">

                    <label for="description">
                        Crop Description
                        <span class="optional-label">(Optional)</span>
                    </label>

                    <textarea
                        name="description"
                        id="description"
                        placeholder="Enter crop description..."
                    ><?= h($formData["description"]) ?></textarea>

                </div>

            </div>

            <div class="button-row">

                <button type="submit" class="primary-btn">

                    <?= !empty($formData["id"])
                        ? "Save Changes"
                        : "+ Add Crop" ?>

                </button>

                <?php if (!empty($formData["id"])): ?>

                    <a href="crops.php" class="cancel-btn">
                        Cancel Edit
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- REGISTERED CROPS -->

    <section class="card">

        <h2>Registered Crops</h2>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Crop Name</th>
                        <th>Crop Type</th>
                        <th>Soil Type</th>
                        <th>Planting Date</th>
                        <th>Fertilizer</th>
                        <th>Field Location</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td><?= h($row["id"]) ?></td>

                            <td>
                                <?= h($row["crop_name"]) ?>
                            </td>

                            <td>
                                <?= h($row["crop_type"] ?: "—") ?>
                            </td>

                            <td>
                                <?= h($row["soil_type"] ?: "—") ?>
                            </td>

                            <td>
                                <?= h($row["planting_date"] ?: "—") ?>
                            </td>

                            <td>
                                <?= h($row["fertilizer"] ?: "—") ?>
                            </td>

                            <td class="location-text">

                                <?= h($row["field_name"] ?: "—") ?><br>

                                <?php
                                $locationParts = array_filter([
                                    $row["field_village"] ?? "",
                                    $row["field_district"] ?? "",
                                    $row["field_state"] ?? ""
                                ], function ($value) {
                                    return trim((string)$value) !== "";
                                });

                                echo h(
                                    !empty($locationParts)
                                        ? implode(", ", $locationParts)
                                        : "Location not provided"
                                );
                                ?>

                            </td>

                            <td class="action-cell">

                                <a
                                    class="edit-btn"
                                    href="crops.php?edit=<?= (int)$row["id"] ?>"
                                >
                                    Edit
                                </a>

                                <form
                                    method="POST"
                                    class="delete-form"
                                    onsubmit="return confirm('Are you sure you want to delete this crop?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= h(csrf()) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$row["id"] ?>"
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

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty">
                            No crops added yet. Add your first crop above.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

        </div>

    </main>

</div>

<script>

/* ---------------- CROP TYPE MAP ---------------- */

const cropTypeMap = <?= json_encode(
    $cropTypeMap,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;

const cropSelect = document.getElementById("crop_name");
const typeSelect = document.getElementById("crop_type");
const soilSelect = document.getElementById("soil_type");

/* ---------------- MANUAL INPUT HANDLING ---------------- */

function setupManual(select, inputId, requiredWhenOther = false) {

    const manualInput = document.getElementById(inputId);

    function toggle() {

        const isOther = select.value === "Other";

        manualInput.style.display = isOther ? "block" : "none";

        manualInput.required = isOther && requiredWhenOther;

        if (!isOther) {
            manualInput.value = "";
        }
    }

    select.addEventListener("change", toggle);

    toggle();
}

setupManual(cropSelect, "crop_name_manual", true);
setupManual(typeSelect, "crop_type_manual", false);
setupManual(soilSelect, "soil_type_manual", false);

/* ---------------- AUTOMATIC CROP TYPE SUGGESTION ---------------- */

cropSelect.addEventListener("change", function () {

    const selectedCrop = this.value;
    const suggestedType = cropTypeMap[selectedCrop];

    const manualType = document.getElementById("crop_type_manual");

    if (suggestedType) {

        typeSelect.value = suggestedType;

        manualType.style.display = "none";
        manualType.required = false;
        manualType.value = "";

    } else if (selectedCrop === "Other") {

        typeSelect.value = "";

        manualType.style.display = "none";
        manualType.required = false;

    } else {

        typeSelect.value = "";

        manualType.style.display = "none";
        manualType.required = false;
        manualType.value = "";
    }
});

/* ---------------- RESTORE VALUES WHEN EDITING ---------------- */

const savedCrop = <?= json_encode($formData["crop_name"] ?? "") ?>;
const savedType = <?= json_encode($formData["crop_type"] ?? "") ?>;
const savedSoil = <?= json_encode($formData["soil_type"] ?? "") ?>;

function restoreSelect(select, savedValue, manualInputId, requiredWhenOther = false) {

    if (!savedValue) {
        select.value = "";
        return;
    }

    const matchingOption = Array.from(select.options).find(
        option => option.value === savedValue
    );

    if (matchingOption) {

        select.value = savedValue;

    } else {

        select.value = "Other";

        const manualInput = document.getElementById(manualInputId);

        manualInput.value = savedValue;
        manualInput.style.display = "block";
        manualInput.required = requiredWhenOther;
    }
}

restoreSelect(
    cropSelect,
    savedCrop,
    "crop_name_manual",
    true
);

restoreSelect(
    typeSelect,
    savedType,
    "crop_type_manual",
    false
);

restoreSelect(
    soilSelect,
    savedSoil,
    "soil_type_manual",
    false
);

/* ---------------- FORM VALIDATION ---------------- */

document.getElementById("cropForm").addEventListener("submit", function (event) {

    let cropName = cropSelect.value;

    if (cropName === "Other") {

        cropName = document
            .getElementById("crop_name_manual")
            .value.trim();
    }

    if (cropName === "") {

        event.preventDefault();

        alert("Please select or enter a crop name.");

        cropSelect.focus();
    }
});

</script>

</body>
</html>

<?php
$stmt->close();
?>
