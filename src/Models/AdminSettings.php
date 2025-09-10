<?php
namespace App\Models;

use App\Config\Database;
use Exception;

// src/Models/AdminSettings.php - Admin Settings Management

class AdminSettings
{
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    /**
     * Get all settings
     */
    public function getAllSettings()
    {
        try {
            $settings = $this->db->fetchAll("
                SELECT setting_key, setting_value, setting_type, description 
                FROM admin_settings 
                ORDER BY setting_key ASC
            ");

            // Convert to key-value array for easier access
            $result = [];
            foreach ($settings as $setting) {
                $value = $setting['setting_value'];

                // Convert based on type
                switch ($setting['setting_type']) {
                    case 'boolean':
                        $value = (bool) $value;
                        break;
                    case 'integer':
                        $value = (int) $value;
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                    // 'string' stays as-is
                }

                $result[$setting['setting_key']] = [
                    'value' => $value,
                    'type' => $setting['setting_type'],
                    'description' => $setting['description']
                ];
            }

            return $result;
        } catch (Exception $e) {
            error_log("AdminSettings getAllSettings error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single setting value
     */
    public function getSetting($key, $default = null)
    {
        try {
            $result = $this->db->fetchOne("
                SELECT setting_value, setting_type 
                FROM admin_settings 
                WHERE setting_key = ?
            ", [$key]);

            if (!$result) {
                return $default;
            }

            $value = $result['setting_value'];

            // Convert based on type
            switch ($result['setting_type']) {
                case 'boolean':
                    return (bool) $value;
                case 'integer':
                    return (int) $value;
                case 'json':
                    return json_decode($value, true);
                default:
                    return $value;
            }
        } catch (Exception $e) {
            error_log("AdminSettings getSetting error: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Update setting value
     */
    public function updateSetting($key, $value, $type = 'string')
    {
        try {
            // Convert value based on type
            $storedValue = $value;
            switch ($type) {
                case 'boolean':
                    $storedValue = $value ? '1' : '0';
                    break;
                case 'json':
                    $storedValue = json_encode($value);
                    break;
                default:
                    $storedValue = (string) $value;
            }

            // Check if setting exists
            $exists = $this->db->fetchOne("
            SELECT id FROM admin_settings WHERE setting_key = ?
        ", [$key]);

            if ($exists) {
                // For UPDATE: success means the query executed without error (even if 0 rows affected)
                $updateResult = $this->db->update('admin_settings', [
                    'setting_value' => $storedValue,
                    'setting_type' => $type
                ], 'setting_key = ?', [$key]);

                // Log the result - UPDATE returns affected rows, but 0 rows affected is still success
                error_log("AdminSettings: UPDATE result for '$key': " . ($updateResult !== false ? 'SUCCESS' : 'FAILED') . " (affected rows: $updateResult)");
                return $updateResult !== false; // Success if not false (0 affected rows is OK)
            } else {
                $insertResult = $this->db->insert('admin_settings', [
                    'setting_key' => $key,
                    'setting_value' => $storedValue,
                    'setting_type' => $type,
                    'description' => ''
                ]);

                // Log the result - INSERT returns the inserted ID
                error_log("AdminSettings: INSERT result for '$key': " . ($insertResult ? 'SUCCESS' : 'FAILED') . " (insert ID: $insertResult)");
                return $insertResult !== false;
            }
        } catch (Exception $e) {
            error_log("AdminSettings updateSetting error for '$key': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update multiple settings at once
     */
    public function updateMultipleSettings($settings)
    {
        try {
            error_log("AdminSettings: updateMultipleSettings called with " . count($settings) . " settings");

            $this->db->beginTransaction();

            foreach ($settings as $key => $data) {
                error_log("AdminSettings: Updating setting '$key' with value: " . print_r($data, true));

                if (!$this->updateSetting($key, $data['value'], $data['type'])) {
                    error_log("AdminSettings: Failed to update setting '$key'");
                    throw new Exception("Failed to update setting: $key");
                } else {
                    error_log("AdminSettings: Successfully updated setting '$key'");
                }
            }

            $this->db->commit();
            error_log("AdminSettings: updateMultipleSettings completed successfully");
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("AdminSettings updateMultipleSettings error: " . $e->getMessage());
            error_log("AdminSettings updateMultipleSettings stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Get all deposit methods for management
     */
    public function getDepositMethods()
    {
        try {
            return $this->db->fetchAll("
                SELECT * FROM deposit_methods 
                ORDER BY name ASC
            ");
        } catch (Exception $e) {
            error_log("AdminSettings getDepositMethods error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single deposit method
     */
    public function getDepositMethod($id)
    {
        try {
            return $this->db->fetchOne("
                SELECT * FROM deposit_methods WHERE id = ?
            ", [$id]);
        } catch (Exception $e) {
            error_log("AdminSettings getDepositMethod error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update deposit method
     */
    public function updateDepositMethod($id, $data)
    {
        try {
            return $this->db->update('deposit_methods', $data, 'id = ?', [$id]) > 0;
        } catch (Exception $e) {
            error_log("AdminSettings updateDepositMethod error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new deposit method
     */
    public function createDepositMethod($data)
    {
        try {
            return $this->db->insert('deposit_methods', $data);
        } catch (Exception $e) {
            error_log("AdminSettings createDepositMethod error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete deposit method
     */
    public function deleteDepositMethod($id)
    {
        try {
            // Check if method has deposits
            $hasDeposits = $this->db->fetchOne("
                SELECT COUNT(*) as count FROM deposits WHERE deposit_method_id = ?
            ", [$id]);

            if ($hasDeposits['count'] > 0) {
                throw new Exception("Cannot delete deposit method with existing deposits");
            }

            return $this->db->delete('deposit_methods', 'id = ?', [$id]) > 0;
        } catch (Exception $e) {
            error_log("AdminSettings deleteDepositMethod error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get platform statistics for settings overview
     */
    public function getPlatformStats()
    {
        try {
            $stats = [];

            // Total platform value
            $result = $this->db->fetchOne("
                SELECT 
                    COALESCE(SUM(balance), 0) as total_user_balance,
                    COUNT(*) as total_users
                FROM users WHERE is_active = 1
            ");
            $stats['total_user_balance'] = $result['total_user_balance'];
            $stats['total_users'] = $result['total_users'];

            // Investment stats
            $result = $this->db->fetchOne("
                SELECT 
                    COALESCE(SUM(invest_amount), 0) as total_invested,
                    COUNT(*) as total_investments
                FROM investments
            ");
            $stats['total_invested'] = $result['total_invested'];
            $stats['total_investments'] = $result['total_investments'];

            // Transaction stats
            $result = $this->db->fetchOne("
                SELECT 
                    COALESCE(SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_deposits,
                    COALESCE(SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_withdrawals,
                    COALESCE(SUM(CASE WHEN type = 'profit' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_profits
                FROM transactions
            ");
            $stats['total_deposits'] = $result['total_deposits'];
            $stats['total_withdrawals'] = $result['total_withdrawals'];
            $stats['total_profits'] = $result['total_profits'];

            return $stats;
        } catch (Exception $e) {
            error_log("AdminSettings getPlatformStats error: " . $e->getMessage());
            return [
                'total_user_balance' => 0,
                'total_users' => 0,
                'total_invested' => 0,
                'total_investments' => 0,
                'total_deposits' => 0,
                'total_withdrawals' => 0,
                'total_profits' => 0
            ];
        }
    }

    /**
     * Get system health information
     */
    public function getSystemHealth()
    {
        try {
            $health = [];

            // Database health
            $health['database'] = $this->db->fetchOne("SELECT 1 as status") ? 'healthy' : 'error';

            // Cron job status (check if profits were distributed today)
            $lastProfitRun = $this->db->fetchOne("
                SELECT MAX(created_at) as last_run 
                FROM transactions 
                WHERE type = 'profit' AND payment_method = 'system'
            ");

            $health['cron_status'] = 'unknown';
            if ($lastProfitRun['last_run']) {
                $lastRun = strtotime($lastProfitRun['last_run']);
                $todayStart = strtotime('today');
                $health['cron_status'] = $lastRun >= $todayStart ? 'active' : 'inactive';
            }

            // Active investments count
            $activeInvestments = $this->db->fetchOne("
                SELECT COUNT(*) as count FROM investments WHERE status = 'active'
            ");
            $health['active_investments'] = $activeInvestments['count'];

            // Pending transactions that need attention
            $pendingItems = $this->db->fetchOne("
                SELECT 
                    (SELECT COUNT(*) FROM deposits WHERE status = 'pending') as pending_deposits,
                    (SELECT COUNT(*) FROM withdrawals WHERE status = 'pending') as pending_withdrawals,
                    (SELECT COUNT(*) FROM transactions WHERE status = 'pending') as pending_transactions
            ");
            $health['pending_deposits'] = $pendingItems['pending_deposits'];
            $health['pending_withdrawals'] = $pendingItems['pending_withdrawals'];
            $health['pending_transactions'] = $pendingItems['pending_transactions'];

            return $health;
        } catch (Exception $e) {
            error_log("AdminSettings getSystemHealth error: " . $e->getMessage());
            return [
                'database' => 'error',
                'cron_status' => 'error',
                'active_investments' => 0,
                'pending_deposits' => 0,
                'pending_withdrawals' => 0,
                'pending_transactions' => 0
            ];
        }
    }

    /**
     * Reset platform to default settings
     */
    public function resetToDefaults()
    {
        try {
            $this->db->beginTransaction();

            $defaultSettings = [
                'site_name' => ['value' => 'Cornerfield Investment Platform', 'type' => 'string'],
                'site_email' => ['value' => 'admin@cornerfield.local', 'type' => 'string'],
                'support_email' => ['value' => 'support@cornerfield.local', 'type' => 'string'],
                'currency_symbol' => ['value' => '$', 'type' => 'string'],
                'signup_bonus' => ['value' => 500, 'type' => 'integer'],
                'referral_bonus_rate' => ['value' => 5, 'type' => 'integer'],
                'withdrawal_fee_rate' => ['value' => 5, 'type' => 'integer'],
                'platform_fee_rate' => ['value' => 2, 'type' => 'integer'],
                'min_withdrawal_amount' => ['value' => 10, 'type' => 'integer'],
                'max_withdrawal_amount' => ['value' => 50000, 'type' => 'integer'],
                'deposit_auto_approval' => ['value' => false, 'type' => 'boolean'],
                'withdrawal_auto_approval' => ['value' => false, 'type' => 'boolean'],
                'maintenance_mode' => ['value' => false, 'type' => 'boolean'],
                'email_notifications' => ['value' => true, 'type' => 'boolean'],
                'profit_distribution_locked' => ['value' => false, 'type' => 'boolean'],
                'show_profit_calculations' => ['value' => true, 'type' => 'boolean'],
                'early_withdrawal_penalty' => ['value' => 10, 'type' => 'integer']
            ];

            foreach ($defaultSettings as $key => $data) {
                $this->updateSetting($key, $data['value'], $data['type']);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("AdminSettings resetToDefaults error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Export all settings as JSON
     */
    public function exportSettings()
    {
        try {
            $settings = $this->getAllSettings();
            $depositMethods = $this->getDepositMethods();

            $export = [
                'settings' => $settings,
                'deposit_methods' => $depositMethods,
                'export_date' => date('Y-m-d H:i:s'),
                'platform_version' => '1.0'
            ];

            return json_encode($export, JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            error_log("AdminSettings exportSettings error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Import settings from JSON
     */
    public function importSettings($jsonData)
    {
        try {
            $data = json_decode($jsonData, true);

            if (!$data || !isset($data['settings'])) {
                throw new Exception("Invalid import data format");
            }

            $this->db->beginTransaction();

            // Import settings
            if (isset($data['settings'])) {
                foreach ($data['settings'] as $key => $setting) {
                    $this->updateSetting($key, $setting['value'], $setting['type']);
                }
            }

            // Import deposit methods (optional)
            if (isset($data['deposit_methods'])) {
                foreach ($data['deposit_methods'] as $method) {
                    // Remove ID to create new methods
                    unset($method['id']);
                    $this->createDepositMethod($method);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("AdminSettings importSettings error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear cache and temporary data
     */
    public function clearCache()
    {
        try {
            // Clear old sessions (older than 7 days)
            $this->db->delete('user_sessions', 'created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
            $this->db->delete('admin_sessions', 'created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');

            // Clear old security logs (older than 30 days)
            $this->db->delete('security_logs', 'created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');

            return true;
        } catch (Exception $e) {
            error_log("AdminSettings clearCache error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics()
    {
        try {
            $analytics = [];

            // Monthly revenue breakdown
            $monthlyRevenue = $this->db->fetchAll("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN fee ELSE 0 END) as deposit_fees,
                    SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN fee ELSE 0 END) as withdrawal_fees,
                    COUNT(CASE WHEN type = 'investment' THEN 1 END) as new_investments,
                    SUM(CASE WHEN type = 'investment' THEN amount ELSE 0 END) as investment_volume
                FROM transactions 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC
                LIMIT 12
            ");
            $analytics['monthly_revenue'] = array_reverse($monthlyRevenue);

            // Today's stats
            $todayStats = $this->db->fetchOne("
                SELECT 
                    COUNT(CASE WHEN type = 'deposit' AND status = 'completed' THEN 1 END) as deposits_today,
                    COUNT(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN 1 END) as withdrawals_today,
                    COUNT(CASE WHEN type = 'investment' THEN 1 END) as investments_today,
                    SUM(CASE WHEN type = 'profit' AND status = 'completed' THEN amount ELSE 0 END) as profits_today
                FROM transactions 
                WHERE DATE(created_at) = CURDATE()
            ");
            $analytics['today'] = $todayStats;

            // Top performing investment plans
            $topPlans = $this->db->fetchAll("
                SELECT 
                    s.name,
                    COUNT(i.id) as total_investments,
                    SUM(i.invest_amount) as total_amount,
                    AVG(i.invest_amount) as avg_amount
                FROM investment_schemas s
                LEFT JOIN investments i ON s.id = i.schema_id
                WHERE s.status = 1
                GROUP BY s.id, s.name
                ORDER BY total_amount DESC
                LIMIT 5
            ");
            $analytics['top_plans'] = $topPlans;

            return $analytics;
        } catch (Exception $e) {
            error_log("AdminSettings getRevenueAnalytics error: " . $e->getMessage());
            return [
                'monthly_revenue' => [],
                'today' => [
                    'deposits_today' => 0,
                    'withdrawals_today' => 0,
                    'investments_today' => 0,
                    'profits_today' => 0
                ],
                'top_plans' => []
            ];
        }
    }

    /**
     * Validate settings before saving
     */
    public function validateSettings($settings)
    {
        $errors = [];

        foreach ($settings as $key => $data) {
            $value = $data['value'];
            $type = $data['type'];

            // Validate based on setting key and type
            switch ($key) {
                case 'signup_bonus':
                case 'min_withdrawal_amount':
                case 'max_withdrawal_amount':
                    if ($type === 'integer' && $value < 0) {
                        $errors[] = ucwords(str_replace('_', ' ', $key)) . " must be a positive number";
                    }
                    break;

                case 'referral_bonus_rate':
                case 'withdrawal_fee_rate':
                case 'platform_fee_rate':
                case 'early_withdrawal_penalty':
                    if ($type === 'integer' && ($value < 0 || $value > 100)) {
                        $errors[] = ucwords(str_replace('_', ' ', $key)) . " must be between 0 and 100";
                    }
                    break;

                case 'site_email':
                case 'support_email':
                    if ($type === 'string' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = ucwords(str_replace('_', ' ', $key)) . " must be a valid email address";
                    }
                    break;

                case 'currency_symbol':
                    if ($type === 'string' && strlen($value) > 5) {
                        $errors[] = "Currency symbol must be 5 characters or less";
                    }
                    break;
            }

            // Validate min/max withdrawal amounts
            if ($key === 'min_withdrawal_amount' && isset($settings['max_withdrawal_amount'])) {
                if ($value >= $settings['max_withdrawal_amount']['value']) {
                    $errors[] = "Minimum withdrawal amount must be less than maximum withdrawal amount";
                }
            }
        }

        return $errors;
    }

    /**
     * Log admin settings changes
     */
    public function logSettingsChange($adminId, $changes)
    {
        try {
            $this->db->insert('security_logs', [
                'event_type' => 'admin_settings_change',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'user_id' => $adminId,
                'data' => json_encode([
                    'changes' => $changes,
                    'timestamp' => date('Y-m-d H:i:s')
                ])
            ]);
            return true;
        } catch (Exception $e) {
            error_log("AdminSettings logSettingsChange error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get settings change history
     */
    public function getSettingsHistory($limit = 50)
    {
        try {
            return $this->db->fetchAll("
                SELECT 
                    sl.*,
                    a.username as admin_username
                FROM security_logs sl
                LEFT JOIN admins a ON sl.user_id = a.id
                WHERE sl.event_type = 'admin_settings_change'
                ORDER BY sl.created_at DESC
                LIMIT ?
            ", [$limit]);
        } catch (Exception $e) {
            error_log("AdminSettings getSettingsHistory error: " . $e->getMessage());
            return [];
        }
    }
}