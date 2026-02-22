<?php
declare(strict_types=1);
namespace App\Config;

/**
 * DatabaseFactory - Provides backward compatibility for existing code
 * that uses DatabaseFactory::create() method
 */
class DatabaseFactory
{
    /**
     * Create a new Database instance with default configuration
     * 
     * @return Database
     * @throws \Exception
     */
    public static function create(): Database
    {
        try {
            // Get database configuration from Config class
            $config = Config::getDatabaseConfig();
            
            // Create and return new Database instance
            return new Database($config);
            
        } catch (\Exception $e) {
            // Log error and re-throw
            error_log("DatabaseFactory::create() failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create a new Database instance with custom configuration
     * 
     * @param array $config Custom database configuration
     * @return Database
     * @throws \Exception
     */
    public static function createWithConfig(array $config): Database
    {
        try {
            // Merge with default config
            $defaultConfig = Config::getDatabaseConfig();
            $mergedConfig = array_merge($defaultConfig, $config);
            
            // Create and return new Database instance
            return new Database($mergedConfig);
            
        } catch (\Exception $e) {
            // Log error and re-throw
            error_log("DatabaseFactory::createWithConfig() failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create a new Database instance for testing purposes
     * 
     * @param string $dbName Test database name
     * @return Database
     * @throws \Exception
     */
    public static function createForTesting(string $dbName = 'test_cornerfield_db'): Database
    {
        try {
            // Get default config and override database name
            $config = Config::getDatabaseConfig();
            $config['dbname'] = $dbName;
            
            // Create and return new Database instance
            return new Database($config);
            
        } catch (\Exception $e) {
            // Log error and re-throw
            error_log("DatabaseFactory::createForTesting() failed: " . $e->getMessage());
            throw $e;
        }
    }
}
