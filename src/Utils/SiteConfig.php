<?php
declare(strict_types=1);

namespace App\Utils;

use App\Config\Database;
use App\Models\SiteSettings;

class SiteConfig
{
    private static $settings = null;
    private static $siteSettings = null;

    /**
     * Initialize site settings
     */
    public static function init()
    {
        if (self::$settings === null) {
            $database = new Database();
            self::$siteSettings = new SiteSettings($database);
            self::$settings = self::$siteSettings->getAll();
        }
    }

    /**
     * Get a setting value
     */
    public static function get($key, $default = null)
    {
        self::init();
        return self::$settings[$key] ?? $default;
    }

    /**
     * Get site name
     */
    public static function getSiteName()
    {
        return self::get('site_name', 'CornerField');
    }

    /**
     * Get site tagline
     */
    public static function getSiteTagline()
    {
        return self::get('site_tagline', 'Your Gateway to Financial Freedom');
    }

    /**
     * Get site logo
     */
    public static function getSiteLogo()
    {
        return self::get('site_logo', '');
    }

    /**
     * Get site favicon
     */
    public static function getSiteFavicon()
    {
        return self::get('site_favicon', '');
    }

    /**
     * Get theme colors
     */
    public static function getThemeColors()
    {
        return [
            'primary' => self::get('primary_color', '#667eea'),
            'secondary' => self::get('secondary_color', '#764ba2'),
            'success' => self::get('success_color', '#10b981'),
            'warning' => self::get('warning_color', '#f59e0b'),
            'danger' => self::get('danger_color', '#ef4444')
        ];
    }

    /**
     * Get company information
     */
    public static function getCompanyInfo()
    {
        return [
            'name' => self::get('company_name', 'CornerField Investments Ltd'),
            'address' => self::get('company_address', ''),
            'phone' => self::get('company_phone', ''),
            'email' => self::get('company_email', ''),
            'website' => self::get('company_website', '')
        ];
    }

    /**
     * Get social media links
     */
    public static function getSocialLinks()
    {
        return [
            'facebook' => self::get('social_facebook', ''),
            'twitter' => self::get('social_twitter', ''),
            'instagram' => self::get('social_instagram', ''),
            'linkedin' => self::get('social_linkedin', ''),
            'telegram' => self::get('social_telegram', '')
        ];
    }

    /**
     * Check if maintenance mode is enabled
     */
    public static function isMaintenanceMode()
    {
        return self::get('maintenance_mode', '0') === '1';
    }

    /**
     * Get maintenance message
     */
    public static function getMaintenanceMessage()
    {
        return self::get('maintenance_message', 'We are currently performing maintenance. Please check back later.');
    }

    /**
     * Generate CSS variables for theme colors
     */
    public static function getThemeCSS()
    {
        $colors = self::getThemeColors();
        $css = ":root {\n";
        foreach ($colors as $name => $color) {
            $css .= "  --{$name}-color: {$color};\n";
        }
        $css .= "}\n";
        return $css;
    }

    /**
     * Generate dynamic CSS based on site settings
     */
    public static function getDynamicCSS()
    {
        $colors = self::getThemeColors();
        $siteName = self::getSiteName();
        $tagline = self::getSiteTagline();
        
        $css = self::getThemeCSS();
        
        // Add custom CSS based on settings
        $css .= "
        .site-name { color: {$colors['primary']}; }
        .site-tagline { color: {$colors['secondary']}; }
        .btn-primary { background: #1e0e62; }
        .btn-success { background: {$colors['success']}; }
        .btn-warning { background: {$colors['warning']}; }
        .btn-danger { background: {$colors['danger']}; }
        ";
        
        return $css;
    }

    /**
     * Get SEO meta tags
     */
    public static function getSEOMetaTags()
    {
        return [
            'title' => self::get('site_name', 'CornerField'),
            'description' => self::get('site_description', 'Premier cryptocurrency investment platform'),
            'keywords' => self::get('seo_keywords', 'cryptocurrency, investment, bitcoin'),
            'author' => self::get('seo_author', 'CornerField Team')
        ];
    }

    /**
     * Get footer information
     */
    public static function getFooterInfo()
    {
        return [
            'text' => self::get('footer_text', '© 2024 CornerField. All rights reserved.'),
            'links' => self::get('footer_links', [])
        ];
    }

    /**
     * Render maintenance page
     */
    public static function renderMaintenancePage()
    {
        if (!self::isMaintenanceMode()) {
            return false;
        }

        $message = self::getMaintenanceMessage();
        $siteName = self::getSiteName();
        $colors = self::getThemeColors();
        
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Maintenance - {$siteName}</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #1e0e62;
                    margin: 0; padding: 0; min-height: 100vh;
                    display: flex; align-items: center; justify-content: center;
                }
                .maintenance-container {
                    text-align: center; color: white; padding: 2rem;
                    background: rgba(255,255,255,0.1); border-radius: 20px;
                    backdrop-filter: blur(10px); box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                }
                .maintenance-icon { font-size: 4rem; margin-bottom: 1rem; }
                .maintenance-title { font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; }
                .maintenance-message { font-size: 1.2rem; opacity: 0.9; line-height: 1.6; }
            </style>
        </head>
        <body>
            <div class='maintenance-container'>
                <div class='maintenance-icon'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' width='48' height='48'><path stroke-linecap='round' stroke-linejoin='round' d='M11.42 15.17l-5.59 5.59a2.12 2.12 0 01-3-3l5.59-5.59m4.17-1.83a5.25 5.25 0 10-7.43-7.43L6.34 6.34l4.24 4.24 4.83-4.83z'/></svg></div>
                <h1 class='maintenance-title'>Under Maintenance</h1>
                <p class='maintenance-message'>{$message}</p>
            </div>
        </body>
        </html>";
        
        return true;
    }
}
