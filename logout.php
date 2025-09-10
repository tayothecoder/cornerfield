<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Utils\SessionManager;

SessionManager::start();
SessionManager::destroy();

header('Location: login.php?message=logged_out');
exit;
?>