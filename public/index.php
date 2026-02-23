<?php
require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

\App\Utils\SessionManager::start();

// check maintenance mode
try {
    $database = new \App\Config\Database();
    $adminSettingsModel = new \App\Models\AdminSettings($database);
    $maintenanceMode = $adminSettingsModel->getSetting('maintenance_mode', 0);
    
    if ($maintenanceMode && !isset($_GET['admin_bypass'])) {
        $siteName = \App\Config\Config::getSiteName();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Site Under Maintenance - <?= htmlspecialchars($siteName) ?></title>
            <style>
                .maintenance-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: sans-serif; }
            </style>
        </head>
        <body class="maintenance-page">
            <div style="text-align: center;">
                <h1>Site Under Maintenance</h1>
                <p>We're currently performing scheduled maintenance. Please check back shortly.</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Exception $e) {
    // if database fails, allow access
}

// redirect to login page
header('Location: ' . \App\Config\Config::getBasePath() . 'login.php');
exit;
