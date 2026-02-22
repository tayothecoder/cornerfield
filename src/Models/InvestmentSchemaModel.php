<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Models/InvestmentSchemaModel.php
 * Purpose: Investment schema model for managing investment plans
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

class InvestmentSchemaModel extends BaseModel
{
    protected string $table = 'investment_schemas';
    
    protected array $fillable = [
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

    /**
     * Find all active investment plans
     * @return array
     */
    public function findActive(): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, name, min_amount, max_amount, daily_rate, duration_days, 
                        total_return, featured, status, description, created_at, updated_at
                 FROM {$this->table} 
                 WHERE status = 1 
                 ORDER BY featured DESC, min_amount ASC"
            );
            
            $stmt->execute();
            $schemas = $stmt->fetchAll();
            
            // Add calculated fields for each schema
            foreach ($schemas as &$schema) {
                $schema = $this->enrichSchemaData($schema);
            }
            
            return $schemas;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch active investment schemas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find featured investment plans only
     * @return array
     */
    public function findFeatured(): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, name, min_amount, max_amount, daily_rate, duration_days, 
                        total_return, featured, status, description, created_at, updated_at
                 FROM {$this->table} 
                 WHERE status = 1 AND featured = 1 
                 ORDER BY min_amount ASC"
            );
            
            $stmt->execute();
            $schemas = $stmt->fetchAll();
            
            // Add calculated fields for each schema
            foreach ($schemas as &$schema) {
                $schema = $this->enrichSchemaData($schema);
            }
            
            return $schemas;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch featured investment schemas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get schema by ID with validation
     * @param int $id
     * @return array|null
     */
    public function findByIdActive(int $id): ?array 
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Schema ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, name, min_amount, max_amount, daily_rate, duration_days, 
                        total_return, featured, status, description, created_at, updated_at
                 FROM {$this->table} 
                 WHERE id = ? AND status = 1"
            );
            
            $stmt->execute([$id]);
            $schema = $stmt->fetch();
            
            if ($schema) {
                $schema = $this->enrichSchemaData($schema);
            }
            
            return $schema ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch investment schema {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get popular investment plans based on usage
     * @param int $limit
     * @return array
     */
    public function findPopular(int $limit = 6): array 
    {
        if ($limit <= 0 || $limit > 20) {
            $limit = 6;
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT s.id, s.name, s.min_amount, s.max_amount, s.daily_rate, 
                        s.duration_days, s.total_return, s.featured, s.status, 
                        s.description, s.created_at, s.updated_at,
                        COUNT(i.id) as investment_count,
                        COALESCE(SUM(i.invest_amount), 0) as total_invested
                 FROM {$this->table} s
                 LEFT JOIN investments i ON s.id = i.schema_id
                 WHERE s.status = 1 
                 GROUP BY s.id, s.name, s.min_amount, s.max_amount, s.daily_rate, 
                          s.duration_days, s.total_return, s.featured, s.status, 
                          s.description, s.created_at, s.updated_at
                 ORDER BY investment_count DESC, total_invested DESC, s.featured DESC
                 LIMIT ?"
            );
            
            $stmt->execute([$limit]);
            $schemas = $stmt->fetchAll();
            
            // Add calculated fields for each schema
            foreach ($schemas as &$schema) {
                $schema = $this->enrichSchemaData($schema);
                $schema['popularity_rank'] = (int)$schema['investment_count'];
                $schema['total_invested_amount'] = (float)$schema['total_invested'];
            }
            
            return $schemas;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch popular investment schemas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get schemas suitable for specific amount
     * @param float $amount
     * @return array
     */
    public function findSuitableForAmount(float $amount): array 
    {
        if (!Validator::isValidAmount($amount)) {
            throw new InvalidArgumentException('Invalid amount provided');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, name, min_amount, max_amount, daily_rate, duration_days, 
                        total_return, featured, status, description, created_at, updated_at
                 FROM {$this->table} 
                 WHERE status = 1 AND min_amount <= ? AND max_amount >= ?
                 ORDER BY featured DESC, daily_rate DESC"
            );
            
            $stmt->execute([$amount, $amount]);
            $schemas = $stmt->fetchAll();
            
            // Add calculated fields for each schema
            foreach ($schemas as &$schema) {
                $schema = $this->enrichSchemaData($schema);
                $schema['recommended'] = true; // Mark as recommended for this amount
            }
            
            return $schemas;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch schemas suitable for amount {$amount}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get investment schema statistics
     * @param int $schemaId
     * @return array
     */
    public function getSchemaStats(int $schemaId): array 
    {
        if ($schemaId <= 0) {
            throw new InvalidArgumentException('Schema ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(i.id) as total_investments,
                    COUNT(CASE WHEN i.status = 'active' THEN 1 END) as active_investments,
                    COUNT(CASE WHEN i.status = 'completed' THEN 1 END) as completed_investments,
                    COALESCE(SUM(i.invest_amount), 0) as total_amount_invested,
                    COALESCE(SUM(i.total_profit_amount), 0) as total_profit_paid,
                    COALESCE(AVG(i.invest_amount), 0) as average_investment_amount,
                    MIN(i.invest_amount) as min_investment_amount,
                    MAX(i.invest_amount) as max_investment_amount,
                    COUNT(DISTINCT i.user_id) as unique_investors
                 FROM investments i
                 WHERE i.schema_id = ?"
            );
            
            $stmt->execute([$schemaId]);
            $stats = $stmt->fetch();
            
            return [
                'total_investments' => (int)$stats['total_investments'],
                'active_investments' => (int)$stats['active_investments'],
                'completed_investments' => (int)$stats['completed_investments'],
                'total_amount_invested' => (float)$stats['total_amount_invested'],
                'total_profit_paid' => (float)$stats['total_profit_paid'],
                'average_investment_amount' => (float)$stats['average_investment_amount'],
                'min_investment_amount' => (float)$stats['min_investment_amount'],
                'max_investment_amount' => (float)$stats['max_investment_amount'],
                'unique_investors' => (int)$stats['unique_investors'],
                'success_rate' => $stats['total_investments'] > 0 ? 
                    round(($stats['completed_investments'] / $stats['total_investments']) * 100, 2) : 0
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to fetch schema stats for ID {$schemaId}: " . $e->getMessage());
            return [
                'total_investments' => 0,
                'active_investments' => 0,
                'completed_investments' => 0,
                'total_amount_invested' => 0.0,
                'total_profit_paid' => 0.0,
                'average_investment_amount' => 0.0,
                'min_investment_amount' => 0.0,
                'max_investment_amount' => 0.0,
                'unique_investors' => 0,
                'success_rate' => 0
            ];
        }
    }

    /**
     * Validate investment amount against schema
     * @param int $schemaId
     * @param float $amount
     * @return array
     */
    public function validateInvestmentAmount(int $schemaId, float $amount): array 
    {
        $schema = $this->findByIdActive($schemaId);
        
        if (!$schema) {
            return [
                'valid' => false,
                'error' => 'Investment plan not available'
            ];
        }

        if (!Validator::isValidAmount($amount)) {
            return [
                'valid' => false,
                'error' => 'Invalid investment amount'
            ];
        }

        if (!Validator::isValidInvestmentAmount($amount, (float)$schema['min_amount'], (float)$schema['max_amount'])) {
            return [
                'valid' => false,
                'error' => "Investment amount must be between \${$schema['min_amount']} and \${$schema['max_amount']}"
            ];
        }

        // Calculate expected returns
        $dailyProfit = $amount * ((float)$schema['daily_rate'] / 100);
        $totalProfit = $amount * ((float)$schema['total_return'] / 100);
        $totalReturn = $amount + $totalProfit;

        return [
            'valid' => true,
            'schema' => $schema,
            'calculations' => [
                'investment_amount' => $amount,
                'daily_profit' => $dailyProfit,
                'total_profit' => $totalProfit,
                'total_return' => $totalReturn,
                'roi_percentage' => (float)$schema['total_return']
            ]
        ];
    }

    /**
     * Check if user can invest in schema (rate limiting)
     * @param int $userId
     * @param int $schemaId
     * @return array
     */
    public function canUserInvest(int $userId, int $schemaId): array 
    {
        if ($userId <= 0) {
            return [
                'can_invest' => false,
                'error' => 'Invalid user ID'
            ];
        }

        try {
            // Check if schema is active
            $schema = $this->findByIdActive($schemaId);
            if (!$schema) {
                return [
                    'can_invest' => false,
                    'error' => 'Investment plan not available'
                ];
            }

            // Check user's active investments in this schema (limit to prevent abuse)
            $maxActiveInvestments = (int)($_ENV['MAX_ACTIVE_INVESTMENTS_PER_SCHEMA'] ?? 5);
            
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as active_count 
                 FROM investments 
                 WHERE user_id = ? AND schema_id = ? AND status = 'active'"
            );
            
            $stmt->execute([$userId, $schemaId]);
            $result = $stmt->fetch();
            
            if ((int)$result['active_count'] >= $maxActiveInvestments) {
                return [
                    'can_invest' => false,
                    'error' => "Maximum {$maxActiveInvestments} active investments allowed per plan"
                ];
            }

            // Check daily investment limit
            $maxDailyInvestment = (float)($_ENV['MAX_DAILY_INVESTMENT_AMOUNT'] ?? 100000.0);
            
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(invest_amount), 0) as today_total 
                 FROM investments 
                 WHERE user_id = ? AND DATE(created_at) = CURDATE()"
            );
            
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            
            $todayTotal = (float)$result['today_total'];
            
            return [
                'can_invest' => true,
                'remaining_daily_limit' => max(0, $maxDailyInvestment - $todayTotal),
                'active_investments_in_schema' => (int)($result['active_count'] ?? 0),
                'max_active_investments' => $maxActiveInvestments
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to check user investment eligibility: " . $e->getMessage());
            return [
                'can_invest' => false,
                'error' => 'Unable to verify investment eligibility'
            ];
        }
    }

    /**
     * Get all schemas with their statistics (admin view)
     * @return array
     */
    public function getAllWithStats(): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT s.id, s.name, s.min_amount, s.max_amount, s.daily_rate, 
                        s.duration_days, s.total_return, s.featured, s.status, 
                        s.description, s.created_at, s.updated_at,
                        COUNT(i.id) as investment_count,
                        COALESCE(SUM(i.invest_amount), 0) as total_invested,
                        COALESCE(SUM(i.total_profit_amount), 0) as total_profit_paid,
                        COUNT(CASE WHEN i.status = 'active' THEN 1 END) as active_investments
                 FROM {$this->table} s
                 LEFT JOIN investments i ON s.id = i.schema_id
                 GROUP BY s.id, s.name, s.min_amount, s.max_amount, s.daily_rate, 
                          s.duration_days, s.total_return, s.featured, s.status, 
                          s.description, s.created_at, s.updated_at
                 ORDER BY s.featured DESC, s.created_at DESC"
            );
            
            $stmt->execute();
            $schemas = $stmt->fetchAll();
            
            // Add calculated fields for each schema
            foreach ($schemas as &$schema) {
                $schema = $this->enrichSchemaData($schema);
                $schema['investment_count'] = (int)$schema['investment_count'];
                $schema['total_invested'] = (float)$schema['total_invested'];
                $schema['total_profit_paid'] = (float)$schema['total_profit_paid'];
                $schema['active_investments'] = (int)$schema['active_investments'];
            }
            
            return $schemas;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch all schemas with stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Enrich schema data with calculated fields
     * @param array $schema
     * @return array
     */
    private function enrichSchemaData(array $schema): array 
    {
        $minAmount = (float)$schema['min_amount'];
        $maxAmount = (float)$schema['max_amount'];
        $dailyRate = (float)$schema['daily_rate'];
        $durationDays = (int)$schema['duration_days'];
        $totalReturn = (float)$schema['total_return'];

        // Calculate various metrics
        $schema['min_daily_profit'] = $minAmount * ($dailyRate / 100);
        $schema['max_daily_profit'] = $maxAmount * ($dailyRate / 100);
        $schema['min_total_profit'] = $minAmount * ($totalReturn / 100);
        $schema['max_total_profit'] = $maxAmount * ($totalReturn / 100);
        $schema['min_total_return'] = $minAmount + $schema['min_total_profit'];
        $schema['max_total_return'] = $maxAmount + $schema['max_total_profit'];
        
        // Risk level based on daily rate
        if ($dailyRate <= 1.5) {
            $schema['risk_level'] = 'Low';
            $schema['risk_color'] = 'success';
        } elseif ($dailyRate <= 3.0) {
            $schema['risk_level'] = 'Medium';
            $schema['risk_color'] = 'warning';
        } else {
            $schema['risk_level'] = 'High';
            $schema['risk_color'] = 'danger';
        }
        
        // Investment tier based on minimum amount
        if ($minAmount < 1000) {
            $schema['tier'] = 'Starter';
            $schema['tier_icon'] = 'starter';
        } elseif ($minAmount < 5000) {
            $schema['tier'] = 'Standard';
            $schema['tier_icon'] = 'growth';
        } elseif ($minAmount < 20000) {
            $schema['tier'] = 'Premium';
            $schema['tier_icon'] = 'premium';
        } else {
            $schema['tier'] = 'Elite';
            $schema['tier_icon'] = 'elite';
        }
        
        // Format for display
        $schema['formatted_min_amount'] = number_format($minAmount, 2);
        $schema['formatted_max_amount'] = number_format($maxAmount, 2);
        $schema['formatted_daily_rate'] = number_format($dailyRate, 2);
        $schema['formatted_total_return'] = number_format($totalReturn, 2);
        $schema['formatted_duration'] = $durationDays . ' days';
        
        // Convert boolean fields
        $schema['featured'] = (bool)$schema['featured'];
        $schema['status'] = (bool)$schema['status'];
        
        return $schema;
    }
}