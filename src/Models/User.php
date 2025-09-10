<?php
namespace App\Models;

use App\Config\Database;
use Exception;

class User
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    /**
     * Find user by email
     */
    public function findByEmail($email)
    {
        try {
            return $this->db->fetchOne("
                SELECT * FROM users 
                WHERE email = ? AND is_active = 1
            ", [$email]);
        } catch (Exception $e) {
            error_log("Error finding user by email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find user by ID
     */
    public function findById($userId)
    {
        try {
            return $this->db->fetchOne("
                SELECT * FROM users 
                WHERE id = ?
            ", [$userId]);
        } catch (Exception $e) {
            error_log("Error finding user by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find user by username
     */
    public function findByUsername($username)
    {
        try {
            return $this->db->fetchOne("
                SELECT * FROM users 
                WHERE username = ? AND is_active = 1
            ", [$username]);
        } catch (Exception $e) {
            error_log("Error finding user by username: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new user
     */
    public function create($userData)
    {
        try {
            return $this->db->insert('users', [
                'email' => $userData['email'],
                'username' => $userData['username'],
                'password_hash' => $userData['password_hash'],
                'first_name' => $userData['first_name'] ?? null,
                'last_name' => $userData['last_name'] ?? null,
                'phone' => $userData['phone'] ?? null,
                'country' => $userData['country'] ?? null,
                'referral_code' => $userData['referral_code'],
                'referred_by' => $userData['referred_by'] ?? null,
                'balance' => $userData['balance'] ?? 0,
                'bonus_balance' => $userData['bonus_balance'] ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user balance
     */
    public function updateBalance($userId, $newBalance)
    {
        try {
            return $this->db->update('users', [
                'balance' => $newBalance,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$userId]) > 0;
        } catch (Exception $e) {
            error_log("Error updating user balance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add to user balance
     */
    public function addToBalance($userId, $amount)
    {
        try {
            $user = $this->findById($userId);
            if (!$user)
                return false;

            $newBalance = $user['balance'] + $amount;
            return $this->updateBalance($userId, $newBalance);
        } catch (Exception $e) {
            error_log("Error adding to user balance: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Subtract from user balance
     */
    public function subtractFromBalance($userId, $amount)
    {
        try {
            $user = $this->findById($userId);
            if (!$user || $user['balance'] < $amount)
                return false;

            $newBalance = $user['balance'] - $amount;
            return $this->updateBalance($userId, $newBalance);
        } catch (Exception $e) {
            error_log("Error subtracting from user balance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update total invested amount
     */
    public function updateTotalInvested($userId, $totalInvested)
    {
        try {
            return $this->db->update('users', [
                'total_invested' => $totalInvested,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$userId]) > 0;
        } catch (Exception $e) {
            error_log("Error updating total invested: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add to total invested amount
     */
    public function addToTotalInvested($userId, $amount)
    {
        try {
            $user = $this->findById($userId);
            if (!$user)
                return false;

            $newTotal = ($user['total_invested'] ?? 0) + $amount;
            return $this->updateTotalInvested($userId, $newTotal);
        } catch (Exception $e) {
            error_log("Error adding to total invested: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update total earned amount
     */
    public function updateTotalEarned($userId, $totalEarned)
    {
        try {
            return $this->db->update('users', [
                'total_earned' => $totalEarned,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$userId]) > 0;
        } catch (Exception $e) {
            error_log("Error updating total earned: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add to total earned amount
     */
    public function addToTotalEarned($userId, $amount)
    {
        try {
            $user = $this->findById($userId);
            if (!$user)
                return false;

            $newTotal = ($user['total_earned'] ?? 0) + $amount;
            return $this->updateTotalEarned($userId, $newTotal);
        } catch (Exception $e) {
            error_log("Error adding to total earned: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update total withdrawn amount
     */
    public function addToTotalWithdrawn($userId, $amount)
    {
        try {
            $user = $this->findById($userId);
            if (!$user)
                return false;

            $newTotal = ($user['total_withdrawn'] ?? 0) + $amount;
            return $this->db->update('users', [
                'total_withdrawn' => $newTotal,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$userId]) > 0;
        } catch (Exception $e) {
            error_log("Error adding to total withdrawn: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user last login
     */
    public function updateLastLogin($userId)
    {
        try {
            return $this->db->update('users', [
                'last_login' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$userId]) > 0;
        } catch (Exception $e) {
            error_log("Error updating last login: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user dashboard statistics
     */
    public function getUserStats($userId)
    {
        try {
            return $this->db->fetchOne("
               SELECT 
                   u.balance,
                   u.bonus_balance,
                   u.total_invested,
                   u.total_earned,
                   u.total_withdrawn,
                   u.referral_code,
                   COUNT(DISTINCT i.id) as total_investments,
                   COUNT(DISTINCT CASE WHEN i.status = 'active' THEN i.id END) as active_investments,
                   COUNT(DISTINCT r.id) as total_referrals,
                   COALESCE(SUM(CASE WHEN i.status = 'active' THEN i.invest_amount END), 0) as active_investment_amount
               FROM users u
               LEFT JOIN investments i ON u.id = i.user_id
               LEFT JOIN referrals r ON u.id = r.referrer_id
               WHERE u.id = ?
               GROUP BY u.id
           ", [$userId]);
        } catch (Exception $e) {
            error_log("Error getting user stats: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if email exists
     */
    public function emailExists($email)
    {
        try {
            $result = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
            return $result !== null;
        } catch (Exception $e) {
            error_log("Error checking email existence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username)
    {
        try {
            $result = $this->db->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
            return $result !== null;
        } catch (Exception $e) {
            error_log("Error checking username existence: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Apply signup bonus to new user
     */
    public function applySignupBonus($userId)
    {
        try {
            // Get signup bonus amount from settings
            $settingsModel = new AdminSettings($this->db);
            $signupBonus = $settingsModel->getSetting('signup_bonus', 500);

            if ($signupBonus > 0) {
                $this->db->beginTransaction();

                // Update user balance
                $this->db->update('users', [
                    'balance' => $this->db->raw('balance + ' . $signupBonus)
                ], 'id = ?', [$userId]);

                // Create transaction record
                $transactionId = $this->db->insert('transactions', [
                    'user_id' => $userId,
                    'type' => 'bonus',
                    'amount' => $signupBonus,
                    'fee' => 0,
                    'net_amount' => $signupBonus,
                    'status' => 'completed',
                    'payment_method' => 'system',
                    'description' => 'Welcome signup bonus',
                    'reference_id' => 'SIGNUP_' . time() . '_' . $userId
                ]);

                $this->db->commit();
                return true;
            }

            return false;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Apply signup bonus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate unique referral code
     */
    public function generateReferralCode($length = 8)
    {
        do {
            $code = strtoupper(substr(md5(uniqid(rand(), true)), 0, $length));
        } while ($this->referralCodeExists($code));

        return $code;
    }

    /**
     * Check if referral code exists
     */
    public function referralCodeExists($code)
    {
        try {
            $result = $this->db->fetchOne("SELECT id FROM users WHERE referral_code = ?", [$code]);
            return $result !== null;
        } catch (Exception $e) {
            error_log("Error checking referral code existence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find user by referral code
     */
    public function findByReferralCode($referralCode)
    {
        try {
            return $this->db->fetchOne("
               SELECT id, username, email FROM users 
               WHERE referral_code = ? AND is_active = 1
           ", [$referralCode]);
        } catch (Exception $e) {
            error_log("Error finding user by referral code: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $profileData)
    {
        try {
            $updateData = [];
            $allowedFields = ['first_name', 'last_name', 'phone', 'country'];

            foreach ($allowedFields as $field) {
                if (isset($profileData[$field])) {
                    $updateData[$field] = $profileData[$field];
                }
            }

            if (empty($updateData)) {
                return false;
            }

            $updateData['updated_at'] = date('Y-m-d H:i:s');

            return $this->db->update('users', $updateData, 'id = ?', [$userId]) > 0;
        } catch (Exception $e) {
            error_log("Error updating user profile: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user password
     */
    public function updatePassword($userId, $newPasswordHash)
    {
        try {
            return $this->db->update('users', [
                'password_hash' => $newPasswordHash,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$userId]) > 0;
        } catch (Exception $e) {
            error_log("Error updating user password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user referrals
     */
    public function getUserReferrals($userId)
    {
        try {
            return $this->db->fetchAll("
               SELECT 
                   u.id,
                   u.username,
                   u.email,
                   u.first_name,
                   u.last_name,
                   u.total_invested,
                   u.created_at,
                   r.commission_rate,
                   r.total_earned as referral_earnings
               FROM users u
               JOIN referrals r ON u.id = r.referred_id
               WHERE r.referrer_id = ?
               ORDER BY u.created_at DESC
           ", [$userId]);
        } catch (Exception $e) {
            error_log("Error getting user referrals: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Admin: Get all users with pagination
     */
    public function getAllUsers($page = 1, $limit = 50, $search = '')
    {
        try {
            $offset = ($page - 1) * $limit;

            $sql = "
               SELECT 
                   id, username, email, first_name, last_name, 
                   balance, total_invested, total_earned, 
                   is_active, created_at
               FROM users
           ";

            $params = [];

            if (!empty($search)) {
                $sql .= " WHERE username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?";
                $searchTerm = "%{$search}%";
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            }

            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error fetching all users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Admin: Get user statistics
     */
    public function getUserStatistics()
    {
        try {
            return $this->db->fetchOne("
               SELECT 
                   COUNT(*) as total_users,
                   COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_users,
                   COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_users,
                   SUM(balance) as total_balance,
                   SUM(total_invested) as total_invested,
                   SUM(total_earned) as total_earned,
                   AVG(balance) as average_balance
               FROM users
           ") ?: [
                'total_users' => 0,
                'active_users' => 0,
                'inactive_users' => 0,
                'total_balance' => 0,
                'total_invested' => 0,
                'total_earned' => 0,
                'average_balance' => 0
            ];
        } catch (Exception $e) {
            error_log("Error fetching user statistics: " . $e->getMessage());
            return [
                'total_users' => 0,
                'active_users' => 0,
                'inactive_users' => 0,
                'total_balance' => 0,
                'total_invested' => 0,
                'total_earned' => 0,
                'average_balance' => 0
            ];
        }
    }

    /**
     * Admin: Update user status
     */
    public function updateUserStatus($userId, $status)
    {
        try {
            return $this->db->update('users', [
                'is_active' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$userId]) > 0;
        } catch (Exception $e) {
            error_log("Error updating user status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user data (generic update method)
     */
    public function update($userId, $updateData)
    {
        try {
            $finalUpdateData = [];
            $allowedFields = [
                'email',
                'username',
                'first_name',
                'last_name',
                'phone',
                'country',
                'balance',
                'bonus_balance',
                'total_invested',
                'total_withdrawn',
                'total_earned',
                'is_active',
                'email_verified',
                'kyc_status'
            ];

            foreach ($allowedFields as $field) {
                if (isset($updateData[$field])) {
                    $finalUpdateData[$field] = $updateData[$field];
                }
            }

            if (empty($finalUpdateData)) {
                return false;
            }

            $finalUpdateData['updated_at'] = date('Y-m-d H:i:s');

            return $this->db->update('users', $finalUpdateData, 'id = ?', [$userId]) > 0;
        } catch (Exception $e) {
            error_log("Error updating user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user investment history
     */
    public function getInvestmentHistory($userId, $limit = 10)
    {
        try {
            return $this->db->fetchAll("
               SELECT 
                   i.id,
                   i.invest_amount,
                   i.total_profit_amount,
                   i.status,
                   i.created_at,
                   i.next_profit_time,
                   s.name as plan_name,
                   s.daily_rate,
                   s.duration_days,
                   DATEDIFF(DATE_ADD(i.created_at, INTERVAL s.duration_days DAY), NOW()) as days_remaining
               FROM investments i
               JOIN investment_schemas s ON i.schema_id = s.id
               WHERE i.user_id = ?
               ORDER BY i.created_at DESC
               LIMIT ?
           ", [$userId, $limit]);
        } catch (Exception $e) {
            error_log("Error fetching user investment history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user by ID with additional data
     */
    public function getUserWithStats($userId)
    {
        try {
            return $this->db->fetchOne("
               SELECT 
                   u.*,
                   COUNT(DISTINCT i.id) as total_investments,
                   COUNT(DISTINCT CASE WHEN i.status = 'active' THEN i.id END) as active_investments,
                   COUNT(DISTINCT r.id) as total_referrals,
                   COALESCE(SUM(CASE WHEN i.status = 'active' THEN i.invest_amount END), 0) as active_investment_amount
               FROM users u
               LEFT JOIN investments i ON u.id = i.user_id
               LEFT JOIN referrals r ON u.id = r.referrer_id
               WHERE u.id = ?
               GROUP BY u.id
           ", [$userId]);
        } catch (Exception $e) {
            error_log("Error fetching user with stats: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create referral relationship
     */
    public function createReferral($referrerId, $referredId, $commissionRate = null) {
        try {
            // Get dynamic referral rate from settings if not provided
            if ($commissionRate === null) {
                $adminSettingsModel = new AdminSettings($this->db);
                $commissionRate = $adminSettingsModel->getSetting('referral_bonus_rate', 5);
            }
            
            $this->db->insert('referrals', [
                'referrer_id' => $referrerId,
                'referred_id' => $referredId,
                'level' => 1,
                'commission_rate' => $commissionRate,
                'total_earned' => 0,
                'status' => 'active'
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("User createReferral error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's referral statistics
     */
    public function getReferralStats($userId)
    {
        try {
            return $this->db->fetchOne("
               SELECT 
                   COUNT(*) as total_referrals,
                   SUM(total_earned) as total_commission_earned,
                   COUNT(CASE WHEN status = 'active' THEN 1 END) as active_referrals
               FROM referrals 
               WHERE referrer_id = ?
           ", [$userId]) ?: [
                'total_referrals' => 0,
                'total_commission_earned' => 0,
                'active_referrals' => 0
            ];
        } catch (Exception $e) {
            error_log("Error fetching referral stats: " . $e->getMessage());
            return [
                'total_referrals' => 0,
                'total_commission_earned' => 0,
                'active_referrals' => 0
            ];
        }
    }

    /**
     * Update referral commission
     */
    public function updateReferralCommission($referrerId, $referredId, $amount)
    {
        try {
            // Get current commission
            $referral = $this->db->fetchOne("
               SELECT total_earned FROM referrals 
               WHERE referrer_id = ? AND referred_id = ?
           ", [$referrerId, $referredId]);

            if ($referral) {
                $newTotal = ($referral['total_earned'] ?? 0) + $amount;
                return $this->db->update('referrals', [
                    'total_earned' => $newTotal
                ], 'referrer_id = ? AND referred_id = ?', [$referrerId, $referredId]) > 0;
            }

            return false;
        } catch (Exception $e) {
            error_log("Error updating referral commission: " . $e->getMessage());
            return false;
        }
    }
}
?>