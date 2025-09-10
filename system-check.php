<?php
// system-check.php - Enhanced to work with new autoloader and modern code structure

// Security check
if (file_exists(__DIR__ . '/config/Config.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Check if Config class exists and is accessible
    if (class_exists('App\Config\Config')) {
        if (\App\Config\Config::isProduction()) {
            die('This tool is not available in production environment.');
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cornerfield System Check & Analysis</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f7fa; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .status-good { color: #22c55e; font-weight: bold; }
        .status-warning { color: #f59e0b; font-weight: bold; }
        .status-error { color: #ef4444; font-weight: bold; }
        .status-info { color: #3b82f6; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .file-tree { background: #f9fafb; padding: 15px; border-radius: 4px; max-height: 500px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px; }
        .folder { color: #3b82f6; font-weight: bold; }
        .file { color: #374151; }
        .debug-file { color: #ef4444; background: #fee2e2; padding: 2px 4px; border-radius: 2px; }
        .ignored-file { color: #9ca3af; }
        .indent-1 { margin-left: 20px; }
        .indent-2 { margin-left: 40px; }
        .indent-3 { margin-left: 60px; }
        .indent-4 { margin-left: 80px; }
        .cleanup-section { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 4px; padding: 15px; margin: 10px 0; }
        .btn { padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #2563eb; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Cornerfield System Analysis</h1>
            <p>Complete project structure analysis with smart filtering</p>
        </div>

        <?php
        $rootDir = __DIR__;
        $issues = [];
        $debugFiles = [];
        
        // Define expected structure for new codebase
        $expectedStructure = [
            'vendor/autoload.php' => 'Composer autoloader',
            'config/Config.php' => 'Configuration file',
            'src/Config/Database.php' => 'Database connection',
            'src/Config/DatabaseFactory.php' => 'Database factory',
            'src/Controllers/AuthController.php' => 'Auth controller',
            'src/Models/Admin.php' => 'Admin model',
            'src/Models/User.php' => 'User model',
            'src/Models/Investment.php' => 'Investment model',
            'src/Models/Transaction.php' => 'Transaction model',
            'src/Models/AdminSettings.php' => 'Admin settings model',
            'src/Models/Profit.php' => 'Profit model',
            'src/Utils/Security.php' => 'Security utilities',
            'src/Utils/SessionManager.php' => 'Session manager',
            'src/Utils/SecurityManager.php' => 'Security manager',
            'src/Utils/GlobalSecurity.php' => 'Global security',
            'src/Services/EmailService.php' => 'Email service',
            'src/Services/PaymentGatewayService.php' => 'Payment gateway service',
            'src/Services/SupportService.php' => 'Support service',
            'src/Services/UserTransferService.php' => 'User transfer service',
            'src/Services/EnhancedAdminSettings.php' => 'Enhanced admin settings',
            'admin/login.php' => 'Admin login page',
            'admin/dashboard.php' => 'Admin dashboard',
            'login.php' => 'User login page',
            'users/dashboard.php' => 'User dashboard',
            'cron/daily-profits.php' => 'Daily profit distribution',
            'setup/run_new_tables.php' => 'Database setup script',
            '.env' => 'Environment variables'
        ];

        // Improved debug patterns - more specific to avoid false positives
        $debugPatterns = [
            'debug_', 'test_', 'temp_', 'check_', 'backup_', 'old_', 'copy_', '_bak', '_test', '_debug'
        ];

        // Paths to ignore (Tabler and other frontend assets)
        $ignorePaths = [
            'public/assets/tabler/',
            'node_modules/',
            '.git/',
            'vendor/',
            'storage/framework/',
            'storage/app/',
            'assets/tabler/'
        ];

        function shouldIgnorePath($path, $ignorePaths) {
            foreach ($ignorePaths as $ignorePath) {
                if (strpos($path, $ignorePath) === 0) {
                    return true;
                }
            }
            return false;
        }

        function isDebugFile($fileName, $debugPatterns) {
            // More intelligent debug file detection
            $fileName = strtolower($fileName);
            
            // Skip common legitimate files that might contain debug-like words
            $legitimateFiles = [
                'testimonials', 'placeholder', 'folders', 'contemporary', 'elegant',
                'checkout', 'checklist', 'checkpoint', 'checker', 'checking',
                'testing', 'testimonial', 'testimony', 'contest', 'contestant',
                'tempest', 'temporary', 'temperature', 'template', 'temple',
                'backup', 'backup', 'background', 'backbone', 'backdoor',
                'oldest', 'older', 'golden', 'bold', 'cold', 'fold',
                'copying', 'copyright', 'copycat', 'copying', 'copywriter',
                'bakery', 'baker', 'baking', 'baklava'
            ];
            
            foreach ($legitimateFiles as $legit) {
                if (strpos($fileName, $legit) !== false) {
                    return false;
                }
            }
            
            // Check for debug patterns with more specific matching
            foreach ($debugPatterns as $pattern) {
                if (strpos($fileName, $pattern) !== false) {
                    return true;
                }
            }
            
            // Check for common debug indicators at start or end
            if (preg_match('/^(debug|test|temp|check|backup|old|copy|bak)/', $fileName)) {
                return true;
            }
            
            if (preg_match('/(debug|test|temp|check|backup|old|copy|bak)$/', $fileName)) {
                return true;
            }
            
            // Check for debug patterns with underscores (more specific)
            if (preg_match('/_(debug|test|temp|check|backup|old|copy|bak)_/', $fileName)) {
                return true;
            }
            
            return false;
        }

        function scanDirectoryEnhanced($dir, $prefix = '', $ignorePaths = [], $level = 0) {
            $files = [];
            if (is_dir($dir) && $level < 6) { // Limit depth to prevent infinite recursion
                $items = scandir($dir);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    
                    $fullPath = $dir . '/' . $item;
                    $relativePath = $prefix . $item;
                    
                    // Skip ignored paths
                    if (shouldIgnorePath($relativePath, $ignorePaths)) {
                        $files[$relativePath] = [
                            'type' => 'ignored',
                            'path' => $fullPath,
                            'level' => $level
                        ];
                        continue;
                    }
                    
                    if (is_dir($fullPath)) {
                        $files[$relativePath] = [
                            'type' => 'directory',
                            'path' => $fullPath,
                            'size' => 0,
                            'modified' => filemtime($fullPath),
                            'level' => $level
                        ];
                        $files = array_merge($files, scanDirectoryEnhanced($fullPath, $relativePath . '/', $ignorePaths, $level + 1));
                    } else {
                        $files[$relativePath] = [
                            'type' => 'file',
                            'path' => $fullPath,
                            'size' => filesize($fullPath),
                            'modified' => filemtime($fullPath),
                            'level' => $level
                        ];
                    }
                }
            }
            return $files;
        }

        $allFiles = scanDirectoryEnhanced($rootDir, '', $ignorePaths);
        
        // Check expected files
        echo '<div class="card">';
        echo '<h2>📋 Core Project Structure</h2>';
        echo '<table>';
        echo '<tr><th>File/Directory</th><th>Status</th><th>Description</th></tr>';
        
        foreach ($expectedStructure as $file => $description) {
            $exists = isset($allFiles[$file]);
            $status = $exists ? '<span class="status-good">✅ EXISTS</span>' : '<span class="status-error">❌ MISSING</span>';
            if (!$exists) {
                $issues[] = "Missing: $file - $description";
            }
            echo "<tr><td>$file</td><td>$status</td><td>$description</td></tr>";
        }
        echo '</table>';
        echo '</div>';

        // Complete file structure view
        echo '<div class="card">';
        echo '<h2>📁 Complete File Structure</h2>';
        echo '<p>Showing all project files (Tabler assets minimized for clarity)</p>';
        echo '<div class="file-tree">';
        
        $currentDir = '';
        foreach ($allFiles as $path => $info) {
            $pathParts = explode('/', $path);
            $fileName = array_pop($pathParts);
            $dirPath = implode('/', $pathParts);
            
            // Show directory headers
            if ($dirPath !== $currentDir && $info['type'] !== 'ignored') {
                if ($dirPath !== '') {
                    $level = count($pathParts);
                    $indentClass = 'indent-' . min($level, 4);
                    echo "<div class='folder $indentClass'>📁 $dirPath/</div>";
                }
                $currentDir = $dirPath;
            }
            
            // Show files
            if ($info['type'] === 'file') {
                $level = count($pathParts) + 1;
                $indentClass = 'indent-' . min($level, 4);
                
                // Check if it's a debug file
                $isDebugFile = isDebugFile($fileName, $debugPatterns);
                
                if ($isDebugFile) {
                    $debugFiles[] = [
                        'path' => $path,
                        'full_path' => $info['path'],
                        'size' => $info['size'],
                        'modified' => date('Y-m-d H:i:s', $info['modified'])
                    ];
                }
                
                $fileClass = $isDebugFile ? 'debug-file' : 'file';
                $icon = $isDebugFile ? '🐛' : '📄';
                $size = $info['size'] < 1024 ? $info['size'] . 'B' : round($info['size'] / 1024, 1) . 'KB';
                
                echo "<div class='$fileClass $indentClass'>$icon $fileName <small>($size)</small></div>";
            } elseif ($info['type'] === 'ignored') {
                $level = count($pathParts);
                $indentClass = 'indent-' . min($level, 4);
                if (strpos($path, 'tabler') !== false) {
                    echo "<div class='ignored-file $indentClass'>📦 $fileName <small>(Tabler assets - " . count(glob($info['path'] . '/*')) . " files)</small></div>";
                }
            }
        }
        echo '</div>';
        echo '</div>';

        // Debug files (excluding Tabler)
        echo '<div class="card">';
        echo '<h2>🧹 Debug Files Detection</h2>';
        
        if (!empty($debugFiles)) {
            echo '<div class="cleanup-section">';
            echo '<h3>⚠️ Debug Files Found (Non-Tabler)</h3>';
            echo '<p>These files appear to be debug files that can be safely deleted:</p>';
            echo '<table>';
            echo '<tr><th>File</th><th>Size</th><th>Modified</th><th>Action</th></tr>';
            foreach ($debugFiles as $file) {
                $size = $file['size'] < 1024 ? $file['size'] . ' B' : round($file['size'] / 1024, 1) . ' KB';
                echo "<tr>";
                echo "<td>{$file['path']}</td>";
                echo "<td>$size</td>";
                echo "<td>{$file['modified']}</td>";
                echo "<td><button class='btn btn-danger' onclick='deleteFile(\"{$file['full_path']}\")'>Delete</button></td>";
                echo "</tr>";
            }
            echo '</table>';
            echo '</div>';
        } else {
            echo '<p class="status-good">✅ No debug files detected</p>';
        }
        echo '</div>';

        // Database connection test
        echo '<div class="card">';
        echo '<h2>🗄️ Database Connection Test</h2>';
        
        try {
            if (file_exists($rootDir . '/vendor/autoload.php')) {
                require_once $rootDir . '/vendor/autoload.php';
                
                // Try both new and old database connection methods
                try {
                    $database = new \App\Config\Database();
                    $db = $database->getConnection();
                    echo '<p class="status-good">✅ Database connection successful (new method)</p>';
                } catch (Exception $e) {
                    // Fallback to DatabaseFactory
                    $database = \App\Config\DatabaseFactory::create();
                    $db = $database->getConnection();
                    echo '<p class="status-good">✅ Database connection successful (factory method)</p>';
                }
                
                // Test basic queries
                $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                echo '<h3>Database Tables (' . count($tables) . ' found):</h3>';
                echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin: 10px 0;">';
                foreach ($tables as $table) {
                    echo "<div style='background: #f0f9ff; padding: 8px; border-radius: 4px; border-left: 3px solid #3b82f6;'>📊 $table</div>";
                }
                echo '</div>';
                
            } else {
                echo '<p class="status-error">❌ Autoloader not found</p>';
            }
        } catch (Exception $e) {
            echo '<p class="status-error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        echo '</div>';

        // Environment check
        echo '<div class="card">';
        echo '<h2>🌍 Environment Configuration</h2>';
        
        if (file_exists($rootDir . '/.env')) {
            echo '<p class="status-good">✅ .env file exists</p>';
            
            if (file_exists($rootDir . '/config/Config.php')) {
                require_once $rootDir . '/vendor/autoload.php';
                
                echo '<table>';
                echo '<tr><th>Setting</th><th>Value</th></tr>';
                echo '<tr><td>Environment</td><td>' . (\App\Config\Config::isProduction() ? '<span class="status-warning">Production</span>' : '<span class="status-info">Development</span>') . '</td></tr>';
                echo '<tr><td>Debug Mode</td><td>' . (\App\Config\Config::isDebug() ? '<span class="status-warning">ON</span>' : '<span class="status-good">OFF</span>') . '</td></tr>';
                echo '<tr><td>Site Name</td><td>' . htmlspecialchars(\App\Config\Config::getSiteName()) . '</td></tr>';
                echo '<tr><td>Database</td><td>' . htmlspecialchars(\App\Config\Config::getDbName()) . '</td></tr>';
                echo '<tr><td>Currency</td><td>' . htmlspecialchars(\App\Config\Config::getCurrencySymbol() . ' ' . \App\Config\Config::getDefaultCurrency()) . '</td></tr>';
                echo '</table>';
            } else {
                echo '<p class="status-error">❌ Config class not found</p>';
            }
        } else {
            echo '<p class="status-error">❌ .env file missing</p>';
            $issues[] = 'Missing .env file - Required for configuration';
        }
        echo '</div>';

        // Autoloader check
        echo '<div class="card">';
        echo '<h2>📦 Autoloader & Dependencies</h2>';
        
        if (file_exists($rootDir . '/vendor/autoload.php')) {
            echo '<p class="status-good">✅ Composer autoloader exists</p>';
            
            // Check if composer.json exists
            if (file_exists($rootDir . '/composer.json')) {
                echo '<p class="status-good">✅ composer.json exists</p>';
                
                // Check if vendor directory has content
                $vendorFiles = glob($rootDir . '/vendor/*', GLOB_ONLYDIR);
                if (count($vendorFiles) > 0) {
                    echo '<p class="status-good">✅ Vendor packages installed (' . count($vendorFiles) . ' packages)</p>';
                } else {
                    echo '<p class="status-warning">⚠️ Vendor directory empty - run composer install</p>';
                }
            } else {
                echo '<p class="status-error">❌ composer.json missing</p>';
            }
        } else {
            echo '<p class="status-error">❌ Composer autoloader missing</p>';
            $issues[] = 'Missing vendor/autoload.php - Run composer install';
        }
        echo '</div>';

        // File count summary
        $fileCount = count(array_filter($allFiles, function($item) { return $item['type'] === 'file'; }));
        $dirCount = count(array_filter($allFiles, function($item) { return $item['type'] === 'directory'; }));
        $ignoredCount = count(array_filter($allFiles, function($item) { return $item['type'] === 'ignored'; }));
        
        echo '<div class="card">';
        echo '<h2>📊 Project Statistics</h2>';
        echo '<table>';
        echo '<tr><td>Total Files</td><td>' . number_format($fileCount) . '</td></tr>';
        echo '<tr><td>Total Directories</td><td>' . number_format($dirCount) . '</td></tr>';
        echo '<tr><td>Ignored Paths (Tabler, etc.)</td><td>' . number_format($ignoredCount) . '</td></tr>';
        echo '<tr><td>Debug Files Found</td><td>' . count($debugFiles) . '</td></tr>';
        echo '<tr><td>Issues Found</td><td>' . count($issues) . '</td></tr>';
        echo '</table>';
        echo '</div>';

        // Navigation
        echo '<div class="card">';
        echo '<h2>🚀 Quick Actions</h2>';
        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        echo '<a href="admin/login.php" class="btn">🔐 Admin Login</a>';
        echo '<a href="admin/dashboard.php" class="btn">📊 Admin Dashboard</a>';
        echo '<a href="login.php" class="btn">👤 User Login</a>';
        echo '<a href="users/dashboard.php" class="btn">👤 User Dashboard</a>';
        echo '<a href="../" class="btn">🏠 Back to Site</a>';
        if (count($debugFiles) > 0) {
            echo '<button class="btn btn-danger" onclick="deleteAllDebugFiles()">🗑️ Delete All Debug Files</button>';
        }
        echo '</div>';
        echo '</div>';

        // Issues summary
        if (!empty($issues)) {
            echo '<div class="card">';
            echo '<h2>⚠️ Issues Found</h2>';
            echo '<ul>';
            foreach ($issues as $issue) {
                echo '<li class="status-error">' . htmlspecialchars($issue) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        // System info
        echo '<div class="card">';
        echo '<h2>💻 System Information</h2>';
        echo '<table>';
        echo '<tr><td>PHP Version</td><td>' . PHP_VERSION . '</td></tr>';
        echo '<tr><td>Server Software</td><td>' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . '</td></tr>';
        echo '<tr><td>Document Root</td><td>' . $_SERVER['DOCUMENT_ROOT'] . '</td></tr>';
        echo '<tr><td>Current Directory</td><td>' . __DIR__ . '</td></tr>';
        echo '<tr><td>Memory Limit</td><td>' . ini_get('memory_limit') . '</td></tr>';
        echo '<tr><td>Max Upload Size</td><td>' . ini_get('upload_max_filesize') . '</td></tr>';
        echo '<tr><td>Max POST Size</td><td>' . ini_get('post_max_size') . '</td></tr>';
        echo '<tr><td>Max Execution Time</td><td>' . ini_get('max_execution_time') . 's</td></tr>';
        echo '</table>';
        echo '</div>';
        ?>
    </div>

    <script>
        function deleteFile(filePath) {
            if (confirm('Are you sure you want to delete this file?\n\n' + filePath)) {
                fetch('<?= $_SERVER['PHP_SELF'] ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete&file=' + encodeURIComponent(filePath)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('File deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error deleting file');
                });
            }
        }

        function deleteAllDebugFiles() {
            if (confirm('Are you sure you want to delete ALL debug files?\n\nThis action cannot be undone.')) {
                fetch('<?= $_SERVER['PHP_SELF'] ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_all_debug'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Debug files deleted successfully! Deleted: ' + data.count + ' files');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error deleting files');
                });
            }
        }
    </script>

    <?php
    // Handle file deletion via AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action === 'delete' && isset($_POST['file'])) {
                $filePath = $_POST['file'];
                
                if (empty($filePath) || !file_exists($filePath)) {
                    echo json_encode(['success' => false, 'message' => 'File not found']);
                    exit;
                }
                
                // Security check - only allow deletion of files in project directory
                $realPath = realpath($filePath);
                $projectPath = realpath($rootDir);
                
                if (strpos($realPath, $projectPath) !== 0) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
                
                // Don't allow deletion of core files
                $relativePath = str_replace($projectPath . '/', '', $realPath);
                if (isset($expectedStructure[$relativePath])) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete core system file']);
                    exit;
                }
                
                try {
                    if (unlink($filePath)) {
                        echo json_encode(['success' => true, 'message' => 'File deleted']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete file']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                
            } elseif ($action === 'delete_all_debug') {
                $deletedCount = 0;
                $errors = [];
                
                foreach ($debugFiles as $file) {
                    $filePath = $file['full_path'];
                    
                    if (file_exists($filePath)) {
                        try {
                            if (unlink($filePath)) {
                                $deletedCount++;
                            } else {
                                $errors[] = $file['path'];
                            }
                        } catch (Exception $e) {
                            $errors[] = $file['path'] . ' (' . $e->getMessage() . ')';
                        }
                    }
                }
                
                if (empty($errors)) {
                    echo json_encode(['success' => true, 'count' => $deletedCount]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Some files could not be deleted: ' . implode(', ', $errors)]);
                }
            }
        }
        exit;
    }
    ?>
</body>
</html>