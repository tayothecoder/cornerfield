<?php
declare(strict_types=1);

namespace App\Models;

use Exception;

class Profit
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    /**
     * Create detailed profit record with transaction
     */
    public function createDailyProfit($data, $skipTransaction = false)
    {
        try {
            if (!$skipTransaction) {
                $this->db->beginTransaction();
            }

            // Create transaction record first
            $transactionId = $this->db->insert('transactions', [
                'user_id' => $data['user_id'],
                'type' => 'profit',
                'amount' => $data['profit_amount'],
                'fee' => 0,
                'net_amount' => $data['profit_amount'],
                'status' => 'completed',
                'payment_method' => 'system',
                'currency' => 'USD',
                'description' => 'Daily profit from ' . ($data['plan_name'] ?? 'investment'),
                'reference_id' => $data['investment_id'],
                'processed_at' => date('Y-m-d H:i:s')
            ]);

            // Create detailed profit record
            $this->db->insert('profits', [
                'transaction_id' => $transactionId,
                'user_id' => $data['user_id'],
                'investment_id' => $data['investment_id'],
                'schema_id' => $data['schema_id'],
                'profit_amount' => $data['profit_amount'],
                'profit_rate' => $data['daily_rate'],
                'investment_amount' => $data['investment_amount'],
                'profit_day' => $data['profit_day'],
                'profit_type' => 'daily',
                'calculation_date' => date('Y-m-d'),
                'distribution_method' => 'automatic',
                'status' => 'distributed',
                'processed_at' => date('Y-m-d H:i:s'),
                'next_profit_date' => $data['next_profit_date'] ?? null
            ]);

            if (!$skipTransaction) {
                $this->db->commit();
            }

            return $transactionId;

        } catch (Exception $e) {
            if (!$skipTransaction) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    /**
     * Get profit statistics for user
     */
    public function getUserProfitStats($userId)
    {
        return $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_profits,
                SUM(profit_amount) as total_profit_earned,
                AVG(profit_amount) as average_daily_profit,
                COUNT(DISTINCT investment_id) as investments_with_profits,
                MAX(created_at) as last_profit_date
            FROM profits 
            WHERE user_id = ? AND status = 'distributed'
        ", [$userId]);
    }

    /**
     * Get daily profit breakdown for investment
     */
    public function getInvestmentProfitHistory($investmentId)
    {
        return $this->db->fetchAll("
            SELECT 
                profit_day,
                profit_amount,
                calculation_date,
                profit_rate,
                is_final_profit,
                created_at
            FROM profits 
            WHERE investment_id = ? 
            ORDER BY profit_day ASC
        ", [$investmentId]);
    }

    /**
     * Get admin profit overview
     */
    public function getAdminProfitStats($dateFrom = null, $dateTo = null)
    {
        $params = [];
        $whereClause = "WHERE status = 'distributed'";

        if ($dateFrom) {
            $whereClause .= " AND DATE(created_at) >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereClause .= " AND DATE(created_at) <= ?";
            $params[] = $dateTo;
        }

        return $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_profit_distributions,
                SUM(profit_amount) as total_profits_paid,
                COUNT(DISTINCT user_id) as users_benefited,
                COUNT(DISTINCT investment_id) as investments_active,
                AVG(profit_amount) as average_profit_amount
            FROM profits 
            $whereClause
        ", $params);
    }

    /**
     * Get profit distribution queue
     */
    public function getProfitDistributionQueue()
    {
        return $this->db->fetchAll("
            SELECT 
                i.id as investment_id,
                i.user_id,
                u.username,
                i.invest_amount,
                s.name as plan_name,
                s.daily_rate,
                i.next_profit_time,
                DATEDIFF(NOW(), i.created_at) + 1 as profit_day
            FROM investments i
            JOIN users u ON i.user_id = u.id
            JOIN investment_schemas s ON i.schema_id = s.id
            WHERE i.status = 'active' 
            AND i.next_profit_time <= NOW()
            ORDER BY i.next_profit_time ASC
        ");
    }
}
?>