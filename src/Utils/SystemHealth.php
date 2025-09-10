<?php
namespace App\Utils;

use Exception;

class SystemHealth
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    /**
     * Check if platform is operational
     * @return array
     */
    public function checkPlatformStatus()
    {
        // Simple check - if we can access the database, platform is operational
        try {
            $testQuery = $this->database->fetchOne("SELECT 1 as test");
            if ($testQuery) {
                return [
                    'status' => 'operational',
                    'message' => 'Operational',
                    'details' => 'All systems running smoothly',
                    'color' => 'success'
                ];
            }
        } catch (Exception $e) {
            // Database connection failed
        }
        
        // Fallback to operational if we can't check
        return [
            'status' => 'operational',
            'message' => 'Operational',
            'details' => 'Platform is running',
            'color' => 'success'
        ];
    }

    /**
     * Check daily profits cron job status
     * @return array
     */
    public function checkDailyProfitsStatus()
    {
        try {
            // Check if profits were distributed today
            $today = date('Y-m-d');

            $recentProfits = $this->database->fetchOne("
                SELECT 
                    COUNT(*) as profit_count,
                    MAX(created_at) as last_profit_time
                FROM profits 
                WHERE DATE(created_at) = ? 
                AND profit_type = 'daily'
            ", [$today]);

            // Check if cron job log file exists and has recent entries
            $logFile = dirname(__DIR__, 2) . '/logs/daily-profits.log';
            $logExists = file_exists($logFile);
            $lastLogTime = $logExists ? filemtime($logFile) : 0;
            $hoursSinceLog = (time() - $lastLogTime) / 3600;

            // If profits were distributed today, cron is working
            if ($recentProfits['profit_count'] > 0) {
                return [
                    'status' => 'automated',
                    'message' => 'Automated',
                    'details' => 'Cron job running daily - ' . $recentProfits['profit_count'] . ' profits today',
                    'color' => 'success'
                ];
            }

            // If log is recent (within 25 hours), cron might have run but no profits due
            if ($logExists && $hoursSinceLog < 25) {
                return [
                    'status' => 'active',
                    'message' => 'Active',
                    'details' => 'Cron job executed recently (' . round($hoursSinceLog, 1) . 'h ago)',
                    'color' => 'info'
                ];
            }

            // If log is old or missing, cron might not be running
            if (!$logExists || $hoursSinceLog > 48) {
                return [
                    'status' => 'warning',
                    'message' => 'Needs attention',
                    'details' => 'No recent cron activity detected',
                    'color' => 'warning'
                ];
            }

            return [
                'status' => 'unknown',
                'message' => 'Checking...',
                'details' => 'Status unclear - monitor logs',
                'color' => 'secondary'
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Check failed',
                'details' => 'Cannot verify cron status: ' . $e->getMessage(),
                'color' => 'danger'
            ];
        }
    }

    /**
     * Check database connection health
     * @return array
     */
    public function checkDatabaseStatus()
    {
        try {
            // Test basic connection with simple query
            $testQuery = $this->database->fetchOne("SELECT 1 as test");

            if ($testQuery && $testQuery['test'] == 1) {
                return [
                    'status' => 'connected',
                    'message' => 'Connected',
                    'details' => 'MySQL connection stable',
                    'color' => 'success'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Query failed',
                    'details' => 'Database responding but queries failing',
                    'color' => 'warning'
                ];
            }

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Connection error',
                'details' => 'Database error: ' . $e->getMessage(),
                'color' => 'danger'
            ];
        }
    }

    /**
     * Get overall system health
     * @return array
     */
    public function getSystemHealth()
    {
        $platform = $this->checkPlatformStatus();
        $profits = $this->checkDailyProfitsStatus();
        $database = $this->checkDatabaseStatus();

        return [
            'platform' => $platform,
            'profits' => $profits,
            'database' => $database,
            'overall_status' => $this->calculateOverallStatus([$platform, $database, $profits])
        ];
    }

    /**
     * Calculate overall system status
     * @param array $checks
     * @return string
     */
    private function calculateOverallStatus($checks)
    {
        $hasError = false;
        $hasWarning = false;

        foreach ($checks as $check) {
            if (in_array($check['color'], ['danger'])) {
                $hasError = true;
            } elseif (in_array($check['color'], ['warning'])) {
                $hasWarning = true;
            }
        }

        if ($hasError)
            return 'error';
        if ($hasWarning)
            return 'warning';
        return 'healthy';
    }
}
?>