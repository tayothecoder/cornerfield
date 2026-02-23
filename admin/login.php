<?php

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// set session cookie path before any session starts
if (session_status() === PHP_SESSION_NONE) {
    $basePath = \App\Config\Config::getBasePath() ?: '/';
    session_set_cookie_params([
        'path' => $basePath,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

try {
    // Use the modern factory pattern
    $database = new \App\Config\Database();
    $adminController = new \App\Controllers\AdminController($database);
} catch (Exception $e) {
    if (\App\Config\Config::isDebug()) {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

// Redirect if already logged in
if ($adminController->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle login form submission
error_log("Admin login attempt - method: " . $_SERVER['REQUEST_METHOD'] . " email: " . ($_POST['email'] ?? 'empty'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    error_log("Admin login processing - email: $email, pass_len: " . strlen($password));

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $result = $adminController->login($email, $password);
            
            if ($result['success']) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = \App\Config\Config::isDebug() ? 'Login error: ' . $e->getMessage() : 'Login failed. Please try again.';
        }
    }
}

$base = \App\Config\Config::getBasePath();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.theme==="dark"||(!localStorage.theme&&window.matchMedia("(prefers-color-scheme:dark)").matches)){document.documentElement.classList.add("dark")}else{document.documentElement.classList.remove("dark")}</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= \App\Config\Config::getSiteName() ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/tailwind-compiled.css?v=4">
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/cornerfield.css?v=4">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($base) ?>/assets/images/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-[#f5f3ff] dark:bg-[#0f0a2e] flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- logo -->
        <div class="text-center mb-8">
            <span class="text-2xl font-semibold tracking-tight text-[#1e0e62] dark:text-white"><?= htmlspecialchars(explode(' ', \App\Config\Config::getSiteName())[0]) ?></span>
            <span class="ml-2 text-[10px] font-medium uppercase tracking-wider bg-[#1e0e62]/10 dark:bg-white/10 text-[#1e0e62] dark:text-white/80 px-2 py-0.5 rounded-full">admin</span>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-8 shadow-sm">
            <?php if ($error): ?>
            <div class="mb-6 p-3 rounded-xl text-sm bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Admin email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#1e0e62] focus:ring-2 focus:ring-[#1e0e62]/20 outline-none transition-colors"
                        placeholder="<?= htmlspecialchars(\App\Config\Config::getAdminEmail()) ?>"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        autocomplete="username"
                        required
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#1e0e62] focus:ring-2 focus:ring-[#1e0e62]/20 outline-none transition-colors"
                        placeholder="Your password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="w-full bg-[#1e0e62] text-white font-medium py-2.5 px-6 rounded-full hover:bg-[#2d1b8a] transition-colors focus:outline-none">
                    Sign in to admin panel
                </button>
            </form>

            <?php if (\App\Config\Config::isDebug()): ?>
            <div class="mt-4 p-3 rounded-xl bg-gray-50 dark:bg-[#0f0a2e] text-xs text-gray-500 dark:text-gray-400">
                <span class="font-medium">Debug mode</span><br>
                Email: <?= \App\Config\Config::getAdminEmail() ?><br>
                Password: admin123
            </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-4">
            <a href="<?= htmlspecialchars($base) ?>/users/dashboard.php" class="text-sm text-gray-500 dark:text-gray-400 hover:text-[#1e0e62] dark:hover:text-white transition-colors">
                Back to user site
            </a>
        </div>
    </div>
</body>
</html>
