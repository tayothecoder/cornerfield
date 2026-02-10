<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Models/ReferralModel.php
 * Purpose: Referral system model with commission tracking
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

class ReferralModel extends BaseModel
{
    protected string $table = 'referrals';
    
    protected array $fillable = [
        'referrer_id',
        'referred_id',
        'level',
        'commission_rate',
        'total_earned',
        'status'
    ];

    /**
     * Find all referrals by referrer ID
     * @param int $userId
     * @return array
     */
    public function findByReferrerId(int $userId): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT r.*, 
                        u.username, u.first_name, u.last_name, u.email, u.created_at as user_created_at,
                        u.total_invested as referred_total_invested, u.kyc_status,
                        (SELECT COUNT(*) FROM investments i WHERE i.user_id = r.referred_id) as investment_count,
                        (SELECT SUM(amount) FROM transactions t WHERE t.user_id = r.referred_id AND t.type = 'investment' AND t.status = 'completed') as total_invested_by_referred
                 FROM {$this->table} r
                 LEFT JOIN users u ON r.referred_id = u.id
                 WHERE r.referrer_id = ? 
                 ORDER BY r.created_at DESC"
            );
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch referrals for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get referral statistics for user
     * @param int $userId
     * @return array
     */
    public function getReferralStats(int $userId): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(*) as total_referrals,
                    COUNT(CASE WHEN r.status = 'active' THEN 1 END) as active_referrals,
                    COALESCE(SUM(r.total_earned), 0) as total_commission_earned,
                    COUNT(CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as referrals_last_30_days,
                    COALESCE(SUM(CASE WHEN t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN t.amount ELSE 0 END), 0) as commission_last_30_days
                 FROM {$this->table} r
                 LEFT JOIN users u ON r.referred_id = u.id
                 LEFT JOIN transactions t ON t.user_id = r.referrer_id AND t.type = 'referral' AND t.status = 'completed'
                 WHERE r.referrer_id = ?"
            );
            
            $stmt->execute([$userId]);
            $stats = $stmt->fetch();
            
            if (!$stats) {
                return [
                    'total_referrals' => 0,
                    'active_referrals' => 0,
                    'total_commission_earned' => 0.0,
                    'referrals_last_30_days' => 0,
                    'commission_last_30_days' => 0.0
                ];
            }
            
            return [
                'total_referrals' => (int)$stats['total_referrals'],
                'active_referrals' => (int)$stats['active_referrals'],
                'total_commission_earned' => (float)$stats['total_commission_earned'],
                'referrals_last_30_days' => (int)$stats['referrals_last_30_days'],
                'commission_last_30_days' => (float)$stats['commission_last_30_days']
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to fetch referral stats for user {$userId}: " . $e->getMessage());
            return [
                'total_referrals' => 0,
                'active_referrals' => 0,
                'total_commission_earned' => 0.0,
                'referrals_last_30_days' => 0,
                'commission_last_30_days' => 0.0
            ];
        }
    }

    /**
     * Create new referral relationship
     * @param int $referrerId
     * @param int $referredId
     * @param float $commissionRate
     * @return array
     */
    public function createReferral(int $referrerId, int $referredId, float $commissionRate): array 
    {
        if ($referrerId <= 0 || $referredId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid user IDs provided'
            ];
        }
        
        if ($referrerId === $referredId) {
            return [
                'success' => false,
                'error' => 'Cannot refer yourself'
            ];
        }
        
        if ($commissionRate < 0 || $commissionRate > 100) {
            return [
                'success' => false,
                'error' => 'Invalid commission rate'
            ];
        }
        
        try {
            // Check if referral relationship already exists
            $stmt = $this->db->prepare(
                "SELECT id FROM {$this->table} WHERE referrer_id = ? AND referred_id = ?"
            );
            $stmt->execute([$referrerId, $referredId]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'error' => 'Referral relationship already exists'
                ];
            }
            
            // Check for circular referrals (prevent A refers B, B refers A)
            $stmt = $this->db->prepare(
                "SELECT id FROM {$this->table} WHERE referrer_id = ? AND referred_id = ?"
            );
            $stmt->execute([$referredId, $referrerId]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'error' => 'Circular referral detected'
                ];
            }
            
            $this->db->beginTransaction();
            
            // Create referral record
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (referrer_id, referred_id, level, commission_rate, total_earned, status, created_at) 
                 VALUES (?, ?, 1, ?, 0.00000000, 'active', NOW())"
            );
            
            $success = $stmt->execute([$referrerId, $referredId, $commissionRate]);
            
            if (!$success) {
                throw new PDOException('Failed to create referral record');
            }
            
            $referralId = (int)$this->db->lastInsertId();
            
            // Update referred user with referrer info
            $stmt = $this->db->prepare(
                "UPDATE users SET referred_by = ? WHERE id = ?"
            );
            $stmt->execute([$referrerId, $referredId]);
            
            $this->db->commit();
            
            // Log referral creation
            Security::logAudit($referrerId, 'referral_created', 'referrals', $referralId, null, [
                'referred_user_id' => $referredId,
                'commission_rate' => $commissionRate
            ]);
            
            return [
                'success' => true,
                'referral_id' => $referralId,
                'message' => 'Referral relationship created successfully'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Failed to create referral: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to create referral relationship. Please try again.'
            ];
        }
    }

    /**
     * Update referral commission earnings
     * @param int $referralId
     * @param float $commissionAmount
     * @return array
     */
    public function addCommission(int $referralId, float $commissionAmount): array 
    {
        if ($referralId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid referral ID'
            ];
        }
        
        if ($commissionAmount <= 0) {
            return [
                'success' => false,
                'error' => 'Commission amount must be positive'
            ];
        }
        
        try {
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET total_earned = total_earned + ?, updated_at = NOW() 
                 WHERE id = ? AND status = 'active'"
            );
            
            $success = $stmt->execute([$commissionAmount, $referralId]);
            
            if ($success && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Commission added successfully'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Referral not found or inactive'
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to add commission to referral {$referralId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to update commission. Please try again.'
            ];
        }
    }

    /**
     * Find referral by referrer and referred user IDs
     * @param int $referrerId
     * @param int $referredId
     * @return array|null
     */
    public function findByReferrerAndReferred(int $referrerId, int $referredId): ?array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE referrer_id = ? AND referred_id = ?"
            );
            
            $stmt->execute([$referrerId, $referredId]);
            $result = $stmt->fetch();
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to find referral: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get top referrers (for leaderboard)
     * @param int $limit
     * @return array
     */
    public function getTopReferrers(int $limit = 10): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT r.referrer_id, 
                        u.username, u.first_name, u.last_name,
                        COUNT(r.id) as total_referrals,
                        SUM(r.total_earned) as total_commission,
                        COUNT(CASE WHEN r.status = 'active' THEN 1 END) as active_referrals
                 FROM {$this->table} r
                 LEFT JOIN users u ON r.referrer_id = u.id
                 WHERE u.is_active = 1
                 GROUP BY r.referrer_id, u.username, u.first_name, u.last_name
                 ORDER BY total_referrals DESC, total_commission DESC
                 LIMIT ?"
            );
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch top referrers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Deactivate referral relationship
     * @param int $referralId
     * @return array
     */
    public function deactivateReferral(int $referralId): array 
    {
        if ($referralId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid referral ID'
            ];
        }
        
        try {
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET status = 'inactive', updated_at = NOW() WHERE id = ?"
            );
            
            $success = $stmt->execute([$referralId]);
            
            if ($success && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Referral deactivated successfully'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Referral not found'
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to deactivate referral {$referralId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to deactivate referral. Please try again.'
            ];
        }
    }

    /**
     * Get referral chain (multi-level)
     * @param int $userId
     * @param int $maxLevels
     * @return array
     */
    public function getReferralChain(int $userId, int $maxLevels = 5): array 
    {
        try {
            $referrals = [];
            $currentLevel = [$userId];
            
            for ($level = 1; $level <= $maxLevels && !empty($currentLevel); $level++) {
                $placeholders = str_repeat('?,', count($currentLevel) - 1) . '?';
                
                $stmt = $this->db->prepare(
                    "SELECT r.*, u.username, u.first_name, u.last_name, u.created_at as user_created_at
                     FROM {$this->table} r
                     LEFT JOIN users u ON r.referred_id = u.id
                     WHERE r.referrer_id IN ($placeholders) AND r.status = 'active'
                     ORDER BY r.created_at DESC"
                );
                
                $stmt->execute($currentLevel);
                $levelReferrals = $stmt->fetchAll();
                
                if (empty($levelReferrals)) {
                    break;
                }
                
                $referrals[$level] = $levelReferrals;
                $currentLevel = array_column($levelReferrals, 'referred_id');
            }
            
            return $referrals;
            
        } catch (PDOException $e) {
            error_log("Failed to get referral chain for user {$userId}: " . $e->getMessage());
            return [];
        }
    }
}