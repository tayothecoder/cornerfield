<?php
require_once __DIR__ . '/src/Utils/SessionManager.php';

SessionManager::start();
SessionManager::destroy();

header('Location: login.php?message=logged_out');
exit;
?>