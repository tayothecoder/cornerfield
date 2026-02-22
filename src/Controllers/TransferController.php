<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Controllers/TransferController.php
 * Purpose: Internal user-to-user fund transfer controller
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Controllers;

use App\Models\UserModel;
use App\Utils\Security;
use App\Utils\Validator;
use App\Utils\JsonResponse;
use PDO;

class TransferController 
{
    private UserModel $userModel;
    private PDO $db;
    
    public function __construct() 
    {
        $this->userModel = new UserModel();
        $this->db = $this->userModel->db;
    }
    
    /**
     * Process internal transfer between users
     * @return void
     */
    public function transfer(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $senderId = (int)$_SESSION['user_id'];
        
        // Rate limiting
        if (!Security::rateLimitCheck('transfer_' . $senderId, 'transfer', 3, 3600)) {
            JsonResponse::error('Too many transfer attempts. Please wait 1 hour.', 429);
            return;
        }
        
        // Validate inputs
        $recipient = Validator::sanitizeString($_POST['recipient'] ?? '', 255);
        $amount = Validator::sanitizeFloat($_POST['amount'] ?? 0);
        $description = Validator::sanitizeString($_POST['description'] ?? $_POST['note'] ?? '', 500);
        
        $errors = [];
        
        if (empty($recipient)) {
            $errors[] = 'Recipient username or email is required';
        }
        
        if ($amount <= 0) {
            $errors[] = 'Transfer amount must be greater than zero';
        }
        
        if ($amount < 1) {
            $errors[] = 'Minimum transfer amount is $1.00';
        }
        
        if ($amount > 50000) {
            $errors[] = 'Maximum transfer amount is $50,000.00';
        }
        
        if (!empty($errors)) {
            JsonResponse::error(implode(', ', $errors));
            return;
        }
        
        try {
            // Get sender details
            $sender = $this->userModel->findById($senderId);
            if (!$sender) {
                JsonResponse::error('Sender account not found');
                return;
            }
            
            // Check if sender account is active and verified
            if (!$sender['is_active']) {
                JsonResponse::error('Your account is inactive. Contact support.');
                return;
            }
            
            if ($sender['kyc_status'] !== 'approved') {
                JsonResponse::error('KYC verification required for transfers');
                return;
            }
            
            // Find recipient by username or email
            $receiver = $this->findRecipient($recipient);
            if (!$receiver) {
                JsonResponse::error('Recipient not found');
                return;
            }
            
            // Prevent self-transfer
            if ($receiver['id'] === $senderId) {
                JsonResponse::error('Cannot transfer to yourself');
                return;
            }
            
            // Check if receiver account is active
            if (!$receiver['is_active']) {
                JsonResponse::error('Recipient account is inactive');
                return;
            }
            
            // Calculate transfer fee (if applicable)
            $transferFee = $this->calculateTransferFee($amount);
            $totalDeduction = $amount + $transferFee;
            
            // Check sender balance
            if ($sender['balance'] < $totalDeduction) {
                JsonResponse::error('Insufficient balance. You need $' . number_format($totalDeduction, 2) . ' including fees.');
                return;
            }
            
            // Process the transfer atomically
            $result = $this->processTransfer($senderId, $receiver['id'], $amount, $transferFee, $description);
            
            if ($result['success']) {
                JsonResponse::success([
                    'message' => $result['message'],
                    'transaction_id' => $result['transaction_id'],
                    'amount' => $amount,
                    'fee' => $transferFee,
                    'recipient' => [
                        'username' => $receiver['username'],
                        'name' => trim(($receiver['first_name'] ?? '') . ' ' . ($receiver['last_name'] ?? ''))
                    ]
                ]);
            } else {
                JsonResponse::error($result['error']);
            }
            
        } catch (\Exception $e) {
            error_log("Transfer error for user {$senderId}: " . $e->getMessage());
            JsonResponse::error('Transfer failed. Please try again.');
        }
    }
    
    /**
     * Get transfer page data including balance, limits, and recent transfers
     * @return array
     */
    public function getTransferData(): array
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new \RuntimeException('Not authenticated');
        }

        $user = $this->userModel->findById($userId);
        $balance = $user ? (float)$user['balance'] : 0.0;

        // get daily used
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(ABS(amount)), 0) as daily_total
             FROM transactions
             WHERE user_id = ? AND type = 'transfer' AND amount < 0
             AND DATE(created_at) = CURDATE()"
        );
        $stmt->execute([$userId]);
        $dailyUsed = (float)$stmt->fetchColumn();

        // get monthly used
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(ABS(amount)), 0) as monthly_total
             FROM transactions
             WHERE user_id = ? AND type = 'transfer' AND amount < 0
             AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
        );
        $stmt->execute([$userId]);
        $monthlyUsed = (float)$stmt->fetchColumn();

        // get recent transfers
        $history = $this->getTransferHistory();
        $recentTransfers = $history['transfers'] ?? [];

        return [
            'availableBalance' => $balance,
            'transferFee' => $this->calculateTransferFee(100), // sample fee
            'recentTransfers' => array_slice($recentTransfers, 0, 10),
            'transferLimits' => [
                'daily' => 5000.00,
                'monthly' => 50000.00,
                'min' => 1.00,
                'max' => 50000.00,
            ],
            'dailyUsed' => $dailyUsed,
            'monthlyUsed' => $monthlyUsed,
        ];
    }

    /**
     * Get transfer history for current user
     * @return array
     */
    public function getTransferHistory(): array 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            return [
                'success' => false,
                'error' => 'Authentication required'
            ];
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            // Get transfers where user is either sender or receiver
            $stmt = $this->db->prepare(
                "SELECT t.*, 
                        sender.username as sender_username, 
                        sender.first_name as sender_first_name, 
                        sender.last_name as sender_last_name,
                        receiver.username as receiver_username,
                        receiver.first_name as receiver_first_name,
                        receiver.last_name as receiver_last_name
                 FROM transactions t
                 LEFT JOIN users sender ON JSON_EXTRACT(t.reference_data, '$.sender_id') = sender.id
                 LEFT JOIN users receiver ON JSON_EXTRACT(t.reference_data, '$.receiver_id') = receiver.id
                 WHERE t.user_id = ? AND t.type = 'transfer' 
                 ORDER BY t.created_at DESC
                 LIMIT 50"
            );
            
            $stmt->execute([$userId]);
            $transfers = $stmt->fetchAll();
            
            // Format transfer data for display
            foreach ($transfers as &$transfer) {
                $referenceData = json_decode($transfer['reference_data'] ?? '{}', true);
                
                $transfer['is_outgoing'] = isset($referenceData['sender_id']) && $referenceData['sender_id'] == $userId;
                $transfer['is_incoming'] = !$transfer['is_outgoing'];
                
                if ($transfer['is_outgoing']) {
                    $transfer['counterparty'] = [
                        'username' => $transfer['receiver_username'],
                        'name' => trim(($transfer['receiver_first_name'] ?? '') . ' ' . ($transfer['receiver_last_name'] ?? ''))
                    ];
                    $transfer['direction'] = 'outgoing';
                    $transfer['display_amount'] = '-$' . number_format((float)$transfer['amount'], 2);
                } else {
                    $transfer['counterparty'] = [
                        'username' => $transfer['sender_username'],
                        'name' => trim(($transfer['sender_first_name'] ?? '') . ' ' . ($transfer['sender_last_name'] ?? ''))
                    ];
                    $transfer['direction'] = 'incoming';
                    $transfer['display_amount'] = '+$' . number_format((float)$transfer['amount'], 2);
                }
                
                $transfer['formatted_date'] = $this->formatDateTime($transfer['created_at']);
                $transfer['status_badge_class'] = $this->getStatusBadgeClass($transfer['status']);
                $transfer['formatted_fee'] = $transfer['fee'] > 0 ? '$' . number_format((float)$transfer['fee'], 2) : 'Free';
            }
            
            return [
                'success' => true,
                'transfers' => $transfers
            ];
            
        } catch (\Exception $e) {
            error_log("Failed to fetch transfer history for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to load transfer history'
            ];
        }
    }
    
    /**
     * Find recipient user by username or email
     * @param string $identifier
     * @return array|null
     */
    private function findRecipient(string $identifier): ?array 
    {
        $user = null;

        // try email first if it looks like one
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = $this->userModel->findByEmail($identifier);
        }

        // fall back to username lookup
        if (!$user) {
            try {
                $user = $this->userModel->findByUsername($identifier);
            } catch (\InvalidArgumentException $e) {
                // identifier is not a valid username format, skip
            }
        }
        
        return $user;
    }
    
    /**
     * Calculate transfer fee
     * @param float $amount
     * @return float
     */
    private function calculateTransferFee(float $amount): float 
    {
        // Get fee settings from environment or database
        $feeType = $_ENV['TRANSFER_FEE_TYPE'] ?? 'free'; // 'free', 'flat', 'percentage'
        $feeValue = (float)($_ENV['TRANSFER_FEE_VALUE'] ?? 0);
        $maxFee = (float)($_ENV['TRANSFER_MAX_FEE'] ?? 10);
        
        switch ($feeType) {
            case 'flat':
                return $feeValue;
                
            case 'percentage':
                $fee = $amount * ($feeValue / 100);
                return min($fee, $maxFee);
                
            case 'free':
            default:
                return 0.0;
        }
    }
    
    /**
     * Process transfer atomically with database transactions
     * @param int $senderId
     * @param int $receiverId
     * @param float $amount
     * @param float $fee
     * @param string $description
     * @return array
     */
    private function processTransfer(int $senderId, int $receiverId, float $amount, float $fee, string $description): array 
    {
        try {
            $this->db->beginTransaction();
            
            // Create reference data
            $referenceData = [
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'transfer_type' => 'internal',
                'description' => $description,
                'fee' => $fee
            ];
            
            // Create outgoing transaction for sender
            $stmt = $this->db->prepare(
                "INSERT INTO transactions (
                    user_id, type, amount, fee, net_amount, status, payment_method,
                    currency, description, reference_data, created_at, updated_at
                ) VALUES (
                    ?, 'transfer', ?, ?, ?, 'completed', 'internal',
                    'USD', ?, ?, NOW(), NOW()
                )"
            );
            
            $netAmount = -($amount + $fee); // Negative for outgoing
            $transactionDescription = "Transfer to " . $this->getReceiverDisplayName($receiverId);
            
            $success = $stmt->execute([
                $senderId,
                $netAmount,
                $fee,
                $netAmount,
                $transactionDescription,
                json_encode($referenceData)
            ]);
            
            if (!$success) {
                throw new \Exception('Failed to create sender transaction');
            }
            
            $senderTransactionId = (int)$this->db->lastInsertId();
            
            // Create incoming transaction for receiver
            $referenceData['related_transaction_id'] = $senderTransactionId;
            
            $stmt = $this->db->prepare(
                "INSERT INTO transactions (
                    user_id, type, amount, fee, net_amount, status, payment_method,
                    currency, description, reference_data, created_at, updated_at
                ) VALUES (
                    ?, 'transfer', ?, 0, ?, 'completed', 'internal',
                    'USD', ?, ?, NOW(), NOW()
                )"
            );
            
            $transactionDescription = "Transfer from " . $this->getSenderDisplayName($senderId);
            
            $success = $stmt->execute([
                $receiverId,
                $amount,
                $amount,
                $transactionDescription,
                json_encode($referenceData)
            ]);
            
            if (!$success) {
                throw new \Exception('Failed to create receiver transaction');
            }
            
            $receiverTransactionId = (int)$this->db->lastInsertId();
            
            // Update sender balance (deduct amount + fee)
            $stmt = $this->db->prepare(
                "UPDATE users SET balance = balance - ?, updated_at = NOW() WHERE id = ?"
            );
            
            $success = $stmt->execute([$amount + $fee, $senderId]);
            
            if (!$success) {
                throw new \Exception('Failed to update sender balance');
            }
            
            // Update receiver balance (add amount)
            $stmt = $this->db->prepare(
                "UPDATE users SET balance = balance + ?, updated_at = NOW() WHERE id = ?"
            );
            
            $success = $stmt->execute([$amount, $receiverId]);
            
            if (!$success) {
                throw new \Exception('Failed to update receiver balance');
            }
            
            // Verify balances are non-negative
            $stmt = $this->db->prepare(
                "SELECT balance FROM users WHERE id = ? AND balance < 0"
            );
            $stmt->execute([$senderId]);
            
            if ($stmt->fetch()) {
                throw new \Exception('Transfer would result in negative balance');
            }
            
            $this->db->commit();
            
            // Log the transfer
            Security::logAudit($senderId, 'internal_transfer_sent', 'transactions', $senderTransactionId, null, [
                'receiver_id' => $receiverId,
                'amount' => $amount,
                'fee' => $fee,
                'description' => $description
            ]);
            
            Security::logAudit($receiverId, 'internal_transfer_received', 'transactions', $receiverTransactionId, null, [
                'sender_id' => $senderId,
                'amount' => $amount
            ]);
            
            return [
                'success' => true,
                'message' => 'Transfer completed successfully',
                'transaction_id' => $senderTransactionId
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Transfer processing error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Transfer failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get receiver display name
     * @param int $receiverId
     * @return string
     */
    private function getReceiverDisplayName(int $receiverId): string 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT username, first_name, last_name FROM users WHERE id = ?"
            );
            $stmt->execute([$receiverId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return 'Unknown User';
            }
            
            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            return $name ?: $user['username'];
            
        } catch (\Exception $e) {
            return 'Unknown User';
        }
    }
    
    /**
     * Get sender display name
     * @param int $senderId
     * @return string
     */
    private function getSenderDisplayName(int $senderId): string 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT username, first_name, last_name FROM users WHERE id = ?"
            );
            $stmt->execute([$senderId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return 'Unknown User';
            }
            
            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            return $name ?: $user['username'];
            
        } catch (\Exception $e) {
            return 'Unknown User';
        }
    }
    
    /**
     * Validate recipient (AJAX endpoint)
     * @return void
     */
    public function validateRecipient(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $currentUserId = (int)$_SESSION['user_id'];
        $recipient = Validator::sanitizeString($_POST['recipient'] ?? '', 255);
        
        if (empty($recipient)) {
            JsonResponse::error('Recipient identifier is required');
            return;
        }
        
        try {
            $user = $this->findRecipient($recipient);
            
            if (!$user) {
                JsonResponse::error('User not found');
                return;
            }
            
            if ($user['id'] === $currentUserId) {
                JsonResponse::error('Cannot transfer to yourself');
                return;
            }
            
            if (!$user['is_active']) {
                JsonResponse::error('Recipient account is inactive');
                return;
            }
            
            // Return sanitized user info
            $userInfo = [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                'member_since' => $user['created_at'],
                'kyc_verified' => $user['kyc_status'] === 'approved'
            ];
            
            JsonResponse::success([
                'message' => 'Recipient found',
                'user' => $userInfo
            ]);
            
        } catch (\Exception $e) {
            error_log("Recipient validation error: " . $e->getMessage());
            JsonResponse::error('Validation failed');
        }
    }
    
    /**
     * Get transfer limits and fees
     * @return void
     */
    public function getLimitsAndFees(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            // Get user balance
            $user = $this->userModel->findById($userId);
            if (!$user) {
                JsonResponse::error('User not found');
                return;
            }
            
            // Calculate daily transfer limit based on KYC status
            $dailyLimit = $user['kyc_status'] === 'approved' ? 10000.0 : 500.0;
            
            // Get today's transfer total
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(ABS(amount)), 0) as daily_total
                 FROM transactions 
                 WHERE user_id = ? AND type = 'transfer' 
                 AND DATE(created_at) = CURDATE()
                 AND amount < 0" // Only outgoing transfers
            );
            
            $stmt->execute([$userId]);
            $dailyTotal = (float)$stmt->fetchColumn();
            
            $limitsAndFees = [
                'available_balance' => (float)$user['balance'],
                'min_transfer' => 1.0,
                'max_transfer' => 50000.0,
                'daily_limit' => $dailyLimit,
                'daily_used' => $dailyTotal,
                'daily_remaining' => max(0, $dailyLimit - $dailyTotal),
                'fee_structure' => [
                    'type' => $_ENV['TRANSFER_FEE_TYPE'] ?? 'free',
                    'value' => (float)($_ENV['TRANSFER_FEE_VALUE'] ?? 0),
                    'max_fee' => (float)($_ENV['TRANSFER_MAX_FEE'] ?? 10),
                    'description' => $this->getFeeDescription()
                ],
                'requirements' => [
                    'kyc_required' => $user['kyc_status'] !== 'approved',
                    'active_account' => $user['is_active']
                ]
            ];
            
            JsonResponse::success(['limits_and_fees' => $limitsAndFees]);
            
        } catch (\Exception $e) {
            error_log("Failed to get limits and fees for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to load transfer information');
        }
    }
    
    /**
     * Get fee description text
     * @return string
     */
    private function getFeeDescription(): string 
    {
        $feeType = $_ENV['TRANSFER_FEE_TYPE'] ?? 'free';
        $feeValue = (float)($_ENV['TRANSFER_FEE_VALUE'] ?? 0);
        
        switch ($feeType) {
            case 'flat':
                return '$' . number_format($feeValue, 2) . ' flat fee per transfer';
            case 'percentage':
                return number_format($feeValue, 1) . '% of transfer amount';
            case 'free':
            default:
                return 'Free internal transfers';
        }
    }
    
    /**
     * Get status badge CSS class
     * @param string $status
     * @return string
     */
    private function getStatusBadgeClass(string $status): string 
    {
        switch ($status) {
            case 'completed':
                return 'bg-cf-success/10 text-cf-success';
            case 'pending':
                return 'bg-cf-warning/10 text-cf-warning';
            case 'processing':
                return 'bg-cf-info/10 text-cf-info';
            case 'failed':
                return 'bg-cf-danger/10 text-cf-danger';
            case 'cancelled':
                return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
            default:
                return 'bg-gray-100 text-gray-600';
        }
    }
    
    /**
     * Format datetime for display
     * @param string $datetime
     * @return string
     */
    private function formatDateTime(string $datetime): string 
    {
        $date = new \DateTime($datetime);
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        if ($diff->d == 0 && $diff->h < 24) {
            if ($diff->h == 0 && $diff->i < 60) {
                if ($diff->i < 1) {
                    return 'Just now';
                }
                return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
            }
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->d < 7) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } else {
            return $date->format('M j, Y g:i A');
        }
    }
}