<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Database;
use App\Models\User;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\AdminSettings;
use App\Models\Profit;

class DailyProfitDistributor
{
    private $database;
    private $userModel;
    private $investmentModel;
    private $transactionModel;

    public function __construct()
    {
        $this->database = new Database();
        $this->userModel = new User($this->database);
        $this->investmentModel = new Investment($this->database);
        $this->transactionModel = new Transaction($this->database);
    }

    public function distributeProfits()
    {
        echo "[" . date('Y-m-d H:i:s') . "] Starting daily profit distribution...\n";

        // Get investments due for profit
        $active_investments = $this->investmentModel->getInvestmentsDueForProfit();

        if (empty($active_investments)) {
            echo "[" . date('Y-m-d H:i:s') . "] No investments due for profit today.\n";
            return [
                'investments_processed' => 0,
                'total_profits' => 0,
                'errors' => 0
            ];
        }

        $total_profits_distributed = 0;
        $investments_processed = 0;
        $errors = 0;

        foreach ($active_investments as $investment) {
            try {
                $profit_distributed = $this->processInvestment($investment);
                $total_profits_distributed += $profit_distributed;
                $investments_processed++;

                echo "[OK] Processed investment ID " . $investment['id'] . ": +$" . number_format($profit_distributed, 2) . " for user " . $investment['user_id'] . "\n";

                // Small delay to prevent overwhelming the database
                usleep(100000); // 0.1 second delay

            } catch (Exception $e) {
                $errors++;
                echo "[ERROR] Error processing investment ID " . $investment['id'] . ": " . $e->getMessage() . "\n";
                error_log("Daily Profit Error - Investment " . $investment['id'] . ": " . $e->getMessage());
            }
        }

        echo "[" . date('Y-m-d H:i:s') . "] Distribution complete!\n";
        echo "[STATS] Investments processed: " . $investments_processed . "\n";
        echo "[PROFIT] Total profits distributed: $" . number_format($total_profits_distributed, 2) . "\n";
        echo "[WARN] Errors encountered: " . $errors . "\n\n";

        return [
            'investments_processed' => $investments_processed,
            'total_profits' => $total_profits_distributed,
            'errors' => $errors
        ];
    }

    private function processInvestment($investment)
    {
        // Get investment schema details
        $schema = $this->investmentModel->getSchemaById($investment['schema_id']);

        if (!$schema) {
            throw new Exception("Schema not found for investment " . $investment['id']);
        }

        // Calculate daily profit
        $daily_profit = $this->investmentModel->calculateDailyProfit($investment['invest_amount'], $schema['daily_rate']);

        // Check if investment has reached completion
        $days_passed = $this->calculateDaysPassed($investment['created_at']);
        $is_completed = $days_passed >= $schema['duration_days'];

        if ($is_completed) {
            return $this->completeInvestment($investment, $schema, $daily_profit);
        } else {
            return $this->addDailyProfit($investment, $schema, $daily_profit);
        }
    }

    private function completeInvestment($investment, $schema, $final_profit)
    {
        $principal_return = $investment['invest_amount'];
        $total_payout = $final_profit + $principal_return;

        try {
            // Start transaction
            $this->database->beginTransaction();

            // Update user balance with both profit and principal
            $adminSettingsModel = new AdminSettings($this->database);
            $profitDistributionLocked = $adminSettingsModel->getSetting('profit_distribution_locked', 0);

            if ($profitDistributionLocked) {

                $this->database->update('users', [
                    'balance' => $this->database->raw('balance + locked_balance + ' . $total_payout),
                    'locked_balance' => 0, 
                    'total_earned' => $this->database->raw('total_earned + ' . $final_profit)
                ], 'id = ?', [$investment['user_id']]);
                
                echo "   [DONE] Released all locked profits + final profit + principal for user " . $investment['user_id'] . "\n";
            } else {

                $this->userModel->addToBalance($investment['user_id'], $total_payout);
                $this->userModel->addToTotalEarned($investment['user_id'], $final_profit);
                
                echo "   [DONE] Added final profit + principal to available balance for user " . $investment['user_id'] . "\n";
            }

            // Create final profit transaction
            $this->transactionModel->createProfitTransaction($investment['user_id'], $final_profit, $investment['id']);

            // Create principal return transaction
            $this->transactionModel->createTransaction([
                'user_id' => $investment['user_id'],
                'type' => 'principal_return',
                'amount' => $principal_return,
                'fee' => 0,
                'net_amount' => $principal_return,
                'status' => 'completed',
                'payment_method' => 'system',
                'currency' => 'USD',
                'description' => "Principal return from completed " . $schema['name'] . " investment",
                'reference_id' => $investment['id'],
                'processed_at' => date('Y-m-d H:i:s')
            ]);

            // Mark investment as completed
            $this->investmentModel->updateInvestmentStatus($investment['id'], 'completed');

            // Update profit times for record keeping
            $this->investmentModel->updateProfitTimes($investment['id'], date('Y-m-d H:i:s'), null);

            $this->database->commit();

            $profit_formatted = number_format($final_profit, 2);
            $principal_formatted = number_format($principal_return, 2);
            echo "[DONE] COMPLETED: Investment " . $investment['id'] . " finished. User " . $investment['user_id'] . " received $" . $profit_formatted . " profit + $" . $principal_formatted . " principal\n";

            return $total_payout;

        } catch (Exception $e) {
            $this->database->rollback();
            throw new Exception("Failed to complete investment: " . $e->getMessage());
        }
    }

    private function addDailyProfit($investment, $schema, $daily_profit)
    {
        try {
            $this->database->beginTransaction();

            // Calculate profit day
            $days_passed = $this->calculateDaysPassed($investment['created_at']);
            $profit_day = $days_passed + 1;

            // Use Profit model with skipTransaction = true
            $profitModel = new Profit($this->database);
            $profitModel->createDailyProfit([
                'user_id' => $investment['user_id'],
                'investment_id' => $investment['id'],
                'schema_id' => $investment['schema_id'],
                'profit_amount' => $daily_profit,
                'daily_rate' => $schema['daily_rate'],
                'investment_amount' => $investment['invest_amount'],
                'profit_day' => $profit_day,
                'plan_name' => $schema['name'],
                'next_profit_date' => date('Y-m-d', strtotime('+1 day'))
            ], true);

            // Update user balance
            $adminSettingsModel = new AdminSettings($this->database);
            $profitDistributionLocked = $adminSettingsModel->getSetting('profit_distribution_locked', 0);

            if ($profitDistributionLocked) {
                $this->database->update('users', [
                    'locked_balance' => $this->database->raw('locked_balance + ' . $daily_profit),
                    'total_earned' => $this->database->raw('total_earned + ' . $daily_profit)
                ], 'id = ?', [$investment['user_id']]);

                echo "   [PROFIT] Locked Mode: Added $" . number_format($daily_profit, 2) . " to locked balance for user " . $investment['user_id'] . "\n";
            } else {
                $this->database->update('users', [
                    'balance' => $this->database->raw('balance + ' . $daily_profit),
                    'total_earned' => $this->database->raw('total_earned + ' . $daily_profit)
                ], 'id = ?', [$investment['user_id']]);

                echo "   [PROFIT] Immediate Mode: Added $" . number_format($daily_profit, 2) . " to available balance for user " . $investment['user_id'] . "\n";
            }

            // Update investment next profit time
            $next_profit_time = date('Y-m-d H:i:s', strtotime('+1 day'));
            $this->investmentModel->updateProfitTimes($investment['id'], date('Y-m-d H:i:s'), $next_profit_time);

            $this->database->commit();
            return $daily_profit;

        } catch (Exception $e) {
            $this->database->rollback();
            throw $e;
        }
    }

    private function calculateDaysPassed($created_at)
    {
        $created_timestamp = strtotime($created_at);
        $current_timestamp = time();
        return floor(($current_timestamp - $created_timestamp) / (24 * 60 * 60));
    }

    /**
     * Create summary report
     */
    public function generateSummaryReport()
    {
        echo "\n[REPORT] DAILY PROFIT SUMMARY REPORT\n";
        echo "================================\n";

        try {
            // Get today's profit transactions
            $today_profits = $this->database->fetchAll("
                SELECT 
                    COUNT(*) as profit_transactions,
                    SUM(amount) as total_profit_paid,
                    COUNT(DISTINCT user_id) as users_benefited
                FROM transactions 
                WHERE type = 'profit' 
                AND DATE(created_at) = CURDATE()
            ");

            if ($today_profits && $today_profits[0]['profit_transactions'] > 0) {
                echo "[PROFIT] Today's Profits: $" . number_format($today_profits[0]['total_profit_paid'], 2) . "\n";
                echo "[USERS] Users Benefited: " . $today_profits[0]['users_benefited'] . "\n";
                echo "[STATS] Transactions: " . $today_profits[0]['profit_transactions'] . "\n";
            } else {
                echo "No profits distributed today.\n";
            }

            // Get active investments count
            $active_investments = $this->database->fetchOne("
                SELECT COUNT(*) as count 
                FROM investments 
                WHERE status = 'active'
            ");

            // Get locked balance summary
            $locked_balances = $this->database->fetchOne("
                SELECT 
                    COUNT(*) as users_with_locked,
                    SUM(locked_balance) as total_locked
                FROM users 
                WHERE locked_balance > 0
            ");

            if ($locked_balances && $locked_balances['total_locked'] > 0) {
                echo "[LOCKED] Locked Profits: $" . number_format($locked_balances['total_locked'], 2) . "\n";
                echo "[USERS] Users with Locked: " . $locked_balances['users_with_locked'] . "\n";
            }

            echo "[REPORT] Active Investments: " . ($active_investments['count'] ?? 0) . "\n";
            echo "================================\n\n";

        } catch (Exception $e) {
            echo "Error generating summary: " . $e->getMessage() . "\n";
        }
    }
}

// Execute the profit distribution
if (php_sapi_name() === 'cli') {
    echo "[START] CORNERFIELD DAILY PROFIT DISTRIBUTION\n";
    echo "========================================\n\n";

    $distributor = new DailyProfitDistributor();
    $result = $distributor->distributeProfits();

    // Generate summary report
    $distributor->generateSummaryReport();

    // Create logs directory if it doesn't exist
    $logsDir = dirname(__FILE__) . '/../logs';
    if (!file_exists($logsDir)) {
        mkdir($logsDir, 0755, true);
    }

    // Log to file
    $log_entry = date('Y-m-d H:i:s') . " - Profits distributed: " . $result['investments_processed'] . " investments, $" . number_format($result['total_profits'], 2) . " total";
    if ($result['errors'] > 0) {
        $log_entry .= ", " . $result['errors'] . " errors";
    }
    $log_entry .= "\n";

    file_put_contents($logsDir . '/daily-profits.log', $log_entry, FILE_APPEND | LOCK_EX);

    echo "[LOG] Log written to: " . $logsDir . "/daily-profits.log\n";
    echo "[OK] Daily profit distribution completed!\n\n";

} else {
    echo "[ERROR] This script must be run from command line\n";
    echo "Usage: php daily-profits.php\n";
}
?>