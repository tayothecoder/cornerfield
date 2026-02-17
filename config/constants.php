<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: config/constants.php
 * Purpose: Application constants
 * Security Level: PUBLIC
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

// Application Information
define('APP_NAME', 'Cornerfield Investment Platform');
define('APP_VERSION', '1.0.0');
define('APP_AUTHOR', 'Cornerfield Development Team');

// Security Constants
define('SESSION_TIMEOUT_MINUTES', 30);
define('CSRF_TOKEN_LIFETIME_SECONDS', 3600);
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// File Upload Constants
define('MAX_UPLOAD_SIZE_BYTES', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx']);
define('UPLOAD_PATH_DOCUMENTS', 'uploads/documents/');
define('UPLOAD_PATH_PROFILE_IMAGES', 'uploads/profile-images/');
define('UPLOAD_PATH_TEMP', 'uploads/temp/');

// Financial Constants
define('MIN_INVESTMENT_AMOUNT', 50.0);
define('MAX_INVESTMENT_AMOUNT', 999999.99);
define('MIN_WITHDRAWAL_AMOUNT', 10.0);
define('MAX_WITHDRAWAL_AMOUNT', 50000.0);
define('DEFAULT_CURRENCY', 'USD');
define('CURRENCY_SYMBOL', '$');
define('DECIMAL_PLACES', 8); // For cryptocurrency precision

// Transaction Types
define('TRANSACTION_TYPES', [
    'deposit',
    'withdrawal',
    'investment',
    'profit',
    'bonus',
    'referral',
    'principal_return'
]);

// Transaction Status
define('TRANSACTION_STATUSES', [
    'pending',
    'processing',
    'completed',
    'failed',
    'cancelled'
]);

// Investment Status
define('INVESTMENT_STATUSES', [
    'active',
    'completed',
    'cancelled'
]);

// KYC Status
define('KYC_STATUSES', [
    'pending',
    'approved',
    'rejected'
]);

// User Roles
define('USER_ROLES', [
    'user',
    'admin',
    'super_admin'
]);

// Rate Limiting Constants
define('RATE_LIMIT_LOGIN', 5); // Max login attempts per 15 minutes
define('RATE_LIMIT_INVESTMENT', 3); // Max investment attempts per hour
define('RATE_LIMIT_WITHDRAWAL', 3); // Max withdrawal attempts per hour
define('RATE_LIMIT_API', 60); // Max API calls per minute

// Email Constants
define('EMAIL_VERIFICATION_TOKEN_LIFETIME', 24 * 3600); // 24 hours
define('PASSWORD_RESET_TOKEN_LIFETIME', 3600); // 1 hour
define('EMAIL_FROM_NAME', 'Cornerfield Support');

// Pagination Constants
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Cache Constants
define('CACHE_LIFETIME_SHORT', 300); // 5 minutes
define('CACHE_LIFETIME_MEDIUM', 1800); // 30 minutes
define('CACHE_LIFETIME_LONG', 3600); // 1 hour

// Log Constants
define('LOG_MAX_SIZE_BYTES', 10 * 1024 * 1024); // 10MB
define('LOG_RETENTION_DAYS', 90);

// Cryptocurrency Networks
define('CRYPTO_NETWORKS', [
    'BTC' => ['BTC'],
    'ETH' => ['ERC20'],
    'USDT' => ['ERC20', 'TRC20', 'BEP20'],
    'LTC' => ['LTC'],
    'XRP' => ['XRP']
]);

// Supported Currencies
define('SUPPORTED_CURRENCIES', [
    'USD',
    'BTC',
    'ETH',
    'USDT',
    'LTC',
    'XRP'
]);

// Payment Method Types
define('PAYMENT_METHOD_TYPES', [
    'crypto',
    'bank',
    'manual',
    'balance',
    'system',
    'auto'
]);

// Admin Permission Levels
define('ADMIN_PERMISSIONS', [
    'view_users',
    'edit_users',
    'delete_users',
    'view_transactions',
    'process_transactions',
    'view_investments',
    'manage_schemas',
    'system_settings',
    'security_logs',
    'impersonate_users'
]);

// Platform Fees (in percentages)
define('DEFAULT_WITHDRAWAL_FEE', 5.0);
define('DEFAULT_DEPOSIT_FEE', 2.5);
define('DEFAULT_PLATFORM_FEE', 2.0);
define('DEFAULT_REFERRAL_COMMISSION', 5.0);

// Investment Limits per User
define('MAX_ACTIVE_INVESTMENTS_PER_USER', 10);
define('MAX_DAILY_INVESTMENT_AMOUNT', 100000.0);

// Security Constants
define('MAX_FAILED_LOGIN_ATTEMPTS', 5);
define('ACCOUNT_LOCKOUT_DURATION', 900); // 15 minutes
define('SESSION_REGENERATION_INTERVAL', 300); // 5 minutes

// Database Constants
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// API Constants
define('API_VERSION', 'v1');
define('API_RATE_LIMIT_PER_MINUTE', 60);
define('API_MAX_RESPONSE_SIZE', 1000000); // 1MB

// Validation Constants
define('USERNAME_MIN_LENGTH', 3);
define('USERNAME_MAX_LENGTH', 20);
define('EMAIL_MAX_LENGTH', 255);
define('NAME_MAX_LENGTH', 100);
define('PHONE_MAX_LENGTH', 20);

// Time Constants
define('SECONDS_PER_MINUTE', 60);
define('SECONDS_PER_HOUR', 3600);
define('SECONDS_PER_DAY', 86400);

// Environment Constants
define('PRODUCTION_ENV', 'production');
define('DEVELOPMENT_ENV', 'development');
define('TESTING_ENV', 'testing');

// Error Constants
define('ERROR_GENERIC', 'An unexpected error occurred. Please try again.');
define('ERROR_INVALID_INPUT', 'Invalid input provided.');
define('ERROR_UNAUTHORIZED', 'You are not authorized to perform this action.');
define('ERROR_FORBIDDEN', 'Access denied.');
define('ERROR_NOT_FOUND', 'The requested resource was not found.');
define('ERROR_RATE_LIMITED', 'Too many requests. Please slow down.');

// Success Messages
define('SUCCESS_GENERIC', 'Operation completed successfully.');
define('SUCCESS_CREATED', 'Record created successfully.');
define('SUCCESS_UPDATED', 'Record updated successfully.');
define('SUCCESS_DELETED', 'Record deleted successfully.');

// File Paths
define('PATH_ROOT', __DIR__ . '/../');
define('PATH_SRC', PATH_ROOT . 'src/');
define('PATH_PUBLIC', PATH_ROOT . 'public/');
define('PATH_UPLOADS', PATH_ROOT . 'uploads/');
define('PATH_LOGS', PATH_ROOT . 'logs/');
define('PATH_CACHE', PATH_ROOT . 'cache/');
define('PATH_TEMPLATES', PATH_ROOT . 'templates/');

// URL Paths (for redirects and links)
define('URL_LOGIN', '/login.php');
define('URL_DASHBOARD', '/users/dashboard.php');
define('URL_ADMIN_LOGIN', '/admin/login.php');
define('URL_ADMIN_DASHBOARD', '/admin/dashboard.php');

// Default Values
define('DEFAULT_SIGNUP_BONUS', 0.0);
define('DEFAULT_PAGINATION_LIMIT', 20);
define('DEFAULT_LANGUAGE', 'en');
define('DEFAULT_TIMEZONE', 'UTC');

// Feature Flags (can be overridden by environment variables)
define('FEATURE_EMAIL_VERIFICATION', true);
define('FEATURE_TWO_FACTOR_AUTH', true);
define('FEATURE_KYC_VERIFICATION', true);
define('FEATURE_REFERRAL_SYSTEM', true);
define('FEATURE_PROFIT_DISTRIBUTION', true);