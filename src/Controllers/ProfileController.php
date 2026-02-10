<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Controllers/ProfileController.php
 * Purpose: User profile management controller
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

class ProfileController 
{
    private UserModel $userModel;
    
    public function __construct() 
    {
        $this->userModel = new UserModel();
    }
    
    /**
     * Get user profile data
     * @return array
     */
    public function getProfile(): array 
    {
        // Check authentication
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            return [
                'success' => false,
                'error' => 'Authentication required'
            ];
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            $user = $this->userModel->findById($userId);
            
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'User not found'
                ];
            }
            
            // Remove sensitive information
            unset($user['password_hash'], $user['password_reset_token'], $user['two_factor_secret']);
            
            return [
                'success' => true,
                'data' => $user
            ];
            
        } catch (\Exception $e) {
            error_log("Profile fetch error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to load profile data'
            ];
        }
    }
    
    /**
     * Update user profile
     * @return void
     */
    public function updateProfile(): void 
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
        
        $userId = (int)$_SESSION['user_id'];
        
        // Rate limiting
        if (!Security::rateLimitCheck('profile_update_' . $userId, 'profile_update', 5, 300)) {
            JsonResponse::error('Too many update attempts. Please wait 5 minutes.', 429);
            return;
        }
        
        // Validate inputs
        $errors = [];
        $updateData = [];
        
        // First name
        if (isset($_POST['first_name'])) {
            $firstName = Validator::sanitizeString($_POST['first_name'], 100);
            if (!empty($firstName) && !Validator::isValidName($firstName)) {
                $errors[] = 'First name contains invalid characters';
            } else {
                $updateData['first_name'] = $firstName;
            }
        }
        
        // Last name
        if (isset($_POST['last_name'])) {
            $lastName = Validator::sanitizeString($_POST['last_name'], 100);
            if (!empty($lastName) && !Validator::isValidName($lastName)) {
                $errors[] = 'Last name contains invalid characters';
            } else {
                $updateData['last_name'] = $lastName;
            }
        }
        
        // Email (requires additional validation)
        if (isset($_POST['email'])) {
            $email = Validator::sanitizeString($_POST['email'], 255);
            if (!Validator::isValidEmail($email)) {
                $errors[] = 'Valid email address is required';
            } else {
                // Check if email is already in use by another user
                $existingUser = $this->userModel->findByEmail($email);
                if ($existingUser && $existingUser['id'] != $userId) {
                    $errors[] = 'Email address is already in use';
                } else {
                    $updateData['email'] = $email;
                    // Note: Email change should trigger verification
                    if ($existingUser['email'] !== $email) {
                        $updateData['email_verified'] = 0; // Reset verification status
                    }
                }
            }
        }
        
        // Phone
        if (isset($_POST['phone'])) {
            $phone = Validator::sanitizeString($_POST['phone'], 20);
            if (!empty($phone) && !Validator::isValidPhone($phone)) {
                $errors[] = 'Invalid phone number format';
            } else {
                $updateData['phone'] = $phone;
            }
        }
        
        // Country
        if (isset($_POST['country'])) {
            $country = Validator::sanitizeString($_POST['country'], 100);
            if (!empty($country)) {
                $updateData['country'] = $country;
            }
        }
        
        // Return validation errors
        if (!empty($errors)) {
            JsonResponse::error(implode(', ', $errors));
            return;
        }
        
        // Check if there's anything to update
        if (empty($updateData)) {
            JsonResponse::error('No data provided for update');
            return;
        }
        
        try {
            // Update profile
            $result = $this->userModel->updateProfile($userId, $updateData);
            
            if ($result['success']) {
                // Update session data if needed
                if (isset($updateData['first_name']) || isset($updateData['last_name'])) {
                    $_SESSION['user_name'] = trim(($updateData['first_name'] ?? '') . ' ' . ($updateData['last_name'] ?? ''));
                }
                
                JsonResponse::success([
                    'message' => $result['message'],
                    'updated_fields' => array_keys($updateData)
                ]);
            } else {
                JsonResponse::error($result['error']);
            }
            
        } catch (\Exception $e) {
            error_log("Profile update error for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Profile update failed. Please try again.');
        }
    }
    
    /**
     * Handle KYC document upload
     * @return void
     */
    public function uploadDocument(): void 
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
        
        $userId = (int)$_SESSION['user_id'];
        
        // Rate limiting for uploads
        if (!Security::rateLimitCheck('document_upload_' . $userId, 'document_upload', 3, 3600)) {
            JsonResponse::error('Too many upload attempts. Please wait 1 hour.', 429);
            return;
        }
        
        // Validate document type
        $documentType = Validator::sanitizeString($_POST['document_type'] ?? '', 50);
        $validTypes = ['passport', 'license', 'utility_bill', 'bank_statement'];
        
        if (!in_array($documentType, $validTypes)) {
            JsonResponse::error('Invalid document type');
            return;
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            JsonResponse::error('Please select a file to upload');
            return;
        }
        
        $file = $_FILES['document'];
        
        try {
            // Validate file
            $validationResult = $this->validateKycDocument($file);
            if (!$validationResult['success']) {
                JsonResponse::error($validationResult['error']);
                return;
            }
            
            // Create upload directory if it doesn't exist
            $uploadDir = dirname(__DIR__, 2) . '/uploads/kyc/' . $userId;
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    JsonResponse::error('Upload directory creation failed');
                    return;
                }
            }
            
            // Generate secure filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = $documentType . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $filePath = $uploadDir . '/' . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                JsonResponse::error('File upload failed');
                return;
            }
            
            // Set proper file permissions
            chmod($filePath, 0644);
            
            // Save document record to database
            $result = $this->saveKycDocument($userId, $documentType, 'uploads/kyc/' . $userId . '/' . $filename);
            
            if ($result['success']) {
                JsonResponse::success([
                    'message' => 'Document uploaded successfully. It will be reviewed within 24-48 hours.',
                    'document_id' => $result['document_id']
                ]);
            } else {
                // Clean up uploaded file on database error
                unlink($filePath);
                JsonResponse::error($result['error']);
            }
            
        } catch (\Exception $e) {
            error_log("Document upload error for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Document upload failed. Please try again.');
        }
    }
    
    /**
     * Validate KYC document file
     * @param array $file
     * @return array
     */
    private function validateKycDocument(array $file): array 
    {
        // Check file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return [
                'success' => false,
                'error' => 'File size must be less than 5MB'
            ];
        }
        
        // Validate MIME type
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/jpg',
            'application/pdf'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimes)) {
            return [
                'success' => false,
                'error' => 'Only JPG, PNG, and PDF files are allowed'
            ];
        }
        
        // Validate file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedExtensions)) {
            return [
                'success' => false,
                'error' => 'Invalid file extension'
            ];
        }
        
        // Additional security check for images
        if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                return [
                    'success' => false,
                    'error' => 'Invalid image file'
                ];
            }
            
            // Check minimum dimensions (300x300)
            if ($imageInfo[0] < 300 || $imageInfo[1] < 300) {
                return [
                    'success' => false,
                    'error' => 'Image must be at least 300x300 pixels'
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Save KYC document record to database
     * @param int $userId
     * @param string $documentType
     * @param string $filePath
     * @return array
     */
    private function saveKycDocument(int $userId, string $documentType, string $filePath): array 
    {
        try {
            $stmt = $this->userModel->db->prepare(
                "INSERT INTO user_documents (user_id, document_type, file_path, status, created_at) 
                 VALUES (?, ?, ?, 'pending', NOW())"
            );
            
            $success = $stmt->execute([$userId, $documentType, $filePath]);
            
            if ($success) {
                $documentId = (int)$this->userModel->db->lastInsertId();
                
                // Log document upload
                Security::logAudit($userId, 'kyc_document_uploaded', 'user_documents', $documentId, null, [
                    'document_type' => $documentType,
                    'file_path' => $filePath
                ]);
                
                return [
                    'success' => true,
                    'document_id' => $documentId
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to save document record'
            ];
            
        } catch (\Exception $e) {
            error_log("Failed to save KYC document: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }
    
    /**
     * Get user's KYC documents
     * @return void
     */
    public function getKycDocuments(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            $stmt = $this->userModel->db->prepare(
                "SELECT id, document_type, status, created_at, reviewed_at
                 FROM user_documents 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC"
            );
            
            $stmt->execute([$userId]);
            $documents = $stmt->fetchAll();
            
            JsonResponse::success(['documents' => $documents]);
            
        } catch (\Exception $e) {
            error_log("Failed to fetch KYC documents for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to load documents');
        }
    }
    
    /**
     * Get profile statistics
     * @return void
     */
    public function getProfileStats(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            // Get user data
            $user = $this->userModel->findById($userId);
            if (!$user) {
                JsonResponse::error('User not found');
                return;
            }
            
            // Calculate profile completeness
            $completenessFields = [
                'first_name', 'last_name', 'phone', 'country', 'email_verified', 'kyc_status'
            ];
            
            $completedFields = 0;
            foreach ($completenessFields as $field) {
                if ($field === 'email_verified' && $user[$field] == 1) {
                    $completedFields++;
                } elseif ($field === 'kyc_status' && $user[$field] === 'approved') {
                    $completedFields++;
                } elseif (!empty($user[$field])) {
                    $completedFields++;
                }
            }
            
            $completenessPercentage = round(($completedFields / count($completenessFields)) * 100);
            
            // Get additional stats
            $stmt = $this->userModel->db->prepare(
                "SELECT 
                    (SELECT COUNT(*) FROM user_documents WHERE user_id = ?) as total_documents,
                    (SELECT COUNT(*) FROM user_documents WHERE user_id = ? AND status = 'approved') as approved_documents,
                    (SELECT COUNT(*) FROM user_wallets WHERE user_id = ?) as total_wallets
                 FROM DUAL"
            );
            
            $stmt->execute([$userId, $userId, $userId]);
            $stats = $stmt->fetch();
            
            JsonResponse::success([
                'completeness_percentage' => $completenessPercentage,
                'completed_fields' => $completedFields,
                'total_fields' => count($completenessFields),
                'total_documents' => (int)$stats['total_documents'],
                'approved_documents' => (int)$stats['approved_documents'],
                'total_wallets' => (int)$stats['total_wallets'],
                'member_since' => $user['created_at'],
                'last_login' => $user['last_login']
            ]);
            
        } catch (\Exception $e) {
            error_log("Failed to get profile stats for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to load profile statistics');
        }
    }
}