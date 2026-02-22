<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Utils/Validator.php
 * Purpose: Input validation class with financial and security validations
 * Security Level: PUBLIC
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Utils;

class Validator 
{
    /**
     * Validate required field
     */
    public static function required(mixed $value): bool 
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        
        if (is_array($value)) {
            return !empty($value);
        }
        
        return $value !== null && $value !== '';
    }
    
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
     * Validate username (alphanumeric, underscore, 3-20 chars)
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
        // At least 8 characters
        if (strlen($password) < 8) {
            return false;
        }
        
        // Must contain uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // Must contain lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        // Must contain number
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        // Must contain special character
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate name (letters, spaces, hyphens)
     */
    public static function isValidName(string $name): bool 
    {
        if (empty($name) || strlen($name) > 100) {
            return false;
        }
        
        return preg_match('/^[a-zA-Z\s\-\'\.]{2,100}$/', $name) === 1;
    }
    
    /**
     * Validate phone number
     */
    public static function isValidPhone(string $phone): bool 
    {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Should be 7-15 digits
        return strlen($cleaned) >= 7 && strlen($cleaned) <= 15;
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
        if (!is_string($value)) {
            return '';
        }
        
        $cleaned = trim($value);
        return substr($cleaned, 0, $maxLength);
    }
    
    /**
     * Validate numeric value
     */
    public static function isNumeric(mixed $value): bool 
    {
        return is_numeric($value);
    }
    
    /**
     * Validate value is within range
     */
    public static function inRange(float $value, float $min, float $max): bool 
    {
        return $value >= $min && $value <= $max;
    }
    
    /**
     * Validate minimum length
     */
    public static function minLength(string $value, int $minLength): bool 
    {
        return strlen($value) >= $minLength;
    }
    
    /**
     * Validate maximum length
     */
    public static function maxLength(string $value, int $maxLength): bool 
    {
        return strlen($value) <= $maxLength;
    }
    
    /**
     * Validate value is in array
     */
    public static function inArray(mixed $value, array $allowedValues): bool 
    {
        return in_array($value, $allowedValues, true);
    }
    
    /**
     * Validate financial amount (positive, max 8 decimals)
     */
    public static function isValidAmount(float $amount): bool 
    {
        if ($amount <= 0) {
            return false;
        }
        
        // Check for reasonable maximum (prevent overflow)
        if ($amount > 999999999.99999999) {
            return false;
        }
        
        // check decimal places (max 8 for crypto precision)
        $decimalPart = strrchr((string)$amount, '.');
        $decimalPlaces = $decimalPart !== false ? strlen(substr($decimalPart, 1)) : 0;
        if ($decimalPlaces > 8) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate investment amount against schema limits
     */
    public static function isValidInvestmentAmount(float $amount, float $min, float $max): bool 
    {
        if (!self::isValidAmount($amount)) {
            return false;
        }
        
        return $amount >= $min && $amount <= $max;
    }
    
    /**
     * Validate cryptocurrency wallet address
     */
    public static function isValidWalletAddress(string $address, string $currency): bool 
    {
        $address = trim($address);
        
        switch (strtoupper($currency)) {
            case 'BTC':
                // Bitcoin address patterns
                return preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address) === 1 ||
                       preg_match('/^bc1[a-z0-9]{39,59}$/', $address) === 1;
            
            case 'ETH':
            case 'USDT':
                // Ethereum address pattern
                return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
                
            case 'LTC':
                // Litecoin address patterns
                return preg_match('/^[LM3][a-km-zA-HJ-NP-Z1-9]{26,33}$/', $address) === 1;
                
            case 'XRP':
                // Ripple address pattern
                return preg_match('/^r[1-9A-HJ-NP-Za-km-z]{25,34}$/', $address) === 1;
                
            default:
                // Generic crypto address validation (26-62 chars, alphanumeric)
                return preg_match('/^[a-zA-Z0-9]{26,62}$/', $address) === 1;
        }
    }
    
    /**
     * Validate network for cryptocurrency
     */
    public static function isValidNetwork(string $network, string $currency): bool 
    {
        $validNetworks = [
            'BTC' => ['BTC'],
            'ETH' => ['ERC20'],
            'USDT' => ['ERC20', 'TRC20', 'BEP20'],
            'LTC' => ['LTC'],
            'XRP' => ['XRP']
        ];
        
        $currency = strtoupper($currency);
        
        if (!isset($validNetworks[$currency])) {
            return false;
        }
        
        return in_array(strtoupper($network), $validNetworks[$currency]);
    }
    
    /**
     * Validate referral code format
     */
    public static function isValidReferralCode(string $code): bool 
    {
        // 8 character alphanumeric code
        return preg_match('/^[A-Z0-9]{8}$/', strtoupper($code)) === 1;
    }
    
    /**
     * Validate URL
     */
    public static function isValidUrl(string $url): bool 
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validate date format (YYYY-MM-DD)
     */
    public static function isValidDate(string $date): bool 
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Validate enum value
     */
    public static function isValidEnum(string $value, array $allowedValues): bool 
    {
        return in_array($value, $allowedValues, true);
    }
    
    /**
     * Validate transaction type
     */
    public static function isValidTransactionType(string $type): bool 
    {
        $validTypes = [
            'deposit',
            'withdrawal', 
            'investment',
            'profit',
            'bonus',
            'referral',
            'principal_return'
        ];
        
        return in_array($type, $validTypes, true);
    }
    
    /**
     * Validate investment status
     */
    public static function isValidInvestmentStatus(string $status): bool 
    {
        $validStatuses = ['active', 'completed', 'cancelled'];
        
        return in_array($status, $validStatuses, true);
    }
    
    /**
     * Validate KYC status
     */
    public static function isValidKycStatus(string $status): bool 
    {
        $validStatuses = ['pending', 'approved', 'rejected'];
        
        return in_array($status, $validStatuses, true);
    }
    
    /**
     * Comprehensive data validation
     */
    public static function validateData(array $data, array $rules): array 
    {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $rule => $ruleValue) {
                switch ($rule) {
                    case 'required':
                        if ($ruleValue && !self::required($value)) {
                            $errors[$field] = ucfirst($field) . ' is required';
                        }
                        break;
                        
                    case 'email':
                        if ($value && !self::isValidEmail((string)$value)) {
                            $errors[$field] = ucfirst($field) . ' must be a valid email address';
                        }
                        break;
                        
                    case 'min':
                        if ($value && is_numeric($value) && $value < $ruleValue) {
                            $errors[$field] = ucfirst($field) . " must be at least {$ruleValue}";
                        }
                        break;
                        
                    case 'max':
                        if ($value && is_numeric($value) && $value > $ruleValue) {
                            $errors[$field] = ucfirst($field) . " must not exceed {$ruleValue}";
                        }
                        break;
                        
                    case 'min_length':
                        if ($value && !self::minLength((string)$value, $ruleValue)) {
                            $errors[$field] = ucfirst($field) . " must be at least {$ruleValue} characters";
                        }
                        break;
                        
                    case 'max_length':
                        if ($value && !self::maxLength((string)$value, $ruleValue)) {
                            $errors[$field] = ucfirst($field) . " must not exceed {$ruleValue} characters";
                        }
                        break;
                        
                    case 'in':
                        if ($value && !self::inArray($value, $ruleValue)) {
                            $errors[$field] = ucfirst($field) . ' has an invalid value';
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }
}