<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Models/UserModel.php
 * Purpose: Complete user model with authentication, balance operations, and referral system
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Models;

use PDO;
use PDOException;
use InvalidArgumentException;
use App\Models\BaseModel;
use App\Utils\Security;
use App\Utils\Validator;

class UserModel extends BaseModel
{
    protected string $table = 'users';
    
    protected array $fillable = [
        'email',
        'username', 
        'password_hash',
        'first_name',
        'last_name',
        'phone',
        'country',
        'balance',
        'locked_balance',
        'bonus_balance',
        'total_invested',
        'total_withdrawn',
        'total_earned',
        'referral_code',
        'referred_by',
        'kyc_status',
        'kyc_document_path',
        'is_active',
        'is_admin',
        'email_verified',
        'email_verification_token',
        'password_reset_token',
        'password_reset_expires',
        'two_factor_secret',
        'two_factor_enabled',
        'login_attempts',
        'last_login_attempt',
        'last_login'
    ];

    /**
     * Find user by email address
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array 
    {
        if (!Validator::isValidEmail($email)) {
            throw new InvalidArgumentException('Invalid email address');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, email, username, password_hash, first_name, last_name, 
                        balance, locked_balance, bonus_balance, total_invested, 
                        total_withdrawn, total_earned, referral_code, referred_by,
                        kyc_status, is_active, is_admin, email_verified, 
                        two_factor_enabled, login_attempts, last_login_attempt,
                        last_login, created_at, updated_at
                 FROM {$this->table} 
                 WHERE email = ? AND is_active = 1"
            );
            
            $stmt->execute([$email]);
            $result = $stmt->fetch();
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch user by email {$email}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find user by username
     * @param string $username
     * @return array|null
     */
    public function findByUsername(string $username): ?array 
    {
        if (!Validator::isValidUsername($username)) {
            throw new InvalidArgumentException('Invalid username format');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, email, username, password_hash, first_name, last_name, 
                        balance, locked_balance, bonus_balance, total_invested, 
                        total_withdrawn, total_earned, referral_code, referred_by,
                        kyc_status, is_active, is_admin, email_verified, 
                        two_factor_enabled, login_attempts, last_login_attempt,
                        last_login, created_at, updated_at
                 FROM {$this->table} 
                 WHERE username = ? AND is_active = 1"
            );
            
            $stmt->execute([$username]);
            $result = $stmt->fetch();
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch user by username {$username}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find user by referral code
     * @param string $referralCode
     * @return array|null
     */
    public function findByReferralCode(string $referralCode): ?array 
    {
        if (!Validator::isValidReferralCode($referralCode)) {
            throw new InvalidArgumentException('Invalid referral code format');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, email, username, first_name, last_name, referral_code, created_at
                 FROM {$this->table} 
                 WHERE referral_code = ? AND is_active = 1"
            );
            
            $stmt->execute([strtoupper($referralCode)]);
            $result = $stmt->fetch();
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch user by referral code {$referralCode}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create new user with complete validation and referral tracking
     * @param array $userData
     * @return array
     */
    public function createUser(array $userData): array 
    {
        // Validate required fields
        $errors = $this->validateUserData($userData);
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors
            ];
        }

        try {
            $this->db->beginTransaction();

            // Check if email or username already exists
            if ($this->emailExists($userData['email'])) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Email address is already registered'
                ];
            }

            if ($this->usernameExists($userData['username'])) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Username is already taken'
                ];
            }

            // Generate unique referral code
            $referralCode = $this->generateReferralCode();

            // Handle referral tracking
            $referrerId = null;
            if (!empty($userData['referral_code'])) {
                $referrer = $this->findByReferralCode($userData['referral_code']);
                if ($referrer) {
                    $referrerId = (int)$referrer['id'];
                }
            }

            // Hash password securely
            $passwordHash = Security::hashPassword($userData['password']);

            // Get welcome bonus from environment or default
            $welcomeBonus = (float)($_ENV['SIGNUP_BONUS'] ?? 50.0);

            // Create user record
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (
                    email, username, password_hash, first_name, last_name, 
                    phone, country, balance, bonus_balance, referral_code, 
                    referred_by, email_verified, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );

            $success = $stmt->execute([
                $userData['email'],
                $userData['username'],
                $passwordHash,
                $userData['first_name'] ?? null,
                $userData['last_name'] ?? null,
                $userData['phone'] ?? null,
                $userData['country'] ?? null,
                0.00000000, // Starting balance
                $welcomeBonus, // Welcome bonus
                $referralCode,
                $referrerId,
                0 // Email not verified initially
            ]);

            if (!$success) {
                throw new PDOException('Failed to create user record');
            }

            $userId = (int)$this->db->lastInsertId();

            // Create referral relationship if applicable
            if ($referrerId) {
                $this->createReferralRelationship($referrerId, $userId);
            }

            // Apply welcome bonus transaction record
            if ($welcomeBonus > 0) {
                $this->recordWelcomeBonus($userId, $welcomeBonus);
            }

            $this->db->commit();

            // Log user creation for audit
            Security::logAudit($userId, 'user_created', 'users', $userId, null, [
                'email' => $userData['email'],
                'username' => $userData['username'],
                'referred_by' => $referrerId,
                'welcome_bonus' => $welcomeBonus
            ]);

            return [
                'success' => true,
                'user_id' => $userId,
                'referral_code' => $referralCode
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
     * Update user profile information
     * @param int $userId
     * @param array $profileData
     * @return array
     */
    public function updateProfile(int $userId, array $profileData): array 
    {
        if ($userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid user ID'
            ];
        }

        $allowedFields = ['first_name', 'last_name', 'phone', 'country'];
        $updateData = [];

        foreach ($allowedFields as $field) {
            if (isset($profileData[$field])) {
                if ($field === 'phone' && !empty($profileData[$field])) {
                    if (!Validator::isValidPhone($profileData[$field])) {
                        return [
                            'success' => false,
                            'error' => 'Invalid phone number format'
                        ];
                    }
                }
                $updateData[$field] = Validator::sanitizeString($profileData[$field], 100);
            }
        }

        if (empty($updateData)) {
            return [
                'success' => false,
                'error' => 'No valid data to update'
            ];
        }

        try {
            $setClause = [];
            $params = [];
            
            foreach ($updateData as $field => $value) {
                $setClause[] = "{$field} = ?";
                $params[] = $value;
            }
            
            $setClause[] = "updated_at = NOW()";
            $params[] = $userId;

            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET " . implode(', ', $setClause) . " WHERE id = ?"
            );

            $success = $stmt->execute($params);

            if ($success && $stmt->rowCount() > 0) {
                Security::logAudit($userId, 'profile_updated', 'users', $userId, null, $updateData);
                return [
                    'success' => true,
                    'message' => 'Profile updated successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'No changes were made'
            ];

        } catch (PDOException $e) {
            error_log("Profile update failed for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to update profile. Please try again.'
            ];
        }
    }

    /**
     * Update user password with proper verification
     * @param int $userId
     * @param string $newPassword
     * @return array
     */
    public function updatePassword(int $userId, string $newPassword): array 
    {
        if ($userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid user ID'
            ];
        }

        if (!Validator::isValidPassword($newPassword)) {
            return [
                'success' => false,
                'error' => 'Password must be at least 8 characters with mixed case, numbers, and symbols'
            ];
        }

        try {
            $passwordHash = Security::hashPassword($newPassword);

            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET password_hash = ?, updated_at = NOW() WHERE id = ?"
            );

            $success = $stmt->execute([$passwordHash, $userId]);

            if ($success && $stmt->rowCount() > 0) {
                Security::logAudit($userId, 'password_changed', 'users', $userId);
                return [
                    'success' => true,
                    'message' => 'Password updated successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to update password'
            ];

        } catch (PDOException $e) {
            error_log("Password update failed for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to update password. Please try again.'
            ];
        }
    }

    /**
     * Update user balance using atomic SQL operations
     * @param int $userId
     * @param float $amount Positive to add, negative to subtract
     * @param string $type Type of balance operation
     * @return array
     */
    public function updateBalance(int $userId, float $amount, string $type = 'balance'): array 
    {
        if ($userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid user ID'
            ];
        }

        if (!in_array($type, ['balance', 'locked_balance', 'bonus_balance'])) {
            return [
                'success' => false,
                'error' => 'Invalid balance type'
            ];
        }

        try {
            // For subtractions, check current balance first
            if ($amount < 0) {
                $stmt = $this->db->prepare("SELECT {$type} FROM {$this->table} WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if (!$user) {
                    return [
                        'success' => false,
                        'error' => 'User not found'
                    ];
                }

                if ($user[$type] < abs($amount)) {
                    return [
                        'success' => false,
                        'error' => 'Insufficient balance'
                    ];
                }
            }

            // Atomic balance update using SQL arithmetic
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET {$type} = {$type} + ?, updated_at = NOW() 
                 WHERE id = ?"
            );

            $success = $stmt->execute([$amount, $userId]);

            if ($success && $stmt->rowCount() > 0) {
                Security::logAudit($userId, 'balance_updated', 'users', $userId, null, [
                    'type' => $type,
                    'amount' => $amount,
                    'operation' => $amount >= 0 ? 'credit' : 'debit'
                ]);

                return [
                    'success' => true,
                    'message' => 'Balance updated successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to update balance'
            ];

        } catch (PDOException $e) {
            error_log("Balance update failed for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to update balance. Please try again.'
            ];
        }
    }

    /**
     * Verify user email
     * @param int $userId
     * @return array
     */
    public function verifyEmail(int $userId): array 
    {
        if ($userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid user ID'
            ];
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET email_verified = 1, email_verification_token = NULL, updated_at = NOW() 
                 WHERE id = ?"
            );

            $success = $stmt->execute([$userId]);

            if ($success && $stmt->rowCount() > 0) {
                Security::logAudit($userId, 'email_verified', 'users', $userId);
                return [
                    'success' => true,
                    'message' => 'Email verified successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to verify email'
            ];

        } catch (PDOException $e) {
            error_log("Email verification failed for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to verify email. Please try again.'
            ];
        }
    }

    /**
     * Record login attempt
     * @param int $userId
     * @return bool
     */
    public function recordLoginAttempt(int $userId): bool 
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET login_attempts = login_attempts + 1, last_login_attempt = NOW() 
                 WHERE id = ?"
            );

            return $stmt->execute([$userId]);

        } catch (PDOException $e) {
            error_log("Failed to record login attempt for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset login attempts after successful login
     * @param int $userId
     * @return bool
     */
    public function resetLoginAttempts(int $userId): bool 
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET login_attempts = 0, last_login = NOW(), updated_at = NOW() 
                 WHERE id = ?"
            );

            return $stmt->execute([$userId]);

        } catch (PDOException $e) {
            error_log("Failed to reset login attempts for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment login attempts
     * @param string $email
     * @return bool
     */
    public function incrementLoginAttempts(string $email): bool 
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET login_attempts = login_attempts + 1, last_login_attempt = NOW() 
                 WHERE email = ?"
            );

            return $stmt->execute([$email]);

        } catch (PDOException $e) {
            error_log("Failed to increment login attempts for email {$email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user account is locked due to too many login attempts
     * @param string $email
     * @return bool
     */
    public function isLocked(string $email): bool 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT login_attempts, last_login_attempt 
                 FROM {$this->table} 
                 WHERE email = ?"
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return false;
            }

            $maxAttempts = (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5);
            $lockoutDuration = (int)($_ENV['ACCOUNT_LOCKOUT_DURATION'] ?? 900); // 15 minutes

            if ($user['login_attempts'] >= $maxAttempts) {
                if ($user['last_login_attempt']) {
                    $lastAttempt = strtotime($user['last_login_attempt']);
                    $timeSinceLastAttempt = time() - $lastAttempt;

                    // If lockout duration has passed, reset attempts
                    if ($timeSinceLastAttempt > $lockoutDuration) {
                        $this->resetLoginAttempts((int)$this->findByEmail($email)['id']);
                        return false;
                    }

                    return true;
                }
            }

            return false;

        } catch (PDOException $e) {
            error_log("Failed to check if user is locked for email {$email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if email exists
     * @param string $email
     * @return bool
     */
    private function emailExists(string $email): bool 
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch() !== false;

        } catch (PDOException $e) {
            error_log("Failed to check email existence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if username exists
     * @param string $username
     * @return bool
     */
    private function usernameExists(string $username): bool 
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch() !== false;

        } catch (PDOException $e) {
            error_log("Failed to check username existence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate unique referral code
     * @return string
     */
    private function generateReferralCode(): string 
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(4)));
            
            $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE referral_code = ?");
            $stmt->execute([$code]);
            
        } while ($stmt->fetch());

        return $code;
    }

    /**
     * Create referral relationship
     * @param int $referrerId
     * @param int $referredId
     * @return bool
     */
    private function createReferralRelationship(int $referrerId, int $referredId): bool 
    {
        try {
            $commissionRate = (float)($_ENV['REFERRAL_COMMISSION_RATE'] ?? 5.0);

            $stmt = $this->db->prepare(
                "INSERT INTO referrals (referrer_id, referred_id, level, commission_rate, 
                 total_earned, status, created_at) 
                 VALUES (?, ?, 1, ?, 0.00000000, 'active', NOW())"
            );

            return $stmt->execute([$referrerId, $referredId, $commissionRate]);

        } catch (PDOException $e) {
            error_log("Failed to create referral relationship: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Record welcome bonus transaction
     * @param int $userId
     * @param float $amount
     * @return bool
     */
    private function recordWelcomeBonus(int $userId, float $amount): bool 
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO transactions (user_id, type, amount, fee, net_amount, status, 
                 payment_method, currency, description, created_at, updated_at) 
                 VALUES (?, 'bonus', ?, 0.00000000, ?, 'completed', 'system', 'USD', 
                 'Welcome signup bonus', NOW(), NOW())"
            );

            return $stmt->execute([$userId, $amount, $amount]);

        } catch (PDOException $e) {
            error_log("Failed to record welcome bonus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate user registration data
     * @param array $data
     * @return array
     */
    private function validateUserData(array $data): array 
    {
        $errors = [];

        if (!isset($data['email']) || !Validator::isValidEmail($data['email'])) {
            $errors[] = 'Valid email address is required';
        }

        if (!isset($data['username']) || !Validator::isValidUsername($data['username'])) {
            $errors[] = 'Username must be 3-20 characters, alphanumeric and underscores only';
        }

        if (!isset($data['password']) || !Validator::isValidPassword($data['password'])) {
            $errors[] = 'Password must be at least 8 characters with mixed case, numbers, and symbols';
        }

        if (isset($data['first_name']) && !empty($data['first_name'])) {
            if (!Validator::isValidName($data['first_name'])) {
                $errors[] = 'First name contains invalid characters';
            }
        }

        if (isset($data['last_name']) && !empty($data['last_name'])) {
            if (!Validator::isValidName($data['last_name'])) {
                $errors[] = 'Last name contains invalid characters';
            }
        }

        if (isset($data['phone']) && !empty($data['phone'])) {
            if (!Validator::isValidPhone($data['phone'])) {
                $errors[] = 'Invalid phone number format';
            }
        }

        return $errors;
    }
}