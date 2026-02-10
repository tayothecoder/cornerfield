# THE ENFORCER - Cornerfield PHP Coding Standards
**Cornerfield Cryptocurrency Investment Platform - Enterprise Security Standards**

Version: 1.0  
Date: February 10, 2026  
Status: **MANDATORY** - Every line of code MUST follow these standards  

---

## CRITICAL AUDIT CONTEXT

**This document addresses 131 critical issues found in audit:**
- 27 CRITICAL security vulnerabilities
- 34 HIGH priority issues  
- 52 MEDIUM priority issues
- 18 LOW priority maintenance issues

**Current completion status: 45%** (not the claimed 95%)

---

# 1. DATABASE SCHEMA REFERENCE

## 1.1 ALL 22 TABLES - EXACT STRUCTURE

### Core User Tables
#### `users` (CANONICAL USER TABLE)
```sql
id INT(11) PRIMARY KEY AUTO_INCREMENT
email VARCHAR(255) UNIQUE NOT NULL
username VARCHAR(100) UNIQUE NOT NULL  
password_hash VARCHAR(255) NOT NULL
first_name VARCHAR(100)
last_name VARCHAR(100)
phone VARCHAR(20)
country VARCHAR(100)
balance DECIMAL(15,8) DEFAULT 0.00000000
locked_balance DECIMAL(15,8) DEFAULT 0.00000000
bonus_balance DECIMAL(15,8) DEFAULT 0.00000000
total_invested DECIMAL(15,8) DEFAULT 0.00000000
total_withdrawn DECIMAL(15,8) DEFAULT 0.00000000
total_earned DECIMAL(15,8) DEFAULT 0.00000000
referral_code VARCHAR(20) UNIQUE
referred_by INT(11) FK -> users.id
kyc_status ENUM('pending','approved','rejected') DEFAULT 'pending'
kyc_document_path VARCHAR(255)
is_active TINYINT(1) DEFAULT 1
is_admin TINYINT(1) DEFAULT 0
email_verified TINYINT(1) DEFAULT 0
email_verification_token VARCHAR(255)
password_reset_token VARCHAR(255)
password_reset_expires TIMESTAMP
two_factor_secret VARCHAR(32)
two_factor_enabled TINYINT(1) DEFAULT 0
login_attempts INT(11) DEFAULT 0
last_login_attempt TIMESTAMP
last_login TIMESTAMP
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

#### `admins` (CANONICAL ADMIN TABLE - NOT admin_users)
```sql
id INT(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT
username VARCHAR(100) UNIQUE NOT NULL
email VARCHAR(255) UNIQUE NOT NULL
password_hash VARCHAR(255) NOT NULL
full_name VARCHAR(255)
role ENUM('super_admin','admin','moderator') DEFAULT 'admin'
status TINYINT(1) DEFAULT 1
last_login TIMESTAMP
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

**CRITICAL ISSUE: `admin_users` table is DUPLICATE and EMPTY - USE `admins` ONLY**

### Session Management Tables
#### `admin_sessions`
```sql
id VARCHAR(128) PRIMARY KEY
admin_id INT(10) UNSIGNED NOT NULL FK -> admins.id
ip_address VARCHAR(45)
user_agent TEXT
last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

#### `user_sessions`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
user_id INT(11) NOT NULL FK -> users.id
session_token VARCHAR(255) UNIQUE NOT NULL
ip_address VARCHAR(45) NOT NULL
user_agent TEXT
device_fingerprint VARCHAR(255)
location JSON
is_active TINYINT(1) DEFAULT 1
expires_at TIMESTAMP NOT NULL
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

### Financial Tables
#### `transactions` (CENTRAL TRANSACTION LOG)
```sql
id INT(11) PRIMARY KEY AUTO_INCREMENT
user_id INT(11) NOT NULL FK -> users.id
type ENUM('deposit','withdrawal','investment','profit','bonus','referral','principal_return') NOT NULL
amount DECIMAL(15,8) NOT NULL
fee DECIMAL(15,8) DEFAULT 0.00000000
net_amount DECIMAL(15,8) NOT NULL
status ENUM('pending','processing','completed','failed','cancelled') DEFAULT 'pending'
payment_method ENUM('crypto','bank','manual','balance','system','auto')
payment_gateway VARCHAR(50)
gateway_transaction_id VARCHAR(255)
wallet_address VARCHAR(255)
currency VARCHAR(10) DEFAULT 'USD'
description TEXT
reference_id BIGINT(20)
admin_note TEXT
processed_by INT(11) FK -> users.id
admin_processed_by INT(10) UNSIGNED FK -> admins.id
processed_by_type ENUM('user','admin')
processed_at TIMESTAMP
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

#### `deposits`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
transaction_id INT(11) UNIQUE NOT NULL FK -> transactions.id
user_id INT(11) NOT NULL FK -> users.id
deposit_method_id BIGINT(20) UNSIGNED NOT NULL FK -> deposit_methods.id
requested_amount DECIMAL(15,8) NOT NULL
fee_amount DECIMAL(15,8) DEFAULT 0.00000000
currency VARCHAR(10) DEFAULT 'USD'
crypto_currency VARCHAR(10) COMMENT 'BTC, ETH, USDT, etc.'
network VARCHAR(50) COMMENT 'TRC20, ERC20, BTC, etc.'
deposit_address VARCHAR(255) COMMENT 'Generated or static wallet address'
transaction_hash VARCHAR(255) COMMENT 'Blockchain TX hash'
gateway_transaction_id VARCHAR(255) COMMENT 'Payment gateway reference'
gateway_response LONGTEXT COMMENT 'Full gateway response JSON'
proof_of_payment VARCHAR(255) COMMENT 'Screenshot upload path for manual deposits'
admin_notes TEXT COMMENT 'Admin verification notes'
status ENUM('pending','processing','completed','failed','cancelled','expired') DEFAULT 'pending'
verification_status ENUM('pending','verified','rejected') DEFAULT 'pending' COMMENT 'For manual deposits'
admin_processed_by INT(10) UNSIGNED FK -> admins.id
processed_at TIMESTAMP
expires_at TIMESTAMP COMMENT 'For crypto payments with time limits'
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

#### `withdrawals`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
transaction_id INT(11) UNIQUE NOT NULL FK -> transactions.id
user_id INT(11) NOT NULL FK -> users.id
requested_amount DECIMAL(15,8) NOT NULL
fee_amount DECIMAL(15,8) DEFAULT 0.00000000
wallet_address VARCHAR(255) NOT NULL
currency VARCHAR(10) DEFAULT 'USDT'
network VARCHAR(50) DEFAULT 'TRC20'
status ENUM('pending','processing','completed','failed','cancelled') DEFAULT 'pending'
admin_processed_by INT(10) UNSIGNED FK -> admins.id
processed_at TIMESTAMP
rejection_reason TEXT
withdrawal_hash VARCHAR(255) COMMENT 'Blockchain transaction hash'
network_fee DECIMAL(15,8) DEFAULT 0.00000000 COMMENT 'Actual network fee paid'
processing_notes TEXT
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

#### `investments`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
user_id INT(11) NOT NULL FK -> users.id
schema_id BIGINT(20) UNSIGNED NOT NULL FK -> investment_schemas.id
invest_amount DOUBLE NOT NULL
total_profit_amount DOUBLE DEFAULT 0
last_profit_time DATETIME
next_profit_time DATETIME
number_of_period INT(11) DEFAULT 30
status VARCHAR(255) DEFAULT 'active'
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

#### `investment_schemas`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
name VARCHAR(255) NOT NULL
min_amount DOUBLE DEFAULT 0
max_amount DOUBLE DEFAULT 0
daily_rate DECIMAL(5,2) NOT NULL
duration_days INT(11) NOT NULL
total_return DECIMAL(5,2) NOT NULL
featured TINYINT(1) DEFAULT 0
status TINYINT(1) DEFAULT 1
description TEXT
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

#### `profits`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
transaction_id INT(11) UNIQUE NOT NULL FK -> transactions.id
user_id INT(11) NOT NULL FK -> users.id
investment_id BIGINT(20) UNSIGNED NOT NULL FK -> investments.id
schema_id BIGINT(20) UNSIGNED NOT NULL FK -> investment_schemas.id
profit_amount DECIMAL(15,8) NOT NULL
profit_rate DECIMAL(5,2) NOT NULL COMMENT 'Daily rate used for calculation'
investment_amount DECIMAL(15,8) NOT NULL COMMENT 'Base investment amount'
profit_day INT(11) NOT NULL COMMENT 'Day number in investment cycle'
profit_type ENUM('daily','bonus','completion','manual') DEFAULT 'daily'
calculation_date DATE NOT NULL
distribution_method ENUM('automatic','manual') DEFAULT 'automatic'
status ENUM('pending','distributed','failed','cancelled') DEFAULT 'distributed'
admin_processed_by INT(10) UNSIGNED FK -> admins.id
processed_at TIMESTAMP
processing_notes TEXT
next_profit_date DATE
is_final_profit TINYINT(1) DEFAULT 0 COMMENT 'Last profit of investment cycle'
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### Payment System Tables
#### `deposit_methods`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
gateway_id INT(10) UNSIGNED FK -> payment_gateways.id
logo VARCHAR(255)
name VARCHAR(255) NOT NULL
type ENUM('auto','manual') DEFAULT 'manual'
gateway_code VARCHAR(255) NOT NULL
charge DOUBLE DEFAULT 0
charge_type ENUM('percentage','fixed') DEFAULT 'percentage'
minimum_deposit DOUBLE DEFAULT 0
maximum_deposit DOUBLE DEFAULT 999999
rate DOUBLE DEFAULT 1
currency VARCHAR(255) DEFAULT 'USD'
currency_symbol VARCHAR(255) DEFAULT '$'
field_options LONGTEXT
payment_details LONGTEXT
status TINYINT(1) DEFAULT 1
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

#### `withdrawal_methods`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
gateway_id INT(10) UNSIGNED FK -> payment_gateways.id
name VARCHAR(255) NOT NULL
type ENUM('auto','manual') DEFAULT 'manual'
currency VARCHAR(255) DEFAULT 'USD'
currency_symbol VARCHAR(255) DEFAULT '$'
charge DOUBLE DEFAULT 0
charge_type ENUM('percentage','fixed') DEFAULT 'percentage'
minimum_withdrawal DOUBLE DEFAULT 10
maximum_withdrawal DOUBLE DEFAULT 999999
processing_time VARCHAR(100) DEFAULT '1-24 hours'
instructions TEXT
status TINYINT(1) DEFAULT 1
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

#### `payment_gateways`
```sql
id INT(11) PRIMARY KEY AUTO_INCREMENT
name VARCHAR(100) NOT NULL
code VARCHAR(50) UNIQUE NOT NULL
type ENUM('crypto','bank','wallet') DEFAULT 'crypto'
api_endpoint VARCHAR(255)
api_key_encrypted TEXT
webhook_secret_encrypted TEXT
supported_currencies JSON
is_active TINYINT(1) DEFAULT 0
settings JSON
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### System Tables
#### `admin_settings`
```sql
id INT(11) PRIMARY KEY AUTO_INCREMENT
setting_key VARCHAR(100) UNIQUE NOT NULL
setting_value TEXT NOT NULL
setting_type ENUM('string','integer','boolean','json') DEFAULT 'string'
description TEXT
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

**CRITICAL SECURITY ISSUE FOUND:**
- CSRF token stored in admin_settings (WRONG!)  
- SMTP password in plaintext (SECURITY BREACH!)

#### `site_settings`
```sql
id INT(11) PRIMARY KEY AUTO_INCREMENT
setting_key VARCHAR(100) UNIQUE NOT NULL
setting_value TEXT
setting_type ENUM('text','textarea','image','color','number','boolean','json') DEFAULT 'text'
category VARCHAR(50) DEFAULT 'general'
description TEXT
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### Security & Audit Tables
#### `security_logs`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
event_type VARCHAR(100) NOT NULL
ip_address VARCHAR(45) NOT NULL
user_agent TEXT
user_id INT(11) FK -> users.id
data JSON
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

#### `email_logs`
```sql
id INT(11) PRIMARY KEY AUTO_INCREMENT
to_email VARCHAR(255) NOT NULL
subject VARCHAR(500) NOT NULL
status ENUM('sent','failed','pending') DEFAULT 'pending'
error_message TEXT
sent_at DATETIME NOT NULL
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

### User Enhancement Tables
#### `user_2fa`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
user_id INT(11) UNIQUE NOT NULL FK -> users.id
secret_key VARCHAR(255) NOT NULL
backup_codes JSON
is_enabled TINYINT(1) DEFAULT 0
last_used_at TIMESTAMP
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

#### `user_documents`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
user_id INT(11) NOT NULL FK -> users.id
document_type ENUM('passport','license','utility_bill','bank_statement') NOT NULL
file_path VARCHAR(500) NOT NULL
status ENUM('pending','approved','rejected') DEFAULT 'pending'
reviewed_by INT(11) FK -> admins.id
reviewed_at TIMESTAMP
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

#### `user_wallets`
```sql
id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
user_id INT(11) NOT NULL FK -> users.id
currency VARCHAR(20) NOT NULL
network VARCHAR(50) NOT NULL
address VARCHAR(255) NOT NULL
is_verified TINYINT(1) DEFAULT 0
is_default TINYINT(1) DEFAULT 0
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
UNIQUE KEY unique_user_currency_address (user_id, currency, address)
```

#### `referrals`
```sql
id INT(11) PRIMARY KEY AUTO_INCREMENT
referrer_id INT(11) NOT NULL FK -> users.id
referred_id INT(11) NOT NULL FK -> users.id
level INT(11) DEFAULT 1
commission_rate DECIMAL(5,2) NOT NULL
total_earned DECIMAL(15,8) DEFAULT 0.00000000
status ENUM('active','inactive') DEFAULT 'active'
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
UNIQUE KEY unique_referral (referrer_id, referred_id)
```

## 1.2 CRITICAL DATABASE ISSUES IDENTIFIED

### DUPLICATE TABLES RESOLUTION
- **`admins`** = CANONICAL (use this)
- **`admin_users`** = DUPLICATE/EMPTY (DELETE this table)

### ID TYPE INCONSISTENCIES FIXED
```php
// WRONG MIX - Fix these:
admins.id: INT(10) UNSIGNED  
deposits.id: BIGINT(20) UNSIGNED
user_sessions.id: BIGINT(20) UNSIGNED
transactions.id: INT(11)

// STANDARD RULE:
// - All primary keys: BIGINT(20) UNSIGNED AUTO_INCREMENT  
// - All foreign keys: BIGINT(20) UNSIGNED
// - EXCEPTION: Legacy tables can keep INT if changing would break existing data
```

### MISSING FOREIGN KEY CONSTRAINTS
```sql
-- ADD THESE MISSING CONSTRAINTS:
ALTER TABLE user_documents ADD CONSTRAINT user_documents_reviewed_by_fk 
    FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL;

ALTER TABLE deposits ADD CONSTRAINT deposits_admin_processed_by_fk 
    FOREIGN KEY (admin_processed_by) REFERENCES admins(id) ON DELETE SET NULL;
```

---

# 2. PHP CODING STANDARDS

## 2.1 MANDATORY FILE HEADER

**Every PHP file MUST start exactly like this:**

```php
<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: {filename}
 * Purpose: {brief description}
 * Security Level: {PUBLIC|PROTECTED|ADMIN_ONLY|SYSTEM_ONLY}
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\{Namespace};

use PDO;
use PDOException;
use InvalidArgumentException;
use App\Config\Database;
use App\Utils\Security;
use App\Utils\Validator;
// ... other specific imports
```

## 2.2 STRICT TYPES & TYPE HINTS

**MANDATORY - No exceptions:**

```php
// RIGHT - Every function/method MUST have complete type hints
public function createInvestment(
    int $userId, 
    int $schemaId, 
    float $amount, 
    string $currency = 'USD'
): array {
    // implementation
}

// RIGHT - Nullable types explicitly declared
public function getUserById(int $userId): ?array {
    // can return array or null
}

// RIGHT - Array type hints with documentation
/**
 * @param array<string, mixed> $userData
 * @return array<string, string>
 */
public function validateUserData(array $userData): array {
    // implementation
}

// WRONG - No type hints
public function createInvestment($userId, $schemaId, $amount) {
    // REJECTED - This will not pass code review
}
```

## 2.3 ERROR HANDLING PATTERN

**Every database operation MUST follow this pattern:**

```php
// RIGHT - Complete error handling
try {
    $this->db->beginTransaction();
    
    // Validate all inputs first
    if ($amount <= 0) {
        throw new InvalidArgumentException('Investment amount must be positive');
    }
    
    $stmt = $this->db->prepare(
        "INSERT INTO investments (user_id, schema_id, invest_amount, created_at) 
         VALUES (?, ?, ?, NOW())"
    );
    
    if (!$stmt->execute([$userId, $schemaId, $amount])) {
        throw new PDOException('Failed to create investment record');
    }
    
    $investmentId = $this->db->lastInsertId();
    
    // Update user balance
    $this->updateUserBalance($userId, -$amount);
    
    $this->db->commit();
    
    // Log successful operation
    Security::logAudit($userId, 'investment_created', 'investments', $investmentId);
    
    return [
        'success' => true,
        'investment_id' => (int)$investmentId,
        'amount' => $amount
    ];
    
} catch (PDOException $e) {
    $this->db->rollBack();
    
    // Log error (but not sensitive details)
    error_log("Investment creation failed for user {$userId}: " . $e->getMessage());
    
    // Return user-friendly error
    return [
        'success' => false,
        'error' => 'Unable to process investment. Please try again.'
    ];
    
} catch (Exception $e) {
    $this->db->rollBack();
    
    error_log("Unexpected error in investment creation: " . $e->getMessage());
    
    return [
        'success' => false,
        'error' => 'An unexpected error occurred. Please contact support.'
    ];
}

// WRONG - No error handling
public function createInvestment($userId, $schemaId, $amount) {
    $sql = "INSERT INTO investments VALUES ($userId, $schemaId, $amount)";
    return $this->db->query($sql);
    // MULTIPLE VIOLATIONS: No prepared statement, no error handling, no validation
}
```

## 2.4 DATABASE CONNECTION PATTERN

**THE SINGLE CANONICAL WAY to get a database connection:**

```php
// RIGHT - The ONLY way to get a database connection
<?php
declare(strict_types=1);

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
                $username = $_ENV['DB_USERNAME'] ?? 'root';
                $password = $_ENV['DB_PASSWORD'] ?? '';
                
                $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
                
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

// Usage in any class:
class UserModel 
{
    private PDO $db;
    
    public function __construct() 
    {
        $this->db = Database::getInstance(); // THE ONLY WAY
    }
    
    // ... rest of class
}

// WRONG - These patterns are FORBIDDEN:
new PDO($dsn, $user, $pass);  // Direct instantiation forbidden
DatabaseFactory::create();    // No factory pattern allowed
$GLOBALS['db'];              // Global variables forbidden
include 'database.php';      // Include-based connections forbidden
```

## 2.5 MODEL PATTERN

**Every model class MUST follow this exact structure:**

```php
<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use InvalidArgumentException;
use App\Config\Database;
use App\Utils\Security;
use App\Utils\Validator;

class UserModel 
{
    private PDO $db;
    
    public function __construct() 
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get user by ID
     * @param int $userId
     * @return array|null User data or null if not found
     * @throws InvalidArgumentException
     */
    public function getUserById(int $userId): ?array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, email, username, first_name, last_name, balance, 
                        created_at, updated_at, kyc_status, is_active 
                 FROM users 
                 WHERE id = ? AND is_active = 1"
            );
            
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch user {$userId}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new user
     * @param array<string, mixed> $userData
     * @return array<string, mixed> Result with success status
     */
    public function createUser(array $userData): array 
    {
        // Validation first
        $errors = $this->validateUserData($userData);
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors
            ];
        }
        
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare(
                "INSERT INTO users (email, username, password_hash, first_name, 
                                   last_name, referral_code, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            
            $success = $stmt->execute([
                $userData['email'],
                $userData['username'],
                password_hash($userData['password'], PASSWORD_ARGON2ID),
                $userData['first_name'],
                $userData['last_name'],
                $this->generateReferralCode()
            ]);
            
            if (!$success) {
                throw new PDOException('Failed to insert user');
            }
            
            $userId = $this->db->lastInsertId();
            
            // Apply signup bonus if configured
            $this->applySignupBonus((int)$userId);
            
            $this->db->commit();
            
            Security::logAudit((int)$userId, 'user_created', 'users', (int)$userId);
            
            return [
                'success' => true,
                'user_id' => (int)$userId
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            
            if ($e->getCode() === '23000') { // Duplicate entry
                return [
                    'success' => false,
                    'error' => 'Email or username already exists'
                ];
            }
            
            error_log("User creation failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to create account. Please try again.'
            ];
        }
    }
    
    /**
     * Validate user data
     * @param array<string, mixed> $data
     * @return array<string> Validation errors
     */
    private function validateUserData(array $data): array 
    {
        $errors = [];
        
        if (!Validator::isValidEmail($data['email'] ?? '')) {
            $errors[] = 'Valid email address is required';
        }
        
        if (!Validator::isValidUsername($data['username'] ?? '')) {
            $errors[] = 'Username must be 3-20 characters, alphanumeric and underscores only';
        }
        
        if (!Validator::isValidPassword($data['password'] ?? '')) {
            $errors[] = 'Password must be at least 8 characters with mixed case, numbers, and symbols';
        }
        
        if (!Validator::isValidName($data['first_name'] ?? '')) {
            $errors[] = 'First name is required';
        }
        
        if (!Validator::isValidName($data['last_name'] ?? '')) {
            $errors[] = 'Last name is required';
        }
        
        return $errors;
    }
    
    /**
     * Generate unique referral code
     * @return string 8-character referral code
     */
    private function generateReferralCode(): string 
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(4)));
            
            // Check if code already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE referral_code = ?");
            $stmt->execute([$code]);
            
        } while ($stmt->fetch());
        
        return $code;
    }
    
    /**
     * Apply signup bonus to new user
     */
    private function applySignupBonus(int $userId): void 
    {
        $bonusAmount = (float)($_ENV['SIGNUP_BONUS'] ?? 0);
        
        if ($bonusAmount > 0) {
            $stmt = $this->db->prepare(
                "UPDATE users SET bonus_balance = bonus_balance + ? WHERE id = ?"
            );
            $stmt->execute([$bonusAmount, $userId]);
        }
    }
}
```

## 2.6 SERVICE PATTERN

**Business logic MUST be in service classes:**

```php
<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use App\Models\UserModel;
use App\Models\InvestmentModel;
use App\Utils\Security;
use App\Utils\Validator;

class InvestmentService 
{
    private UserModel $userModel;
    private InvestmentModel $investmentModel;
    
    public function __construct() 
    {
        $this->userModel = new UserModel();
        $this->investmentModel = new InvestmentModel();
    }
    
    /**
     * Process new investment with all business rules
     * @param int $userId
     * @param int $schemaId  
     * @param float $amount
     * @return array<string, mixed>
     */
    public function processInvestment(int $userId, int $schemaId, float $amount): array 
    {
        // Validate user
        $user = $this->userModel->getUserById($userId);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'Invalid user'
            ];
        }
        
        if (!$user['is_active']) {
            return [
                'success' => false,
                'error' => 'Account is not active'
            ];
        }
        
        // Validate investment schema
        $schema = $this->investmentModel->getSchemaById($schemaId);
        if (!$schema || !$schema['status']) {
            return [
                'success' => false,
                'error' => 'Investment plan not available'
            ];
        }
        
        // Validate amount
        if ($amount < $schema['min_amount']) {
            return [
                'success' => false,
                'error' => "Minimum investment is {$schema['min_amount']} USD"
            ];
        }
        
        if ($amount > $schema['max_amount']) {
            return [
                'success' => false,
                'error' => "Maximum investment is {$schema['max_amount']} USD"
            ];
        }
        
        // Check balance
        if ($user['balance'] < $amount) {
            return [
                'success' => false,
                'error' => 'Insufficient balance'
            ];
        }
        
        // Process investment
        $result = $this->investmentModel->createInvestment($userId, $schemaId, $amount);
        
        if ($result['success']) {
            // Send confirmation email
            $this->sendInvestmentConfirmation($user, $schema, $amount);
            
            // Calculate referral commission if applicable
            if ($user['referred_by']) {
                $this->processReferralCommission($user['referred_by'], $amount);
            }
        }
        
        return $result;
    }
    
    /**
     * Send investment confirmation email
     */
    private function sendInvestmentConfirmation(array $user, array $schema, float $amount): void 
    {
        // Implementation - send email
    }
    
    /**
     * Process referral commission
     */
    private function processReferralCommission(int $referrerId, float $investmentAmount): void 
    {
        $commissionRate = (float)($_ENV['REFERRAL_COMMISSION_RATE'] ?? 5.0);
        $commissionAmount = $investmentAmount * ($commissionRate / 100);
        
        // Add commission to referrer balance
        $this->userModel->addBalance($referrerId, $commissionAmount, 'referral_commission');
    }
}
```

## 2.7 CONTROLLER PATTERN

**Every controller MUST follow this pattern:**

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\InvestmentService;
use App\Utils\Security;
use App\Utils\Validator;
use App\Utils\JsonResponse;

class InvestmentController 
{
    private InvestmentService $investmentService;
    
    public function __construct() 
    {
        $this->investmentService = new InvestmentService();
    }
    
    /**
     * Handle investment creation request
     */
    public function createInvestment(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Rate limiting
        if (!Security::rateLimitCheck($_SESSION['user_id'], 'investment_create', 5, 3600)) {
            JsonResponse::error('Too many investment attempts. Please wait.', 429);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::error('Authentication required', 401);
            return;
        }
        
        // Input validation
        $schemaId = Validator::sanitizeInt($_POST['schema_id'] ?? 0);
        $amount = Validator::sanitizeFloat($_POST['amount'] ?? 0);
        
        if ($schemaId <= 0) {
            JsonResponse::error('Invalid investment plan');
            return;
        }
        
        if ($amount <= 0) {
            JsonResponse::error('Invalid investment amount');
            return;
        }
        
        // Process through service
        $result = $this->investmentService->processInvestment(
            (int)$_SESSION['user_id'],
            $schemaId,
            $amount
        );
        
        if ($result['success']) {
            JsonResponse::success($result);
        } else {
            JsonResponse::error($result['error'] ?? 'Investment failed');
        }
    }
}
```

## 2.8 INPUT VALIDATION RULES

**EVERY input MUST be validated using these patterns:**

```php
<?php
declare(strict_types=1);

namespace App\Utils;

class Validator 
{
    /**
     * Validate email address
     */
    public static function isValidEmail(string $email): bool 
    {
        if (empty($email) || strlen($email) > 255) {
            return false;
        }
        
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate username
     */
    public static function isValidUsername(string $username): bool 
    {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username) === 1;
    }
    
    /**
     * Validate password strength
     */
    public static function isValidPassword(string $password): bool 
    {
        if (strlen($password) < 8) return false;
        if (!preg_match('/[A-Z]/', $password)) return false;  // Uppercase
        if (!preg_match('/[a-z]/', $password)) return false;  // Lowercase
        if (!preg_match('/[0-9]/', $password)) return false;  // Number
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) return false; // Special char
        
        return true;
    }
    
    /**
     * Sanitize integer input
     */
    public static function sanitizeInt(mixed $value): int 
    {
        return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }
    
    /**
     * Sanitize float input  
     */
    public static function sanitizeFloat(mixed $value): float 
    {
        return (float)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
    
    /**
     * Sanitize string input
     */
    public static function sanitizeString(mixed $value, int $maxLength = 255): string 
    {
        $cleaned = trim((string)$value);
        return substr($cleaned, 0, $maxLength);
    }
    
    /**
     * Validate wallet address
     */
    public static function isValidWalletAddress(string $address, string $currency): bool 
    {
        $address = trim($address);
        
        switch (strtoupper($currency)) {
            case 'BTC':
                return preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address) === 1;
            
            case 'ETH':
            case 'USDT':
                return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
                
            default:
                return strlen($address) >= 26 && strlen($address) <= 62;
        }
    }
    
    /**
     * Validate investment amount
     */
    public static function isValidInvestmentAmount(float $amount, float $min, float $max): bool 
    {
        return $amount >= $min && $amount <= $max && $amount > 0;
    }
}

// Usage in controllers:
$email = Validator::sanitizeString($_POST['email'] ?? '');
if (!Validator::isValidEmail($email)) {
    JsonResponse::error('Invalid email address');
    return;
}
```

---

# 3. SECURITY STANDARDS

## 3.1 AUTHENTICATION & SESSION MANAGEMENT

### PASSWORD HASHING (MANDATORY)
```php
// RIGHT - Use Argon2ID with proper cost
public function hashPassword(string $password): string 
{
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,  // 64 MB
        'time_cost' => 4,        // 4 iterations
        'threads' => 3           // 3 threads
    ]);
}

// RIGHT - Verify password
public function verifyPassword(string $password, string $hash): bool 
{
    return password_verify($password, $hash);
}

// WRONG - Weak hashing
$hash = md5($password);           // FORBIDDEN
$hash = sha1($password);          // FORBIDDEN  
$hash = password_hash($password, PASSWORD_DEFAULT); // Too weak
```

### SESSION MANAGEMENT
```php
<?php
declare(strict_types=1);

namespace App\Utils;

class SessionManager 
{
    /**
     * Start secure session
     */
    public static function start(): void 
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_lifetime', '0');
            ini_set('session.cookie_path', '/');
            ini_set('session.cookie_domain', $_SERVER['HTTP_HOST'] ?? '');
            ini_set('session.cookie_secure', '1');      // HTTPS only
            ini_set('session.cookie_httponly', '1');    // No JS access
            ini_set('session.cookie_samesite', 'Strict'); // CSRF protection
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', '1800');  // 30 minutes
            
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['last_regeneration'])) {
                self::regenerateSessionId();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                self::regenerateSessionId();
            }
            
            // Session fingerprinting for security
            $currentFingerprint = self::getFingerprint();
            if (isset($_SESSION['fingerprint']) && $_SESSION['fingerprint'] !== $currentFingerprint) {
                self::destroy();
                throw new SecurityException('Session security violation detected');
            }
            $_SESSION['fingerprint'] = $currentFingerprint;
        }
    }
    
    /**
     * Regenerate session ID
     */
    public static function regenerateSessionId(): void 
    {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    /**
     * Get session fingerprint for security
     */
    private static function getFingerprint(): string 
    {
        return hash('sha256', 
            $_SERVER['HTTP_USER_AGENT'] . 
            $_SERVER['REMOTE_ADDR'] . 
            $_SERVER['HTTP_ACCEPT_LANGUAGE']
        );
    }
    
    /**
     * Authenticate user session
     */
    public static function authenticateUser(int $userId, string $role = 'user'): void 
    {
        self::regenerateSessionId();
        
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $role;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Update user last login
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool 
    {
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            return false;
        }
        
        // Check session timeout (30 minutes)
        if (time() - $_SESSION['last_activity'] > 1800) {
            self::destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Destroy session
     */
    public static function destroy(): void 
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        
        // Clear session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
    }
}
```

## 3.2 CSRF PROTECTION (PROPER IMPLEMENTATION)

**DO NOT STORE CSRF TOKENS IN DATABASE!**

```php
<?php
declare(strict_types=1);

namespace App\Utils;

class Security 
{
    /**
     * Generate CSRF token for current session
     */
    public static function generateCsrfToken(): string 
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCsrfToken(string $token): bool 
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF token for forms
     */
    public static function getCsrfTokenInput(): string 
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}

// Usage in templates:
echo Security::getCsrfTokenInput();

// Usage in controllers:
if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
    JsonResponse::error('Invalid security token', 403);
    return;
}

// WRONG - Do NOT store CSRF tokens in database:
// INSERT INTO admin_settings (setting_key, setting_value) VALUES ('csrf_token', '...');
// This is a SECURITY VIOLATION found in current code!
```

## 3.3 XSS PROTECTION

**ALL OUTPUT MUST BE ESCAPED:**

```php
<?php
// RIGHT - Escape all user data in templates
echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
echo htmlspecialchars($transaction['description'], ENT_QUOTES, 'UTF-8');

// RIGHT - Helper function for cleaner templates
function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Usage in templates:
<h1>Welcome, <?= esc($user['first_name']) ?>!</h1>
<p>Balance: $<?= number_format($user['balance'], 2) ?></p>

// WRONG - Raw output (found throughout current codebase)
echo $user['username'];        // XSS vulnerability
echo $transaction['description']; // XSS vulnerability

// WRONG - Using unreliable functions
echo strip_tags($input);       // Can be bypassed
echo filter_var($input);       // Inconsistent behavior
```

## 3.4 SQL INJECTION PREVENTION

**ONLY prepared statements allowed:**

```php
// RIGHT - Prepared statements with typed parameters
public function getUserTransactions(int $userId, int $limit = 50): array 
{
    $stmt = $this->db->prepare(
        "SELECT id, type, amount, status, created_at 
         FROM transactions 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT ?"
    );
    
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

// RIGHT - More complex prepared statement
public function searchUsers(string $search, array $statuses): array 
{
    $placeholders = str_repeat('?,', count($statuses) - 1) . '?';
    
    $stmt = $this->db->prepare(
        "SELECT id, email, username, created_at 
         FROM users 
         WHERE (email LIKE ? OR username LIKE ?) 
         AND status IN ($placeholders)
         LIMIT 100"
    );
    
    $params = ["%{$search}%", "%{$search}%"];
    $params = array_merge($params, $statuses);
    
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// WRONG - String concatenation (SECURITY BREACH)
$sql = "SELECT * FROM users WHERE id = " . $userId;              // FORBIDDEN
$sql = "SELECT * FROM users WHERE email = '$email'";             // FORBIDDEN
$sql = "SELECT * FROM users WHERE name LIKE '%$search%'";        // FORBIDDEN
$this->db->query($sql);                                          // FORBIDDEN

// WRONG - sprintf/vsprintf (still vulnerable)
$sql = sprintf("SELECT * FROM users WHERE id = %d", $userId);    // FORBIDDEN
```

## 3.5 RATE LIMITING

```php
<?php
declare(strict_types=1);

namespace App\Utils;

class RateLimiter 
{
    private static array $attempts = [];
    
    /**
     * Check rate limit for action
     * @param string $identifier User ID or IP address
     * @param string $action Action being performed
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if within limits, false if exceeded
     */
    public static function isAllowed(
        string $identifier, 
        string $action, 
        int $maxAttempts = 5, 
        int $timeWindow = 900
    ): bool {
        $key = "{$action}:{$identifier}";
        $now = time();
        
        // Clean old attempts
        if (isset(self::$attempts[$key])) {
            self::$attempts[$key] = array_filter(
                self::$attempts[$key], 
                fn($timestamp) => $now - $timestamp < $timeWindow
            );
        }
        
        // Check if limit exceeded
        $currentAttempts = count(self::$attempts[$key] ?? []);
        
        if ($currentAttempts >= $maxAttempts) {
            return false;
        }
        
        // Record this attempt
        self::$attempts[$key][] = $now;
        
        return true;
    }
    
    /**
     * Get remaining attempts
     */
    public static function getRemainingAttempts(
        string $identifier, 
        string $action, 
        int $maxAttempts = 5
    ): int {
        $key = "{$action}:{$identifier}";
        $currentAttempts = count(self::$attempts[$key] ?? []);
        
        return max(0, $maxAttempts - $currentAttempts);
    }
}

// Usage in controllers:
$userIp = $_SERVER['REMOTE_ADDR'];
$userId = $_SESSION['user_id'] ?? $userIp;

if (!RateLimiter::isAllowed($userId, 'login', 5, 900)) {
    JsonResponse::error('Too many login attempts. Please wait 15 minutes.', 429);
    return;
}

if (!RateLimiter::isAllowed($userId, 'investment_create', 3, 3600)) {
    JsonResponse::error('Too many investment attempts. Please wait 1 hour.', 429);
    return;
}
```

## 3.6 FILE UPLOAD SECURITY

```php
<?php
declare(strict_types=1);

namespace App\Utils;

class FileUploader 
{
    private const ALLOWED_TYPES = [
        'image/jpeg',
        'image/png', 
        'image/gif',
        'application/pdf'
    ];
    
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'pdf'
    ];
    
    /**
     * Upload file securely
     * @param array $file $_FILES array element
     * @param string $uploadDir Upload directory
     * @return array Upload result
     */
    public static function upload(array $file, string $uploadDir): array 
    {
        // Validate file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }
        
        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['success' => false, 'error' => 'File too large (max 5MB)'];
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, self::ALLOWED_TYPES)) {
            return ['success' => false, 'error' => 'Invalid file type'];
        }
        
        // Validate extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return ['success' => false, 'error' => 'Invalid file extension'];
        }
        
        // Generate secure filename
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $filepath = rtrim($uploadDir, '/') . '/' . $filename;
        
        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'error' => 'Upload failed'];
        }
        
        // Set proper file permissions
        chmod($filepath, 0644);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => $file['size']
        ];
    }
}

// WRONG - Insecure upload patterns (found in audit):
move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $_FILES['file']['name']); // Dangerous!
```

## 3.7 ENCRYPTION FOR SENSITIVE DATA

```php
<?php
declare(strict_types=1);

namespace App\Utils;

class Encryption 
{
    /**
     * Encrypt sensitive data
     */
    public static function encrypt(string $data): string 
    {
        $key = self::getEncryptionKey();
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        
        // Prepend IV to encrypted data
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public static function decrypt(string $encryptedData): string 
    {
        $key = self::getEncryptionKey();
        $data = base64_decode($encryptedData);
        
        // Extract IV and encrypted content
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    }
    
    /**
     * Get encryption key from environment
     */
    private static function getEncryptionKey(): string 
    {
        $key = $_ENV['ENCRYPTION_KEY'] ?? null;
        
        if (empty($key) || strlen($key) !== 64) {
            throw new \RuntimeException('Invalid encryption key configuration');
        }
        
        return hex2bin($key);
    }
}

// Usage for storing sensitive data:
$encryptedApiKey = Encryption::encrypt($apiKey);
// Store $encryptedApiKey in database

$apiKey = Encryption::decrypt($encryptedApiKey);
// Use decrypted API key

// WRONG - Plaintext sensitive data (found in admin_settings table):
// 'email_smtp_password' => 'Superadmin1000$'  // SECURITY BREACH!
```

---

# 4. FRONTEND STANDARDS

## 4.1 DESIGN SYSTEM - EXACT COLORS

```css
/* MANDATORY COLOR PALETTE */
:root {
  /* Primary Brand Colors */
  --cf-primary: #667eea;        /* Main brand blue */
  --cf-primary-dark: #5a67d8;   /* Hover states */
  --cf-primary-light: #a5b4fc;  /* Disabled states */
  
  /* Secondary Colors */
  --cf-secondary: #764ba2;       /* Purple accent */
  --cf-secondary-dark: #6b46c1;
  --cf-secondary-light: #c4b5fd;
  
  /* Status Colors */
  --cf-success: #10b981;         /* Green for profits, success */
  --cf-success-dark: #059669;
  --cf-success-light: #86efac;
  
  --cf-warning: #f59e0b;         /* Yellow for warnings */
  --cf-warning-dark: #d97706;
  --cf-warning-light: #fcd34d;
  
  --cf-danger: #ef4444;          /* Red for errors, losses */
  --cf-danger-dark: #dc2626;
  --cf-danger-light: #fca5a5;
  
  --cf-info: #3b82f6;           /* Blue for information */
  --cf-info-dark: #2563eb;
  --cf-info-light: #93c5fd;
  
  /* Neutral Colors */
  --cf-gray-50: #f9fafb;
  --cf-gray-100: #f3f4f6;
  --cf-gray-200: #e5e7eb;
  --cf-gray-300: #d1d5db;
  --cf-gray-400: #9ca3af;
  --cf-gray-500: #6b7280;
  --cf-gray-600: #4b5563;
  --cf-gray-700: #374151;
  --cf-gray-800: #1f2937;
  --cf-gray-900: #111827;
  
  /* Light/Dark Mode Variables */
  --cf-bg-primary: #ffffff;      /* Main background */
  --cf-bg-secondary: #f9fafb;    /* Card backgrounds */
  --cf-text-primary: #111827;    /* Main text */
  --cf-text-secondary: #6b7280;  /* Secondary text */
  --cf-border: #e5e7eb;          /* Borders */
}

/* Dark Mode Overrides */
[data-theme="dark"] {
  --cf-bg-primary: #111827;
  --cf-bg-secondary: #1f2937;
  --cf-text-primary: #f9fafb;
  --cf-text-secondary: #d1d5db;
  --cf-border: #374151;
}
```

## 4.2 COMPONENT PATTERNS

### Card Component
```html
<!-- Standard Card -->
<div class="cf-card">
    <div class="cf-card-header">
        <h3 class="cf-card-title">Investment Balance</h3>
        <span class="cf-card-subtitle">Current Holdings</span>
    </div>
    <div class="cf-card-body">
        <div class="cf-balance-display">
            <span class="cf-currency">$</span>
            <span class="cf-amount">1,234.56</span>
        </div>
    </div>
    <div class="cf-card-footer">
        <button class="cf-btn cf-btn-primary">View Details</button>
    </div>
</div>
```

```css
.cf-card {
    background: var(--cf-bg-secondary);
    border: 1px solid var(--cf-border);
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}

.cf-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.cf-card-header {
    padding: 1.5rem 1.5rem 0;
}

.cf-card-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--cf-text-primary);
    margin: 0 0 0.25rem 0;
}

.cf-card-subtitle {
    font-size: 0.875rem;
    color: var(--cf-text-secondary);
}

.cf-card-body {
    padding: 1.5rem;
}

.cf-card-footer {
    padding: 0 1.5rem 1.5rem;
    border-top: 1px solid var(--cf-border);
    margin-top: 1rem;
    padding-top: 1rem;
}
```

### Button System
```html
<!-- Primary Button -->
<button class="cf-btn cf-btn-primary">Invest Now</button>

<!-- Secondary Button -->
<button class="cf-btn cf-btn-secondary">View History</button>

<!-- Success Button -->
<button class="cf-btn cf-btn-success">Confirm Withdrawal</button>

<!-- Danger Button -->
<button class="cf-btn cf-btn-danger">Cancel Investment</button>

<!-- Loading State -->
<button class="cf-btn cf-btn-primary cf-btn-loading">
    <span class="cf-spinner"></span>
    Processing...
</button>
```

```css
.cf-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 6px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    line-height: 1.5;
    white-space: nowrap;
}

.cf-btn:disabled,
.cf-btn-loading {
    opacity: 0.6;
    cursor: not-allowed;
}

.cf-btn-primary {
    background: var(--cf-primary);
    color: white;
    border-color: var(--cf-primary);
}

.cf-btn-primary:hover:not(:disabled) {
    background: var(--cf-primary-dark);
    border-color: var(--cf-primary-dark);
}

.cf-btn-secondary {
    background: transparent;
    color: var(--cf-text-primary);
    border-color: var(--cf-border);
}

.cf-btn-secondary:hover:not(:disabled) {
    background: var(--cf-gray-50);
}

[data-theme="dark"] .cf-btn-secondary:hover:not(:disabled) {
    background: var(--cf-gray-800);
}

.cf-btn-success {
    background: var(--cf-success);
    color: white;
    border-color: var(--cf-success);
}

.cf-btn-success:hover:not(:disabled) {
    background: var(--cf-success-dark);
}

.cf-btn-danger {
    background: var(--cf-danger);
    color: white;
    border-color: var(--cf-danger);
}

.cf-btn-danger:hover:not(:disabled) {
    background: var(--cf-danger-dark);
}

.cf-spinner {
    width: 1rem;
    height: 1rem;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
```

### Form Components
```html
<!-- Standard Form -->
<form class="cf-form" id="investmentForm">
    <?= Security::getCsrfTokenInput() ?>
    
    <div class="cf-form-group">
        <label for="investment_plan" class="cf-label">Investment Plan</label>
        <select id="investment_plan" name="schema_id" class="cf-select" required>
            <option value="">Select a plan...</option>
            <option value="1">Basic Plan - 2% Daily</option>
            <option value="2">Premium Plan - 3% Daily</option>
        </select>
        <div class="cf-form-error" id="schema_id-error"></div>
    </div>
    
    <div class="cf-form-group">
        <label for="amount" class="cf-label">Investment Amount</label>
        <div class="cf-input-group">
            <span class="cf-input-prefix">$</span>
            <input type="number" id="amount" name="amount" class="cf-input" 
                   min="50" step="0.01" required placeholder="0.00">
            <span class="cf-input-suffix">USD</span>
        </div>
        <div class="cf-form-help">Minimum investment: $50</div>
        <div class="cf-form-error" id="amount-error"></div>
    </div>
    
    <div class="cf-form-actions">
        <button type="submit" class="cf-btn cf-btn-primary">
            Create Investment
        </button>
        <button type="button" class="cf-btn cf-btn-secondary">
            Cancel
        </button>
    </div>
</form>
```

```css
.cf-form {
    max-width: 500px;
}

.cf-form-group {
    margin-bottom: 1.5rem;
}

.cf-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--cf-text-primary);
    margin-bottom: 0.5rem;
}

.cf-input,
.cf-select,
.cf-textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border: 1px solid var(--cf-border);
    border-radius: 6px;
    background: var(--cf-bg-primary);
    color: var(--cf-text-primary);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.cf-input:focus,
.cf-select:focus,
.cf-textarea:focus {
    outline: none;
    border-color: var(--cf-primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.cf-input-group {
    display: flex;
    align-items: center;
}

.cf-input-group .cf-input {
    border-radius: 0;
    border-left: none;
    border-right: none;
}

.cf-input-prefix,
.cf-input-suffix {
    padding: 0.5rem 0.75rem;
    background: var(--cf-gray-50);
    border: 1px solid var(--cf-border);
    font-size: 0.875rem;
    color: var(--cf-text-secondary);
}

.cf-input-prefix {
    border-radius: 6px 0 0 6px;
    border-right: none;
}

.cf-input-suffix {
    border-radius: 0 6px 6px 0;
    border-left: none;
}

[data-theme="dark"] .cf-input-prefix,
[data-theme="dark"] .cf-input-suffix {
    background: var(--cf-gray-800);
}

.cf-form-help {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--cf-text-secondary);
}

.cf-form-error {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--cf-danger);
    display: none;
}

.cf-form-error.show {
    display: block;
}

.cf-form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}
```

## 4.3 DARK MODE IMPLEMENTATION

```html
<!-- Theme Toggle Button -->
<button class="cf-theme-toggle" id="themeToggle" title="Toggle dark mode">
    <span class="cf-theme-icon cf-theme-sun">☀️</span>
    <span class="cf-theme-icon cf-theme-moon">🌙</span>
</button>
```

```css
.cf-theme-toggle {
    position: relative;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    border: none;
    background: var(--cf-bg-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    overflow: hidden;
}

.cf-theme-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.cf-theme-sun {
    opacity: 1;
}

.cf-theme-moon {
    opacity: 0;
    transform: translate(-50%, -50%) rotate(180deg);
}

[data-theme="dark"] .cf-theme-sun {
    opacity: 0;
    transform: translate(-50%, -50%) rotate(-180deg);
}

[data-theme="dark"] .cf-theme-moon {
    opacity: 1;
    transform: translate(-50%, -50%) rotate(0deg);
}
```

```javascript
// Dark Mode Toggle Implementation
class ThemeManager {
    constructor() {
        this.init();
    }
    
    init() {
        // Get saved theme or default to light
        const savedTheme = localStorage.getItem('cf-theme') || 'light';
        this.setTheme(savedTheme);
        
        // Add event listener to toggle button
        const toggleButton = document.getElementById('themeToggle');
        if (toggleButton) {
            toggleButton.addEventListener('click', () => this.toggleTheme());
        }
    }
    
    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('cf-theme', theme);
    }
    
    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
    }
    
    getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    }
}

// Initialize theme manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new ThemeManager();
});
```

## 4.4 JAVASCRIPT PATTERNS (VANILLA JS ONLY)

```javascript
// AJAX Request Helper (NO JQUERY!)
class ApiClient {
    /**
     * Make authenticated API request
     * @param {string} url 
     * @param {Object} options 
     * @returns {Promise}
     */
    static async request(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };
        
        const config = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                return await response.text();
            }
            
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    }
    
    /**
     * POST request with CSRF token
     */
    static async post(url, data = {}) {
        // Get CSRF token from meta tag or form
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                       || document.querySelector('input[name="csrf_token"]')?.value;
        
        if (csrfToken) {
            data.csrf_token = csrfToken;
        }
        
        return this.request(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
    }
}

// Form Validation Helper
class FormValidator {
    constructor(formElement) {
        this.form = formElement;
        this.errors = {};
        this.init();
    }
    
    init() {
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.validateForm();
        });
        
        // Real-time validation
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }
    
    validateForm() {
        this.errors = {};
        
        const inputs = this.form.querySelectorAll('input[required], select[required], textarea[required]');
        inputs.forEach(input => this.validateField(input));
        
        if (Object.keys(this.errors).length === 0) {
            this.submitForm();
        } else {
            this.displayErrors();
        }
    }
    
    validateField(field) {
        const value = field.value.trim();
        const name = field.name;
        
        // Required validation
        if (field.hasAttribute('required') && !value) {
            this.errors[name] = 'This field is required';
            return;
        }
        
        // Email validation
        if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                this.errors[name] = 'Please enter a valid email address';
            }
        }
        
        // Number validation
        if (field.type === 'number' && value) {
            const num = parseFloat(value);
            const min = parseFloat(field.min);
            const max = parseFloat(field.max);
            
            if (isNaN(num)) {
                this.errors[name] = 'Please enter a valid number';
            } else if (!isNaN(min) && num < min) {
                this.errors[name] = `Value must be at least ${min}`;
            } else if (!isNaN(max) && num > max) {
                this.errors[name] = `Value must not exceed ${max}`;
            }
        }
    }
    
    displayErrors() {
        // Clear all previous errors
        this.form.querySelectorAll('.cf-form-error').forEach(errorEl => {
            errorEl.textContent = '';
            errorEl.classList.remove('show');
        });
        
        // Display new errors
        Object.keys(this.errors).forEach(fieldName => {
            const errorElement = document.getElementById(`${fieldName}-error`);
            if (errorElement) {
                errorElement.textContent = this.errors[fieldName];
                errorElement.classList.add('show');
            }
            
            // Add error styling to field
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.style.borderColor = 'var(--cf-danger)';
            }
        });
    }
    
    clearFieldError(field) {
        const errorElement = document.getElementById(`${field.name}-error`);
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.classList.remove('show');
        }
        field.style.borderColor = '';
    }
    
    async submitForm() {
        const submitButton = this.form.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        
        // Show loading state
        submitButton.classList.add('cf-btn-loading');
        submitButton.innerHTML = '<span class="cf-spinner"></span> Processing...';
        submitButton.disabled = true;
        
        try {
            const formData = new FormData(this.form);
            const data = Object.fromEntries(formData.entries());
            
            const response = await ApiClient.post(this.form.action, data);
            
            if (response.success) {
                this.handleSuccess(response);
            } else {
                this.handleError(response.error || 'An error occurred');
            }
            
        } catch (error) {
            this.handleError('Network error. Please try again.');
        } finally {
            // Restore button state
            submitButton.classList.remove('cf-btn-loading');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    }
    
    handleSuccess(response) {
        // Show success notification
        NotificationManager.show('Success!', 'success');
        
        // Redirect if specified
        if (response.redirect) {
            window.location.href = response.redirect;
        }
    }
    
    handleError(message) {
        NotificationManager.show(message, 'error');
    }
}

// Notification System
class NotificationManager {
    static show(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `cf-notification cf-notification-${type}`;
        notification.innerHTML = `
            <div class="cf-notification-content">
                <span class="cf-notification-icon">${this.getIcon(type)}</span>
                <span class="cf-notification-message">${message}</span>
                <button class="cf-notification-close">×</button>
            </div>
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Animate in
        requestAnimationFrame(() => {
            notification.classList.add('cf-notification-show');
        });
        
        // Auto remove
        setTimeout(() => {
            this.remove(notification);
        }, duration);
        
        // Manual close
        notification.querySelector('.cf-notification-close').addEventListener('click', () => {
            this.remove(notification);
        });
    }
    
    static getIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        return icons[type] || icons.info;
    }
    
    static remove(notification) {
        notification.classList.add('cf-notification-hide');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
}

// Initialize form validators on page load
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        new FormValidator(form);
    });
});
```

## 4.5 NO INLINE STYLES OR SCRIPTS

```html
<!-- RIGHT - External CSS and JS -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cornerfield Investment Platform</title>
    
    <!-- External CSS only -->
    <link rel="stylesheet" href="/assets/css/cornerfield.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- In production, use compiled Tailwind via PostCSS -->
</head>
<body>
    <div class="cf-container">
        <!-- Content -->
    </div>
    
    <!-- External JS only -->
    <script src="/assets/js/cornerfield.min.js"></script>
</body>
</html>

<!-- WRONG - Inline styles and scripts (found in audit) -->
<div style="color: red; font-size: 14px;">Error message</div>  <!-- FORBIDDEN -->
<script>alert('Hello');</script>                               <!-- FORBIDDEN -->
<button onclick="doSomething()">Click</button>                 <!-- FORBIDDEN -->
```

---

# 5. FILE STRUCTURE

## 5.1 EXACT DIRECTORY STRUCTURE

```
cornerfield/
├── autoload.php                 # SINGLE autoloader (no vendor/autoload.php)
├── index.php                    # Main entry point
├── .env                         # Environment variables (NEVER commit)
├── .env.example                 # Environment template
├── .htaccess                    # Apache configuration
├── composer.json                # Dependency management
├── README.md                    # Project documentation
│
├── config/
│   ├── Database.php             # Database configuration ONLY
│   └── constants.php            # Application constants
│
├── src/
│   ├── Config/
│   │   ├── Config.php           # Main configuration class
│   │   └── Database.php         # Database connection class
│   │
│   ├── Models/
│   │   ├── UserModel.php
│   │   ├── InvestmentModel.php
│   │   ├── TransactionModel.php
│   │   ├── AdminModel.php
│   │   └── BaseModel.php        # Shared model functionality
│   │
│   ├── Controllers/
│   │   ├── UserController.php
│   │   ├── InvestmentController.php
│   │   ├── TransactionController.php
│   │   └── AdminController.php
│   │
│   ├── Services/
│   │   ├── InvestmentService.php
│   │   ├── PaymentService.php
│   │   ├── EmailService.php
│   │   └── NotificationService.php
│   │
│   ├── Utils/
│   │   ├── Security.php
│   │   ├── Validator.php
│   │   ├── Encryption.php
│   │   ├── RateLimiter.php
│   │   ├── FileUploader.php
│   │   └── JsonResponse.php
│   │
│   └── Middleware/
│       ├── AuthMiddleware.php
│       ├── CsrfMiddleware.php
│       └── RateLimitMiddleware.php
│
├── public/                      # Web-accessible files
│   ├── index.php               # Public entry point
│   ├── login.php
│   ├── register.php
│   └── api/                    # API endpoints
│       ├── invest.php
│       ├── withdraw.php
│       └── transactions.php
│
├── users/                       # User dashboard
│   ├── dashboard.php
│   ├── investments.php
│   ├── transactions.php
│   ├── profile.php
│   └── includes/
│       ├── header.php
│       ├── footer.php
│       ├── sidebar.php
│       └── auth-check.php
│
├── admin/                       # Admin panel
│   ├── dashboard.php
│   ├── users.php
│   ├── investments.php
│   ├── transactions.php
│   ├── settings.php
│   └── includes/
│       ├── header.php
│       ├── footer.php
│       ├── sidebar.php
│       └── admin-auth.php
│
├── templates/                   # Reusable templates
│   ├── email/
│   │   ├── welcome.php
│   │   ├── investment-confirm.php
│   │   └── withdrawal-confirm.php
│   └── pdf/
│       └── investment-report.php
│
├── assets/
│   ├── css/
│   │   ├── cornerfield.css      # Main stylesheet
│   │   ├── cornerfield.min.css  # Minified version
│   │   └── admin.css           # Admin-specific styles
│   │
│   ├── js/
│   │   ├── cornerfield.js       # Main JavaScript
│   │   ├── cornerfield.min.js   # Minified version
│   │   ├── admin.js            # Admin JavaScript
│   │   └── components/         # JS components
│   │       ├── form-validator.js
│   │       ├── notification.js
│   │       └── theme-manager.js
│   │
│   ├── images/
│   │   ├── logo.png
│   │   ├── favicon.ico
│   │   └── icons/
│   │
│   └── vendor/                  # Third-party assets
│       └── tailwind/
│           └── tailwind.config.js
│
├── uploads/                     # User uploads
│   ├── documents/              # KYC documents
│   ├── profile-images/         # Profile photos
│   └── temp/                   # Temporary files
│
├── logs/                       # Application logs
│   ├── app.log
│   ├── security.log
│   ├── error.log
│   └── api.log
│
├── database/
│   ├── schema.sql              # Database structure
│   ├── migrations/             # Database changes
│   │   ├── 001_create_users.sql
│   │   ├── 002_create_investments.sql
│   │   └── 003_add_security_logs.sql
│   └── seeders/                # Test data
│       └── demo_data.sql
│
├── tests/                      # Unit tests
│   ├── Models/
│   ├── Services/
│   └── Utils/
│
└── docs/                       # Documentation
    ├── API.md
    ├── DEPLOYMENT.md
    └── SECURITY.md
```

## 5.2 FILE NAMING CONVENTIONS

```php
// Classes: PascalCase
UserModel.php
InvestmentService.php
SecurityHelper.php

// Files: lowercase with hyphens
user-dashboard.php
investment-history.php
password-reset.php

// Directories: lowercase with hyphens
user-documents/
email-templates/
payment-gateways/

// Constants: UPPER_SNAKE_CASE
MAX_INVESTMENT_AMOUNT
DEFAULT_CURRENCY_CODE
SESSION_TIMEOUT_MINUTES

// Variables: camelCase
$userName
$investmentAmount
$currentBalance

// Database tables: lowercase with underscores (as defined in schema)
users
admin_sessions
investment_schemas
user_documents
```

## 5.3 AUTOLOADING RULES

```php
<?php
// autoload.php - THE ONLY AUTOLOADER ALLOWED

spl_autoload_register(function ($className) {
    // Only handle App namespace
    if (strpos($className, 'App\\') !== 0) {
        return;
    }
    
    // Remove App\ prefix
    $className = substr($className, 4);
    
    // Convert namespace separators to directory separators
    $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
    
    // Build full path
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $fileName;
    
    // Load if exists
    if (file_exists($filePath)) {
        require_once $filePath;
        return;
    }
    
    // Log missing class for debugging
    error_log("Autoloader: Could not load class {$className}. Expected file: {$filePath}");
});

// Usage examples:
// App\Models\UserModel       -> src/Models/UserModel.php
// App\Services\EmailService -> src/Services/EmailService.php  
// App\Utils\Security         -> src/Utils/Security.php

// WRONG - These paths are FORBIDDEN:
require_once dirname(__DIR__) . '/vendor/autoload.php';  // Does not exist!
include 'path/to/class.php';                             // Manual includes forbidden
```

---

# 6. DATABASE INTERACTION RULES

## 6.1 SINGLE CONNECTION CLASS

**Use ONLY the Database class from section 2.4 - no exceptions.**

## 6.2 TRANSACTION PATTERNS FOR FINANCIAL OPERATIONS

```php
<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use App\Config\Database;
use App\Utils\Security;

class FinancialService 
{
    private PDO $db;
    
    public function __construct() 
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Process investment with full transaction safety
     * @param int $userId
     * @param int $schemaId
     * @param float $amount
     * @return array
     */
    public function processInvestment(int $userId, int $schemaId, float $amount): array 
    {
        try {
            // Start transaction
            $this->db->beginTransaction();
            
            // 1. Validate user has sufficient balance
            $userBalance = $this->getUserBalance($userId);
            if ($userBalance < $amount) {
                throw new \InvalidArgumentException('Insufficient balance');
            }
            
            // 2. Create main transaction record
            $transactionId = $this->createTransaction($userId, 'investment', $amount, [
                'schema_id' => $schemaId,
                'description' => 'Investment creation'
            ]);
            
            // 3. Create investment record
            $investmentId = $this->createInvestmentRecord($userId, $schemaId, $amount, $transactionId);
            
            // 4. Update user balance (deduct investment amount)
            $this->updateUserBalance($userId, -$amount, 'investment_deduction');
            
            // 5. Update user total_invested
            $this->updateUserTotalInvested($userId, $amount);
            
            // 6. Log security event
            Security::logAudit($userId, 'investment_created', 'investments', $investmentId, null, [
                'amount' => $amount,
                'schema_id' => $schemaId,
                'transaction_id' => $transactionId
            ]);
            
            // Commit transaction
            $this->db->commit();
            
            return [
                'success' => true,
                'investment_id' => $investmentId,
                'transaction_id' => $transactionId,
                'amount' => $amount
            ];
            
        } catch (\Exception $e) {
            // Rollback on any error
            $this->db->rollBack();
            
            error_log("Investment processing failed for user {$userId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e instanceof \InvalidArgumentException 
                         ? $e->getMessage() 
                         : 'Unable to process investment. Please try again.'
            ];
        }
    }
    
    /**
     * Process withdrawal with dual verification
     */
    public function processWithdrawal(int $userId, float $amount, string $walletAddress, string $currency): array 
    {
        try {
            $this->db->beginTransaction();
            
            // 1. Validate withdrawal limits and balance
            $this->validateWithdrawal($userId, $amount);
            
            // 2. Calculate fees
            $feeAmount = $this->calculateWithdrawalFee($amount);
            $totalDeduction = $amount + $feeAmount;
            
            // 3. Create transaction record
            $transactionId = $this->createTransaction($userId, 'withdrawal', $amount, [
                'fee_amount' => $feeAmount,
                'wallet_address' => $walletAddress,
                'currency' => $currency,
                'status' => 'pending'
            ]);
            
            // 4. Create withdrawal record  
            $withdrawalId = $this->createWithdrawalRecord($userId, $transactionId, $amount, $feeAmount, $walletAddress, $currency);
            
            // 5. Lock funds (deduct from available balance)
            $this->updateUserBalance($userId, -$totalDeduction, 'withdrawal_lock');
            
            // 6. Update user total_withdrawn (pending)
            $this->updateUserTotalWithdrawn($userId, $amount);
            
            // 7. Security log
            Security::logAudit($userId, 'withdrawal_requested', 'withdrawals', $withdrawalId, null, [
                'amount' => $amount,
                'fee' => $feeAmount,
                'currency' => $currency,
                'wallet_address' => substr($walletAddress, 0, 10) . '...' // Masked for security
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'withdrawal_id' => $withdrawalId,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'fee' => $feeAmount,
                'status' => 'pending'
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            
            error_log("Withdrawal processing failed for user {$userId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e instanceof \InvalidArgumentException 
                         ? $e->getMessage() 
                         : 'Unable to process withdrawal. Please try again.'
            ];
        }
    }
    
    /**
     * Create main transaction record (used by all financial operations)
     */
    private function createTransaction(int $userId, string $type, float $amount, array $details = []): int 
    {
        $stmt = $this->db->prepare(
            "INSERT INTO transactions (user_id, type, amount, fee, net_amount, status, 
                                     payment_method, currency, description, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        
        $stmt->execute([
            $userId,
            $type,
            $amount,
            $details['fee_amount'] ?? 0,
            $amount - ($details['fee_amount'] ?? 0),
            $details['status'] ?? 'pending',
            $details['payment_method'] ?? 'balance',
            $details['currency'] ?? 'USD',
            $details['description'] ?? null
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    // Additional helper methods...
}
```

## 6.3 COLUMN NAME MAPPING

**Database column names MUST match exactly:**

```php
<?php
// RIGHT - Exact column name matching
class UserModel 
{
    public function getUserData(int $userId): ?array 
    {
        $stmt = $this->db->prepare(
            "SELECT id, email, username, first_name, last_name, phone, country,
                    balance, locked_balance, bonus_balance, total_invested, 
                    total_withdrawn, total_earned, referral_code, referred_by,
                    kyc_status, is_active, email_verified, two_factor_enabled,
                    login_attempts, last_login, created_at, updated_at
             FROM users 
             WHERE id = ?"
        );
        
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result ? [
            'id' => (int)$result['id'],
            'email' => $result['email'],
            'username' => $result['username'],
            'first_name' => $result['first_name'],
            'last_name' => $result['last_name'],
            'phone' => $result['phone'],
            'country' => $result['country'],
            'balance' => (float)$result['balance'],
            'locked_balance' => (float)$result['locked_balance'],
            'bonus_balance' => (float)$result['bonus_balance'],
            'total_invested' => (float)$result['total_invested'],
            'total_withdrawn' => (float)$result['total_withdrawn'],
            'total_earned' => (float)$result['total_earned'],
            'referral_code' => $result['referral_code'],
            'referred_by' => $result['referred_by'] ? (int)$result['referred_by'] : null,
            'kyc_status' => $result['kyc_status'],
            'is_active' => (bool)$result['is_active'],
            'email_verified' => (bool)$result['email_verified'],
            'two_factor_enabled' => (bool)$result['two_factor_enabled'],
            'login_attempts' => (int)$result['login_attempts'],
            'last_login' => $result['last_login'],
            'created_at' => $result['created_at'],
            'updated_at' => $result['updated_at']
        ] : null;
    }
}

// WRONG - Column name mismatches (found in audit):
$sql = "SELECT user_id FROM transactions";     // Column is 'user_id', not 'userId'
$sql = "SELECT creation_date FROM users";     // Column is 'created_at', not 'creation_date'
$sql = "SELECT is_admin FROM users";          // Column might not exist - check schema
```

---

# 7. ERROR HANDLING & LOGGING

## 7.1 LOGGING STANDARDS

```php
<?php
declare(strict_types=1);

namespace App\Utils;

class Logger 
{
    private const LOG_LEVELS = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];
    
    private static string $logDir = __DIR__ . '/../../logs/';
    
    /**
     * Log security events
     */
    public static function security(string $message, array $context = []): void 
    {
        self::writeLog('security', 'WARNING', $message, $context);
    }
    
    /**
     * Log financial transactions
     */
    public static function financial(string $message, array $context = []): void 
    {
        self::writeLog('financial', 'INFO', $message, $context);
    }
    
    /**
     * Log application errors
     */
    public static function error(string $message, array $context = []): void 
    {
        self::writeLog('app', 'ERROR', $message, $context);
    }
    
    /**
     * Log API requests
     */
    public static function api(string $method, string $endpoint, int $statusCode, array $context = []): void 
    {
        $message = "{$method} {$endpoint} - {$statusCode}";
        self::writeLog('api', 'INFO', $message, $context);
    }
    
    /**
     * Write log entry
     */
    private static function writeLog(string $logFile, string $level, string $message, array $context = []): void 
    {
        $timestamp = date('Y-m-d H:i:s');
        $userId = $_SESSION['user_id'] ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Sanitize context (remove sensitive data)
        $safeContext = self::sanitizeContext($context);
        
        $logEntry = sprintf(
            "[%s] %s - User:%s - IP:%s - %s - Context:%s\n",
            $timestamp,
            $level,
            $userId,
            $ip,
            $message,
            json_encode($safeContext)
        );
        
        $logPath = self::$logDir . $logFile . '.log';
        
        // Ensure log directory exists
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0750, true);
        }
        
        // Write to log file
        file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Rotate log if too large (> 10MB)
        if (file_exists($logPath) && filesize($logPath) > 10 * 1024 * 1024) {
            self::rotateLog($logPath);
        }
    }
    
    /**
     * Remove sensitive data from context
     */
    private static function sanitizeContext(array $context): array 
    {
        $sensitiveKeys = [
            'password', 'password_hash', 'token', 'secret', 'key',
            'credit_card', 'ssn', 'private_key', 'api_key'
        ];
        
        $sanitized = $context;
        
        array_walk_recursive($sanitized, function (&$value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $value = '[REDACTED]';
            }
        });
        
        return $sanitized;
    }
    
    /**
     * Rotate log file
     */
    private static function rotateLog(string $logPath): void 
    {
        $backupPath = $logPath . '.' . date('Y-m-d-H-i-s');
        rename($logPath, $backupPath);
        
        // Keep only last 10 backup files
        $backupFiles = glob($logPath . '.*');
        if (count($backupFiles) > 10) {
            sort($backupFiles);
            array_slice($backupFiles, 0, -10);
            foreach (array_slice($backupFiles, 0, -10) as $oldBackup) {
                unlink($oldBackup);
            }
        }
    }
}

// Usage examples:
Logger::security('Failed login attempt', [
    'email' => 'user@example.com',
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

Logger::financial('Investment created', [
    'user_id' => 123,
    'amount' => 1000.00,
    'schema_id' => 2,
    'transaction_id' => 456
]);

Logger::error('Database connection failed', [
    'error' => $e->getMessage(),
    'file' => __FILE__,
    'line' => __LINE__
]);

Logger::api('POST', '/api/invest', 200, [
    'user_id' => 123,
    'response_time' => 0.25
]);
```

## 7.2 ERROR PAGE PATTERNS

```php
<?php
declare(strict_types=1);

namespace App\Utils;

class ErrorHandler 
{
    /**
     * Handle uncaught exceptions
     */
    public static function handleException(\Throwable $exception): void 
    {
        $errorId = uniqid('ERR_');
        
        // Log the full error
        Logger::error('Uncaught exception', [
            'error_id' => $errorId,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        // Show user-friendly error
        if (self::isApiRequest()) {
            self::showApiError($errorId);
        } else {
            self::showHtmlError($errorId);
        }
        
        exit;
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool 
    {
        if (!(error_reporting() & $severity)) {
            return false; // Error reporting is turned off
        }
        
        $errorId = uniqid('ERR_');
        
        Logger::error('PHP Error', [
            'error_id' => $errorId,
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line
        ]);
        
        // Convert to exception for consistent handling
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    
    /**
     * Check if request is for API
     */
    private static function isApiRequest(): bool 
    {
        return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }
    
    /**
     * Show JSON error response
     */
    private static function showApiError(string $errorId): void 
    {
        http_response_code(500);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'error' => 'An internal error occurred. Please try again later.',
            'error_id' => $errorId,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Show HTML error page
     */
    private static function showHtmlError(string $errorId): void 
    {
        http_response_code(500);
        
        // In production, show generic error page
        if (!self::isDebugMode()) {
            include __DIR__ . '/../../templates/errors/500.php';
            return;
        }
        
        // In debug mode, show more details
        include __DIR__ . '/../../templates/errors/debug.php';
    }
    
    /**
     * Check if in debug mode
     */
    private static function isDebugMode(): bool 
    {
        return $_ENV['APP_DEBUG'] === 'true' || $_ENV['APP_ENV'] === 'development';
    }
}

// Set error handlers
set_exception_handler([ErrorHandler::class, 'handleException']);
set_error_handler([ErrorHandler::class, 'handleError']);

// Error page template (templates/errors/500.php):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error - Cornerfield</title>
    <link rel="stylesheet" href="/assets/css/cornerfield.min.css">
</head>
<body>
    <div class="cf-error-page">
        <div class="cf-container">
            <div class="cf-error-content">
                <h1 class="cf-error-title">Oops! Something went wrong</h1>
                <p class="cf-error-message">
                    We're experiencing technical difficulties. Our team has been notified 
                    and is working to resolve the issue.
                </p>
                <p class="cf-error-id">
                    Error ID: <code><?= htmlspecialchars($errorId ?? 'N/A') ?></code>
                </p>
                <div class="cf-error-actions">
                    <a href="/" class="cf-btn cf-btn-primary">Return Home</a>
                    <a href="/contact" class="cf-btn cf-btn-secondary">Contact Support</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
```

## 7.3 DEBUG VS PRODUCTION MODES

```php
<?php
// Environment Configuration (.env file)

# Production Settings
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=WARNING
ERROR_DISPLAY=false

# Development Settings  
APP_ENV=development
APP_DEBUG=true
LOG_LEVEL=DEBUG
ERROR_DISPLAY=true

// Config class implementation
class Config 
{
    public static function isProduction(): bool 
    {
        return ($_ENV['APP_ENV'] ?? 'production') === 'production';
    }
    
    public static function isDebug(): bool 
    {
        return $_ENV['APP_DEBUG'] === 'true';
    }
    
    public static function getLogLevel(): string 
    {
        return $_ENV['LOG_LEVEL'] ?? 'WARNING';
    }
    
    public static function shouldDisplayErrors(): bool 
    {
        return $_ENV['ERROR_DISPLAY'] === 'true';
    }
}

// Usage in application
if (Config::isDebug()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
}
```

## 7.4 WHAT TO LOG vs WHAT NOT TO LOG

```php
// LOG THESE (Security & Audit):
Logger::security('Login attempt', ['email' => $email, 'success' => false]);
Logger::financial('Investment created', ['user_id' => $userId, 'amount' => $amount]);
Logger::security('Admin access', ['admin_id' => $adminId, 'action' => 'user_edit']);
Logger::error('Database error', ['query' => 'SELECT...', 'error' => $e->getMessage()]);

// DO NOT LOG THESE (Privacy & Security):
Logger::info('User data', ['password' => $password]);           // FORBIDDEN - passwords
Logger::info('Payment info', ['credit_card' => $ccNumber]);     // FORBIDDEN - financial data
Logger::info('Personal data', ['ssn' => $ssn]);                // FORBIDDEN - PII
Logger::debug('Full request', $_POST);                         // FORBIDDEN - may contain secrets

// SANITIZE BEFORE LOGGING:
$safeData = [
    'email' => $userEmail,
    'amount' => $amount,
    'wallet' => substr($walletAddress, 0, 8) . '...',  // Mask wallet address
    'ip' => $_SERVER['REMOTE_ADDR']
];
Logger::financial('Withdrawal request', $safeData);
```

---

# 8. API/RESPONSE PATTERNS

## 8.1 JSON RESPONSE FORMAT

```php
<?php
declare(strict_types=1);

namespace App\Utils;

class JsonResponse 
{
    /**
     * Send success response
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     */
    public static function success($data = null, int $statusCode = 200): void 
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'timestamp' => date('c'),
            'data' => $data
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Send error response
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $errors Additional error details
     */
    public static function error(string $message, int $statusCode = 400, array $errors = []): void 
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'timestamp' => date('c'),
            'error' => $message
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        // Add request ID for debugging in non-production
        if (!Config::isProduction()) {
            $response['request_id'] = uniqid('REQ_');
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Send paginated response
     * @param array $items Data items
     * @param int $total Total items available
     * @param int $page Current page
     * @param int $limit Items per page
     */
    public static function paginated(array $items, int $total, int $page, int $limit): void 
    {
        $totalPages = (int)ceil($total / $limit);
        
        self::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $total,
                'items_per_page' => $limit,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]);
    }
    
    /**
     * Send validation error response
     * @param array $validationErrors Field validation errors
     */
    public static function validationError(array $validationErrors): void 
    {
        self::error('Validation failed', 422, $validationErrors);
    }
}

// Standard API Response Examples:

// Success with data
JsonResponse::success([
    'investment_id' => 123,
    'amount' => 1000.00,
    'status' => 'active'
]);
// Output:
{
    "success": true,
    "timestamp": "2026-02-10T12:00:00+00:00",
    "data": {
        "investment_id": 123,
        "amount": 1000.00,
        "status": "active"
    }
}

// Error response
JsonResponse::error('Insufficient balance', 400);
// Output:
{
    "success": false,
    "timestamp": "2026-02-10T12:00:00+00:00",
    "error": "Insufficient balance"
}

// Validation error
JsonResponse::validationError([
    'email' => 'Valid email is required',
    'amount' => 'Amount must be at least $50'
]);
// Output:
{
    "success": false,
    "timestamp": "2026-02-10T12:00:00+00:00",
    "error": "Validation failed",
    "errors": {
        "email": "Valid email is required",
        "amount": "Amount must be at least $50"
    }
}
```

## 8.2 HTTP STATUS CODES

```php
// SUCCESS CODES
200 - OK (Successful GET, PUT, PATCH)
201 - Created (Successful POST with new resource)
202 - Accepted (Request accepted, processing async)
204 - No Content (Successful DELETE)

// CLIENT ERROR CODES
400 - Bad Request (Invalid request format/parameters)
401 - Unauthorized (Authentication required)
403 - Forbidden (Authentication valid but no permission)
404 - Not Found (Resource doesn't exist)
409 - Conflict (Resource already exists)
422 - Unprocessable Entity (Validation failed)
429 - Too Many Requests (Rate limit exceeded)

// SERVER ERROR CODES
500 - Internal Server Error (Server-side error)
503 - Service Unavailable (Maintenance mode)

// Usage in controllers:
if (!$user) {
    JsonResponse::error('User not found', 404);
    return;
}

if (!$this->hasPermission($user, 'invest')) {
    JsonResponse::error('Insufficient permissions', 403);
    return;
}

if ($user['balance'] < $amount) {
    JsonResponse::error('Insufficient balance', 400);
    return;
}

if (!RateLimiter::isAllowed($userId, 'invest', 5)) {
    JsonResponse::error('Too many requests', 429);
    return;
}
```

## 8.3 AJAX ENDPOINT PATTERNS

```php
<?php
// public/api/invest.php - Investment API endpoint
declare(strict_types=1);

require_once dirname(__DIR__) . '/autoload.php';

use App\Controllers\InvestmentController;
use App\Utils\JsonResponse;
use App\Utils\Security;
use App\Utils\SessionManager;

// Start secure session
SessionManager::start();

// CORS headers for API (if needed)
header('Access-Control-Allow-Origin: https://cornerfield.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::error('Method not allowed', 405);
}

// Validate Content-Type
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') === false) {
    JsonResponse::error('Content-Type must be application/json', 400);
}

// Rate limiting
$userIp = $_SERVER['REMOTE_ADDR'];
if (!Security::rateLimitCheck($userIp, 'api_invest', 10, 3600)) {
    JsonResponse::error('Rate limit exceeded', 429);
}

// Authentication check
if (!SessionManager::isAuthenticated()) {
    JsonResponse::error('Authentication required', 401);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        JsonResponse::error('Invalid JSON', 400);
    }
    
    // CSRF validation
    if (!Security::validateCsrfToken($input['csrf_token'] ?? '')) {
        JsonResponse::error('Invalid security token', 403);
    }
    
    // Process request through controller
    $controller = new InvestmentController();
    $controller->processApiInvestment($input);
    
} catch (\Exception $e) {
    Logger::error('API error in invest endpoint', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'ip' => $userIp
    ]);
    
    JsonResponse::error('Internal server error', 500);
}
```

```javascript
// Frontend API usage
class CornerFieldAPI {
    constructor() {
        this.baseUrl = '/api';
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    }
    
    async createInvestment(schemaId, amount) {
        try {
            const response = await fetch(`${this.baseUrl}/invest.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    csrf_token: this.csrfToken,
                    schema_id: schemaId,
                    amount: amount
                })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || `HTTP ${response.status}`);
            }
            
            return data;
            
        } catch (error) {
            console.error('Investment API error:', error);
            throw error;
        }
    }
    
    async getTransactions(page = 1, limit = 20) {
        try {
            const response = await fetch(
                `${this.baseUrl}/transactions.php?page=${page}&limit=${limit}`,
                {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || `HTTP ${response.status}`);
            }
            
            return data;
            
        } catch (error) {
            console.error('Transactions API error:', error);
            throw error;
        }
    }
}

// Usage in forms
document.addEventListener('DOMContentLoaded', () => {
    const api = new CornerFieldAPI();
    
    const investmentForm = document.getElementById('investmentForm');
    if (investmentForm) {
        investmentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(investmentForm);
            const schemaId = parseInt(formData.get('schema_id'));
            const amount = parseFloat(formData.get('amount'));
            
            try {
                const result = await api.createInvestment(schemaId, amount);
                
                NotificationManager.show('Investment created successfully!', 'success');
                
                // Redirect to dashboard
                if (result.data?.redirect) {
                    window.location.href = result.data.redirect;
                }
                
            } catch (error) {
                NotificationManager.show(error.message, 'error');
            }
        });
    }
});
```

---

# ENFORCEMENT & COMPLIANCE

## MANDATORY CODE REVIEW CHECKLIST

**Every pull request/code submission MUST pass ALL these checks:**

### ✅ SECURITY CHECKLIST
- [ ] All database queries use prepared statements (NO string concatenation)
- [ ] All user input is validated and sanitized
- [ ] All output is properly escaped (htmlspecialchars)
- [ ] CSRF tokens present and validated on state-changing operations
- [ ] Rate limiting implemented on sensitive endpoints
- [ ] Session security properly configured
- [ ] No hardcoded credentials or secrets
- [ ] Sensitive data encrypted at rest
- [ ] Audit logging for financial operations

### ✅ CODE QUALITY CHECKLIST
- [ ] File starts with `<?php declare(strict_types=1);`
- [ ] Complete namespace and use statements
- [ ] All functions have type hints for parameters and return values
- [ ] Database connection using ONLY Database::getInstance()
- [ ] Error handling with try/catch blocks
- [ ] No duplicate code or files
- [ ] PSR-12 coding standards followed
- [ ] No inline styles or JavaScript

### ✅ DATABASE CHECKLIST
- [ ] Column names match database schema exactly
- [ ] Foreign key relationships respected
- [ ] Financial operations wrapped in transactions
- [ ] No direct queries without proper validation

### ✅ FRONTEND CHECKLIST
- [ ] Uses defined CSS custom properties (--cf-* variables)
- [ ] Follows component patterns from section 4.2
- [ ] No inline styles or scripts
- [ ] Proper form validation and CSRF tokens
- [ ] Responsive design with proper accessibility

### ✅ DOCUMENTATION CHECKLIST
- [ ] Function/method docblocks with @param and @return
- [ ] Complex business logic commented
- [ ] Security considerations documented
- [ ] API endpoints documented with examples

## VIOLATION CONSEQUENCES

### CRITICAL VIOLATIONS (Immediate Rejection)
- SQL injection vulnerabilities
- XSS vulnerabilities  
- Hardcoded credentials
- Missing CSRF protection on financial operations
- No error handling on database operations

### HIGH VIOLATIONS (Must Fix Before Merge)
- Missing input validation
- Inconsistent database patterns
- Duplicate code
- Missing type hints
- Security logging missing

### MEDIUM VIOLATIONS (Fix in Next Sprint)
- Missing documentation
- Code style violations
- Non-optimal patterns

## FINAL NOTES

This document is based on the audit of 131 critical issues found in the Cornerfield platform. **EVERY SINGLE RULE in this document is mandatory** and addresses real security vulnerabilities and code quality issues discovered.

The platform currently has a **45% completion rate** (not 95% as claimed), primarily due to:

1. **27 CRITICAL security vulnerabilities** - mostly addressed in this enforcer
2. **Autoloader failure** preventing application execution  
3. **Hardcoded credentials** exposing admin access
4. **Missing CSRF protection** on financial operations
5. **SQL injection vulnerabilities** in multiple locations
6. **Incomplete rate limiting** allowing abuse
7. **Inconsistent architecture patterns** throughout codebase

**This ENFORCER document must be followed religiously for every line of code written to ensure enterprise-grade security for the financial platform.**

---

**Document Version:** 1.0  
**Last Updated:** February 10, 2026  
**Status:** MANDATORY - NO EXCEPTIONS  
**Review Required:** Every 90 days or after security incidents  

**Remember: This is a FINANCIAL platform handling real money. Security is not optional.**