<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Models/TransactionModel.php
 * Purpose: Transaction model with financial operations and balance calculation
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

class TransactionModel extends BaseModel
{
    protected string $table = 'transactions';
    
    protected array $fillable = [
        'user_id',
        'type',
        'amount',
        'fee',
        'net_amount',
        'status',
        'payment_method',
        'payment_gateway',
        'gateway_transaction_id',
        'wallet_address',
        'currency',
        'description',
        'reference_id',
        'admin_note',
        'processed_by',
        'admin_processed_by',
        'processed_by_type',
        'processed_at'
    ];

    /**
     * Find transactions by user ID with pagination
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
                "SELECT id, user_id, type, amount, fee, net_amount, status, payment_method, 
                        payment_gateway, gateway_transaction_id, wallet_address, currency, 
                        description, reference_id, admin_note, processed_by, admin_processed_by, 
                        processed_by_type, processed_at, created_at, updated_at
                 FROM {$this->table} 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC, id DESC
                 LIMIT ? OFFSET ?"
            );
            
            $stmt->execute([$userId, $limit, $offset]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch transactions for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find transactions by user ID and type
     * @param int $userId
     * @param string $type
     * @return array
     */
    public function findByType(int $userId, string $type): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        if (!Validator::isValidTransactionType($type)) {
            throw new InvalidArgumentException('Invalid transaction type');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, user_id, type, amount, fee, net_amount, status, payment_method, 
                        payment_gateway, gateway_transaction_id, wallet_address, currency, 
                        description, reference_id, admin_note, processed_by, admin_processed_by, 
                        processed_by_type, processed_at, created_at, updated_at
                 FROM {$this->table} 
                 WHERE user_id = ? AND type = ? 
                 ORDER BY created_at DESC, id DESC
                 LIMIT 100"
            );
            
            $stmt->execute([$userId, $type]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch {$type} transactions for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create transaction with auto-generated reference ID
     * @param array $data
     * @return array
     */
    public function createTransaction(array $data): array 
    {
        // Validate required fields
        $requiredFields = ['user_id', 'type', 'amount'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return [
                    'success' => false,
                    'error' => "Required field {$field} is missing"
                ];
            }
        }

        // Validate data types
        if (!is_int($data['user_id']) || $data['user_id'] <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid user ID'
            ];
        }

        if (!Validator::isValidTransactionType($data['type'])) {
            return [
                'success' => false,
                'error' => 'Invalid transaction type'
            ];
        }

        if (!Validator::isValidAmount((float)$data['amount'])) {
            return [
                'success' => false,
                'error' => 'Invalid transaction amount'
            ];
        }

        try {
            // only start a transaction if one isn't already active (avoids nesting issues)
            $ownTransaction = !$this->db->inTransaction();
            if ($ownTransaction) {
                $this->db->beginTransaction();
            }

            // Set default values
            $transactionData = [
                'user_id' => (int)$data['user_id'],
                'type' => $data['type'],
                'amount' => (float)$data['amount'],
                'fee' => (float)($data['fee'] ?? 0),
                'net_amount' => (float)$data['amount'] - (float)($data['fee'] ?? 0),
                'status' => $data['status'] ?? 'pending',
                'payment_method' => $data['payment_method'] ?? null,
                'payment_gateway' => $data['payment_gateway'] ?? null,
                'gateway_transaction_id' => $data['gateway_transaction_id'] ?? null,
                'wallet_address' => $data['wallet_address'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'description' => $data['description'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'admin_note' => $data['admin_note'] ?? null,
                'processed_by' => $data['processed_by'] ?? null,
                'admin_processed_by' => $data['admin_processed_by'] ?? null,
                'processed_by_type' => $data['processed_by_type'] ?? null,
                'processed_at' => $data['processed_at'] ?? null
            ];

            // Create the transaction
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} 
                 (user_id, type, amount, fee, net_amount, status, payment_method, 
                  payment_gateway, gateway_transaction_id, wallet_address, currency, 
                  description, reference_id, admin_note, processed_by, admin_processed_by, 
                  processed_by_type, processed_at, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );

            $success = $stmt->execute([
                $transactionData['user_id'],
                $transactionData['type'],
                $transactionData['amount'],
                $transactionData['fee'],
                $transactionData['net_amount'],
                $transactionData['status'],
                $transactionData['payment_method'],
                $transactionData['payment_gateway'],
                $transactionData['gateway_transaction_id'],
                $transactionData['wallet_address'],
                $transactionData['currency'],
                $transactionData['description'],
                $transactionData['reference_id'],
                $transactionData['admin_note'],
                $transactionData['processed_by'],
                $transactionData['admin_processed_by'],
                $transactionData['processed_by_type'],
                $transactionData['processed_at']
            ]);

            if (!$success) {
                throw new PDOException('Failed to create transaction record');
            }

            $transactionId = (int)$this->db->lastInsertId();

            // Generate reference ID if not provided
            if (empty($transactionData['reference_id'])) {
                $referenceId = $this->generateReferenceId($transactionData['type'], $transactionId);
                
                $stmt = $this->db->prepare(
                    "UPDATE {$this->table} SET reference_id = ? WHERE id = ?"
                );
                $stmt->execute([$referenceId, $transactionId]);
            }

            if ($ownTransaction) {
                $this->db->commit();
            }

            // Log transaction creation
            Security::logAudit(
                $transactionData['user_id'], 
                'transaction_created', 
                'transactions', 
                $transactionId, 
                null, 
                [
                    'type' => $transactionData['type'],
                    'amount' => $transactionData['amount'],
                    'status' => $transactionData['status']
                ]
            );

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'reference_id' => $referenceId ?? $transactionData['reference_id']
            ];

        } catch (PDOException $e) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Transaction creation failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to create transaction. Please try again.'
            ];
        }
    }

    /**
     * Get user balance from transactions
     * @param int $userId
     * @return array
     */
    public function getUserBalance(int $userId): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            // Get balance directly from users table (primary source of truth)
            $stmt = $this->db->prepare(
                "SELECT balance, locked_balance, bonus_balance, 
                        total_invested, total_withdrawn, total_earned
                 FROM users 
                 WHERE id = ?"
            );
            
            $stmt->execute([$userId]);
            $userBalance = $stmt->fetch();
            
            if (!$userBalance) {
                return [
                    'available_balance' => 0.0,
                    'locked_balance' => 0.0,
                    'bonus_balance' => 0.0,
                    'total_balance' => 0.0,
                    'total_invested' => 0.0,
                    'total_withdrawn' => 0.0,
                    'total_earned' => 0.0
                ];
            }

            // Calculate transaction-based balance for verification
            $stmt = $this->db->prepare(
                "SELECT 
                    COALESCE(SUM(CASE 
                        WHEN type IN ('deposit', 'profit', 'bonus', 'referral', 'principal_return') 
                        THEN net_amount 
                        WHEN type IN ('withdrawal', 'investment') 
                        THEN -net_amount 
                        ELSE 0 
                    END), 0) as calculated_balance,
                    COALESCE(SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN net_amount ELSE 0 END), 0) as total_deposits,
                    COALESCE(SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_withdrawals,
                    COALESCE(SUM(CASE WHEN type = 'investment' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_investments,
                    COALESCE(SUM(CASE WHEN type = 'profit' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_profits,
                    COALESCE(SUM(CASE WHEN type = 'bonus' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_bonuses
                 FROM {$this->table} 
                 WHERE user_id = ? AND status = 'completed'"
            );
            
            $stmt->execute([$userId]);
            $calculatedBalance = $stmt->fetch();

            $availableBalance = (float)$userBalance['balance'];
            $lockedBalance = (float)$userBalance['locked_balance'];
            $bonusBalance = (float)$userBalance['bonus_balance'];

            return [
                'available_balance' => $availableBalance,
                'locked_balance' => $lockedBalance,
                'bonus_balance' => $bonusBalance,
                'total_balance' => $availableBalance + $lockedBalance + $bonusBalance,
                'total_invested' => (float)$userBalance['total_invested'],
                'total_withdrawn' => (float)$userBalance['total_withdrawn'],
                'total_earned' => (float)$userBalance['total_earned'],
                'calculated_balance' => (float)$calculatedBalance['calculated_balance'],
                'total_deposits' => (float)$calculatedBalance['total_deposits'],
                'total_withdrawals' => (float)$calculatedBalance['total_withdrawals'],
                'total_investments' => (float)$calculatedBalance['total_investments'],
                'total_profits' => (float)$calculatedBalance['total_profits'],
                'total_bonuses' => (float)$calculatedBalance['total_bonuses']
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to get user balance for user {$userId}: " . $e->getMessage());
            return [
                'available_balance' => 0.0,
                'locked_balance' => 0.0,
                'bonus_balance' => 0.0,
                'total_balance' => 0.0,
                'total_invested' => 0.0,
                'total_withdrawn' => 0.0,
                'total_earned' => 0.0
            ];
        }
    }

    /**
     * Get user transaction statistics
     * @param int $userId
     * @return array
     */
    public function getStats(int $userId): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    type,
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COALESCE(SUM(fee), 0) as total_fees,
                    COALESCE(AVG(amount), 0) as average_amount,
                    MIN(created_at) as first_transaction,
                    MAX(created_at) as last_transaction
                 FROM {$this->table} 
                 WHERE user_id = ? 
                 GROUP BY type
                 ORDER BY type"
            );
            
            $stmt->execute([$userId]);
            $typeStats = $stmt->fetchAll();

            // Overall stats
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(*) as total_transactions,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_transactions,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_transactions,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_transactions,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COALESCE(SUM(fee), 0) as total_fees,
                    MIN(created_at) as first_transaction_date,
                    MAX(created_at) as last_transaction_date
                 FROM {$this->table} 
                 WHERE user_id = ?"
            );
            
            $stmt->execute([$userId]);
            $overallStats = $stmt->fetch();

            // Recent activity (last 30 days)
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(*) as recent_transactions,
                    COALESCE(SUM(amount), 0) as recent_amount
                 FROM {$this->table} 
                 WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            
            $stmt->execute([$userId]);
            $recentStats = $stmt->fetch();

            return [
                'by_type' => $typeStats,
                'total_transactions' => (int)$overallStats['total_transactions'],
                'completed_transactions' => (int)$overallStats['completed_transactions'],
                'pending_transactions' => (int)$overallStats['pending_transactions'],
                'failed_transactions' => (int)$overallStats['failed_transactions'],
                'total_amount' => (float)$overallStats['total_amount'],
                'total_fees' => (float)$overallStats['total_fees'],
                'first_transaction_date' => $overallStats['first_transaction_date'],
                'last_transaction_date' => $overallStats['last_transaction_date'],
                'recent_transactions' => (int)$recentStats['recent_transactions'],
                'recent_amount' => (float)$recentStats['recent_amount']
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to get transaction stats for user {$userId}: " . $e->getMessage());
            return [
                'by_type' => [],
                'total_transactions' => 0,
                'completed_transactions' => 0,
                'pending_transactions' => 0,
                'failed_transactions' => 0,
                'total_amount' => 0.0,
                'total_fees' => 0.0,
                'first_transaction_date' => null,
                'last_transaction_date' => null,
                'recent_transactions' => 0,
                'recent_amount' => 0.0
            ];
        }
    }

    /**
     * Get recent transactions for dashboard
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getRecentTransactions(int $userId, int $limit = 10): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        if ($limit <= 0 || $limit > 50) {
            $limit = 10;
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, type, amount, fee, net_amount, status, currency, 
                        description, created_at
                 FROM {$this->table} 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC, id DESC
                 LIMIT ?"
            );
            
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch recent transactions for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Search transactions with filters
     * @param int $userId
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function searchTransactions(int $userId, array $filters = [], int $limit = 50, int $offset = 0): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            $whereConditions = ['user_id = ?'];
            $params = [$userId];

            // Type filter
            if (!empty($filters['type']) && Validator::isValidTransactionType($filters['type'])) {
                $whereConditions[] = 'type = ?';
                $params[] = $filters['type'];
            }

            // Status filter
            if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'processing', 'completed', 'failed', 'cancelled'])) {
                $whereConditions[] = 'status = ?';
                $params[] = $filters['status'];
            }

            // Date range filter
            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'created_at >= ?';
                $params[] = $filters['date_from'] . ' 00:00:00';
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'created_at <= ?';
                $params[] = $filters['date_to'] . ' 23:59:59';
            }

            // Amount range filter
            if (!empty($filters['amount_min'])) {
                $whereConditions[] = 'amount >= ?';
                $params[] = (float)$filters['amount_min'];
            }

            if (!empty($filters['amount_max'])) {
                $whereConditions[] = 'amount <= ?';
                $params[] = (float)$filters['amount_max'];
            }

            $whereClause = implode(' AND ', $whereConditions);
            
            $stmt = $this->db->prepare(
                "SELECT id, type, amount, fee, net_amount, status, payment_method, 
                        currency, description, created_at, updated_at
                 FROM {$this->table} 
                 WHERE {$whereClause}
                 ORDER BY created_at DESC, id DESC
                 LIMIT ? OFFSET ?"
            );
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to search transactions for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count transactions with filters
     * @param int $userId
     * @param array $filters
     * @return int
     */
    public function countTransactions(int $userId, array $filters = []): int 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            $whereConditions = ['user_id = ?'];
            $params = [$userId];

            // Apply same filters as searchTransactions
            if (!empty($filters['type']) && Validator::isValidTransactionType($filters['type'])) {
                $whereConditions[] = 'type = ?';
                $params[] = $filters['type'];
            }

            if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'processing', 'completed', 'failed', 'cancelled'])) {
                $whereConditions[] = 'status = ?';
                $params[] = $filters['status'];
            }

            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'created_at >= ?';
                $params[] = $filters['date_from'] . ' 00:00:00';
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'created_at <= ?';
                $params[] = $filters['date_to'] . ' 23:59:59';
            }

            if (!empty($filters['amount_min'])) {
                $whereConditions[] = 'amount >= ?';
                $params[] = (float)$filters['amount_min'];
            }

            if (!empty($filters['amount_max'])) {
                $whereConditions[] = 'amount <= ?';
                $params[] = (float)$filters['amount_max'];
            }

            $whereClause = implode(' AND ', $whereConditions);
            
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM {$this->table} WHERE {$whereClause}"
            );
            
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return (int)$result['count'];
            
        } catch (PDOException $e) {
            error_log("Failed to count transactions for user {$userId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Generate reference ID for transaction
     * @param string $type
     * @param int $transactionId
     * @return string
     */
    private function generateReferenceId(string $type, int $transactionId): string 
    {
        $typePrefix = match($type) {
            'deposit' => 'DEP',
            'withdrawal' => 'WTH',
            'investment' => 'INV',
            'profit' => 'PRF',
            'bonus' => 'BON',
            'referral' => 'REF',
            'principal_return' => 'RET',
            default => 'TXN'
        };

        $timestamp = time();
        $random = strtoupper(bin2hex(random_bytes(3)));
        
        return "{$typePrefix}{$timestamp}{$random}";
    }
}