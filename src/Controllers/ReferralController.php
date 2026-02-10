<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Controllers/ReferralController.php
 * Purpose: Referral system management controller
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\ReferralModel;
use App\Utils\Security;
use App\Utils\Validator;
use App\Utils\JsonResponse;

class ReferralController 
{
    private UserModel $userModel;
    private ReferralModel $referralModel;
    
    public function __construct() 
    {
        $this->userModel = new UserModel();
        $this->referralModel = new ReferralModel();
    }
    
    /**
     * Get user's referrals and statistics
     * @return array
     */
    public function getMyReferrals(): array 
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
            // Get referral statistics
            $stats = $this->referralModel->getReferralStats($userId);
            
            // Get referral list
            $referrals = $this->referralModel->findByReferrerId($userId);
            
            // Format referrals for display
            foreach ($referrals as &$referral) {
                $referral['formatted_total_earned'] = number_format((float)$referral['total_earned'], 2);
                $referral['formatted_commission_rate'] = number_format((float)$referral['commission_rate'], 1);
                $referral['status_badge_class'] = $this->getStatusBadgeClass($referral['status']);
                $referral['member_since'] = $this->formatDateTime($referral['user_created_at']);
                
                // Calculate referral's activity level
                $referral['activity_level'] = $this->calculateActivityLevel(
                    (int)$referral['investment_count'],
                    (float)$referral['total_invested_by_referred']
                );
            }
            
            // Format statistics
            $stats['formatted_total_earned'] = number_format($stats['total_commission_earned'], 2);
            $stats['formatted_last_30_days'] = number_format($stats['commission_last_30_days'], 2);
            
            return [
                'success' => true,
                'stats' => $stats,
                'referrals' => $referrals
            ];
            
        } catch (\Exception $e) {
            error_log("Failed to fetch referrals for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to load referral data'
            ];
        }
    }
    
    /**
     * Get referral link for user
     * @return string
     */
    public function getReferralLink(): string 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            return '';
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            $user = $this->userModel->findById($userId);
            
            if (!$user || empty($user['referral_code'])) {
                return '';
            }
            
            // Generate referral URL
            $baseUrl = $_SERVER['HTTPS'] ? 'https://' : 'http://';
            $baseUrl .= $_SERVER['HTTP_HOST'];
            $referralUrl = $baseUrl . '/register.php?ref=' . $user['referral_code'];
            
            return $referralUrl;
            
        } catch (\Exception $e) {
            error_log("Failed to generate referral link for user {$userId}: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Get referral link via AJAX
     * @return void
     */
    public function getReferralLinkAjax(): void 
    {
        $link = $this->getReferralLink();
        
        if (!empty($link)) {
            JsonResponse::success(['referral_link' => $link]);
        } else {
            JsonResponse::error('Failed to generate referral link');
        }
    }
    
    /**
     * Get referral commission history
     * @return void
     */
    public function getCommissionHistory(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            $stmt = $this->userModel->db->prepare(
                "SELECT t.*, u.username as referred_username, u.first_name, u.last_name
                 FROM transactions t
                 LEFT JOIN referrals r ON JSON_EXTRACT(t.description, '$.referral_id') = r.id
                 LEFT JOIN users u ON r.referred_id = u.id
                 WHERE t.user_id = ? AND t.type = 'referral' AND t.status = 'completed'
                 ORDER BY t.created_at DESC
                 LIMIT 100"
            );
            
            $stmt->execute([$userId]);
            $commissions = $stmt->fetchAll();
            
            // Format commission data
            foreach ($commissions as &$commission) {
                $commission['formatted_amount'] = number_format((float)$commission['amount'], 2);
                $commission['formatted_date'] = $this->formatDateTime($commission['created_at']);
                
                // Try to extract additional info from description
                if (!empty($commission['description'])) {
                    $description = json_decode($commission['description'], true);
                    if (is_array($description)) {
                        $commission['commission_info'] = $description;
                    }
                }
            }
            
            JsonResponse::success(['commissions' => $commissions]);
            
        } catch (\Exception $e) {
            error_log("Failed to fetch commission history for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to load commission history');
        }
    }
    
    /**
     * Get referral program information
     * @return void
     */
    public function getProgramInfo(): void 
    {
        try {
            // Get program settings from environment or database
            $commissionRate = (float)($_ENV['REFERRAL_COMMISSION_RATE'] ?? 5.0);
            $signupBonus = (float)($_ENV['SIGNUP_BONUS'] ?? 50.0);
            $minWithdrawal = (float)($_ENV['MIN_WITHDRAWAL'] ?? 10.0);
            
            // Get program statistics
            $stmt = $this->userModel->db->prepare(
                "SELECT 
                    COUNT(*) as total_referrals,
                    SUM(total_earned) as total_commissions_paid,
                    COUNT(DISTINCT referrer_id) as active_referrers
                 FROM referrals 
                 WHERE status = 'active'"
            );
            
            $stmt->execute();
            $programStats = $stmt->fetch();
            
            $programInfo = [
                'commission_rate' => $commissionRate,
                'signup_bonus' => $signupBonus,
                'min_withdrawal' => $minWithdrawal,
                'payment_schedule' => 'Instant upon referred user\'s investment',
                'cookie_duration' => '30 days',
                'max_levels' => 1, // Single level for now
                'program_stats' => [
                    'total_referrals' => (int)($programStats['total_referrals'] ?? 0),
                    'total_commissions_paid' => number_format((float)($programStats['total_commissions_paid'] ?? 0), 2),
                    'active_referrers' => (int)($programStats['active_referrers'] ?? 0)
                ],
                'benefits' => [
                    'Earn ' . $commissionRate . '% commission on all referral investments',
                    'No limit on number of referrals',
                    'Instant commission payments',
                    'Real-time tracking and reporting',
                    'Professional marketing materials provided'
                ],
                'terms' => [
                    'Referred users must complete KYC verification',
                    'Commission paid only on verified investments',
                    'Self-referrals or fake accounts will result in account termination',
                    'Commission rates subject to change with 30 days notice'
                ]
            ];
            
            JsonResponse::success(['program_info' => $programInfo]);
            
        } catch (\Exception $e) {
            error_log("Failed to fetch program info: " . $e->getMessage());
            JsonResponse::error('Failed to load program information');
        }
    }
    
    /**
     * Get referral leaderboard (top referrers)
     * @return void
     */
    public function getLeaderboard(): void 
    {
        try {
            $limit = (int)($_GET['limit'] ?? 10);
            $limit = min(max($limit, 5), 50); // Between 5 and 50
            
            $topReferrers = $this->referralModel->getTopReferrers($limit);
            
            // Format leaderboard data (anonymize sensitive info)
            foreach ($topReferrers as $index => &$referrer) {
                $referrer['rank'] = $index + 1;
                $referrer['display_name'] = $this->anonymizeUsername($referrer['username']);
                $referrer['formatted_commission'] = number_format((float)$referrer['total_commission'], 2);
                
                // Remove sensitive information
                unset($referrer['referrer_id'], $referrer['username'], $referrer['first_name'], $referrer['last_name']);
            }
            
            JsonResponse::success(['leaderboard' => $topReferrers]);
            
        } catch (\Exception $e) {
            error_log("Failed to fetch referral leaderboard: " . $e->getMessage());
            JsonResponse::error('Failed to load leaderboard');
        }
    }
    
    /**
     * Generate referral materials (banners, links, etc.)
     * @return void
     */
    public function getMarketingMaterials(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $referralLink = $this->getReferralLink();
        
        if (empty($referralLink)) {
            JsonResponse::error('Unable to generate referral link');
            return;
        }
        
        try {
            $materials = [
                'referral_link' => $referralLink,
                'short_link' => $this->generateShortLink($referralLink),
                'social_messages' => [
                    'facebook' => "🚀 Join me on Cornerfield Investment Platform and start earning today! Get a $50 welcome bonus when you sign up through my link: {$referralLink}",
                    'twitter' => "💰 Earning passive income with @Cornerfield! Join me and get $50 welcome bonus: {$referralLink} #InvestmentPlatform #PassiveIncome",
                    'linkedin' => "I've been using Cornerfield Investment Platform for my cryptocurrency investments. Great returns and professional service. Join through my referral link and get a $50 bonus: {$referralLink}",
                    'whatsapp' => "Hey! I found this amazing investment platform called Cornerfield. They're giving $50 bonus to new users. Check it out: {$referralLink}"
                ],
                'email_template' => [
                    'subject' => 'Start Your Investment Journey with Cornerfield',
                    'body' => "Hi there!\n\nI wanted to share with you an investment platform I've been using called Cornerfield. They offer:\n\n✅ Professional cryptocurrency investment management\n✅ Transparent daily profits\n✅ Secure and regulated platform\n✅ $50 welcome bonus for new users\n\nYou can join through my referral link and get started: {$referralLink}\n\nFeel free to reach out if you have any questions!\n\nBest regards"
                ],
                'banners' => [
                    [
                        'size' => '728x90',
                        'type' => 'leaderboard',
                        'url' => '/assets/banners/referral-728x90.png',
                        'html' => '<a href="' . $referralLink . '" target="_blank"><img src="/assets/banners/referral-728x90.png" alt="Join Cornerfield - $50 Bonus" style="border:0;"></a>'
                    ],
                    [
                        'size' => '300x250',
                        'type' => 'medium_rectangle', 
                        'url' => '/assets/banners/referral-300x250.png',
                        'html' => '<a href="' . $referralLink . '" target="_blank"><img src="/assets/banners/referral-300x250.png" alt="Join Cornerfield - $50 Bonus" style="border:0;"></a>'
                    ],
                    [
                        'size' => '160x600',
                        'type' => 'skyscraper',
                        'url' => '/assets/banners/referral-160x600.png', 
                        'html' => '<a href="' . $referralLink . '" target="_blank"><img src="/assets/banners/referral-160x600.png" alt="Join Cornerfield - $50 Bonus" style="border:0;"></a>'
                    ]
                ]
            ];
            
            JsonResponse::success(['materials' => $materials]);
            
        } catch (\Exception $e) {
            error_log("Failed to generate marketing materials: " . $e->getMessage());
            JsonResponse::error('Failed to generate marketing materials');
        }
    }
    
    /**
     * Track referral click (optional analytics)
     * @return void
     */
    public function trackClick(): void 
    {
        $referralCode = Validator::sanitizeString($_POST['ref_code'] ?? '', 20);
        $source = Validator::sanitizeString($_POST['source'] ?? 'direct', 50);
        
        if (empty($referralCode)) {
            JsonResponse::error('Invalid referral code');
            return;
        }
        
        try {
            // Find referrer by code
            $referrer = $this->userModel->findByReferralCode($referralCode);
            
            if (!$referrer) {
                JsonResponse::error('Invalid referral code');
                return;
            }
            
            // Log the click for analytics (optional)
            $clickData = [
                'referrer_id' => $referrer['id'],
                'referral_code' => $referralCode,
                'source' => $source,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // You could store this in a referral_clicks table for analytics
            // For now, just log it
            Security::logAudit(0, 'referral_click', 'referral_tracking', 0, null, $clickData);
            
            JsonResponse::success(['message' => 'Click tracked']);
            
        } catch (\Exception $e) {
            error_log("Failed to track referral click: " . $e->getMessage());
            JsonResponse::error('Tracking failed');
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
            case 'active':
                return 'bg-cf-success/10 text-cf-success';
            case 'inactive':
                return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
            default:
                return 'bg-gray-100 text-gray-600';
        }
    }
    
    /**
     * Calculate activity level based on investments
     * @param int $investmentCount
     * @param float $totalInvested
     * @return string
     */
    private function calculateActivityLevel(int $investmentCount, float $totalInvested): string 
    {
        if ($investmentCount === 0) {
            return 'inactive';
        } elseif ($investmentCount < 3 || $totalInvested < 500) {
            return 'low';
        } elseif ($investmentCount < 10 || $totalInvested < 2000) {
            return 'medium';
        } else {
            return 'high';
        }
    }
    
    /**
     * Anonymize username for public display
     * @param string $username
     * @return string
     */
    private function anonymizeUsername(string $username): string 
    {
        $length = strlen($username);
        
        if ($length <= 3) {
            return str_repeat('*', $length);
        } elseif ($length <= 6) {
            return substr($username, 0, 2) . str_repeat('*', $length - 2);
        } else {
            return substr($username, 0, 3) . str_repeat('*', $length - 6) . substr($username, -3);
        }
    }
    
    /**
     * Generate short link (placeholder implementation)
     * @param string $longUrl
     * @return string
     */
    private function generateShortLink(string $longUrl): string 
    {
        // In a real implementation, you might use a URL shortening service
        // For now, just return the original URL
        return $longUrl;
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
        } elseif ($diff->d < 30) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } else {
            return $date->format('M j, Y');
        }
    }
}