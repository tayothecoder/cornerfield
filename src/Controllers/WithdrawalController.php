<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Controllers/WithdrawalController.php
 * Purpose: Withdrawal controller with balance validation and security
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Controllers;

use App\Models\WithdrawalModel;
use App\Models\UserModel;
use App\Utils\Security;
use App\Utils\Validator;
use App\Utils\JsonResponse;

class WithdrawalController
{
    private WithdrawalModel $withdrawalModel;
    private UserModel $userModel;

    public function __construct()
    {
        $this->withdrawalModel = new WithdrawalModel();
        $this->userModel = new UserModel();
    }

    /**
     * Create new withdrawal request
     * @return void
     */
    public function create(): void
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }

        // Authentication check
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated'] || !isset($_SESSION['user_id'])) {
            JsonResponse::unauthorized();
            return;
        }

        $userId = (int)$_SESSION['user_id'];

        // Rate limiting (stricter for withdrawals)
        if (!Security::rateLimitCheck((string)$userId, 'withdrawal_create', 2, 3600)) {
            JsonResponse::error('Too many withdrawal attempts. Please wait before creating another withdrawal.', 429);
            return;
        }

        // Input validation
        $amount = Validator::sanitizeFloat($_POST['amount'] ?? 0);
        $walletAddress = Validator::sanitizeString($_POST['wallet_address'] ?? '', 255);
        $currency = Validator::sanitizeString($_POST['currency'] ?? 'USDT', 10);
        $network = Validator::sanitizeString($_POST['network'] ?? 'TRC20', 50);

        // Validate required fields
        if (!Validator::isValidAmount($amount)) {
            JsonResponse::error('Please enter a valid withdrawal amount');
            return;
        }

        if (empty($walletAddress)) {
            JsonResponse::error('Wallet address is required');
            return;
        }

        if (!Validator::isValidWalletAddress($walletAddress, $currency)) {
            JsonResponse::error('Invalid wallet address format for selected currency');
            return;
        }

        if (!Validator::isValidNetwork($network, $currency)) {
            JsonResponse::error('Invalid network for selected currency');
            return;
        }

        // Get current user for balance check
        $user = $this->userModel->findById($userId);
        if (!$user) {
            JsonResponse::error('User account not found');
            return;
        }

        // Check account status
        if (!$user['is_active']) {
            JsonResponse::error('Account is not active');
            return;
        }

        // Calculate fees and validate
        $feeCalculation = $this->withdrawalModel->calculateFee($amount);
        $totalRequired = $feeCalculation['total_deduction'];

        // Check minimum/maximum limits
        $withdrawalSettings = $this->getWithdrawalLimits();
        
        if ($amount < $withdrawalSettings['minimum_withdrawal']) {
            JsonResponse::error("Minimum withdrawal amount is $" . number_format($withdrawalSettings['minimum_withdrawal'], 2));
            return;
        }

        if ($amount > $withdrawalSettings['maximum_withdrawal']) {
            JsonResponse::error("Maximum withdrawal amount is $" . number_format($withdrawalSettings['maximum_withdrawal'], 2));
            return;
        }

        // Check sufficient balance
        if ((float)$user['balance'] < $totalRequired) {
            JsonResponse::error(
                'Insufficient balance. Available: $' . number_format((float)$user['balance'], 2) . 
                ', Required: $' . number_format($totalRequired, 2) . 
                ' (including $' . number_format($feeCalculation['fee'], 2) . ' fee)'
            );
            return;
        }

        // Additional security checks
        if ($this->hasRecentSimilarWithdrawal($userId, $amount, $walletAddress)) {
            JsonResponse::error('Similar withdrawal detected recently. Please wait before creating another withdrawal.');
            return;
        }

        try {
            // Create the withdrawal
            $result = $this->withdrawalModel->createWithdrawal($userId, $amount, $walletAddress, $currency, $network);

            if ($result['success']) {
                // Log successful withdrawal creation
                Security::logAudit(
                    $userId,
                    'withdrawal_request_created',
                    'withdrawals',
                    $result['withdrawal_id'],
                    null,
                    [
                        'amount' => $amount,
                        'currency' => $currency,
                        'network' => $network,
                        'wallet_preview' => substr($walletAddress, 0, 10) . '...' . substr($walletAddress, -6),
                        'fee' => $feeCalculation['fee']
                    ]
                );

                JsonResponse::success([
                    'withdrawal_id' => $result['withdrawal_id'],
                    'transaction_id' => $result['transaction_id'],
                    'reference_id' => $result['reference_id'],
                    'amount' => $amount,
                    'fee_amount' => $result['fee_amount'],
                    'total_deducted' => $result['total_deducted'],
                    'currency' => $currency,
                    'network' => $network,
                    'status' => 'pending',
                    'message' => 'Withdrawal request created successfully. Your request will be processed within 1-24 hours.'
                ]);
            } else {
                JsonResponse::error($result['error'] ?? 'Failed to create withdrawal');
            }

        } catch (\Exception $e) {
            error_log("Withdrawal creation error: " . $e->getMessage());
            JsonResponse::error('Unable to process withdrawal. Please try again.');
        }
    }

    /**
     * Get user's withdrawal history
     * @return void
     */
    public function getHistory(): void
    {
        // Authentication check
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated'] || !isset($_SESSION['user_id'])) {
            JsonResponse::unauthorized();
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        $page = max(1, Validator::sanitizeInt($_GET['page'] ?? 1));
        $limit = min(100, max(10, Validator::sanitizeInt($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        try {
            $withdrawals = $this->withdrawalModel->findByUserId($userId, $limit, $offset);
            
            // Format withdrawals for display
            foreach ($withdrawals as &$withdrawal) {
                $withdrawal = $this->formatWithdrawalForDisplay($withdrawal);
            }

            JsonResponse::success([
                'withdrawals' => $withdrawals,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $this->withdrawalModel->count(['user_id' => $userId])
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Failed to fetch withdrawal history: " . $e->getMessage());
            JsonResponse::error('Unable to load withdrawal history');
        }
    }

    /**
     * Calculate withdrawal fee in real-time
     * @return void
     */
    public function calculateFee(): void
    {
        // Authentication check
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            JsonResponse::unauthorized();
            return;
        }

        $amount = Validator::sanitizeFloat($_POST['amount'] ?? 0);

        if (!Validator::isValidAmount($amount)) {
            JsonResponse::error('Invalid amount');
            return;
        }

        try {
            $feeInfo = $this->withdrawalModel->calculateFee($amount);
            
            JsonResponse::success([
                'amount' => $amount,
                'fee' => $feeInfo['fee'],
                'fee_rate' => $feeInfo['fee_rate'],
                'net_amount' => $feeInfo['net_amount'],
                'total_deduction' => $feeInfo['total_deduction'],
                'fee_display' => '$' . number_format($feeInfo['fee'], 2) . ' (' . number_format($feeInfo['fee_rate'], 2) . '%)'
            ]);

        } catch (\Exception $e) {
            error_log("Fee calculation error: " . $e->getMessage());
            JsonResponse::error('Unable to calculate fee');
        }
    }

    /**
     * Get user balance and withdrawal limits
     * @return void
     */
    public function getUserBalance(): void
    {
        // Authentication check
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated'] || !isset($_SESSION['user_id'])) {
            JsonResponse::unauthorized();
            return;
        }

        $userId = (int)$_SESSION['user_id'];

        try {
            $user = $this->userModel->findById($userId);
            if (!$user) {
                JsonResponse::error('User not found');
                return;
            }

            $withdrawalSettings = $this->getWithdrawalLimits();
            
            // Check for pending withdrawals
            $pendingWithdrawals = $this->withdrawalModel->findPending($userId);
            $pendingAmount = array_sum(array_column($pendingWithdrawals, 'requested_amount'));

            JsonResponse::success([
                'balance' => [
                    'available' => (float)$user['balance'],
                    'locked' => (float)$user['locked_balance'],
                    'total' => (float)$user['balance'] + (float)$user['locked_balance'],
                    'formatted_available' => '$' . number_format((float)$user['balance'], 2),
                ],
                'limits' => $withdrawalSettings,
                'pending' => [
                    'count' => count($pendingWithdrawals),
                    'amount' => $pendingAmount,
                    'formatted_amount' => '$' . number_format($pendingAmount, 2)
                ],
                'restrictions' => $this->getWithdrawalRestrictions($user)
            ]);

        } catch (\Exception $e) {
            error_log("Failed to get user balance: " . $e->getMessage());
            JsonResponse::error('Unable to load balance information');
        }
    }

    /**
     * Get supported withdrawal currencies and networks
     * @return void
     */
    public function getSupportedCurrencies(): void
    {
        // Authentication check
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            JsonResponse::unauthorized();
            return;
        }

        try {
            $currencies = [
                [
                    'code' => 'BTC',
                    'name' => 'Bitcoin',
                    'networks' => ['BTC'],
                    'icon' => '₿'
                ],
                [
                    'code' => 'ETH',
                    'name' => 'Ethereum',
                    'networks' => ['ERC20'],
                    'icon' => 'Ξ'
                ],
                [
                    'code' => 'USDT',
                    'name' => 'Tether',
                    'networks' => ['ERC20', 'TRC20', 'BEP20'],
                    'icon' => '₮'
                ],
                [
                    'code' => 'LTC',
                    'name' => 'Litecoin',
                    'networks' => ['LTC'],
                    'icon' => 'Ł'
                ]
            ];

            JsonResponse::success(['currencies' => $currencies]);

        } catch (\Exception $e) {
            error_log("Failed to get supported currencies: " . $e->getMessage());
            JsonResponse::error('Unable to load supported currencies');
        }
    }

    /**
     * Check if user has recent similar withdrawal (fraud prevention)
     * @param int $userId
     * @param float $amount
     * @param string $walletAddress
     * @return bool
     */
    private function hasRecentSimilarWithdrawal(int $userId, float $amount, string $walletAddress): bool
    {
        try {
            $stmt = $this->withdrawalModel->db->prepare(
                "SELECT COUNT(*) as count 
                 FROM withdrawals 
                 WHERE user_id = ? 
                   AND (ABS(requested_amount - ?) < 0.01 OR wallet_address = ?)
                   AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                   AND status NOT IN ('cancelled', 'failed')"
            );
            
            $stmt->execute([$userId, $amount, $walletAddress]);
            $result = $stmt->fetch();
            
            return (int)$result['count'] > 0;
            
        } catch (\Exception $e) {
            error_log("Error checking recent withdrawals: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get withdrawal limits from settings
     * @return array
     */
    private function getWithdrawalLimits(): array
    {
        try {
            // This would typically come from admin settings or database
            return [
                'minimum_withdrawal' => 10.0,
                'maximum_withdrawal' => 50000.0,
                'daily_limit' => 10000.0,
                'fee_rate' => 5.0,
                'processing_time' => '1-24 hours'
            ];
        } catch (\Exception $e) {
            // Return defaults if database fails
            return [
                'minimum_withdrawal' => 10.0,
                'maximum_withdrawal' => 50000.0,
                'daily_limit' => 10000.0,
                'fee_rate' => 5.0,
                'processing_time' => '1-24 hours'
            ];
        }
    }

    /**
     * Get withdrawal restrictions for user
     * @param array $user
     * @return array
     */
    private function getWithdrawalRestrictions(array $user): array
    {
        $restrictions = [];

        if ($user['kyc_status'] !== 'approved') {
            $restrictions[] = 'KYC verification required for withdrawals above $500';
        }

        if (!$user['email_verified']) {
            $restrictions[] = 'Email verification required';
        }

        return $restrictions;
    }

    /**
     * Format withdrawal for display
     * @param array $withdrawal
     * @return array
     */
    private function formatWithdrawalForDisplay(array $withdrawal): array
    {
        $withdrawal['formatted_amount'] = '$' . number_format((float)$withdrawal['requested_amount'], 2);
        $withdrawal['formatted_fee'] = '$' . number_format((float)$withdrawal['fee_amount'], 2);
        $withdrawal['formatted_date'] = date('M j, Y H:i', strtotime($withdrawal['created_at']));
        $withdrawal['status_badge'] = $this->getStatusBadge($withdrawal['status']);
        
        // Truncate wallet address for security
        if (!empty($withdrawal['wallet_address'])) {
            $address = $withdrawal['wallet_address'];
            if (strlen($address) > 20) {
                $withdrawal['display_address'] = substr($address, 0, 10) . '...' . substr($address, -6);
            } else {
                $withdrawal['display_address'] = $address;
            }
        }

        // Add processing info
        if (!empty($withdrawal['withdrawal_hash'])) {
            $hash = $withdrawal['withdrawal_hash'];
            $withdrawal['display_hash'] = substr($hash, 0, 10) . '...' . substr($hash, -6);
        }

        return $withdrawal;
    }

    /**
     * Get status badge HTML class
     * @param string $status
     * @return string
     */
    private function getStatusBadge(string $status): string
    {
        return match($status) {
            'completed' => 'success',
            'processing' => 'warning',
            'pending' => 'info',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary'
        };
    }
}