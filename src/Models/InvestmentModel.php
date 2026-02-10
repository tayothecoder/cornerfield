<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Models/InvestmentModel.php
 * Purpose: Investment model with financial transaction safety
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

class InvestmentModel extends BaseModel
{
    protected string $table = 'investments';
    
    protected array $fillable = [
        'user_id',
        'schema_id',
        'invest_amount',
        'total_profit_amount',
        'last_profit_time',
        'next_profit_time',
        'number_of_period',
        'status'
    ];

    /**
     * Find all investments by user ID
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
                "SELECT i.id, i.user_id, i.schema_id, i.invest_amount, i.total_profit_amount, 
                        i.last_profit_time, i.next_profit_time, i.number_of_period, i.status, 
                        i.created_at, i.updated_at,
                        s.name as schema_name, s.daily_rate, s.duration_days, s.total_return
                 FROM {$this->table} i
                 INNER JOIN investment_schemas s ON i.schema_id = s.id
                 WHERE i.user_id = ? 
                 ORDER BY i.created_at DESC"
            );
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch investments for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find active investments by user ID
     * @param int $userId
     * @return array
     */
    public function findActiveByUserId(int $userId): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT i.id, i.user_id, i.schema_id, i.invest_amount, i.total_profit_amount, 
                        i.last_profit_time, i.next_profit_time, i.number_of_period, i.status, 
                        i.created_at, i.updated_at,
                        s.name as schema_name, s.daily_rate, s.duration_days, s.total_return,
                        DATEDIFF(DATE_ADD(i.created_at, INTERVAL s.duration_days DAY), NOW()) as days_remaining
                 FROM {$this->table} i
                 INNER JOIN investment_schemas s ON i.schema_id = s.id
                 WHERE i.user_id = ? AND i.status = 'active'
                 ORDER BY i.created_at DESC"
            );
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch active investments for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get investment with schema details
     * @param int $id
     * @return array|null
     */
    public function getWithSchema(int $id): ?array 
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Investment ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT i.id, i.user_id, i.schema_id, i.invest_amount, i.total_profit_amount, 
                        i.last_profit_time, i.next_profit_time, i.number_of_period, i.status, 
                        i.created_at, i.updated_at,
                        s.name as schema_name, s.min_amount, s.max_amount, s.daily_rate, 
                        s.duration_days, s.total_return, s.featured, s.description,
                        u.email, u.username, u.first_name, u.last_name
                 FROM {$this->table} i
                 INNER JOIN investment_schemas s ON i.schema_id = s.id
                 INNER JOIN users u ON i.user_id = u.id
                 WHERE i.id = ?"
            );
            
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch investment {$id} with schema: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create new investment with atomic operations
     * @param int $userId
     * @param int $schemaId
     * @param float $amount
     * @return array
     */
    public function createInvestment(int $userId, int $schemaId, float $amount): array 
    {
        if ($userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid user ID'
            ];
        }
        
        if ($schemaId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid investment schema ID'
            ];
        }
        
        if (!Validator::isValidAmount($amount)) {
            return [
                'success' => false,
                'error' => 'Invalid investment amount'
            ];
        }

        try {
            $this->db->beginTransaction();

            // 1. Validate investment schema
            $stmt = $this->db->prepare(
                "SELECT id, name, min_amount, max_amount, daily_rate, duration_days, total_return, status 
                 FROM investment_schemas 
                 WHERE id = ? AND status = 1"
            );
            $stmt->execute([$schemaId]);
            $schema = $stmt->fetch();

            if (!$schema) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Investment plan not available'
                ];
            }

            // 2. Validate amount against schema limits
            if (!Validator::isValidInvestmentAmount($amount, $schema['min_amount'], $schema['max_amount'])) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => "Investment amount must be between ${schema['min_amount']} and ${schema['max_amount']}"
                ];
            }

            // 3. Check user balance
            $stmt = $this->db->prepare("SELECT balance FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'User account not found or inactive'
                ];
            }

            if ($user['balance'] < $amount) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Insufficient balance'
                ];
            }

            // 4. Create investment record
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (user_id, schema_id, invest_amount, total_profit_amount, 
                                            last_profit_time, next_profit_time, number_of_period, 
                                            status, created_at, updated_at) 
                 VALUES (?, ?, ?, 0, NULL, DATE_ADD(NOW(), INTERVAL 1 DAY), ?, 'active', NOW(), NOW())"
            );

            $success = $stmt->execute([
                $userId, 
                $schemaId, 
                $amount, 
                $schema['duration_days']
            ]);

            if (!$success) {
                throw new PDOException('Failed to create investment record');
            }

            $investmentId = (int)$this->db->lastInsertId();

            // 5. Create transaction record
            $transactionId = $this->createTransactionRecord($userId, $amount, $investmentId, $schema['name']);
            if (!$transactionId) {
                throw new PDOException('Failed to create transaction record');
            }

            // 6. Deduct balance atomically
            $stmt = $this->db->prepare(
                "UPDATE users 
                 SET balance = balance - ?, 
                     total_invested = total_invested + ?,
                     updated_at = NOW() 
                 WHERE id = ? AND balance >= ?"
            );

            $success = $stmt->execute([$amount, $amount, $userId, $amount]);

            if (!$success || $stmt->rowCount() === 0) {
                throw new PDOException('Failed to deduct balance - insufficient funds or user not found');
            }

            $this->db->commit();

            // Log successful investment creation
            Security::logAudit($userId, 'investment_created', 'investments', $investmentId, null, [
                'amount' => $amount,
                'schema_id' => $schemaId,
                'schema_name' => $schema['name'],
                'transaction_id' => $transactionId
            ]);

            return [
                'success' => true,
                'investment_id' => $investmentId,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'schema_name' => $schema['name'],
                'duration_days' => $schema['duration_days'],
                'daily_rate' => (float)$schema['daily_rate']
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Investment creation failed for user {$userId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to process investment. Please try again.'
            ];
        }
    }

    /**
     * Complete investment and return principal
     * @param int $id
     * @return array
     */
    public function completeInvestment(int $id): array 
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid investment ID'
            ];
        }

        try {
            $this->db->beginTransaction();

            // Get investment details
            $investment = $this->getWithSchema($id);
            if (!$investment) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Investment not found'
                ];
            }

            if ($investment['status'] !== 'active') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Investment is not active'
                ];
            }

            // 1. Mark investment as completed
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET status = 'completed', updated_at = NOW() 
                 WHERE id = ?"
            );

            $success = $stmt->execute([$id]);
            if (!$success) {
                throw new PDOException('Failed to update investment status');
            }

            // 2. Return principal to user balance
            $principalAmount = (float)$investment['invest_amount'];
            $stmt = $this->db->prepare(
                "UPDATE users 
                 SET balance = balance + ?, 
                     updated_at = NOW() 
                 WHERE id = ?"
            );

            $success = $stmt->execute([$principalAmount, $investment['user_id']]);
            if (!$success) {
                throw new PDOException('Failed to return principal to user balance');
            }

            // 3. Create principal return transaction
            $transactionId = $this->createPrincipalReturnTransaction($investment['user_id'], $principalAmount, $id, $investment['schema_name']);
            if (!$transactionId) {
                throw new PDOException('Failed to create principal return transaction');
            }

            $this->db->commit();

            // Log investment completion
            Security::logAudit((int)$investment['user_id'], 'investment_completed', 'investments', $id, null, [
                'principal_returned' => $principalAmount,
                'total_profit' => $investment['total_profit_amount'],
                'schema_name' => $investment['schema_name'],
                'transaction_id' => $transactionId
            ]);

            return [
                'success' => true,
                'investment_id' => $id,
                'principal_returned' => $principalAmount,
                'total_profit_earned' => (float)$investment['total_profit_amount'],
                'transaction_id' => $transactionId
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Investment completion failed for ID {$id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to complete investment. Please try again.'
            ];
        }
    }

    /**
     * Get user investment statistics
     * @param int $userId
     * @return array
     */
    public function getUserStats(int $userId): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(*) as total_investments,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_investments,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_investments,
                    COALESCE(SUM(invest_amount), 0) as total_invested,
                    COALESCE(SUM(total_profit_amount), 0) as total_profit_earned,
                    COALESCE(SUM(CASE WHEN status = 'active' THEN invest_amount ELSE 0 END), 0) as active_investment_amount
                 FROM {$this->table} 
                 WHERE user_id = ?"
            );
            
            $stmt->execute([$userId]);
            $stats = $stmt->fetch();
            
            // Get average daily return
            $stmt = $this->db->prepare(
                "SELECT AVG(s.daily_rate) as avg_daily_rate
                 FROM {$this->table} i
                 INNER JOIN investment_schemas s ON i.schema_id = s.id
                 WHERE i.user_id = ? AND i.status = 'active'"
            );
            
            $stmt->execute([$userId]);
            $avgRate = $stmt->fetch();
            
            return [
                'total_investments' => (int)$stats['total_investments'],
                'active_investments' => (int)$stats['active_investments'],
                'completed_investments' => (int)$stats['completed_investments'],
                'total_invested' => (float)$stats['total_invested'],
                'total_profit_earned' => (float)$stats['total_profit_earned'],
                'active_investment_amount' => (float)$stats['active_investment_amount'],
                'average_daily_rate' => (float)($avgRate['avg_daily_rate'] ?? 0)
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to fetch user stats for user {$userId}: " . $e->getMessage());
            return [
                'total_investments' => 0,
                'active_investments' => 0,
                'completed_investments' => 0,
                'total_invested' => 0.0,
                'total_profit_earned' => 0.0,
                'active_investment_amount' => 0.0,
                'average_daily_rate' => 0.0
            ];
        }
    }

    /**
     * Create investment transaction record
     * @param int $userId
     * @param float $amount
     * @param int $investmentId
     * @param string $schemaName
     * @return int|false
     */
    private function createTransactionRecord(int $userId, float $amount, int $investmentId, string $schemaName): int|false 
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO transactions (user_id, type, amount, fee, net_amount, status, 
                                          payment_method, currency, description, reference_id, 
                                          created_at, updated_at) 
                 VALUES (?, 'investment', ?, 0, ?, 'completed', 'balance', 'USD', ?, ?, NOW(), NOW())"
            );
            
            $description = "Investment in {$schemaName}";
            $success = $stmt->execute([$userId, $amount, $amount, $description, $investmentId]);
            
            return $success ? (int)$this->db->lastInsertId() : false;
            
        } catch (PDOException $e) {
            error_log("Failed to create transaction record: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create principal return transaction record
     * @param int $userId
     * @param float $amount
     * @param int $investmentId
     * @param string $schemaName
     * @return int|false
     */
    private function createPrincipalReturnTransaction(int $userId, float $amount, int $investmentId, string $schemaName): int|false 
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO transactions (user_id, type, amount, fee, net_amount, status, 
                                          payment_method, currency, description, reference_id, 
                                          created_at, updated_at) 
                 VALUES (?, 'principal_return', ?, 0, ?, 'completed', 'system', 'USD', ?, ?, NOW(), NOW())"
            );
            
            $description = "Principal return from completed {$schemaName} investment";
            $success = $stmt->execute([$userId, $amount, $amount, $description, $investmentId]);
            
            return $success ? (int)$this->db->lastInsertId() : false;
            
        } catch (PDOException $e) {
            error_log("Failed to create principal return transaction: " . $e->getMessage());
            return false;
        }
    }
}