<?php
declare(strict_types=1);
namespace App\Config;

// config/Config.php - Working version for admin files

class Config {
    public function __construct() {
        EnvLoader::load();
    }
    
    public static function init() {
        EnvLoader::load();
        self::initErrorReporting();
    }
    
    // Database Configuration
    public static function getDbHost() {
        return EnvLoader::get('DB_HOST', 'localhost');
    }
    
    public static function getDbName() {
        return EnvLoader::get('DB_NAME', 'cornerfield_db');
    }
    
    public static function getDbUser() {
        return EnvLoader::get('DB_USER', 'cornerfield');
    }
    
    public static function getDbPassword() {
        return EnvLoader::get('DB_PASS', '');
    }

    public static function getDatabaseConfig() {
        return [
            'host' => self::getDbHost(),
            'dbname' => self::getDbName(),
            'username' => self::getDbUser(),
            'password' => self::getDbPassword(),
            'charset' => 'utf8mb4'
        ];
    }
    
    // Application Configuration
    public static function getSiteName() {
        return EnvLoader::get('APP_NAME', 'Cornerfield Investment Platform');
    }
    
    public static function getSiteUrl() {
        return EnvLoader::get('APP_URL', 'http://localhost/cornerfield');
    }

    /**
     * Base path for asset and link URLs (e.g. '' or '/cornerfield-main').
     * Use when the app runs in a subdirectory so CSS/JS/images load correctly.
     */
    public static function getBasePath() {
        $url = self::getSiteUrl();
        $path = parse_url($url, PHP_URL_PATH);
        return $path ? rtrim($path, '/') : '';
    }
    
    public static function getAdminEmail() {
        return EnvLoader::get('APP_ADMIN_EMAIL', 'admin@cornerfield.local');
    }
    
    public static function isDebug() {
        return EnvLoader::getBool('APP_DEBUG', false);
    }

    public static function getAppEnv() {
        return EnvLoader::get('APP_ENV', 'local');
    }

    public static function isLocal() {
        return self::getAppEnv() === 'local';
    }
    
    public static function isProduction() {
        return self::getAppEnv() === 'production';
    }

    public static function isDevelopment() {
        return self::getAppEnv() === 'development';
    }

    // Initialize error reporting based on environment
    public static function initErrorReporting() {
        if (self::isDebug() && !self::isProduction()) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
        }
    }
    
    // Security Configuration
    public static function getJwtSecret() {
        return EnvLoader::get('JWT_SECRET', 'default-jwt-secret-change-me');
    }
    
    public static function getEncryptionKey() {
        return EnvLoader::get('ENCRYPTION_KEY', 'default-encryption-key-change-me');
    }
    
    public static function getSessionLifetime() {
        return EnvLoader::getInt('SESSION_LIFETIME', 7200);
    }
    
    public static function getMaxLoginAttempts() {
        return EnvLoader::getInt('MAX_LOGIN_ATTEMPTS', 5);
    }
    
    public static function getLoginLockoutTime() {
        return EnvLoader::getInt('LOGIN_LOCKOUT_TIME', 900);
    }
    
    // Payment Gateway Configuration
    public static function getCryptomusApiKey() {
        return EnvLoader::get('CRYPTOMUS_API_KEY');
    }
    
    public static function getCryptomusMerchantId() {
        return EnvLoader::get('CRYPTOMUS_MERCHANT_ID');
    }
    
    // File Upload Configuration
    public static function getUploadPath() {
        return '../storage/uploads/';
    }
    
    public static function getMaxFileSize() {
        return EnvLoader::getInt('UPLOAD_MAX_SIZE', 5242880);
    }
    
    public static function getAllowedExtensions() {
        return EnvLoader::getArray('UPLOAD_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
    }
    
    // Investment & Financial Configuration
    public static function getSignupBonus() {
        return (float) EnvLoader::get('SIGNUP_BONUS', '25');
    }
    
    public static function getMinWithdrawal() {
        return (float) EnvLoader::get('MIN_WITHDRAWAL', '10');
    }
    
    public static function getWithdrawalFee() {
        return (float) EnvLoader::get('WITHDRAWAL_FEE', '2');
    }

    public static function getMinInvestment() {
        return (float) EnvLoader::get('MIN_INVESTMENT', '10');
    }

    public static function getMaxInvestment() {
        return (float) EnvLoader::get('MAX_INVESTMENT', '1000000');
    }

    public static function getDefaultCurrency() {
        return EnvLoader::get('DEFAULT_CURRENCY', 'USD');
    }

    public static function getCurrencySymbol() {
        return EnvLoader::get('CURRENCY_SYMBOL', '$');
    }
    
    // Referral System Configuration
    public static function getReferralLevels() {
        return EnvLoader::getInt('REFERRAL_LEVELS', 3);
    }
    
    public static function getReferralRates() {
        return EnvLoader::getArray('REFERRAL_RATES', [10, 5, 2]);
    }

    // URL Helpers
    public static function url($path = '') {
        return rtrim(self::getSiteUrl(), '/') . '/' . ltrim($path, '/');
    }
    
    public static function adminUrl($path = '') {
        return rtrim(self::getSiteUrl(), '/') . '/public/' . ltrim($path, '/');
    }

    // Mail Configuration
    public static function getMailHost() {
        return EnvLoader::get('MAIL_HOST', 'localhost');
    }

    public static function getMailPort() {
        return EnvLoader::getInt('MAIL_PORT', 587);
    }

    public static function getMailUsername() {
        return EnvLoader::get('MAIL_USERNAME', '');
    }

    public static function getMailPassword() {
        return EnvLoader::get('MAIL_PASSWORD', '');
    }

    public static function getMailFromAddress() {
        return EnvLoader::get('MAIL_FROM_ADDRESS', 'noreply@cornerfield.local');
    }

    public static function getMailFromName() {
        return EnvLoader::get('MAIL_FROM_NAME', 'Cornerfield');
    }

    // Generic getter for any config value
    public static function get($key, $default = null) {
        return EnvLoader::get($key, $default);
    }

    // Additional methods for your existing .env values
    public static function getDailyProfitTime() {
        return EnvLoader::get('DAILY_PROFIT_TIME', '00:00:00');
    }

    public static function getLogLevel() {
        return EnvLoader::get('LOG_LEVEL', 'error');
    }

    public static function isCacheEnabled() {
        return EnvLoader::getBool('CACHE_ENABLED', false);
    }

    public static function getNowpaymentsApiKey() {
        return EnvLoader::get('NOWPAYMENTS_API_KEY');
    }
}

// Initialize configuration on load
Config::init();
?>