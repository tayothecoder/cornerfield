<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class SiteSettings
{
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    /**
     * Get a single setting value
     */
    public function get($key, $default = null)
    {
        $result = $this->db->fetchAll(
            "SELECT setting_value FROM site_settings WHERE setting_key = ?",
            [$key]
        );

        if (empty($result)) {
            return $default;
        }

        $value = $result[0]['setting_value'];
        
        // Try to decode JSON values
        if (json_decode($value) !== null) {
            return json_decode($value, true);
        }

        return $value;
    }

    /**
     * Set a single setting value
     */
    public function set($key, $value, $type = 'text', $category = 'general', $description = '')
    {
        // Convert arrays/objects to JSON
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
            $type = 'json';
        }

        return $this->db->query(
            "INSERT INTO site_settings (setting_key, setting_value, setting_type, category, description) 
             VALUES (?, ?, ?, ?, ?) 
             ON DUPLICATE KEY UPDATE 
             setting_value = VALUES(setting_value),
             setting_type = VALUES(setting_type),
             category = VALUES(category),
             description = VALUES(description),
             updated_at = CURRENT_TIMESTAMP",
            [$key, $value, $type, $category, $description]
        );
    }

    /**
     * Get all settings grouped by category
     */
    public function getAllByCategory()
    {
        $settings = $this->db->fetchAll(
            "SELECT * FROM site_settings ORDER BY category, setting_key"
        );

        $grouped = [];
        foreach ($settings as $setting) {
            $category = $setting['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }

            // Decode JSON values
            if ($setting['setting_type'] === 'json' && $setting['setting_value']) {
                $setting['setting_value'] = json_decode($setting['setting_value'], true);
            }

            $grouped[$category][] = $setting;
        }

        return $grouped;
    }

    /**
     * Get all settings as key-value pairs
     */
    public function getAll()
    {
        $settings = $this->db->fetchAll(
            "SELECT setting_key, setting_value, setting_type FROM site_settings"
        );

        $result = [];
        foreach ($settings as $setting) {
            $value = $setting['setting_value'];
            
            // Decode JSON values
            if ($setting['setting_type'] === 'json' && $value) {
                $value = json_decode($value, true);
            }

            $result[$setting['setting_key']] = $value;
        }

        return $result;
    }

    /**
     * Update multiple settings at once
     */
    public function updateMultiple($settings)
    {
        $this->db->beginTransaction();
        
        try {
            foreach ($settings as $key => $data) {
                $value = $data['value'] ?? $data;
                $type = $data['type'] ?? 'text';
                $category = $data['category'] ?? 'general';
                $description = $data['description'] ?? '';

                $this->set($key, $value, $type, $category, $description);
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Delete a setting
     */
    public function delete($key)
    {
        return $this->db->query(
            "DELETE FROM site_settings WHERE setting_key = ?",
            [$key]
        );
    }

    /**
     * Check if maintenance mode is enabled
     */
    public function isMaintenanceMode()
    {
        return $this->get('maintenance_mode', '0') === '1';
    }

    /**
     * Get maintenance message
     */
    public function getMaintenanceMessage()
    {
        return $this->get('maintenance_message', 'We are currently performing maintenance. Please check back later.');
    }

    /**
     * Get site branding info
     */
    public function getBranding()
    {
        return [
            'name' => $this->get('site_name', 'CornerField'),
            'tagline' => $this->get('site_tagline', 'Your Gateway to Financial Freedom'),
            'logo' => $this->get('site_logo', ''),
            'favicon' => $this->get('site_favicon', ''),
            'description' => $this->get('site_description', '')
        ];
    }

    /**
     * Get theme colors
     */
    public function getThemeColors()
    {
        return [
            'primary' => $this->get('primary_color', '#667eea'),
            'secondary' => $this->get('secondary_color', '#764ba2'),
            'success' => $this->get('success_color', '#10b981'),
            'warning' => $this->get('warning_color', '#f59e0b'),
            'danger' => $this->get('danger_color', '#ef4444')
        ];
    }

    /**
     * Get company information
     */
    public function getCompanyInfo()
    {
        return [
            'name' => $this->get('company_name', 'CornerField Investments Ltd'),
            'address' => $this->get('company_address', ''),
            'phone' => $this->get('company_phone', ''),
            'email' => $this->get('company_email', ''),
            'website' => $this->get('company_website', '')
        ];
    }

    /**
     * Get social media links
     */
    public function getSocialLinks()
    {
        return [
            'facebook' => $this->get('social_facebook', ''),
            'twitter' => $this->get('social_twitter', ''),
            'instagram' => $this->get('social_instagram', ''),
            'linkedin' => $this->get('social_linkedin', ''),
            'telegram' => $this->get('social_telegram', '')
        ];
    }
}
