<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Config/Database.php
 * Purpose: Single database connection class using PDO singleton pattern
 * Security Level: SYSTEM_ONLY
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Config;

use PDO;
use PDOException;

class Database 
{
    private static ?PDO $instance = null;
    
    /**
     * Get the single database connection instance
     */
    public static function getInstance(): PDO 
    {
        if (self::$instance === null) {
            try {
                $host = $_ENV['DB_HOST'] ?? 'localhost';
                $dbname = $_ENV['DB_NAME'] ?? 'cornerfield_db';
                $username = $_ENV['DB_USER'] ?? 'root';
                $password = $_ENV['DB_PASS'] ?? '';
                $port = $_ENV['DB_PORT'] ?? '3306';
                
                $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4;port={$port}";
                
                self::$instance = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'"
                ]);
                
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new PDOException("Database connection unavailable");
            }
        }
        
        return self::$instance;
    }
    
    /**
     * Close the connection (for testing)
     */
    public static function closeConnection(): void 
    {
        self::$instance = null;
    }
}