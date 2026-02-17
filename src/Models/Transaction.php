<?php
namespace App\Models;

use App\Config\Database;
use Exception;

class Transaction
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    public function createTransaction($data)
    {
        try {
            return $this->db->insert('transactions', [
                'user_id' => $data['user_id'],
                'type' => $data['type'],
                'amount' => $data['amount'],
                'fee' => $data['fee'] ?? 0,
                'net_amount' => $data['net_amount'],
                'status' => $data['status'] ?? 'pending',
                'payment_method' => $data['payment_method'] ?? 'crypto',
                'payment_gateway' => $data['payment_gateway'] ?? null,
                'gateway_transaction_id' => $data['gateway_transaction_id'] ?? null,
                'wallet_address' => $data['wallet_address'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'description' => $data['description'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'admin_note' => $data['admin_note'] ?? null,
                'processed_by' => $data['processed_by'] ?? null,
                'processed_at' => $data['processed_at'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error creating transaction: " . $e->getMessage());
            return false;
        }
    }

    public function getTransactionById($transactionId)
    {
        try {
            return $this->db->fetchOne("
                SELECT 
                    t.*,
                    u.username,
                    u.email
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                WHERE t.id = ?
            ", [$transactionId]);
        } catch (Exception $e) {
            error_log("Error fetching transaction: " . $e->getMessage());
            return false;
        }
    }

    public function getUserTransactions($userId, $type = null, $limit = 50, $offset = 0)
    {
        try {
            $sql = "
                SELECT 
                    id,
                    type,
                    amount,
                    fee,
                    net_amount,
                    status,
                    payment_method,
                    currency,
                    description,
                    reference_id,
                    created_at
                FROM transactions 
                WHERE user_id = ?
            ";

            $params = [$userId];

            if ($type) {
                $sql .= " AND type = ?";
                $params[] = $type;
            }

            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error fetching user transactions: " . $e->getMessage());
            return [];
        }
    }

    public function updateTransactionStatus($transactionId, $status, $processedBy = null, $isAdmin = false)
    {
        try {
            $updateData = [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($processedBy) {
                if ($isAdmin) {
                    $updateData['admin_processed_by'] = $processedBy;
                    $updateData['processed_by_type'] = 'admin';
                    $updateData['processed_by'] = null; // Clear user processed_by
                } else {
                    $updateData['processed_by'] = $processedBy;
                    $updateData['processed_by_type'] = 'user';
                    $updateData['admin_processed_by'] = null; // Clear admin processed_by
                }
                $updateData['processed_at'] = date('Y-m-d H:i:s');
            }

            $result = $this->db->update('transactions', $updateData, 'id = ?', [$transactionId]);
            return $result > 0;

        } catch (Exception $e) {
            error_log("Error updating transaction status: " . $e->getMessage());
            return false;
        }
    }

    public function getUserTransactionStats($userId)
    {
        try {
            return $this->db->fetchOne("
                SELECT 
                    COUNT(*) as total_transactions,
                    COUNT(CASE WHEN type = 'deposit' THEN 1 END) as total_deposits,
                    COUNT(CASE WHEN type = 'withdrawal' THEN 1 END) as total_withdrawals,
                    COUNT(CASE WHEN type = 'investment' THEN 1 END) as total_investments,
                    COUNT(CASE WHEN type = 'profit' THEN 1 END) as total_profits,
                    SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN net_amount ELSE 0 END) as total_deposited,
                    SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN net_amount ELSE 0 END) as total_withdrawn,
                    SUM(CASE WHEN type = 'profit' AND status = 'completed' THEN net_amount ELSE 0 END) as total_profit_earned
                FROM transactions 
                WHERE user_id = ?
            ", [$userId]) ?: [
                'total_transactions' => 0,
                'total_deposits' => 0,
                'total_withdrawals' => 0,
                'total_investments' => 0,
                'total_profits' => 0,
                'total_deposited' => 0,
                'total_withdrawn' => 0,
                'total_profit_earned' => 0
            ];
        } catch (Exception $e) {
            error_log("Error fetching user transaction stats: " . $e->getMessage());
            return [
                'total_transactions' => 0,
                'total_deposits' => 0,
                'total_withdrawals' => 0,
                'total_investments' => 0,
                'total_profits' => 0,
                'total_deposited' => 0,
                'total_withdrawn' => 0,
                'total_profit_earned' => 0
            ];
        }
    }

    public function createDepositTransaction($userId, $amount, $paymentData = [])
    {
        $transactionData = [
            'user_id' => $userId,
            'type' => 'deposit',
            'amount' => $amount,
            'fee' => $paymentData['fee'] ?? 0,
            'net_amount' => $amount - ($paymentData['fee'] ?? 0),
            'status' => $paymentData['status'] ?? 'pending',
            'payment_method' => $paymentData['payment_method'] ?? 'crypto',
            'payment_gateway' => $paymentData['payment_gateway'] ?? null,
            'gateway_transaction_id' => $paymentData['gateway_transaction_id'] ?? null,
            'wallet_address' => $paymentData['wallet_address'] ?? null,
            'currency' => $paymentData['currency'] ?? 'USD',
            'description' => "Deposit via " . ($paymentData['payment_method'] ?? 'crypto')
        ];

        return $this->createTransaction($transactionData);
    }

    public function createWithdrawalTransaction($userId, $amount, $withdrawalData = [])
    {
        $fee = $withdrawalData['fee'] ?? 0;
        $netAmount = $amount;

        $transactionData = [
            'user_id' => $userId,
            'type' => 'withdrawal',
            'amount' => $amount,        
            'fee' => $fee,  
            'net_amount' => $netAmount,
            'status' => 'pending',
            'payment_method' => $withdrawalData['payment_method'] ?? 'crypto',
            'wallet_address' => $withdrawalData['wallet_address'] ?? null,
            'currency' => $withdrawalData['currency'] ?? 'USD',
            'description' => "Withdrawal to " . ($withdrawalData['wallet_address'] ?? 'crypto wallet')
        ];

        return $this->createTransaction($transactionData);
    }

    public function createProfitTransaction($userId, $amount, $investmentId)
    {
        $transactionData = [
            'user_id' => $userId,
            'type' => 'profit',
            'amount' => $amount,
            'fee' => 0,
            'net_amount' => $amount,
            'status' => 'completed',
            'payment_method' => 'system',
            'currency' => 'USD',
            'description' => "Daily profit from investment",
            'reference_id' => $investmentId,
            'processed_at' => date('Y-m-d H:i:s')
        ];

        return $this->createTransaction($transactionData);
    }

    public function createReferralTransaction($userId, $amount, $referredUserId, $level = 1)
    {
        $transactionData = [
            'user_id' => $userId,
            'type' => 'referral',
            'amount' => $amount,
            'fee' => 0,
            'net_amount' => $amount,
            'status' => 'completed',
            'payment_method' => 'system',
            'currency' => 'USD',
            'description' => "Level {$level} referral commission",
            'reference_id' => $referredUserId,
            'processed_at' => date('Y-m-d H:i:s')
        ];

        return $this->createTransaction($transactionData);
    }

    public function getPendingTransactions($type = null)
    {
        try {
            $sql = "
                SELECT 
                    t.*,
                    u.username,
                    u.email
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                WHERE t.status = 'pending'
            ";

            $params = [];

            if ($type) {
                $sql .= " AND t.type = ?";
                $params[] = $type;
            }

            $sql .= " ORDER BY t.created_at ASC";

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error fetching pending transactions: " . $e->getMessage());
            return [];
        }
    }

    public function getAllTransactions($page = 1, $limit = 50, $filters = [])
    {
        try {
            $offset = ($page - 1) * $limit;

            $sql = "
                SELECT 
                    t.*,
                    u.username,
                    u.email
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                WHERE 1=1
            ";

            $params = [];

            if (!empty($filters['type'])) {
                $sql .= " AND t.type = ?";
                $params[] = $filters['type'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND t.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['user_id'])) {
                $sql .= " AND t.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND DATE(t.created_at) >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND DATE(t.created_at) <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error fetching all transactions: " . $e->getMessage());
            return [];
        }
    }

    public function getTransactionStatistics($dateFrom = null, $dateTo = null)
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_transactions,
                    COUNT(CASE WHEN type = 'deposit' THEN 1 END) as total_deposits,
                    COUNT(CASE WHEN type = 'withdrawal' THEN 1 END) as total_withdrawals,
                    COUNT(CASE WHEN type = 'investment' THEN 1 END) as total_investments,
                    COUNT(CASE WHEN type = 'profit' THEN 1 END) as total_profits,
                    SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN net_amount ELSE 0 END) as total_deposited,
                    SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN net_amount ELSE 0 END) as total_withdrawn,
                    SUM(CASE WHEN type = 'investment' AND status = 'completed' THEN net_amount ELSE 0 END) as total_invested,
                    SUM(CASE WHEN type = 'profit' AND status = 'completed' THEN net_amount ELSE 0 END) as total_profits_paid
                FROM transactions
                WHERE 1=1
            ";

            $params = [];

            if ($dateFrom) {
                $sql .= " AND DATE(created_at) >= ?";
                $params[] = $dateFrom;
            }

            if ($dateTo) {
                $sql .= " AND DATE(created_at) <= ?";
                $params[] = $dateTo;
            }

            return $this->db->fetchOne($sql, $params) ?: [
                'total_transactions' => 0,
                'total_deposits' => 0,
                'total_withdrawals' => 0,
                'total_investments' => 0,
                'total_profits' => 0,
                'total_deposited' => 0,
                'total_withdrawn' => 0,
                'total_invested' => 0,
                'total_profits_paid' => 0
            ];
        } catch (Exception $e) {
            error_log("Error fetching transaction statistics: " . $e->getMessage());
            return [
                'total_transactions' => 0,
                'total_deposits' => 0,
                'total_withdrawals' => 0,
                'total_investments' => 0,
                'total_profits' => 0,
                'total_deposited' => 0,
                'total_withdrawn' => 0,
                'total_invested' => 0,
                'total_profits_paid' => 0
            ];
        }
    }

    public function addAdminNote($transactionId, $note, $adminId)
    {
        try {
            return $this->db->update('transactions', [
                'admin_note' => $note,
                'admin_processed_by' => $adminId,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$transactionId]) > 0;
        } catch (Exception $e) {
            error_log("Error adding admin note to transaction: " . $e->getMessage());
            return false;
        }
    }

    public static function generateReference($type = 'TXN')
    {
        return $type . date('Ymd') . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
    }
}
?>