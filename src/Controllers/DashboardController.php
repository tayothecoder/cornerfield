<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Controllers/DashboardController.php
 * Purpose: Dashboard controller for gathering user dashboard data
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\InvestmentModel;
use App\Models\TransactionModel;
use App\Models\InvestmentSchemaModel;
use App\Utils\Security;
use App\Utils\JsonResponse;

class DashboardController 
{
    private UserModel $userModel;
    private InvestmentModel $investmentModel;
    private TransactionModel $transactionModel;
    private InvestmentSchemaModel $schemaModel;
    
    public function __construct() 
    {
        $this->userModel = new UserModel();
        $this->investmentModel = new InvestmentModel();
        $this->transactionModel = new TransactionModel();
        $this->schemaModel = new InvestmentSchemaModel();
    }

    /**
     * Gather all dashboard data for the authenticated user
     * @return array
     */
    public function index(): array 
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? 0;
        
        if ($userId <= 0) {
            return [
                'success' => false,
                'error' => 'User not authenticated'
            ];
        }

        try {
            // Gather all dashboard data
            $data = [
                'success' => true,
                'user_info' => $this->getUserInfo($userId),
                'balance' => $this->getBalanceData($userId),
                'active_investments' => $this->getActiveInvestments($userId),
                'recent_transactions' => $this->getRecentTransactions($userId),
                'investment_stats' => $this->getInvestmentStats($userId),
                'featured_plans' => $this->getFeaturedPlans(),
                'dashboard_stats' => $this->getDashboardStats($userId),
                'notifications' => $this->getUserNotifications($userId),
                'quick_stats' => $this->getQuickStats($userId)
            ];

            // Log dashboard access
            Security::logAudit($userId, 'dashboard_accessed', 'users', $userId);

            return $data;

        } catch (\Exception $e) {
            error_log("Dashboard data gathering failed for user {$userId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to load dashboard data. Please refresh the page.'
            ];
        }
    }

    /**
     * Get user information
     * @param int $userId
     * @return array
     */
    private function getUserInfo(int $userId): array 
    {
        $user = $this->userModel->findById($userId);
        
        if (!$user) {
            return [
                'id' => $userId,
                'first_name' => 'User',
                'last_name' => '',
                'username' => 'unknown',
                'email' => 'unknown',
                'kyc_status' => 'pending',
                'email_verified' => false,
                'two_factor_enabled' => false,
                'member_since' => date('Y-m-d'),
                'last_login' => null
            ];
        }

        // Calculate membership duration
        $memberSince = new \DateTime($user['created_at']);
        $now = new \DateTime();
        $membershipDays = $memberSince->diff($now)->days;

        return [
            'id' => (int)$user['id'],
            'first_name' => $user['first_name'] ?? 'User',
            'last_name' => $user['last_name'] ?? '',
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'display_name' => $user['first_name'] ?? $user['username'],
            'kyc_status' => $user['kyc_status'],
            'email_verified' => (bool)$user['email_verified'],
            'two_factor_enabled' => (bool)$user['two_factor_enabled'],
            'member_since' => $user['created_at'],
            'membership_days' => $membershipDays,
            'last_login' => $user['last_login'],
            'referral_code' => $user['referral_code'] ?? null,
            'country' => $user['country'] ?? null,
            'phone' => $user['phone'] ?? null
        ];
    }

    /**
     * Get balance data
     * @param int $userId
     * @return array
     */
    private function getBalanceData(int $userId): array 
    {
        $balance = $this->transactionModel->getUserBalance($userId);
        
        return [
            'available_balance' => $balance['available_balance'],
            'locked_balance' => $balance['locked_balance'],
            'bonus_balance' => $balance['bonus_balance'],
            'total_balance' => $balance['total_balance'],
            'total_invested' => $balance['total_invested'],
            'total_withdrawn' => $balance['total_withdrawn'],
            'total_earned' => $balance['total_earned'],
            'formatted' => [
                'available_balance' => '$' . number_format($balance['available_balance'], 2),
                'locked_balance' => '$' . number_format($balance['locked_balance'], 2),
                'bonus_balance' => '$' . number_format($balance['bonus_balance'], 2),
                'total_balance' => '$' . number_format($balance['total_balance'], 2),
                'total_invested' => '$' . number_format($balance['total_invested'], 2),
                'total_withdrawn' => '$' . number_format($balance['total_withdrawn'], 2),
                'total_earned' => '$' . number_format($balance['total_earned'], 2)
            ]
        ];
    }

    /**
     * Get active investments with progress
     * @param int $userId
     * @return array
     */
    private function getActiveInvestments(int $userId): array 
    {
        $investments = $this->investmentModel->findActiveByUserId($userId);
        
        $enrichedInvestments = [];
        
        foreach ($investments as $investment) {
            $durationDays = (int)$investment['duration_days'];
            $createdDate = new \DateTime($investment['created_at']);
            $now = new \DateTime();
            $daysElapsed = $createdDate->diff($now)->days;
            $daysRemaining = max(0, $durationDays - $daysElapsed);
            $progressPercentage = $durationDays > 0 ? min(100, ($daysElapsed / $durationDays) * 100) : 0;
            
            // Calculate expected returns
            $dailyRate = (float)$investment['daily_rate'];
            $investAmount = (float)$investment['invest_amount'];
            $totalProfitEarned = (float)$investment['total_profit_amount'];
            $expectedTotalProfit = $investAmount * ((float)$investment['total_return'] / 100);
            $expectedDailyProfit = $investAmount * ($dailyRate / 100);
            $remainingProfit = max(0, $expectedTotalProfit - $totalProfitEarned);
            
            $enrichedInvestments[] = [
                'id' => (int)$investment['id'],
                'schema_name' => $investment['schema_name'],
                'invest_amount' => $investAmount,
                'total_profit_amount' => $totalProfitEarned,
                'daily_rate' => $dailyRate,
                'duration_days' => $durationDays,
                'days_elapsed' => $daysElapsed,
                'days_remaining' => $daysRemaining,
                'progress_percentage' => round($progressPercentage, 1),
                'expected_daily_profit' => $expectedDailyProfit,
                'expected_total_profit' => $expectedTotalProfit,
                'remaining_profit' => $remainingProfit,
                'status' => $investment['status'],
                'created_at' => $investment['created_at'],
                'last_profit_time' => $investment['last_profit_time'],
                'next_profit_time' => $investment['next_profit_time'],
                'formatted' => [
                    'invest_amount' => '$' . number_format($investAmount, 2),
                    'total_profit_amount' => '$' . number_format($totalProfitEarned, 2),
                    'expected_daily_profit' => '$' . number_format($expectedDailyProfit, 2),
                    'expected_total_profit' => '$' . number_format($expectedTotalProfit, 2),
                    'remaining_profit' => '$' . number_format($remainingProfit, 2),
                    'daily_rate' => number_format($dailyRate, 2) . '%'
                ]
            ];
        }
        
        return $enrichedInvestments;
    }

    /**
     * Get recent transactions for dashboard
     * @param int $userId
     * @return array
     */
    private function getRecentTransactions(int $userId): array 
    {
        $transactions = $this->transactionModel->getRecentTransactions($userId, 10);
        
        $enrichedTransactions = [];
        
        foreach ($transactions as $transaction) {
            $amount = (float)$transaction['amount'];
            $fee = (float)$transaction['fee'];
            $netAmount = (float)$transaction['net_amount'];
            
            // Determine display color based on transaction type
            $typeColor = match($transaction['type']) {
                'deposit', 'profit', 'bonus', 'referral', 'principal_return' => 'success',
                'withdrawal' => 'warning',
                'investment' => 'info',
                default => 'secondary'
            };
            
            // Status color
            $statusColor = match($transaction['status']) {
                'completed' => 'success',
                'pending', 'processing' => 'warning',
                'failed', 'cancelled' => 'danger',
                default => 'secondary'
            };
            
            $enrichedTransactions[] = [
                'id' => (int)$transaction['id'],
                'type' => $transaction['type'],
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'status' => $transaction['status'],
                'currency' => $transaction['currency'],
                'description' => $transaction['description'],
                'created_at' => $transaction['created_at'],
                'type_color' => $typeColor,
                'status_color' => $statusColor,
                'type_display' => ucwords(str_replace('_', ' ', $transaction['type'])),
                'status_display' => ucwords($transaction['status']),
                'formatted' => [
                    'amount' => '$' . number_format($amount, 2),
                    'fee' => $fee > 0 ? '$' . number_format($fee, 2) : '-',
                    'net_amount' => '$' . number_format($netAmount, 2),
                    'date' => date('M j, Y g:i A', strtotime($transaction['created_at']))
                ]
            ];
        }
        
        return $enrichedTransactions;
    }

    /**
     * Get investment statistics
     * @param int $userId
     * @return array
     */
    private function getInvestmentStats(int $userId): array 
    {
        $stats = $this->investmentModel->getUserStats($userId);
        
        // Calculate ROI percentage
        $totalInvested = $stats['total_invested'];
        $totalEarned = $stats['total_profit_earned'];
        $roiPercentage = $totalInvested > 0 ? ($totalEarned / $totalInvested) * 100 : 0;
        
        return [
            'total_investments' => $stats['total_investments'],
            'active_investments' => $stats['active_investments'],
            'completed_investments' => $stats['completed_investments'],
            'total_invested' => $totalInvested,
            'total_profit_earned' => $totalEarned,
            'active_investment_amount' => $stats['active_investment_amount'],
            'average_daily_rate' => $stats['average_daily_rate'],
            'roi_percentage' => $roiPercentage,
            'formatted' => [
                'total_invested' => '$' . number_format($totalInvested, 2),
                'total_profit_earned' => '$' . number_format($totalEarned, 2),
                'active_investment_amount' => '$' . number_format($stats['active_investment_amount'], 2),
                'average_daily_rate' => number_format($stats['average_daily_rate'], 2) . '%',
                'roi_percentage' => number_format($roiPercentage, 1) . '%'
            ]
        ];
    }

    /**
     * Get featured investment plans
     * @return array
     */
    private function getFeaturedPlans(): array 
    {
        $plans = $this->schemaModel->findFeatured();
        
        // Limit to 4 featured plans for dashboard
        return array_slice($plans, 0, 4);
    }

    /**
     * Get dashboard statistics
     * @param int $userId
     * @return array
     */
    private function getDashboardStats(int $userId): array 
    {
        $balance = $this->getBalanceData($userId);
        $investmentStats = $this->getInvestmentStats($userId);
        $transactionStats = $this->transactionModel->getStats($userId);
        
        // Calculate growth metrics
        $monthlyGrowth = $this->calculateMonthlyGrowth($userId);
        $weeklyGrowth = $this->calculateWeeklyGrowth($userId);
        
        return [
            'portfolio_value' => $balance['total_balance'] + $investmentStats['active_investment_amount'],
            'monthly_growth' => $monthlyGrowth,
            'weekly_growth' => $weeklyGrowth,
            'total_transactions' => $transactionStats['total_transactions'],
            'pending_transactions' => $transactionStats['pending_transactions'],
            'investment_success_rate' => $investmentStats['completed_investments'] > 0 ? 
                round(($investmentStats['completed_investments'] / $investmentStats['total_investments']) * 100, 1) : 0,
            'average_investment' => $investmentStats['total_investments'] > 0 ? 
                $investmentStats['total_invested'] / $investmentStats['total_investments'] : 0,
            'formatted' => [
                'portfolio_value' => '$' . number_format($balance['total_balance'] + $investmentStats['active_investment_amount'], 2),
                'monthly_growth' => ($monthlyGrowth >= 0 ? '+' : '') . number_format($monthlyGrowth, 1) . '%',
                'weekly_growth' => ($weeklyGrowth >= 0 ? '+' : '') . number_format($weeklyGrowth, 1) . '%',
                'average_investment' => '$' . number_format($investmentStats['total_investments'] > 0 ? 
                    $investmentStats['total_invested'] / $investmentStats['total_investments'] : 0, 2)
            ]
        ];
    }

    /**
     * Get user notifications (placeholder for now)
     * @param int $userId
     * @return array
     */
    private function getUserNotifications(int $userId): array 
    {
        // This would typically fetch from a notifications table
        // For now, we'll return some sample notifications based on user state
        
        $notifications = [];
        
        // Check if user has pending KYC
        $user = $this->userModel->findById($userId);
        if ($user && $user['kyc_status'] === 'pending') {
            $notifications[] = [
                'id' => 'kyc_pending',
                'type' => 'warning',
                'title' => 'KYC Verification Required',
                'message' => 'Complete your KYC verification to unlock higher investment limits.',
                'action_url' => '/users/profile.php',
                'action_text' => 'Verify Now',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Check if user has no active investments
        $investmentStats = $this->investmentModel->getUserStats($userId);
        if ($investmentStats['active_investments'] === 0) {
            $notifications[] = [
                'id' => 'no_investments',
                'type' => 'info',
                'title' => 'Start Your Investment Journey',
                'message' => 'Explore our investment plans and start earning daily profits.',
                'action_url' => '/users/invest.php',
                'action_text' => 'View Plans',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        return $notifications;
    }

    /**
     * Get quick statistics for widgets
     * @param int $userId
     * @return array
     */
    private function getQuickStats(int $userId): array 
    {
        try {
            // Today's profit
            $stmt = $this->transactionModel->db->prepare(
                "SELECT COALESCE(SUM(amount), 0) as today_profit 
                 FROM transactions 
                 WHERE user_id = ? AND type = 'profit' AND status = 'completed' 
                 AND DATE(created_at) = CURDATE()"
            );
            $stmt->execute([$userId]);
            $todayProfit = (float)$stmt->fetch()['today_profit'];
            
            // This month's earnings
            $stmt = $this->transactionModel->db->prepare(
                "SELECT COALESCE(SUM(amount), 0) as month_earnings 
                 FROM transactions 
                 WHERE user_id = ? AND type = 'profit' AND status = 'completed' 
                 AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
            );
            $stmt->execute([$userId]);
            $monthEarnings = (float)$stmt->fetch()['month_earnings'];
            
            // Total referral earnings
            $stmt = $this->transactionModel->db->prepare(
                "SELECT COALESCE(SUM(amount), 0) as referral_earnings 
                 FROM transactions 
                 WHERE user_id = ? AND type = 'referral' AND status = 'completed'"
            );
            $stmt->execute([$userId]);
            $referralEarnings = (float)$stmt->fetch()['referral_earnings'];
            
            return [
                'today_profit' => $todayProfit,
                'month_earnings' => $monthEarnings,
                'referral_earnings' => $referralEarnings,
                'formatted' => [
                    'today_profit' => '$' . number_format($todayProfit, 2),
                    'month_earnings' => '$' . number_format($monthEarnings, 2),
                    'referral_earnings' => '$' . number_format($referralEarnings, 2)
                ]
            ];
            
        } catch (\Exception $e) {
            error_log("Failed to get quick stats for user {$userId}: " . $e->getMessage());
            return [
                'today_profit' => 0.0,
                'month_earnings' => 0.0,
                'referral_earnings' => 0.0,
                'formatted' => [
                    'today_profit' => '$0.00',
                    'month_earnings' => '$0.00',
                    'referral_earnings' => '$0.00'
                ]
            ];
        }
    }

    /**
     * Calculate monthly growth percentage
     * @param int $userId
     * @return float
     */
    private function calculateMonthlyGrowth(int $userId): float 
    {
        try {
            // Current month earnings
            $stmt = $this->transactionModel->db->prepare(
                "SELECT COALESCE(SUM(amount), 0) as current_month 
                 FROM transactions 
                 WHERE user_id = ? AND type = 'profit' AND status = 'completed' 
                 AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
            );
            $stmt->execute([$userId]);
            $currentMonth = (float)$stmt->fetch()['current_month'];
            
            // Previous month earnings
            $stmt = $this->transactionModel->db->prepare(
                "SELECT COALESCE(SUM(amount), 0) as previous_month 
                 FROM transactions 
                 WHERE user_id = ? AND type = 'profit' AND status = 'completed' 
                 AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                 AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
            );
            $stmt->execute([$userId]);
            $previousMonth = (float)$stmt->fetch()['previous_month'];
            
            if ($previousMonth <= 0) {
                return $currentMonth > 0 ? 100.0 : 0.0;
            }
            
            return (($currentMonth - $previousMonth) / $previousMonth) * 100;
            
        } catch (\Exception $e) {
            error_log("Failed to calculate monthly growth for user {$userId}: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Calculate weekly growth percentage
     * @param int $userId
     * @return float
     */
    private function calculateWeeklyGrowth(int $userId): float 
    {
        try {
            // This week earnings
            $stmt = $this->transactionModel->db->prepare(
                "SELECT COALESCE(SUM(amount), 0) as this_week 
                 FROM transactions 
                 WHERE user_id = ? AND type = 'profit' AND status = 'completed' 
                 AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)"
            );
            $stmt->execute([$userId]);
            $thisWeek = (float)$stmt->fetch()['this_week'];
            
            // Previous week earnings
            $stmt = $this->transactionModel->db->prepare(
                "SELECT COALESCE(SUM(amount), 0) as previous_week 
                 FROM transactions 
                 WHERE user_id = ? AND type = 'profit' AND status = 'completed' 
                 AND YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)"
            );
            $stmt->execute([$userId]);
            $previousWeek = (float)$stmt->fetch()['previous_week'];
            
            if ($previousWeek <= 0) {
                return $thisWeek > 0 ? 100.0 : 0.0;
            }
            
            return (($thisWeek - $previousWeek) / $previousWeek) * 100;
            
        } catch (\Exception $e) {
            error_log("Failed to calculate weekly growth for user {$userId}: " . $e->getMessage());
            return 0.0;
        }
    }
}