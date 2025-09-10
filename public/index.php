<?php
require_once __DIR__ . '/../config/Config.php';
require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Models/Investment.php';
require_once __DIR__ . '/../src/Models/Transaction.php';
require_once __DIR__ . '/../src/Utils/SessionManager.php';

// Check maintenance mode
try {
    $database = DatabaseFactory::create();
    $adminSettingsModel = new AdminSettings($database);
    $maintenanceMode = $adminSettingsModel->getSetting('maintenance_mode', 0);
    
    if ($maintenanceMode && !isset($_GET['admin_bypass'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Site Under Maintenance - <?= Config::getSiteName() ?></title>
            <link href="assets/tabler/dist/css/tabler.min.css" rel="stylesheet">
            <style>
                .maintenance-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .crypto-icon { color: #f7931a; font-size: 4rem; }
            </style>
        </head>
        <body class="maintenance-page">
            <div class="container text-center">
                <div class="crypto-icon mb-4">₿</div>
                <h1 class="display-4 mb-3">Site Under Maintenance</h1>
                <p class="lead text-muted mb-4">We're currently performing scheduled maintenance. Please check back shortly.</p>
                <div class="text-muted">Expected completion: Within 2 hours</div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Exception $e) {
    // If database fails, allow access
}

?>