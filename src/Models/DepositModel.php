<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Models/DepositModel.php
 * Purpose: Deposit model with financial transaction security
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

class DepositModel extends BaseModel
{
    protected string $table = 'deposits';
    
    protected array $fillable = [
        'transaction_id',
        'user_id',
        'deposit_method_id',
        'requested_amount',
        'fee_amount',
        'currency',
        'crypto_currency',
        'network',
        'deposit_address',
        'transaction_hash',
        'gateway_transaction_id',
        'gateway_response',
        'proof_of_payment',
        'admin_notes',
        'status',
        'verification_status',
        'admin_processed_by',
        'processed_at',
        'expires_at'
    ];

    /**
     * Find deposits by user ID with pagination
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
                "SELECT d.*, dm.name as method_name, dm.type as method_type, t.status as transaction_status
                 FROM {$this->table} d 
                 LEFT JOIN deposit_methods dm ON d.deposit_method_id = dm.id
                 LEFT JOIN transactions t ON d.transaction_id = t.id
                 WHERE d.user_id = ? 
                 ORDER BY d.created_at DESC, d.id DESC
                 LIMIT ? OFFSET ?"
            );
            
            $stmt->execute([$userId, $limit, $offset]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch deposits for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find pending deposits for user
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
                "SELECT d.*, dm.name as method_name, dm.type as method_type
                 FROM {$this->table} d 
                 LEFT JOIN deposit_methods dm ON d.deposit_method_id = dm.id
                 WHERE d.user_id = ? AND d.status IN ('pending', 'processing')
                 ORDER BY d.created_at DESC"
            );
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch pending deposits for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create deposit with transaction record atomically
     * @param int $userId
     * @param int $depositMethodId
     * @param float $amount
     * @param array $extra Additional data (network, currency, etc.)
     * @return array
     */
    public function createDeposit(int $userId, int $depositMethodId, float $amount, array $extra = []): array 
    {
        if ($userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid user ID'
            ];
        }

        if ($depositMethodId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid deposit method'
            ];
        }

        if (!Validator::isValidAmount($amount)) {
            return [
                'success' => false,
                'error' => 'Invalid deposit amount'
            ];
        }

        try {
            $this->db->beginTransaction();

            // Get deposit method details
            $stmt = $this->db->prepare("SELECT * FROM deposit_methods WHERE id = ? AND status = 1");
            $stmt->execute([$depositMethodId]);
            $depositMethod = $stmt->fetch();

            if (!$depositMethod) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Deposit method not available'
                ];
            }

            // Validate amount against method limits
            if ($amount < $depositMethod['minimum_deposit']) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => "Minimum deposit amount is $" . number_format($depositMethod['minimum_deposit'], 2)
                ];
            }

            if ($amount > $depositMethod['maximum_deposit']) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => "Maximum deposit amount is $" . number_format($depositMethod['maximum_deposit'], 2)
                ];
            }

            // Calculate fee
            $feeAmount = 0.0;
            if ($depositMethod['charge_type'] === 'percentage') {
                $feeAmount = $amount * ($depositMethod['charge'] / 100);
            } else {
                $feeAmount = (float)$depositMethod['charge'];
            }

            $netAmount = $amount - $feeAmount;

            // Create transaction record first
            $transactionModel = new TransactionModel();
            $transactionResult = $transactionModel->createTransaction([
                'user_id' => $userId,
                'type' => 'deposit',
                'amount' => $amount,
                'fee' => $feeAmount,
                'net_amount' => $netAmount,
                'status' => 'pending',
                'payment_method' => 'crypto',
                'currency' => $extra['currency'] ?? 'USD',
                'description' => 'Deposit via ' . $depositMethod['name']
            ]);

            if (!$transactionResult['success']) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Failed to create transaction record'
                ];
            }

            $transactionId = $transactionResult['transaction_id'];

            // Generate deposit address if needed (placeholder for crypto auto methods)
            $depositAddress = null;
            if ($depositMethod['type'] === 'auto' && !empty($extra['currency'])) {
                $depositAddress = $this->generateDepositAddress($extra['currency'], $extra['network'] ?? '');
            }

            // Set expiration for crypto deposits (24 hours)
            $expiresAt = null;
            if ($depositMethod['type'] === 'auto') {
                $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours
            }

            // Create deposit record
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} 
                 (transaction_id, user_id, deposit_method_id, requested_amount, fee_amount, 
                  currency, crypto_currency, network, deposit_address, transaction_hash, 
                  gateway_transaction_id, gateway_response, proof_of_payment, admin_notes, 
                  status, verification_status, expires_at, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, NOW(), NOW())"
            );

            $success = $stmt->execute([
                $transactionId,
                $userId,
                $depositMethodId,
                $amount,
                $feeAmount,
                $extra['currency'] ?? 'USD',
                $extra['crypto_currency'] ?? null,
                $extra['network'] ?? null,
                $depositAddress,
                $extra['transaction_hash'] ?? null,
                null, // gateway_transaction_id
                null, // gateway_response
                $extra['proof_of_payment'] ?? null,
                null, // admin_notes
                $expiresAt  // expires_at
            ]);

            if (!$success) {
                throw new PDOException('Failed to create deposit record');
            }

            $depositId = (int)$this->db->lastInsertId();

            $this->db->commit();

            // Log deposit creation
            Security::logAudit(
                $userId, 
                'deposit_created', 
                'deposits', 
                $depositId, 
                null, 
                [
                    'method' => $depositMethod['name'],
                    'amount' => $amount,
                    'fee' => $feeAmount,
                    'currency' => $extra['currency'] ?? 'USD'
                ]
            );

            return [
                'success' => true,
                'deposit_id' => $depositId,
                'transaction_id' => $transactionId,
                'deposit_address' => $depositAddress,
                'expires_at' => $expiresAt,
                'fee_amount' => $feeAmount,
                'net_amount' => $netAmount
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Deposit creation failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to process deposit. Please try again.'
            ];
        }
    }

    /**
     * Update deposit status
     * @param int $id
     * @param string $status
     * @param int|null $adminId
     * @return array
     */
    public function updateStatus(int $id, string $status, ?int $adminId = null): array 
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid deposit ID'
            ];
        }

        $validStatuses = ['pending', 'processing', 'completed', 'failed', 'cancelled', 'expired'];
        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'error' => 'Invalid status'
            ];
        }

        try {
            $this->db->beginTransaction();

            // Get current deposit
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            $deposit = $stmt->fetch();

            if (!$deposit) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Deposit not found'
                ];
            }

            // Update deposit status
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET status = ?, verification_status = 'verified', admin_processed_by = ?, 
                     processed_at = NOW(), updated_at = NOW()
                 WHERE id = ?"
            );

            $success = $stmt->execute([$status, $adminId, $id]);

            if (!$success) {
                throw new PDOException('Failed to update deposit status');
            }

            // Update related transaction status
            $transactionModel = new TransactionModel();
            $stmt = $this->db->prepare(
                "UPDATE transactions 
                 SET status = ?, admin_processed_by = ?, processed_at = NOW(), updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([$status, $adminId, $deposit['transaction_id']]);

            // If completed, update user balance
            if ($status === 'completed') {
                $userModel = new UserModel();
                $balanceResult = $userModel->updateBalance(
                    (int)$deposit['user_id'], 
                    $deposit['requested_amount'] - $deposit['fee_amount'], 
                    'balance'
                );

                if (!$balanceResult['success']) {
                    $this->db->rollBack();
                    return [
                        'success' => false,
                        'error' => 'Failed to update user balance'
                    ];
                }
            }

            $this->db->commit();

            // Log status change
            Security::logAudit(
                (int)$deposit['user_id'], 
                'deposit_status_updated', 
                'deposits', 
                $id, 
                ['status' => $deposit['status']], 
                ['status' => $status, 'admin_id' => $adminId]
            );

            return [
                'success' => true,
                'message' => 'Deposit status updated successfully'
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Failed to update deposit status: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to update deposit status'
            ];
        }
    }

    /**
     * Get active deposit methods
     * @return array
     */
    public function getDepositMethods(): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, name, type, gateway_code, charge, charge_type, 
                        minimum_deposit, maximum_deposit, currency, currency_symbol, 
                        payment_details, status
                 FROM deposit_methods 
                 WHERE status = 1 
                 ORDER BY name ASC"
            );
            
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch deposit methods: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate deposit address for crypto payments (placeholder)
     * @param string $currency
     * @param string $network
     * @return string|null
     */
    private function generateDepositAddress(string $currency, string $network): ?string 
    {
        // Placeholder for crypto payment gateway integration
        // In production, this would integrate with actual payment gateways
        // like NOWPayments, CoinGate, Cryptomus, etc.
        
        return match(strtoupper($currency)) {
            'BTC' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'ETH', 'USDT' => '0x' . bin2hex(random_bytes(20)),
            'LTC' => 'L' . bin2hex(random_bytes(16)),
            default => null
        };
    }
}