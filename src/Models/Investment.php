<?php
namespace App\Models;

use App\Config\Database;
use App\Models\AdminSettings;
use App\Models\User;
use App\Models\Transaction;
use App\Utils\ReferenceGenerator;
use Exception;

class Investment
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    public function getAllSchemas()
    {
        try {
            return $this->db->fetchAll("
                SELECT * FROM investment_schemas 
                WHERE status = 1 
                ORDER BY min_amount ASC
            ");
        } catch (Exception $e) {
            error_log("Error fetching investment schemas: " . $e->getMessage());
            return [];
        }
    }

    public function getSchemaById($schemaId)
    {
        try {
            return $this->db->fetchOne("
                SELECT * FROM investment_schemas 
                WHERE id = ? AND status = 1
            ", [$schemaId]);
        } catch (Exception $e) {
            error_log("Error fetching investment schema: " . $e->getMessage());
            return false;
        }
    }

    public function createInvestment($data)
    {
        try {
            // Start transaction
            $this->db->beginTransaction();

            // Get schema details
            $schema = $this->getSchemaById($data['schema_id']);
            if (!$schema) {
                throw new Exception('Invalid investment plan');
            }

            // Handle platform fee calculation
            $adminSettingsModel = new AdminSettings($this->db);
            $platformFeeRate = $adminSettingsModel->getSetting('platform_fee_rate', 0);
            
            // Determine amounts based on whether platform fee info was provided
            if (isset($data['original_amount']) && isset($data['platform_fee'])) {
                // Called from invest.php with fee already calculated
                $originalAmount = $data['original_amount'];
                $platformFee = $data['platform_fee'];
                $netAmount = $data['amount']; // This is already the net amount
            } else {
                // Called directly - calculate platform fee
                $originalAmount = $data['amount'];
                $platformFee = $originalAmount * ($platformFeeRate / 100);
                $netAmount = $originalAmount - $platformFee;
            }

            // Validate net amount against schema limits
            if ($netAmount < $schema['min_amount'] || $netAmount > $schema['max_amount']) {
                throw new Exception('Investment amount out of range after platform fee');
            }

            // Check user balance (against original amount including fee)
            $userModel = new User($this->db);
            $user = $userModel->findById($data['user_id']);

            if (!$user) {
                throw new Exception('User not found');
            }

            if ($user['balance'] < $originalAmount) {
                throw new Exception('Insufficient balance');
            }

            // Calculate values based on NET amount (after platform fee)
            $daily_rate = $schema['daily_rate'] / 100;
            $total_profit = $netAmount * $daily_rate * $schema['duration_days'];
            $next_profit_time = date('Y-m-d H:i:s', strtotime('+1 day'));

            // Create investment record (using net amount for profit calculations)
            $investment_id = $this->db->insert('investments', [
                'user_id' => $data['user_id'],
                'schema_id' => $data['schema_id'],
                'invest_amount' => $netAmount,  // Store net amount for profit calculations
                'total_profit_amount' => $total_profit,
                'next_profit_time' => $next_profit_time,
                'number_of_period' => $schema['duration_days'],
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if (!$investment_id) {
                throw new Exception('Failed to create investment record');
            }

            // Deduct FULL amount (including platform fee) from user balance
            $success = $userModel->subtractFromBalance($data['user_id'], $originalAmount);
            if (!$success) {
                throw new Exception('Failed to update user balance');
            }

            // Add to total invested (original amount)
            $userModel->addToTotalInvested($data['user_id'], $originalAmount);

            // Create transaction record with fee details
            $transactionModel = new Transaction($this->db);
            $transactionModel->createTransaction([
                'user_id' => $data['user_id'],
                'type' => 'investment',
                'amount' => $originalAmount,  
                'fee' => $platformFee,       
                'net_amount' => $netAmount,  
                'status' => 'completed',
                'payment_method' => 'balance',
                'currency' => 'USD',
                'gateway_transaction_id' => ReferenceGenerator::generateInvestmentId(),
                'description' => "Investment in {$schema['name']}" . ($platformFee > 0 ? " (Platform fee: {$platformFeeRate}%)" : ""),
                'reference_id' => $investment_id
            ]);

            // Pay referral commission if user has referrer
            $this->payReferralCommission($data['user_id'], $originalAmount);

            $this->db->commit();
            return $investment_id;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Investment creation error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getUserInvestments($userId, $status = null)
    {
        try {
            $sql = "
                SELECT 
                    i.*,
                    s.name as plan_name,
                    s.daily_rate,
                    s.duration_days,
                    DATEDIFF(DATE_ADD(i.created_at, INTERVAL s.duration_days DAY), NOW()) as days_remaining
                FROM investments i
                JOIN investment_schemas s ON i.schema_id = s.id
                WHERE i.user_id = ?
            ";

            $params = [$userId];

            if ($status) {
                $sql .= " AND i.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY i.created_at DESC";

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error fetching user investments: " . $e->getMessage());
            return [];
        }
    }

    public function getInvestmentById($investmentId)
    {
        try {
            return $this->db->fetchOne("
                SELECT 
                    i.*,
                    s.name as plan_name,
                    s.daily_rate,
                    s.duration_days
                FROM investments i
                JOIN investment_schemas s ON i.schema_id = s.id
                WHERE i.id = ?
            ", [$investmentId]);
        } catch (Exception $e) {
            error_log("Error fetching investment: " . $e->getMessage());
            return false;
        }
    }

    public function updateInvestmentStatus($investmentId, $status)
    {
        try {
            return $this->db->update('investments', [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$investmentId]) > 0;
        } catch (Exception $e) {
            error_log("Error updating investment status: " . $e->getMessage());
            return false;
        }
    }

    public function updateProfitTimes($investmentId, $lastProfitTime, $nextProfitTime)
    {
        try {
            return $this->db->update('investments', [
                'last_profit_time' => $lastProfitTime,
                'next_profit_time' => $nextProfitTime,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$investmentId]) > 0;
        } catch (Exception $e) {
            error_log("Error updating profit times: " . $e->getMessage());
            return false;
        }
    }

    public function getInvestmentsDueForProfit()
    {
        try {
            return $this->db->fetchAll("
                SELECT 
                    i.*,
                    s.daily_rate,
                    s.duration_days,
                    u.email as user_email
                FROM investments i
                JOIN investment_schemas s ON i.schema_id = s.id
                JOIN users u ON i.user_id = u.id
                WHERE i.status = 'active' 
                AND i.next_profit_time <= NOW()
                AND u.is_active = 1
            ");
        } catch (Exception $e) {
            error_log("Error fetching investments due for profit: " . $e->getMessage());
            return [];
        }
    }

    public function getUserInvestmentStats($userId)
    {
        try {
            return $this->db->fetchOne("
                SELECT 
                    COUNT(*) as total_investments,
                    SUM(invest_amount) as total_invested,
                    SUM(CASE WHEN status = 'active' THEN invest_amount ELSE 0 END) as active_investments,
                    SUM(CASE WHEN status = 'completed' THEN invest_amount ELSE 0 END) as completed_investments,
                    AVG(CASE WHEN status = 'active' THEN 
                        DATEDIFF(DATE_ADD(created_at, INTERVAL number_of_period DAY), NOW()) 
                        ELSE 0 END) as avg_days_remaining
                FROM investments 
                WHERE user_id = ?
            ", [$userId]) ?: [
                'total_investments' => 0,
                'total_invested' => 0,
                'active_investments' => 0,
                'completed_investments' => 0,
                'avg_days_remaining' => 0
            ];
        } catch (Exception $e) {
            error_log("Error fetching user investment stats: " . $e->getMessage());
            return [
                'total_investments' => 0,
                'total_invested' => 0,
                'active_investments' => 0,
                'completed_investments' => 0,
                'avg_days_remaining' => 0
            ];
        }
    }

    public function calculateDailyProfit($investAmount, $dailyRate)
    {
        return ($investAmount * $dailyRate) / 100;
    }

    public function calculateTotalProfit($investAmount, $totalReturn)
    {
        return ($investAmount * $totalReturn) / 100;
    }

    public function getActiveInvestmentCount($userId)
    {
        try {
            $result = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM investments 
                WHERE user_id = ? AND status = 'active'
            ", [$userId]);
            return (int) ($result['count'] ?? 0);
        } catch (Exception $e) {
            error_log("Error getting active investment count: " . $e->getMessage());
            return 0;
        }
    }

    public function getAllInvestments($page = 1, $limit = 50, $status = null)
    {
        try {
            $offset = ($page - 1) * $limit;

            $sql = "
                SELECT 
                    i.*,
                    s.name as plan_name,
                    u.username,
                    u.email
                FROM investments i
                JOIN investment_schemas s ON i.schema_id = s.id
                JOIN users u ON i.user_id = u.id
            ";

            $params = [];

            if ($status) {
                $sql .= " WHERE i.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY i.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error fetching all investments: " . $e->getMessage());
            return [];
        }
    }

    public function getInvestmentStatistics()
    {
        try {
            return $this->db->fetchOne("
                SELECT 
                    COUNT(*) as total_investments,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_investments,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_investments,
                    SUM(invest_amount) as total_amount_invested,
                    SUM(CASE WHEN status = 'active' THEN invest_amount ELSE 0 END) as active_amount,
                    AVG(invest_amount) as average_investment_amount
                FROM investments
            ") ?: [
                'total_investments' => 0,
                'active_investments' => 0,
                'completed_investments' => 0,
                'total_amount_invested' => 0,
                'active_amount' => 0,
                'average_investment_amount' => 0
            ];
        } catch (Exception $e) {
            error_log("Error fetching investment statistics: " . $e->getMessage());
            return [
                'total_investments' => 0,
                'active_investments' => 0,
                'completed_investments' => 0,
                'total_amount_invested' => 0,
                'active_amount' => 0,
                'average_investment_amount' => 0
            ];
        }
    }

    public function getActiveSchemas()
    {
        return $this->getAllSchemas();
    }

    public function schemaHasActiveInvestments($schemaId)
    {
        try {
            $result = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM investments 
                WHERE schema_id = ? AND status = 'active'
            ", [$schemaId]);
            return (int) ($result['count'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log("Error checking schema active investments: " . $e->getMessage());
            return false;
        }
    }

    public function deleteSchema($schemaId)
    {
        try {
            return $this->db->delete('investment_schemas', 'id = ?', [$schemaId]) > 0;
        } catch (Exception $e) {
            error_log("Error deleting investment schema: " . $e->getMessage());
            return false;
        }
    }

    public function createSchema($data)
    {
        try {
            return $this->db->insert('investment_schemas', [
                'name' => $data['name'],
                'min_amount' => $data['min_amount'],
                'max_amount' => $data['max_amount'],
                'daily_rate' => $data['daily_rate'],
                'duration_days' => $data['duration_days'],
                'total_return' => $data['total_return'],
                'featured' => $data['featured'] ?? 0,
                'status' => $data['status'] ?? 1,
                'description' => $data['description'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error creating investment schema: " . $e->getMessage());
            return false;
        }
    }

    public function updateSchema($schemaId, $data)
    {
        try {
            $updateData = [];
            $allowedFields = [
                'name',
                'min_amount',
                'max_amount',
                'daily_rate',
                'duration_days',
                'total_return',
                'featured',
                'status',
                'description'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (empty($updateData)) {
                return false;
            }

            $updateData['updated_at'] = date('Y-m-d H:i:s');

            return $this->db->update('investment_schemas', $updateData, 'id = ?', [$schemaId]) > 0;
        } catch (Exception $e) {
            error_log("Error updating investment schema: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Pay referral commission for investment
     */
    private function payReferralCommission($userId, $investmentAmount)
    {
        try {
            // Get dynamic referral rate
            $adminSettingsModel = new AdminSettings($this->db);
            $referralRate = $adminSettingsModel->getSetting('referral_bonus_rate', 0);
            
            if ($referralRate <= 0) {
                return; // No referral bonus configured
            }
            
            // Find if user has a referrer
            $user = $this->db->fetchOne("SELECT referred_by FROM users WHERE id = ?", [$userId]);
            if (!$user || !$user['referred_by']) {
                return; // No referrer
            }
            
            $referrerId = $user['referred_by'];
            $commissionAmount = $investmentAmount * ($referralRate / 100);
            
            // Add commission to referrer's balance
            $this->db->update('users', [
                'balance' => $this->db->raw('balance + ' . $commissionAmount)
            ], 'id = ?', [$referrerId]);
            
            // Create transaction record for referral commission
            $transactionModel = new Transaction($this->db);
            
            $transactionModel->createTransaction([
                'user_id' => $referrerId,
                'type' => 'referral',
                'amount' => $commissionAmount,
                'net_amount' => $commissionAmount,
                'description' => "Referral commission ({$referralRate}%) from user investment",
                'status' => 'completed',
                'payment_method' => 'system',
                'reference_id' => $userId
            ]); // Create referral commission transaction
            
            // Update referral record
            $this->db->update('referrals', [
                'total_earned' => $this->db->raw('total_earned + ' . $commissionAmount)
            ], 'referrer_id = ? AND referred_id = ?', [$referrerId, $userId]);
            
            error_log("Referral commission paid: {$commissionAmount} to user {$referrerId} for investment by user {$userId}");
            
        } catch (Exception $e) {
            error_log("Referral commission error: " . $e->getMessage());
            // Don't throw exception - referral failure shouldn't break investment
        }
    }
}
?>