<?php
declare(strict_types=1);
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use App\Config\Database;
use Exception as BaseException;

/**
 * Enhanced Email Service
 * Handles SMTP configuration and email sending using PHPMailer
 */
class EmailService {
    private $database;
    private $config;
    private $mailer;
    
    public function __construct($database) {
        $this->database = $database;
        $this->config = $this->getEmailConfig();
        // Only initialize mailer if we have SMTP credentials
        if (!empty($this->config['smtp_username']) && !empty($this->config['smtp_password'])) {
            $this->initializeMailer();
        }
    }
    
    /**
     * Initialize PHPMailer with configuration
     */
    private function initializeMailer() {
        try {
            $this->mailer = new PHPMailer(true);
            
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp_host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['smtp_username'];
            $this->mailer->Password = $this->config['smtp_password'];
            $this->mailer->SMTPSecure = $this->config['smtp_encryption'];
            $this->mailer->Port = $this->config['smtp_port'];
            
            // Default settings
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
            // Debug mode (only in development)
            if ($this->config['debug_mode']) {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            }
            
        } catch (Exception $e) {
            error_log("EmailService initialization failed: " . $e->getMessage());
            throw new BaseException("Email service initialization failed");
        }
    }
    
    /**
     * Get email configuration from database
     */
    private function getEmailConfig() {
        try {
            $config = $this->database->fetchAll("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key LIKE 'email_%'");
            $emailConfig = [];
            
            foreach ($config as $setting) {
                $emailConfig[$setting['setting_key']] = $setting['setting_value'];
            }
            
            // Map database fields to configuration keys
            $mappedConfig = [
                'smtp_host' => $emailConfig['email_smtp_host'] ?? 'localhost',
                'smtp_port' => $emailConfig['email_smtp_port'] ?? 587,
                'smtp_username' => $emailConfig['email_smtp_username'] ?? '',
                'smtp_password' => $emailConfig['email_smtp_password'] ?? '',
                'smtp_encryption' => $emailConfig['email_smtp_encryption'] ?? 'tls',
                'from_email' => $emailConfig['email_from_email'] ?? 'noreply@cornerfield.local',
                'from_name' => $emailConfig['email_from_name'] ?? 'CornerField',
                'debug_mode' => false
            ];
            
            return $mappedConfig;
            
        } catch (BaseException $e) {
            error_log("Failed to get email config: " . $e->getMessage());
            return [
                'smtp_host' => 'localhost',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_encryption' => 'tls',
                'from_email' => 'noreply@cornerfield.local',
                'from_name' => 'CornerField',
                'debug_mode' => false
            ];
        }
    }
    
    /**
     * Send a simple email
     */
    public function sendEmail($to, $subject, $message, $isHTML = true) {
        try {
            if (!$this->mailer) {
                throw new Exception("Email service not properly configured. Please set up SMTP credentials first.");
            }
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            
            if ($isHTML) {
                $this->mailer->Body = $this->wrapInTemplate($message);
                $this->mailer->AltBody = strip_tags($message);
            } else {
                $this->mailer->Body = $message;
                $this->mailer->isHTML(false);
            }
            
            $result = $this->mailer->send();
            $this->logEmail($to, $subject, 'sent', '');
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log("Email sending failed: " . $error);
            $this->logEmail($to, $subject, 'failed', $error);
            
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $error
            ];
        }
    }
    
    /**
     * Send email with template
     */
    public function sendTemplateEmail($to, $template, $data = []) {
        $subject = $this->getTemplateSubject($template, $data);
        $message = $this->renderTemplate($template, $data);
        
        return $this->sendEmail($to, $subject, $message, true);
    }
    
    /**
     * Send welcome email to new users
     */
    public function sendWelcomeEmail($user) {
        $data = [
            'user_name' => $user['first_name'] . ' ' . $user['last_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'login_url' => $this->config['site_url'] . '/users/login.php'
        ];
        
        return $this->sendTemplateEmail($user['email'], 'welcome', $data);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($user, $resetToken) {
        $data = [
            'user_name' => $user['first_name'] . ' ' . $user['last_name'],
            'reset_url' => $this->config['site_url'] . '/users/reset-password.php?token=' . $resetToken,
            'expiry_hours' => 24
        ];
        
        return $this->sendTemplateEmail($user['email'], 'password_reset', $data);
    }
    
    /**
     * Send investment confirmation email
     */
    public function sendInvestmentConfirmation($user, $investment) {
        $data = [
            'user_name' => $user['first_name'] . ' ' . $user['last_name'],
            'investment_amount' => $investment['amount'],
            'investment_plan' => $investment['plan_name'],
            'daily_rate' => $investment['daily_rate'],
            'duration_days' => $investment['duration_days'],
            'expected_return' => $investment['expected_return']
        ];
        
        return $this->sendTemplateEmail($user['email'], 'investment_confirmation', $data);
    }
    
    /**
     * Send withdrawal confirmation email
     */
    public function sendWithdrawalConfirmation($user, $withdrawal) {
        $data = [
            'user_name' => $user['first_name'] . ' ' . $user['last_name'],
            'withdrawal_amount' => $withdrawal['amount'],
            'fee_amount' => $withdrawal['fee_amount'],
            'net_amount' => $withdrawal['amount'] - $withdrawal['fee_amount'],
            'status' => $withdrawal['status']
        ];
        
        return $this->sendTemplateEmail($user['email'], 'withdrawal_confirmation', $data);
    }
    
    /**
     * Send admin notification email
     */
    public function sendAdminNotification($subject, $message, $adminEmails = []) {
        if (empty($adminEmails)) {
            $adminEmails = $this->getAdminEmails();
        }
        
        $results = [];
        foreach ($adminEmails as $email) {
            $results[$email] = $this->sendEmail($email, $subject, $message, true);
        }
        
        return $results;
    }
    
    /**
     * Get admin emails from database
     */
    private function getAdminEmails() {
        try {
            $admins = $this->database->fetchAll("SELECT email FROM admins WHERE is_active = 1");
            return array_column($admins, 'email');
        } catch (BaseException $e) {
            error_log("Failed to get admin emails: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Render email template
     */
    private function renderTemplate($template, $data) {
        $templateFile = __DIR__ . '/../Templates/emails/' . $template . '.html';
        
        if (!file_exists($templateFile)) {
            return $this->getDefaultTemplate($template, $data);
        }
        
        $content = file_get_contents($templateFile);
        return $this->replaceTemplateVariables($content, $data);
    }
    
    /**
     * Get default template if file doesn't exist
     */
    private function getDefaultTemplate($template, $data) {
        $defaults = [
            'welcome' => '<h2>Welcome to CornerField!</h2><p>Hello {{user_name}}, welcome to our platform!</p>',
            'password_reset' => '<h2>Password Reset Request</h2><p>Click here to reset your password: <a href="{{reset_url}}">Reset Password</a></p>',
            'investment_confirmation' => '<h2>Investment Confirmed</h2><p>Your investment of ${{investment_amount}} has been confirmed.</p>',
            'withdrawal_confirmation' => '<h2>Withdrawal {{status}}</h2><p>Your withdrawal of ${{withdrawal_amount}} has been {{status}}.</p>'
        ];
        
        $content = $defaults[$template] ?? '<h2>{{subject}}</h2><p>{{message}}</p>';
        return $this->replaceTemplateVariables($content, $data);
    }
    
    /**
     * Replace template variables
     */
    private function replaceTemplateVariables($content, $data) {
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }
    
    /**
     * Get template subject
     */
    private function getTemplateSubject($template, $data) {
        $subjects = [
            'welcome' => 'Welcome to CornerField!',
            'password_reset' => 'Password Reset Request',
            'investment_confirmation' => 'Investment Confirmed',
            'withdrawal_confirmation' => 'Withdrawal ' . ucfirst($data['status'] ?? 'Updated')
        ];
        
        return $subjects[$template] ?? 'CornerField Notification';
    }
    
    /**
     * Wrap message in email template
     */
    private function wrapInTemplate($message) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>CornerField</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>CornerField</h1>
                </div>
                <div class="content">
                    ' . $message . '
                </div>
                <div class="footer">
                    <p>This email was sent from CornerField. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Log email activity
     */
    private function logEmail($to, $subject, $status, $error = '') {
        try {
            $this->database->insert('email_logs', [
                'to_email' => $to,
                'subject' => $subject,
                'status' => $status,
                'error_message' => $error,
                'sent_at' => date('Y-m-d H:i:s')
            ]);
        } catch (BaseException $e) {
            error_log("Failed to log email: " . $e->getMessage());
        }
    }
    
    /**
     * Test email configuration
     */
    public function testConfiguration() {
        try {
            if (!$this->mailer) {
                return [
                    'success' => false,
                    'message' => 'Email service not properly configured. Please set up SMTP credentials first.'
                ];
            }
            
            // Test SMTP connection without sending actual email
            $this->mailer->SMTPConnect();
            
            return [
                'success' => true,
                'message' => 'SMTP connection test successful. Email configuration is working properly.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP connection test failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if email service is properly configured
     */
    public function isConfigured() {
        return $this->mailer !== null;
    }
    
    /**
     * Reinitialize mailer with updated configuration
     */
    public function reinitializeMailer() {
        try {
            // Reload configuration from database
            $this->config = $this->getEmailConfig();
            
            // Only initialize if we have SMTP credentials
            if (!empty($this->config['smtp_username']) && !empty($this->config['smtp_password'])) {
                $this->initializeMailer();
                return true;
            } else {
                $this->mailer = null;
                return false;
            }
        } catch (Exception $e) {
            error_log("Failed to reinitialize mailer: " . $e->getMessage());
            $this->mailer = null;
            return false;
        }
    }
    
    /**
     * Get email statistics
     */
    public function getEmailStats() {
        try {
            $stats = $this->database->fetchOne("
                SELECT 
                    COUNT(*) as total_emails,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_emails,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_emails
                FROM email_logs
            ");
            
            return $stats ?: ['total_emails' => 0, 'sent_emails' => 0, 'failed_emails' => 0];
            
        } catch (BaseException $e) {
            error_log("Failed to get email stats: " . $e->getMessage());
            return ['total_emails' => 0, 'sent_emails' => 0, 'failed_emails' => 0];
        }
    }
    
    /**
     * Get all available email templates
     */
    public function getAvailableTemplates() {
        return [
            'welcome' => 'Welcome Email',
            'password_reset' => 'Password Reset',
            'investment_confirmation' => 'Investment Confirmation',
            'withdrawal_confirmation' => 'Withdrawal Confirmation'
        ];
    }
    
    /**
     * Get template content
     */
    public function getTemplateContent($template) {
        $templateFile = __DIR__ . '/../Templates/emails/' . $template . '.html';
        
        if (file_exists($templateFile)) {
            return file_get_contents($templateFile);
        }
        
        // Return default template if file doesn't exist
        return $this->getDefaultTemplate($template, []);
    }
    
    /**
     * Save template content
     */
    public function saveTemplate($template, $content) {
        try {
            $templateDir = __DIR__ . '/../Templates/emails/';
            
            // Ensure directory exists
            if (!is_dir($templateDir)) {
                mkdir($templateDir, 0755, true);
            }
            
            $templateFile = $templateDir . $template . '.html';
            
            // Save template file
            if (file_put_contents($templateFile, $content) === false) {
                throw new Exception("Failed to save template file");
            }
            
            return [
                'success' => true,
                'message' => 'Template saved successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Failed to save template {$template}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to save template: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get template variables for a specific template
     */
    public function getTemplateVariables($template) {
        $variables = [
            'welcome' => [
                'user_name' => 'User\'s full name',
                'username' => 'User\'s username',
                'email' => 'User\'s email address',
                'login_url' => 'Login page URL'
            ],
            'password_reset' => [
                'user_name' => 'User\'s full name',
                'reset_url' => 'Password reset URL',
                'expiry_hours' => 'Token expiry time in hours'
            ],
            'investment_confirmation' => [
                'user_name' => 'User\'s full name',
                'investment_amount' => 'Investment amount',
                'investment_plan' => 'Investment plan name',
                'daily_rate' => 'Daily interest rate',
                'duration_days' => 'Investment duration in days',
                'expected_return' => 'Expected return amount'
            ],
            'withdrawal_confirmation' => [
                'user_name' => 'User\'s full name',
                'withdrawal_amount' => 'Withdrawal amount',
                'fee_amount' => 'Fee amount',
                'net_amount' => 'Net amount after fees',
                'status' => 'Withdrawal status'
            ]
        ];
        
        return $variables[$template] ?? [];
    }
    
    /**
     * Preview template with sample data
     */
    public function previewTemplate($template, $customData = []) {
        $sampleData = $this->getSampleData($template);
        
        // Merge custom data with sample data
        $data = array_merge($sampleData, $customData);
        
        $subject = $this->getTemplateSubject($template, $data);
        $message = $this->renderTemplate($template, $data);
        
        return [
            'subject' => $subject,
            'message' => $message,
            'variables' => $this->getTemplateVariables($template)
        ];
    }
    
    /**
     * Get sample data for template preview
     */
    private function getSampleData($template) {
        $sampleData = [
            'welcome' => [
                'user_name' => 'John Doe',
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'login_url' => 'https://cornerfield.local/users/login.php'
            ],
            'password_reset' => [
                'user_name' => 'John Doe',
                'reset_url' => 'https://cornerfield.local/users/reset-password.php?token=sample123',
                'expiry_hours' => 24
            ],
            'investment_confirmation' => [
                'user_name' => 'John Doe',
                'investment_amount' => '1000.00',
                'investment_plan' => 'Premium Plan',
                'daily_rate' => '2.5',
                'duration_days' => 30,
                'expected_return' => '1750.00'
            ],
            'withdrawal_confirmation' => [
                'user_name' => 'John Doe',
                'withdrawal_amount' => '500.00',
                'fee_amount' => '5.00',
                'net_amount' => '495.00',
                'status' => 'completed'
            ]
        ];
        
        return $sampleData[$template] ?? [];
    }
}
