<?php
/**
 * Enhanced Admin Settings Service
 * Manages all integrated settings for the admin panel
 */

namespace App\Services;

use App\Services\PaymentGatewayService;
use App\Services\EmailService;
use App\Services\SupportService;
use App\Services\UserTransferService;
use Exception;

class EnhancedAdminSettings {
    private $database;
    private $paymentGateway;
    private $emailService;
    private $supportService;
    private $transferService;
    
    public function __construct($database) {
        $this->database = $database;
        $this->initializeServices();
    }
    
    /**
     * Initialize all services
     */
    private function initializeServices() {
        try {
            $this->paymentGateway = new PaymentGatewayService($this->database);
            $this->emailService = new EmailService($this->database);
            $this->supportService = new SupportService($this->database);
            $this->transferService = new UserTransferService($this->database);
        } catch (Exception $e) {
            error_log("Error initializing services: " . $e->getMessage());
        }
    }
    
    /**
     * Get all settings organized by category
     */
    public function getAllSettings() {
        try {
            $settings = [];
            
            // Platform Settings
            $settings['platform'] = $this->getPlatformSettings();
            
            // Payment Gateway Settings
            $settings['payment_gateways'] = $this->getPaymentGatewaySettings();
            
            // Email Settings
            $settings['email'] = $this->getEmailSettings();
            
            // Support System Settings
            $settings['support'] = $this->getSupportSettings();
            
            // Transfer Settings
            $settings['transfers'] = $this->getTransferSettings();
            
            // Security Settings
            $settings['security'] = $this->getSecuritySettings();
            
            // Notification Settings
            $settings['notifications'] = $this->getNotificationSettings();
            
            return $settings;
            
        } catch (Exception $e) {
            error_log("Error getting all settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get platform settings
     */
    private function getPlatformSettings() {
        try {
            $settings = $this->database->fetchAll("SELECT * FROM admin_settings WHERE setting_key LIKE 'platform_%' OR setting_key IN ('site_name', 'site_description', 'maintenance_mode')");
            $result = [];
            
            foreach ($settings as $setting) {
                $result[$setting['setting_key']] = $setting['setting_value'];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting platform settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get payment gateway settings
     */
    private function getPaymentGatewaySettings() {
        try {
            $settings = $this->database->fetchAll("SELECT * FROM admin_settings WHERE setting_key LIKE 'payment_%'");
            $result = [];
            
            foreach ($settings as $setting) {
                $result[$setting['setting_key']] = $setting['setting_value'];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting payment gateway settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get email settings
     */
    private function getEmailSettings() {
        try {
            $settings = $this->database->fetchAll("SELECT * FROM admin_settings WHERE setting_key LIKE 'email_%'");
            $result = [];
            
            foreach ($settings as $setting) {
                $result[$setting['setting_key']] = $setting['setting_value'];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting email settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get support system settings
     */
    private function getSupportSettings() {
        try {
            $settings = $this->database->fetchAll("SELECT * FROM admin_settings WHERE setting_key LIKE 'support_%'");
            $result = [];
            
            foreach ($settings as $setting) {
                $result[$setting['setting_key']] = $setting['setting_value'];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting support settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get transfer settings
     */
    private function getTransferSettings() {
        try {
            $settings = $this->database->fetchAll("SELECT * FROM admin_settings WHERE setting_key LIKE 'transfer_%'");
            $result = [];
            
            foreach ($settings as $setting) {
                $result[$setting['setting_key']] = $setting['setting_value'];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting transfer settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get security settings
     */
    private function getSecuritySettings() {
        try {
            $settings = $this->database->fetchAll("SELECT * FROM admin_settings WHERE setting_key LIKE 'security_%'");
            $result = [];
            
            foreach ($settings as $setting) {
                $result[$setting['setting_key']] = $setting['setting_value'];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting security settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get notification settings
     */
    private function getNotificationSettings() {
        try {
            $settings = $this->database->fetchAll("SELECT * FROM admin_settings WHERE setting_key LIKE 'notification_%'");
            $result = [];
            
            foreach ($settings as $setting) {
                $result[$setting['setting_key']] = $setting['setting_value'];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting notification settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update settings by category
     */
    public function updateSettingsByCategory($category, $settings) {
        try {
            foreach ($settings as $key => $value) {
                $this->database->update('admin_settings', 
                    ['setting_value' => $value], 
                    'setting_key = ?', 
                    [$key]
                );
            }
            
            return ['success' => true, 'message' => ucfirst($category) . ' settings updated successfully'];
        } catch (Exception $e) {
            error_log("Error updating $category settings: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating settings'];
        }
    }
    
    /**
     * Test payment gateway connection
     */
    public function testPaymentGateway($gateway) {
        try {
            switch ($gateway) {
                case 'cryptomus':
                    // Test with minimal data
                    $result = $this->paymentGateway->createCryptomusPayment(1.00, 'USD', 'TEST' . time(), 'Test payment', 'test@example.com');
                    return $result;
                    
                case 'nowpayments':
                    // Test with minimal data
                    $result = $this->paymentGateway->createNOWPaymentsPayment(1.00, 'USD', 'TEST' . time(), 'Test payment', 'test@example.com');
                    return $result;
                    
                default:
                    return ['success' => false, 'message' => 'Unknown gateway'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test email system
     */
    public function testEmailSystem() {
        try {
            return $this->emailService->testConfiguration();
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get system health status
     */
    public function getSystemHealth() {
        try {
            $health = [];
            
            // Database connection
            $health['database'] = $this->database->isConnected() ? 'healthy' : 'unhealthy';
            
            // Payment gateways
            $cryptomusEnabled = $this->getPaymentGatewaySettings()['payment_cryptomus_enabled'] ?? '0';
            $nowpaymentsEnabled = $this->getPaymentGatewaySettings()['payment_nowpayments_enabled'] ?? '0';
            $health['payment_gateways'] = ($cryptomusEnabled === '1' || $nowpaymentsEnabled === '1') ? 'configured' : 'not_configured';
            
            // Email system
            $emailEnabled = $this->getEmailSettings()['email_smtp_enabled'] ?? '0';
            $health['email_system'] = $emailEnabled === '1' ? 'configured' : 'not_configured';
            
            // Support system
            $supportEnabled = $this->getSupportSettings()['support_enabled'] ?? '0';
            $health['support_system'] = $supportEnabled === '1' ? 'enabled' : 'disabled';
            
            // Transfer system
            $transferEnabled = $this->getTransferSettings()['transfer_enabled'] ?? '0';
            $health['transfer_system'] = $transferEnabled === '1' ? 'enabled' : 'disabled';
            
            return $health;
            
        } catch (Exception $e) {
            error_log("Error getting system health: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get system statistics
     */
    public function getSystemStats() {
        try {
            $stats = [];
            
            // User statistics
            $userStats = $this->database->fetchOne("SELECT COUNT(*) as total, COUNT(CASE WHEN status = 'active' THEN 1 END) as active FROM users");
            $stats['users'] = $userStats;
            
            // Investment statistics
            $investmentStats = $this->database->fetchOne("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total_amount FROM investments WHERE status = 'active'");
            $stats['investments'] = $investmentStats;
            
            // Transaction statistics
            $transactionStats = $this->database->fetchOne("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total_amount FROM transactions WHERE status = 'completed'");
            $stats['transactions'] = $transactionStats;
            
            // Support ticket statistics
            if (isset($this->supportService)) {
                $stats['support_tickets'] = $this->supportService->getTicketStats();
            }
            
            // Transfer statistics
            if (isset($this->transferService)) {
                $stats['transfers'] = $this->transferService->getTransferStats();
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting system stats: " . $e->getMessage());
            return [];
        }
    }
}
