<?php
/**
 * Script to create new database tables for enhanced admin functionality
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Database;

try {
    // Create database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Connected to database successfully.\n";
    
    // Read and execute SQL file
    $sqlFile = __DIR__ . '/create_new_tables.sql';
    $sql = file_get_contents($sqlFile);
    
    if (!$sql) {
        throw new Exception("Could not read SQL file: $sqlFile");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip comments and empty lines
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            $errorCount++;
            echo "✗ Error: " . $e->getMessage() . "\n";
            echo "  Statement: " . substr($statement, 0, 100) . "...\n";
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Successful statements: $successCount\n";
    echo "Failed statements: $errorCount\n";
    
    if ($errorCount === 0) {
        echo "\n✅ All tables created successfully!\n";
    } else {
        echo "\n⚠️  Some statements failed. Check the errors above.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
