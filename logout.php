<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: logout.php
 * Purpose: Simple logout handler
 * Security Level: PUBLIC
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

// Include autoload
require_once __DIR__ . '/autoload.php';

use App\Controllers\AuthController;

// Create auth controller and handle logout
$authController = new AuthController();
$authController->logout();