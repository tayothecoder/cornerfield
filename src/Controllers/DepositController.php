<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Controllers/DepositController.php
 * Purpose: Deposit controller with CSRF protection and validation
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Controllers;

use App\Models\DepositModel;
use App\Models\DepositMethodModel;
use App\Models\UserModel;
use App\Utils\Security;
use App\Utils\Validator;
use App\Utils\JsonResponse;

class DepositController
{
    private DepositModel $depositModel;
    private DepositMethodModel $methodModel;
    private UserModel $userModel;

    public function __construct()
    {
        $this->depositModel = new DepositModel();
        $this->methodModel = new DepositMethodModel();
        $this->userModel = new UserModel();
    }

    /**
     * Show available deposit methods
     * @return void
     */
    public function showMethods(): void
    {
        // Authentication check
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            JsonResponse::unauthorized();
            return;
        }

        try {
            $methods = $this->methodModel->findActive();
            
            // Add calculated fee information for each method
            foreach ($methods as &$method) {
                $method['display_fee'] = $this->formatFeeDisplay($method);
                $method['limits_text'] = $this->formatLimitsDisplay($method);
            }
            
            JsonResponse::success([
                'methods' => $methods,
                'currencies' => $this->methodModel->getSupportedCurrencies()
            ]);
            
        } catch (\Exception $e) {
            error_log("Failed to fetch deposit methods: " . $e->getMessage());
            JsonResponse::error('Unable to load deposit methods');
        }
    }

    /**
     * Create new deposit request
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

        // Rate limiting
        if (!Security::rateLimitCheck((string)$userId, 'deposit_create', 3, 3600)) {
            JsonResponse::error('Too many deposit attempts. Please wait before creating another deposit.', 429);
            return;
        }

        // Input validation
        $methodId = Validator::sanitizeInt($_POST['method_id'] ?? 0);
        $amount = Validator::sanitizeFloat($_POST['amount'] ?? 0);
        $currency = Validator::sanitizeString($_POST['currency'] ?? 'USD', 10);
        $network = Validator::sanitizeString($_POST['network'] ?? '', 50);
        $transactionHash = Validator::sanitizeString($_POST['transaction_hash'] ?? '', 255);

        // Validate required fields
        if ($methodId <= 0) {
            JsonResponse::error('Please select a deposit method');
            return;
        }

        if (!Validator::isValidAmount($amount)) {
            JsonResponse::error('Please enter a valid amount');
            return;
        }

        // Get and validate deposit method
        $method = $this->methodModel->findById($methodId);
        if (!$method || $method['status'] != 1) {
            JsonResponse::error('Selected deposit method is not available');
            return;
        }

        // Validate amount against method limits
        if ($amount < $method['minimum_deposit']) {
            JsonResponse::error("Minimum deposit amount is $" . number_format($method['minimum_deposit'], 2));
            return;
        }

        if ($amount > $method['maximum_deposit']) {
            JsonResponse::error("Maximum deposit amount is $" . number_format($method['maximum_deposit'], 2));
            return;
        }

        // Handle file upload for manual deposits
        $proofOfPayment = null;
        if ($method['type'] === 'manual' && isset($_FILES['proof_of_payment'])) {
            $uploadResult = $this->handleProofUpload($_FILES['proof_of_payment']);
            if (!$uploadResult['success']) {
                JsonResponse::error($uploadResult['error']);
                return;
            }
            $proofOfPayment = $uploadResult['filepath'];
        }

        // Prepare extra data
        $extraData = [
            'currency' => $currency,
            'network' => $network,
            'transaction_hash' => $transactionHash,
            'proof_of_payment' => $proofOfPayment
        ];

        // For auto deposits, add crypto currency info
        if ($method['type'] === 'auto') {
            $extraData['crypto_currency'] = $this->determineCryptoCurrency($currency, $network);
        }

        try {
            // Create the deposit
            $result = $this->depositModel->createDeposit($userId, $methodId, $amount, $extraData);

            if ($result['success']) {
                // Log successful deposit creation
                Security::logAudit(
                    $userId,
                    'deposit_request_created',
                    'deposits',
                    $result['deposit_id'],
                    null,
                    [
                        'method' => $method['name'],
                        'amount' => $amount,
                        'currency' => $currency
                    ]
                );

                // Prepare response data
                $responseData = [
                    'deposit_id' => $result['deposit_id'],
                    'transaction_id' => $result['transaction_id'],
                    'amount' => $amount,
                    'fee_amount' => $result['fee_amount'],
                    'net_amount' => $result['net_amount'],
                    'method' => $method['name'],
                    'status' => 'pending',
                    'message' => 'Deposit request created successfully'
                ];

                // Add deposit address for auto deposits
                if ($method['type'] === 'auto' && !empty($result['deposit_address'])) {
                    $responseData['deposit_address'] = $result['deposit_address'];
                    $responseData['expires_at'] = $result['expires_at'];
                }

                JsonResponse::success($responseData);
            } else {
                JsonResponse::error($result['error'] ?? 'Failed to create deposit');
            }

        } catch (\Exception $e) {
            error_log("Deposit creation error: " . $e->getMessage());
            JsonResponse::error('Unable to process deposit. Please try again.');
        }
    }

    /**
     * Get user's deposit history
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
            $deposits = $this->depositModel->findByUserId($userId, $limit, $offset);
            
            // Format deposits for display
            foreach ($deposits as &$deposit) {
                $deposit = $this->formatDepositForDisplay($deposit);
            }

            JsonResponse::success([
                'deposits' => $deposits,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $this->depositModel->count(['user_id' => $userId])
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Failed to fetch deposit history: " . $e->getMessage());
            JsonResponse::error('Unable to load deposit history');
        }
    }

    /**
     * Calculate deposit fee in real-time
     * @return void
     */
    public function calculateFee(): void
    {
        // Authentication check
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            JsonResponse::unauthorized();
            return;
        }

        $methodId = Validator::sanitizeInt($_POST['method_id'] ?? 0);
        $amount = Validator::sanitizeFloat($_POST['amount'] ?? 0);

        if ($methodId <= 0 || !Validator::isValidAmount($amount)) {
            JsonResponse::error('Invalid parameters');
            return;
        }

        try {
            $feeInfo = $this->methodModel->calculateMethodFee($methodId, $amount);
            
            if (isset($feeInfo['error'])) {
                JsonResponse::error($feeInfo['error']);
                return;
            }

            JsonResponse::success($feeInfo);

        } catch (\Exception $e) {
            error_log("Fee calculation error: " . $e->getMessage());
            JsonResponse::error('Unable to calculate fee');
        }
    }

    /**
     * Handle proof of payment file upload
     * @param array $file
     * @return array
     */
    private function handleProofUpload(array $file): array
    {
        // Validate file upload
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }

        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'error' => 'File too large (max 5MB)'];
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type. Only images and PDFs allowed.'];
        }

        // Generate secure filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . date('Y-m-d') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        
        // Ensure upload directory exists
        $uploadDir = dirname(__DIR__, 2) . '/uploads/deposits/' . date('Y/m');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filepath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            chmod($filepath, 0644);
            
            return [
                'success' => true,
                'filepath' => str_replace(dirname(__DIR__, 2), '', $filepath),
                'filename' => $filename
            ];
        }

        return ['success' => false, 'error' => 'Upload failed'];
    }

    /**
     * Format deposit for display
     * @param array $deposit
     * @return array
     */
    private function formatDepositForDisplay(array $deposit): array
    {
        $deposit['formatted_amount'] = '$' . number_format((float)$deposit['requested_amount'], 2);
        $deposit['formatted_fee'] = '$' . number_format((float)$deposit['fee_amount'], 2);
        $deposit['formatted_date'] = date('M j, Y H:i', strtotime($deposit['created_at']));
        $deposit['status_badge'] = $this->getStatusBadge($deposit['status']);
        $deposit['verification_badge'] = $this->getVerificationBadge($deposit['verification_status']);
        
        // Truncate sensitive data
        if (!empty($deposit['deposit_address'])) {
            $address = $deposit['deposit_address'];
            if (strlen($address) > 20) {
                $deposit['display_address'] = substr($address, 0, 10) . '...' . substr($address, -6);
            }
        }

        return $deposit;
    }

    /**
     * Format fee display text
     * @param array $method
     * @return string
     */
    private function formatFeeDisplay(array $method): string
    {
        if ($method['charge_type'] === 'percentage') {
            return number_format($method['charge'], 2) . '%';
        } else {
            return '$' . number_format($method['charge'], 2);
        }
    }

    /**
     * Format limits display text
     * @param array $method
     * @return string
     */
    private function formatLimitsDisplay(array $method): string
    {
        $min = '$' . number_format($method['minimum_deposit'], 2);
        $max = '$' . number_format($method['maximum_deposit'], 0);
        return "Min: {$min} • Max: {$max}";
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
            'expired' => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Get verification badge HTML class
     * @param string $status
     * @return string
     */
    private function getVerificationBadge(string $status): string
    {
        return match($status) {
            'verified' => 'success',
            'rejected' => 'danger',
            'pending' => 'warning',
            default => 'secondary'
        };
    }

    /**
     * Determine crypto currency from currency and network
     * @param string $currency
     * @param string $network
     * @return string
     */
    private function determineCryptoCurrency(string $currency, string $network): string
    {
        return match(strtoupper($currency)) {
            'BTC' => 'BTC',
            'ETH' => 'ETH',
            'USDT' => 'USDT',
            'LTC' => 'LTC',
            'BNB' => 'BNB',
            default => strtoupper($currency)
        };
    }
}