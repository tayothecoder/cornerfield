<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Models/WithdrawalModel.php
 * Purpose: Withdrawal model with balance validation and financial security
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
use App\Models\TransactionModel;
use App\Models\UserModel;
use App\Utils\Security;
use App\Utils\Validator;

class WithdrawalModel extends BaseModel
{
    protected string $table = 'withdrawals';
    
    protected array $fillable = [
        'transaction_id',
        'user_id',
        'requested_amount',
        'fee_amount',
        'wallet_address',
        'currency',
        'network',
        'status',
        'admin_processed_by',
        'processed_at',
        'rejection_reason',
        'withdrawal_hash',
        'network_fee',
        'processing_notes'
    ];

    /**
     * Find withdrawals by user ID with pagination
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findByUserId(int $userId, int $limit = 50, int $offset = 0): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }
        
        if ($offset < 0) {
            $offset = 0;
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT w.*, t.status as transaction_status, t.reference_id
                 FROM {$this->table} w 
                 LEFT JOIN transactions t ON w.transaction_id = t.id
                 WHERE w.user_id = ? 
                 ORDER BY w.created_at DESC, w.id DESC
                 LIMIT ? OFFSET ?"
            );
            
            $stmt->execute([$userId, $limit, $offset]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch withdrawals for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find pending withdrawals for user
     * @param int $userId
     * @return array
     */
    public function findPending(int $userId): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT w.*, t.reference_id
                 FROM {$this->table} w 
                 LEFT JOIN transactions t ON w.transaction_id = t.id
                 WHERE w.user_id = ? AND w.status IN ('pending', 'processing')
                 ORDER BY w.created_at DESC"
            );
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch pending withdrawals for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create withdrawal with balance validation and atomic operations
     * @param int $userId
     * @param float $amount
     * @param string $walletAddress
     * @param string $currency
     * @param string $network
     * @return array
     */
    public function createWithdrawal(int $userId, float $amount, string $walletAddress, string $currency, string $network): array 
    {
        if ($userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid user ID'
            ];
        }

        if (!Validator::isValidAmount($amount)) {
            return [
                'success' => false,
                'error' => 'Invalid withdrawal amount'
            ];
        }

        if (!Validator::isValidWalletAddress($walletAddress, $currency)) {
            return [
                'success' => false,
                'error' => 'Invalid wallet address format'
            ];
        }

        if (!Validator::isValidNetwork($network, $currency)) {
            return [
                'success' => false,
                'error' => 'Invalid network for selected currency'
            ];
        }

        try {
            $this->db->beginTransaction();

            // Get withdrawal settings
            $withdrawalSettings = $this->getWithdrawalSettings();
            $minAmount = $withdrawalSettings['minimum_withdrawal'];
            $maxAmount = $withdrawalSettings['maximum_withdrawal'];

            // Validate amount limits
            if ($amount < $minAmount) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => "Minimum withdrawal amount is $" . number_format($minAmount, 2)
                ];
            }

            if ($amount > $maxAmount) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => "Maximum withdrawal amount is $" . number_format($maxAmount, 2)
                ];
            }

            // Get user current balance
            $userModel = new UserModel();
            $user = $userModel->findById($userId);

            if (!$user) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'User not found'
                ];
            }

            // Calculate fee
            $feeCalculation = $this->calculateFee($amount);
            $feeAmount = $feeCalculation['fee'];
            $totalDeduction = $amount + $feeAmount;

            // Check sufficient balance (atomic check with update)
            if ((float)$user['balance'] < $totalDeduction) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Insufficient balance. Available: $' . number_format((float)$user['balance'], 2) . 
                              ', Required: $' . number_format($totalDeduction, 2)
                ];
            }

            // Deduct balance atomically (prevents double-spending)
            $stmt = $this->db->prepare(
                "UPDATE users 
                 SET balance = balance - ?, updated_at = NOW() 
                 WHERE id = ? AND balance >= ?"
            );

            $success = $stmt->execute([$totalDeduction, $userId, $totalDeduction]);

            if (!$success || $stmt->rowCount() === 0) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Insufficient balance or concurrent transaction detected'
                ];
            }

            // Create transaction record
            $transactionModel = new TransactionModel();
            $transactionResult = $transactionModel->createTransaction([
                'user_id' => $userId,
                'type' => 'withdrawal',
                'amount' => $amount,
                'fee' => $feeAmount,
                'net_amount' => $amount, // User receives the requested amount
                'status' => 'pending',
                'payment_method' => 'crypto',
                'wallet_address' => $walletAddress,
                'currency' => $currency,
                'description' => "Withdrawal to {$currency} ({$network}) address"
            ]);

            if (!$transactionResult['success']) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Failed to create transaction record'
                ];
            }

            $transactionId = $transactionResult['transaction_id'];

            // Create withdrawal record
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} 
                 (transaction_id, user_id, requested_amount, fee_amount, wallet_address, 
                  currency, network, status, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())"
            );

            $success = $stmt->execute([
                $transactionId,
                $userId,
                $amount,
                $feeAmount,
                $walletAddress,
                strtoupper($currency),
                strtoupper($network)
            ]);

            if (!$success) {
                throw new PDOException('Failed to create withdrawal record');
            }

            $withdrawalId = (int)$this->db->lastInsertId();

            $this->db->commit();

            // Log withdrawal creation
            Security::logAudit(
                $userId, 
                'withdrawal_created', 
                'withdrawals', 
                $withdrawalId, 
                null, 
                [
                    'amount' => $amount,
                    'fee' => $feeAmount,
                    'currency' => $currency,
                    'network' => $network,
                    'wallet_address' => substr($walletAddress, 0, 10) . '...' . substr($walletAddress, -6)
                ]
            );

            return [
                'success' => true,
                'withdrawal_id' => $withdrawalId,
                'transaction_id' => $transactionId,
                'reference_id' => $transactionResult['reference_id'],
                'fee_amount' => $feeAmount,
                'total_deducted' => $totalDeduction
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Withdrawal creation failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to process withdrawal. Please try again.'
            ];
        }
    }

    /**
     * Update withdrawal status
     * @param int $id
     * @param string $status
     * @param int|null $adminId
     * @param string|null $hash
     * @return array
     */
    public function updateStatus(int $id, string $status, ?int $adminId = null, ?string $hash = null): array 
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid withdrawal ID'
            ];
        }

        $validStatuses = ['pending', 'processing', 'completed', 'failed', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'error' => 'Invalid status'
            ];
        }

        try {
            $this->db->beginTransaction();

            // Get current withdrawal
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            $withdrawal = $stmt->fetch();

            if (!$withdrawal) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Withdrawal not found'
                ];
            }

            // Update withdrawal status
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET status = ?, admin_processed_by = ?, processed_at = NOW(), 
                     withdrawal_hash = ?, updated_at = NOW()
                 WHERE id = ?"
            );

            $success = $stmt->execute([$status, $adminId, $hash, $id]);

            if (!$success) {
                throw new PDOException('Failed to update withdrawal status');
            }

            // Update related transaction status
            $stmt = $this->db->prepare(
                "UPDATE transactions 
                 SET status = ?, admin_processed_by = ?, processed_at = NOW(), updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([$status, $adminId, $withdrawal['transaction_id']]);

            // If failed or cancelled, refund the balance
            if (in_array($status, ['failed', 'cancelled'])) {
                $refundAmount = $withdrawal['requested_amount'] + $withdrawal['fee_amount'];
                
                $stmt = $this->db->prepare(
                    "UPDATE users 
                     SET balance = balance + ?, updated_at = NOW() 
                     WHERE id = ?"
                );
                $stmt->execute([$refundAmount, $withdrawal['user_id']]);

                // Create refund transaction record
                $transactionModel = new TransactionModel();
                $transactionModel->createTransaction([
                    'user_id' => (int)$withdrawal['user_id'],
                    'type' => 'deposit',
                    'amount' => $refundAmount,
                    'fee' => 0,
                    'net_amount' => $refundAmount,
                    'status' => 'completed',
                    'payment_method' => 'system',
                    'currency' => 'USD',
                    'description' => "Refund for {$status} withdrawal #{$withdrawal['id']}"
                ]);
            }

            $this->db->commit();

            // Log status change
            Security::logAudit(
                (int)$withdrawal['user_id'], 
                'withdrawal_status_updated', 
                'withdrawals', 
                $id, 
                ['status' => $withdrawal['status']], 
                [
                    'status' => $status, 
                    'admin_id' => $adminId,
                    'hash' => $hash ? substr($hash, 0, 10) . '...' : null
                ]
            );

            return [
                'success' => true,
                'message' => 'Withdrawal status updated successfully'
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Failed to update withdrawal status: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to update withdrawal status'
            ];
        }
    }

    /**
     * Get withdrawal methods
     * @return array
     */
    public function getWithdrawalMethods(): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, name, type, currency, currency_symbol, charge, charge_type,
                        minimum_withdrawal, maximum_withdrawal, processing_time, 
                        instructions, status
                 FROM withdrawal_methods 
                 WHERE status = 1 
                 ORDER BY name ASC"
            );
            
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch withdrawal methods: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate withdrawal fee
     * @param float $amount
     * @return array
     */
    public function calculateFee(float $amount): array 
    {
        try {
            // Get fee settings from admin settings
            $stmt = $this->db->prepare(
                "SELECT setting_value FROM admin_settings 
                 WHERE setting_key IN ('withdrawal_fee_rate', 'min_withdrawal_amount', 'max_withdrawal_amount')"
            );
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $feeRate = (float)($settings['withdrawal_fee_rate'] ?? 5.0); // Default 5%
            $fee = $amount * ($feeRate / 100);

            // Minimum fee of $1
            $minFee = 1.0;
            if ($fee < $minFee) {
                $fee = $minFee;
            }

            return [
                'fee' => $fee,
                'fee_rate' => $feeRate,
                'net_amount' => $amount, // User gets the full requested amount
                'total_deduction' => $amount + $fee
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to calculate withdrawal fee: " . $e->getMessage());
            
            // Default fee calculation
            return [
                'fee' => $amount * 0.05, // 5% default
                'fee_rate' => 5.0,
                'net_amount' => $amount,
                'total_deduction' => $amount + ($amount * 0.05)
            ];
        }
    }

    /**
     * Get withdrawal settings from admin_settings
     * @return array
     */
    private function getWithdrawalSettings(): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT setting_key, setting_value FROM admin_settings 
                 WHERE setting_key IN ('min_withdrawal_amount', 'max_withdrawal_amount', 'withdrawal_fee_rate')"
            );
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return [
                'minimum_withdrawal' => (float)($settings['min_withdrawal_amount'] ?? 10.0),
                'maximum_withdrawal' => (float)($settings['max_withdrawal_amount'] ?? 50000.0),
                'fee_rate' => (float)($settings['withdrawal_fee_rate'] ?? 5.0)
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to get withdrawal settings: " . $e->getMessage());
            
            return [
                'minimum_withdrawal' => 10.0,
                'maximum_withdrawal' => 50000.0,
                'fee_rate' => 5.0
            ];
        }
    }
}