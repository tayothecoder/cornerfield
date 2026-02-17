<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Models/UserWalletModel.php
 * Purpose: User cryptocurrency wallet address management
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

class UserWalletModel extends BaseModel
{
    protected string $table = 'user_wallets';
    
    protected array $fillable = [
        'user_id',
        'currency',
        'network',
        'address',
        'is_verified',
        'is_default'
    ];

    /**
     * Find all wallets by user ID
     * @param int $userId
     * @return array
     */
    public function findByUserId(int $userId): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT uw.*, 
                        (SELECT COUNT(*) FROM withdrawals w WHERE w.wallet_address = uw.address AND w.user_id = uw.user_id) as usage_count,
                        (SELECT MAX(w.created_at) FROM withdrawals w WHERE w.wallet_address = uw.address AND w.user_id = uw.user_id) as last_used_at
                 FROM {$this->table} uw
                 WHERE uw.user_id = ?
                 ORDER BY uw.is_default DESC, uw.created_at DESC"
            );
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch wallets for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add new wallet address
     * @param int $userId
     * @param string $currency
     * @param string $network
     * @param string $address
     * @return array
     */
    public function addWallet(int $userId, string $currency, string $network, string $address): array 
    {
        // Validate inputs
        if ($userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid user ID'
            ];
        }
        
        $currency = strtoupper(Validator::sanitizeString($currency, 20));
        $network = strtoupper(Validator::sanitizeString($network, 50));
        $address = Validator::sanitizeString($address, 255);
        
        if (empty($currency)) {
            return [
                'success' => false,
                'error' => 'Currency is required'
            ];
        }
        
        if (empty($network)) {
            return [
                'success' => false,
                'error' => 'Network is required'
            ];
        }
        
        if (empty($address)) {
            return [
                'success' => false,
                'error' => 'Wallet address is required'
            ];
        }
        
        // Validate wallet address format
        if (!Validator::isValidWalletAddress($address, $currency)) {
            return [
                'success' => false,
                'error' => 'Invalid wallet address format for ' . $currency
            ];
        }
        
        // Validate network for currency
        if (!Validator::isValidNetwork($network, $currency)) {
            return [
                'success' => false,
                'error' => 'Invalid network for ' . $currency
            ];
        }
        
        try {
            // Check if address already exists for this user
            $stmt = $this->db->prepare(
                "SELECT id FROM {$this->table} WHERE user_id = ? AND address = ?"
            );
            $stmt->execute([$userId, $address]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'error' => 'This wallet address is already saved'
                ];
            }
            
            // Check wallet limit per user (max 10 wallets)
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ?"
            );
            $stmt->execute([$userId]);
            $count = $stmt->fetchColumn();
            
            if ($count >= 10) {
                return [
                    'success' => false,
                    'error' => 'Maximum of 10 wallets allowed per user'
                ];
            }
            
            $this->db->beginTransaction();
            
            // Check if this is the first wallet for this user/currency combination
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE user_id = ? AND currency = ?"
            );
            $stmt->execute([$userId, $currency]);
            $isFirstWallet = $stmt->fetchColumn() == 0;
            
            // Add wallet
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (user_id, currency, network, address, is_verified, is_default, created_at) 
                 VALUES (?, ?, ?, ?, 0, ?, NOW())"
            );
            
            $success = $stmt->execute([$userId, $currency, $network, $address, $isFirstWallet ? 1 : 0]);
            
            if (!$success) {
                throw new PDOException('Failed to add wallet');
            }
            
            $walletId = (int)$this->db->lastInsertId();
            
            $this->db->commit();
            
            // Log wallet addition
            Security::logAudit($userId, 'wallet_added', 'user_wallets', $walletId, null, [
                'currency' => $currency,
                'network' => $network,
                'address_hash' => hash('sha256', $address) // Don't log full address
            ]);
            
            return [
                'success' => true,
                'wallet_id' => $walletId,
                'message' => 'Wallet address added successfully'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            
            // Check for duplicate entry error
            if ($e->getCode() === '23000') {
                return [
                    'success' => false,
                    'error' => 'This wallet address is already saved'
                ];
            }
            
            error_log("Failed to add wallet: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to add wallet address. Please try again.'
            ];
        }
    }

    /**
     * Remove wallet address
     * @param int $id
     * @param int $userId
     * @return array
     */
    public function removeWallet(int $id, int $userId): array 
    {
        if ($id <= 0 || $userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid wallet or user ID'
            ];
        }
        
        try {
            // Check if wallet exists and belongs to user
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?"
            );
            $stmt->execute([$id, $userId]);
            $wallet = $stmt->fetch();
            
            if (!$wallet) {
                return [
                    'success' => false,
                    'error' => 'Wallet not found'
                ];
            }
            
            // Check if wallet has been used in withdrawals
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM withdrawals WHERE wallet_address = ? AND user_id = ?"
            );
            $stmt->execute([$wallet['address'], $userId]);
            $usageCount = (int)$stmt->fetchColumn();
            
            if ($usageCount > 0) {
                return [
                    'success' => false,
                    'error' => 'Cannot delete wallet that has been used for withdrawals. Contact support if needed.'
                ];
            }
            
            $this->db->beginTransaction();
            
            $wasDefault = (bool)$wallet['is_default'];
            
            // Delete wallet
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?"
            );
            
            $success = $stmt->execute([$id, $userId]);
            
            if (!$success || $stmt->rowCount() === 0) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Failed to remove wallet'
                ];
            }
            
            // If this was the default wallet, make another wallet default
            if ($wasDefault) {
                $stmt = $this->db->prepare(
                    "UPDATE {$this->table} 
                     SET is_default = 1 
                     WHERE user_id = ? AND currency = ? 
                     ORDER BY created_at ASC 
                     LIMIT 1"
                );
                $stmt->execute([$userId, $wallet['currency']]);
            }
            
            $this->db->commit();
            
            // Log wallet removal
            Security::logAudit($userId, 'wallet_removed', 'user_wallets', $id, null, [
                'currency' => $wallet['currency'],
                'network' => $wallet['network'],
                'was_default' => $wasDefault
            ]);
            
            return [
                'success' => true,
                'message' => 'Wallet address removed successfully'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Failed to remove wallet {$id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to remove wallet address. Please try again.'
            ];
        }
    }

    /**
     * Set wallet as default for its currency
     * @param int $id
     * @param int $userId
     * @return array
     */
    public function setDefault(int $id, int $userId): array 
    {
        if ($id <= 0 || $userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid wallet or user ID'
            ];
        }
        
        try {
            // Check if wallet exists and belongs to user
            $stmt = $this->db->prepare(
                "SELECT currency FROM {$this->table} WHERE id = ? AND user_id = ?"
            );
            $stmt->execute([$id, $userId]);
            $wallet = $stmt->fetch();
            
            if (!$wallet) {
                return [
                    'success' => false,
                    'error' => 'Wallet not found'
                ];
            }
            
            $this->db->beginTransaction();
            
            // Remove default status from all wallets of this currency for this user
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET is_default = 0 WHERE user_id = ? AND currency = ?"
            );
            $stmt->execute([$userId, $wallet['currency']]);
            
            // Set this wallet as default
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET is_default = 1 WHERE id = ? AND user_id = ?"
            );
            
            $success = $stmt->execute([$id, $userId]);
            
            if (!$success || $stmt->rowCount() === 0) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Failed to set default wallet'
                ];
            }
            
            $this->db->commit();
            
            // Log default wallet change
            Security::logAudit($userId, 'wallet_default_changed', 'user_wallets', $id, null, [
                'currency' => $wallet['currency']
            ]);
            
            return [
                'success' => true,
                'message' => 'Default wallet updated successfully'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Failed to set default wallet {$id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to set default wallet. Please try again.'
            ];
        }
    }

    /**
     * Get default wallet for specific currency
     * @param int $userId
     * @param string $currency
     * @return array|null
     */
    public function getDefaultWallet(int $userId, string $currency): ?array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} 
                 WHERE user_id = ? AND currency = ? AND is_default = 1 
                 LIMIT 1"
            );
            
            $stmt->execute([$userId, strtoupper($currency)]);
            $result = $stmt->fetch();
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to get default wallet: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get wallets by currency
     * @param int $userId
     * @param string $currency
     * @return array
     */
    public function findByCurrency(int $userId, string $currency): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} 
                 WHERE user_id = ? AND currency = ?
                 ORDER BY is_default DESC, created_at DESC"
            );
            
            $stmt->execute([$userId, strtoupper($currency)]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch wallets by currency: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verify wallet address (admin function)
     * @param int $id
     * @param bool $isVerified
     * @return array
     */
    public function verifyWallet(int $id, bool $isVerified = true): array 
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid wallet ID'
            ];
        }
        
        try {
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET is_verified = ? WHERE id = ?"
            );
            
            $success = $stmt->execute([$isVerified ? 1 : 0, $id]);
            
            if ($success && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Wallet verification status updated'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Wallet not found'
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to verify wallet {$id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to update wallet verification. Please try again.'
            ];
        }
    }

    /**
     * Get wallet statistics for user
     * @param int $userId
     * @return array
     */
    public function getUserWalletStats(int $userId): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(*) as total_wallets,
                    COUNT(DISTINCT currency) as unique_currencies,
                    COUNT(CASE WHEN is_verified = 1 THEN 1 END) as verified_wallets,
                    COUNT(CASE WHEN is_default = 1 THEN 1 END) as default_wallets
                 FROM {$this->table}
                 WHERE user_id = ?"
            );
            
            $stmt->execute([$userId]);
            $stats = $stmt->fetch();
            
            if (!$stats) {
                return [
                    'total_wallets' => 0,
                    'unique_currencies' => 0,
                    'verified_wallets' => 0,
                    'default_wallets' => 0
                ];
            }
            
            return [
                'total_wallets' => (int)$stats['total_wallets'],
                'unique_currencies' => (int)$stats['unique_currencies'],
                'verified_wallets' => (int)$stats['verified_wallets'],
                'default_wallets' => (int)$stats['default_wallets']
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to fetch wallet stats for user {$userId}: " . $e->getMessage());
            return [
                'total_wallets' => 0,
                'unique_currencies' => 0,
                'verified_wallets' => 0,
                'default_wallets' => 0
            ];
        }
    }

    /**
     * Check if wallet address is already in use
     * @param string $address
     * @param int|null $excludeUserId
     * @return bool
     */
    public function isAddressInUse(string $address, ?int $excludeUserId = null): bool 
    {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE address = ?";
            $params = [$address];
            
            if ($excludeUserId !== null) {
                $sql .= " AND user_id != ?";
                $params[] = $excludeUserId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return (int)$stmt->fetchColumn() > 0;
            
        } catch (PDOException $e) {
            error_log("Failed to check address usage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get supported currencies and networks
     * @return array
     */
    public function getSupportedCurrencies(): array 
    {
        return [
            'BTC' => [
                'name' => 'Bitcoin',
                'networks' => ['BTC'],
                'min_confirmations' => 3
            ],
            'ETH' => [
                'name' => 'Ethereum',
                'networks' => ['ERC20'],
                'min_confirmations' => 12
            ],
            'USDT' => [
                'name' => 'Tether USD',
                'networks' => ['ERC20', 'TRC20', 'BEP20'],
                'min_confirmations' => 12
            ],
            'LTC' => [
                'name' => 'Litecoin',
                'networks' => ['LTC'],
                'min_confirmations' => 6
            ],
            'XRP' => [
                'name' => 'Ripple',
                'networks' => ['XRP'],
                'min_confirmations' => 1
            ]
        ];
    }
}