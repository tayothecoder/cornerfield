<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Controllers/InvestmentController.php
 * Purpose: Investment controller with CSRF protection and validation
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Controllers;

use App\Models\InvestmentModel;
use App\Models\InvestmentSchemaModel;
use App\Models\UserModel;
use App\Utils\Security;
use App\Utils\Validator;
use App\Utils\JsonResponse;

class InvestmentController 
{
    private InvestmentModel $investmentModel;
    private InvestmentSchemaModel $schemaModel;
    private UserModel $userModel;
    
    public function __construct() 
    {
        $this->investmentModel = new InvestmentModel();
        $this->schemaModel = new InvestmentSchemaModel();
        $this->userModel = new UserModel();
    }

    /**
     * Get all active investment plans
     * @return void
     */
    /**
     * Get investment plans data for template rendering
     * @return array
     */
    public function getInvestmentPlans(): array
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $plans = $this->schemaModel->findActive();

        // map plans to template format
        $mappedPlans = [];
        foreach ($plans as $plan) {
            $mappedPlans[] = [
                'id' => (int)$plan['id'],
                'name' => $plan['name'],
                'description' => $plan['description'] ?? '',
                'min_amount' => (float)($plan['min_amount'] ?? 100),
                'max_amount' => (float)($plan['max_amount'] ?? 10000),
                'daily_return' => (float)($plan['daily_rate'] ?? 0),
                'duration_days' => (int)($plan['duration_days'] ?? 30),
                'total_return' => (float)($plan['total_return'] ?? 0),
                'features' => ['Daily Payouts', '24/7 Support'],
                'popular' => (bool)($plan['featured'] ?? false),
                'color' => 'blue',
            ];
        }

        $userBalance = 0.0;
        $activeInvestments = 0;
        if ($userId > 0) {
            $userModel = new \App\Models\UserModel();
            $user = $userModel->findById($userId);
            $userBalance = $user ? (float)$user['balance'] : 0.0;
            $stats = $this->investmentModel->getUserStats($userId);
            $activeInvestments = $stats['active_investments'] ?? 0;
        }

        return [
            'plans' => $mappedPlans,
            'userBalance' => $userBalance,
            'activeInvestments' => $activeInvestments,
        ];
    }

    public function getPlans(): void 
    {
        try {
            $plans = $this->schemaModel->findActive();
            
            JsonResponse::success([
                'plans' => $plans,
                'total_plans' => count($plans)
            ]);
            
        } catch (\Exception $e) {
            error_log("Failed to fetch investment plans: " . $e->getMessage());
            JsonResponse::error('Unable to load investment plans');
        }
    }

    /**
     * Get featured investment plans
     * @return void
     */
    public function getFeaturedPlans(): void 
    {
        try {
            $plans = $this->schemaModel->findFeatured();
            
            JsonResponse::success([
                'featured_plans' => $plans,
                'total_featured' => count($plans)
            ]);
            
        } catch (\Exception $e) {
            error_log("Failed to fetch featured investment plans: " . $e->getMessage());
            JsonResponse::error('Unable to load featured investment plans');
        }
    }

    /**
     * Get popular investment plans
     * @return void
     */
    public function getPopularPlans(): void 
    {
        try {
            $limit = Validator::sanitizeInt($_GET['limit'] ?? 6);
            $limit = max(1, min(20, $limit)); // Ensure reasonable limit
            
            $plans = $this->schemaModel->findPopular($limit);
            
            JsonResponse::success([
                'popular_plans' => $plans,
                'total_popular' => count($plans)
            ]);
            
        } catch (\Exception $e) {
            error_log("Failed to fetch popular investment plans: " . $e->getMessage());
            JsonResponse::error('Unable to load popular investment plans');
        }
    }

    /**
     * Process new investment
     * @return void
     */
    public function invest(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized('Please log in to create an investment');
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];

        // CSRF Protection
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            JsonResponse::forbidden('Invalid security token');
            return;
        }

        // Rate limiting
        if (!Security::rateLimitCheck((string)$userId, 'investment_create', 5, 3600)) {
            JsonResponse::error('Too many investment attempts. Please wait 1 hour.', 429);
            return;
        }

        // Input validation
        $schemaId = Validator::sanitizeInt($_POST['schema_id'] ?? 0);
        $amount = Validator::sanitizeFloat($_POST['amount'] ?? 0);

        $validationErrors = [];

        if ($schemaId <= 0) {
            $validationErrors['schema_id'] = 'Please select a valid investment plan';
        }

        if (!Validator::isValidAmount($amount)) {
            $validationErrors['amount'] = 'Please enter a valid investment amount';
        }

        if (!empty($validationErrors)) {
            JsonResponse::validationError($validationErrors);
            return;
        }

        try {
            // Validate investment schema and amount
            $validation = $this->schemaModel->validateInvestmentAmount($schemaId, $amount);
            if (!$validation['valid']) {
                JsonResponse::error($validation['error']);
                return;
            }

            // Check if user can invest (rate limiting per schema)
            $eligibility = $this->schemaModel->canUserInvest($userId, $schemaId);
            if (!$eligibility['can_invest']) {
                JsonResponse::error($eligibility['error']);
                return;
            }

            // Process the investment
            $result = $this->investmentModel->createInvestment($userId, $schemaId, $amount);

            if ($result['success']) {
                // Add calculation details to response
                $responseData = [
                    'investment' => $result,
                    'calculations' => $validation['calculations'],
                    'schema' => $validation['schema']
                ];

                JsonResponse::success($responseData);
            } else {
                JsonResponse::error($result['error'] ?? 'Investment creation failed');
            }

        } catch (\Exception $e) {
            error_log("Investment processing failed for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Unable to process investment. Please try again.');
        }
    }

    /**
     * Get user's investments with plan details
     * @return void
     */
    public function getMyInvestments(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized('Please log in to view your investments');
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];

        try {
            // Get investment type filter
            $statusFilter = $_GET['status'] ?? 'all';
            
            if ($statusFilter === 'active') {
                $investments = $this->investmentModel->findActiveByUserId($userId);
            } else {
                $investments = $this->investmentModel->findByUserId($userId);
            }

            // Enrich investments with progress and calculations
            $enrichedInvestments = [];
            
            foreach ($investments as $investment) {
                $enriched = $this->enrichInvestmentData($investment);
                
                // Apply status filter if not 'all'
                if ($statusFilter !== 'all' && $enriched['status'] !== $statusFilter) {
                    continue;
                }
                
                $enrichedInvestments[] = $enriched;
            }

            // Get investment statistics
            $stats = $this->investmentModel->getUserStats($userId);

            JsonResponse::success([
                'investments' => $enrichedInvestments,
                'total_investments' => count($enrichedInvestments),
                'stats' => $stats,
                'filter' => $statusFilter
            ]);

        } catch (\Exception $e) {
            error_log("Failed to fetch investments for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Unable to load your investments');
        }
    }

    /**
     * Get specific investment details
     * @return void
     */
    public function getInvestmentDetails(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized('Please log in to view investment details');
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        $investmentId = Validator::sanitizeInt($_GET['id'] ?? 0);

        if ($investmentId <= 0) {
            JsonResponse::error('Invalid investment ID');
            return;
        }

        try {
            $investment = $this->investmentModel->getWithSchema($investmentId);
            
            if (!$investment) {
                JsonResponse::notFound('Investment not found');
                return;
            }

            // Verify ownership
            if ((int)$investment['user_id'] !== $userId) {
                JsonResponse::forbidden('Access denied');
                return;
            }

            $enrichedInvestment = $this->enrichInvestmentData($investment);

            JsonResponse::success([
                'investment' => $enrichedInvestment
            ]);

        } catch (\Exception $e) {
            error_log("Failed to fetch investment details {$investmentId} for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Unable to load investment details');
        }
    }

    /**
     * Validate investment before creation (AJAX preview)
     * @return void
     */
    public function validateInvestment(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized('Please log in');
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];

        // Input validation
        $schemaId = Validator::sanitizeInt($_POST['schema_id'] ?? 0);
        $amount = Validator::sanitizeFloat($_POST['amount'] ?? 0);

        if ($schemaId <= 0) {
            JsonResponse::error('Invalid investment plan');
            return;
        }

        if (!Validator::isValidAmount($amount)) {
            JsonResponse::error('Invalid investment amount');
            return;
        }

        try {
            // Validate investment schema and amount
            $validation = $this->schemaModel->validateInvestmentAmount($schemaId, $amount);
            if (!$validation['valid']) {
                JsonResponse::error($validation['error']);
                return;
            }

            // Check user balance
            $user = $this->userModel->findById($userId);
            if (!$user) {
                JsonResponse::error('User not found');
                return;
            }

            $userBalance = (float)$user['balance'];
            if ($userBalance < $amount) {
                JsonResponse::error("Insufficient balance. You have \${$userBalance}, but need \${$amount}");
                return;
            }

            // Check if user can invest
            $eligibility = $this->schemaModel->canUserInvest($userId, $schemaId);
            if (!$eligibility['can_invest']) {
                JsonResponse::error($eligibility['error']);
                return;
            }

            // Return validation success with calculations
            JsonResponse::success([
                'valid' => true,
                'calculations' => $validation['calculations'],
                'schema' => $validation['schema'],
                'user_balance' => $userBalance,
                'remaining_balance' => $userBalance - $amount,
                'eligibility' => $eligibility
            ]);

        } catch (\Exception $e) {
            error_log("Investment validation failed for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Unable to validate investment');
        }
    }

    /**
     * Get investment plans suitable for user's balance
     * @return void
     */
    public function getSuitablePlans(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized('Please log in');
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];

        try {
            // Get user's balance
            $user = $this->userModel->findById($userId);
            if (!$user) {
                JsonResponse::error('User not found');
                return;
            }

            $userBalance = (float)$user['balance'];
            
            if ($userBalance <= 0) {
                JsonResponse::success([
                    'suitable_plans' => [],
                    'message' => 'Please add funds to your account to view suitable investment plans',
                    'user_balance' => $userBalance
                ]);
                return;
            }

            // Get plans suitable for user's balance
            $suitablePlans = $this->schemaModel->findSuitableForAmount($userBalance);

            JsonResponse::success([
                'suitable_plans' => $suitablePlans,
                'total_suitable' => count($suitablePlans),
                'user_balance' => $userBalance,
                'formatted_balance' => '$' . number_format($userBalance, 2)
            ]);

        } catch (\Exception $e) {
            error_log("Failed to fetch suitable plans for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Unable to load suitable investment plans');
        }
    }

    /**
     * Get investment schema statistics
     * @return void
     */
    public function getSchemaStats(): void 
    {
        $schemaId = Validator::sanitizeInt($_GET['schema_id'] ?? 0);

        if ($schemaId <= 0) {
            JsonResponse::error('Invalid schema ID');
            return;
        }

        try {
            $stats = $this->schemaModel->getSchemaStats($schemaId);
            $schema = $this->schemaModel->findByIdActive($schemaId);

            if (!$schema) {
                JsonResponse::notFound('Investment plan not found');
                return;
            }

            JsonResponse::success([
                'schema' => $schema,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            error_log("Failed to fetch schema stats for ID {$schemaId}: " . $e->getMessage());
            JsonResponse::error('Unable to load investment plan statistics');
        }
    }

    /**
     * Enrich investment data with calculated fields
     * @param array $investment
     * @return array
     */
    private function enrichInvestmentData(array $investment): array 
    {
        $durationDays = (int)$investment['duration_days'];
        $createdDate = new \DateTime($investment['created_at']);
        $now = new \DateTime();
        $daysElapsed = $createdDate->diff($now)->days;
        $daysRemaining = max(0, $durationDays - $daysElapsed);
        $progressPercentage = $durationDays > 0 ? min(100, ($daysElapsed / $durationDays) * 100) : 0;
        
        // Calculate financial metrics
        $dailyRate = (float)$investment['daily_rate'];
        $investAmount = (float)$investment['invest_amount'];
        $totalProfitEarned = (float)$investment['total_profit_amount'];
        $totalReturnPercentage = (float)$investment['total_return'];
        $expectedTotalProfit = $investAmount * ($totalReturnPercentage / 100);
        $expectedDailyProfit = $investAmount * ($dailyRate / 100);
        $remainingProfit = max(0, $expectedTotalProfit - $totalProfitEarned);
        $profitProgressPercentage = $expectedTotalProfit > 0 ? ($totalProfitEarned / $expectedTotalProfit) * 100 : 0;
        
        // Status information
        $status = $investment['status'];
        $statusColor = match($status) {
            'active' => 'success',
            'completed' => 'info',
            'cancelled' => 'danger',
            default => 'secondary'
        };
        
        // Next profit calculation (if active)
        $nextProfitDate = null;
        if ($status === 'active' && !empty($investment['next_profit_time'])) {
            $nextProfitDate = $investment['next_profit_time'];
        } elseif ($status === 'active' && empty($investment['last_profit_time'])) {
            // First profit tomorrow if no profit yet
            $nextProfitDate = date('Y-m-d H:i:s', strtotime('+1 day'));
        } elseif ($status === 'active' && !empty($investment['last_profit_time'])) {
            // Next profit 24 hours after last profit
            $nextProfitDate = date('Y-m-d H:i:s', strtotime($investment['last_profit_time'] . ' +1 day'));
        }

        return [
            'id' => (int)$investment['id'],
            'user_id' => (int)$investment['user_id'],
            'schema_id' => (int)$investment['schema_id'],
            'schema_name' => $investment['schema_name'],
            'invest_amount' => $investAmount,
            'total_profit_amount' => $totalProfitEarned,
            'daily_rate' => $dailyRate,
            'duration_days' => $durationDays,
            'total_return' => $totalReturnPercentage,
            'status' => $status,
            'created_at' => $investment['created_at'],
            'updated_at' => $investment['updated_at'],
            'last_profit_time' => $investment['last_profit_time'],
            'next_profit_time' => $nextProfitDate,
            
            // Calculated fields
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'progress_percentage' => round($progressPercentage, 1),
            'expected_daily_profit' => $expectedDailyProfit,
            'expected_total_profit' => $expectedTotalProfit,
            'remaining_profit' => $remainingProfit,
            'profit_progress_percentage' => round($profitProgressPercentage, 1),
            'completion_date' => date('Y-m-d', strtotime($investment['created_at'] . " +{$durationDays} days")),
            'roi_percentage' => $totalReturnPercentage,
            'is_completed' => $status === 'completed',
            'is_active' => $status === 'active',
            'is_profitable' => $totalProfitEarned > 0,
            'status_color' => $statusColor,
            'status_display' => ucwords($status),
            
            // Formatted values
            'formatted' => [
                'invest_amount' => '$' . number_format($investAmount, 2),
                'total_profit_amount' => '$' . number_format($totalProfitEarned, 2),
                'expected_daily_profit' => '$' . number_format($expectedDailyProfit, 2),
                'expected_total_profit' => '$' . number_format($expectedTotalProfit, 2),
                'remaining_profit' => '$' . number_format($remainingProfit, 2),
                'daily_rate' => number_format($dailyRate, 2) . '%',
                'total_return' => number_format($totalReturnPercentage, 2) . '%',
                'created_date' => date('M j, Y', strtotime($investment['created_at'])),
                'completion_date' => date('M j, Y', strtotime($investment['created_at'] . " +{$durationDays} days")),
                'next_profit_date' => $nextProfitDate ? date('M j, Y g:i A', strtotime($nextProfitDate)) : null,
                'duration' => $durationDays . ' days'
            ]
        ];
    }
}