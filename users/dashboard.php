<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\User;
use App\Models\Investment;
use App\Models\Transaction;
use App\Utils\SessionManager;
use App\Models\AdminSettings;

try {
    $database = new Database();
    $adminSettingsModel = new AdminSettings($database);
    $maintenanceMode = $adminSettingsModel->getSetting('maintenance_mode', 0);

    if ($maintenanceMode && !isset($_GET['admin_bypass'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Site Under Maintenance - <?= Config::getSiteName() ?></title>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                }
                .maintenance-container {
                    text-align: center;
                    padding: 2rem;
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(20px);
                    border-radius: 24px;
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
                }
                .crypto-icon {
                    font-size: 4rem;
                    margin-bottom: 1rem;
                    background: linear-gradient(45deg, #f7931a, #ffd700);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                h1 { font-size: 2.5rem; margin-bottom: 1rem; font-weight: 700; }
                .lead { font-size: 1.2rem; margin-bottom: 1.5rem; opacity: 0.9; }
                .completion { opacity: 0.8; font-size: 0.95rem; }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <div class="crypto-icon">₿</div>
                <h1>Site Under Maintenance</h1>
                <p class="lead">We're currently performing scheduled maintenance. Please check back shortly.</p>
                <div class="completion">Expected completion: Within 2 hours</div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Exception $e) {
    // If database fails, allow access
}

// Start session and check authentication
SessionManager::start();

// Check if we're impersonating
$isImpersonating = SessionManager::get('is_impersonating');
$impersonatedUserId = SessionManager::get('impersonating_user_id');

if ($isImpersonating && $impersonatedUserId) {
    $user_id = $impersonatedUserId;
    error_log("Impersonation active - Using user ID: $user_id");
} elseif (!SessionManager::get('user_logged_in')) {
    header('Location: ../login.php');
    exit;
} else {
    $user_id = SessionManager::get('user_id');
}

try {
    $database = new Database();
    $userModel = new User($database);
    $currentUser = $userModel->findById($user_id);

    if (!$currentUser) {
        header('Location: ../login.php');
        exit;
    }

    $stats = $userModel->getUserStats($user_id);
    $investmentHistory = $userModel->getInvestmentHistory($user_id, 5);
    $investmentModel = new Investment($database);
    $transactionModel = new Transaction($database);
    $investmentSchemas = $investmentModel->getAllSchemas();
    $recentTransactions = $transactionModel->getUserTransactions($user_id, null, 5);

} catch (Exception $e) {
    if (Config::isDebug()) {
        die('Error: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

// Impersonation alert
if (SessionManager::get('is_impersonating')) {
    echo '<div class="impersonation-alert alert alert-warning alert-dismissible" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>
                <strong>Admin Impersonation Active</strong>
                <div>You are viewing this account as an administrator. All actions are being logged.</div>
            </div>
            <a href="../admin/stop-impersonation.php" class="btn btn-warning btn-sm ms-auto">
                Return to Admin Panel
            </a>
        </div>
    </div>';
}

include __DIR__ . '/includes/header.php';
?>

    <style>
    .welcome-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: var(--radius-lg);
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
    }

    .welcome-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .welcome-subtitle {
        font-size: 1rem;
        opacity: 0.9;
        font-weight: 400;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card.gradient {
        background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--primary-color);
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .stat-card.balance::before { background: var(--success-color); }
    .stat-card.earned::before { background: var(--warning-color); }
    .stat-card.investments::before { background: var(--info-color); }
    .stat-card.referrals::before { background: var(--dark-color); }

    .stat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
            color: white;
        }

    .stat-card.balance .stat-icon { background: var(--success-color); }
    .stat-card.earned .stat-icon { background: var(--warning-color); }
    .stat-card.investments .stat-icon { background: var(--info-color); }
    .stat-card.referrals .stat-icon { background: var(--dark-color); }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        color: var(--text-primary);
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .quick-actions {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        color: var(--text-primary);
        display: flex;
        align-items: center;
    }

    .section-title i {
        margin-right: 0.5rem;
        color: var(--primary-color);
    }

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .action-btn {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 1rem;
        text-decoration: none;
        color: var(--text-primary);
        text-align: center;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }

    .action-btn:hover {
        border-color: var(--primary-color);
            transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        color: var(--primary-color);
        text-decoration: none;
    }

    .action-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
    }

    .action-btn.deposit .action-icon { background: var(--success-color); }
    .action-btn.withdraw .action-icon { background: var(--warning-color); }
    .action-btn.referrals .action-icon { background: var(--info-color); }
    .action-btn.transactions .action-icon { background: var(--dark-color); }

    .action-title {
        font-weight: 600;
        font-size: 0.875rem;
    }

    .action-desc {
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .investment-plans {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
    }

    .plan-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: var(--radius);
        padding: 1.25rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .plan-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
    }

    .plan-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .plan-name {
        font-weight: 600;
        font-size: 1rem;
    }

    .plan-rate {
        background: rgba(255, 255, 255, 0.2);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .plan-details {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .plan-detail {
        text-align: center;
    }

    .detail-value {
        font-size: 1.125rem;
        font-weight: 700;
        margin-bottom: 0.125rem;
    }

    .detail-label {
        font-size: 0.75rem;
        opacity: 0.9;
    }

        .plan-action {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: var(--radius);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-block;
        font-size: 0.875rem;
        backdrop-filter: blur(10px);
    }

    .plan-action:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-1px);
        color: white;
        text-decoration: none;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .recent-activity {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
    }

    .activity-item {
        display: flex;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--border-color);
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.75rem;
        font-size: 0.875rem;
        color: white;
    }

    .activity-icon.deposit { background: var(--success-color); }
    .activity-icon.withdrawal { background: var(--warning-color); }
    .activity-icon.withdraw { background: var(--warning-color); }
    .activity-icon.profit { background: var(--info-color); }

    .activity-content {
        flex: 1;
    }

    .activity-title {
        font-weight: 600;
        margin-bottom: 0.125rem;
        font-size: 0.875rem;
    }

    .activity-time {
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    .activity-amount {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .amount.positive { color: var(--success-color); }
    .amount.negative { color: var(--danger-color); }

    @media (max-width: 768px) {
        .content-grid { 
            grid-template-columns: 1fr; 
        }
        .stats-grid { 
            grid-template-columns: 1fr; 
        }
        .actions-grid { 
            grid-template-columns: repeat(2, 1fr); 
        }
        .welcome-title { 
            font-size: 1.5rem; 
        }
        }
    </style>

<!-- Welcome Section -->
<div class="welcome-section gradient-bg">
    <h1 class="welcome-title">Welcome back, <?= htmlspecialchars($currentUser['first_name'] ?? 'User') ?>! 👋</h1>
    <p class="welcome-subtitle">Here's what's happening with your investments today</p>
                    </div>

<!-- Statistics Grid -->
<div class="stats-grid">
    <div class="stat-card balance gradient-card gradient-shadow">
        <div class="stat-header">
            <div class="stat-icon gradient-bg-success">
                <i class="fas fa-wallet"></i>
            </div>
                                </div>
        <div class="stat-value gradient-text">$<?= number_format($stats['balance'] ?? 0, 2) ?></div>
        <div class="stat-label">Available Balance</div>
                            </div>

    <div class="stat-card earned gradient-card gradient-shadow">
        <div class="stat-header">
            <div class="stat-icon gradient-bg-warning">
                <i class="fas fa-chart-line"></i>
            </div>
                            </div>
        <div class="stat-value gradient-text">$<?= number_format($stats['total_earned'] ?? 0, 2) ?></div>
        <div class="stat-label">Total Earned</div>
                        </div>

    <div class="stat-card investments gradient-card gradient-shadow">
        <div class="stat-header">
            <div class="stat-icon gradient-bg">
                <i class="fas fa-rocket"></i>
            </div>
                                    </div>
        <div class="stat-value gradient-text"><?= number_format($stats['active_investments'] ?? 0) ?></div>
        <div class="stat-label">Active Investments</div>
                        </div>

    <div class="stat-card referrals gradient-card gradient-shadow">
        <div class="stat-header">
            <div class="stat-icon gradient-bg-alt">
                <i class="fas fa-users"></i>
                                        </div>
                                    </div>
        <div class="stat-value gradient-text"><?= number_format($stats['referral_count'] ?? 0) ?></div>
        <div class="stat-label">Total Referrals</div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
<div class="quick-actions gradient-card">
    <h2 class="section-title gradient-text">
        <i class="fas fa-bolt"></i>
        Quick Actions
    </h2>
    <div class="actions-grid">
        <a href="deposit.php" class="action-btn deposit">
            <div class="action-icon gradient-bg-success">
                <i class="fas fa-plus"></i>
                                </div>
            <div class="action-title">Deposit Funds</div>
            <div class="action-desc">Add money via crypto</div>
        </a>

        <a href="withdraw.php" class="action-btn withdraw">
            <div class="action-icon gradient-bg-warning">
                <i class="fas fa-minus"></i>
                                        </div>
            <div class="action-title">Withdraw</div>
            <div class="action-desc">Cash out your profits</div>
        </a>

        <a href="referrals.php" class="action-btn referrals">
            <div class="action-icon gradient-bg-alt">
                <i class="fas fa-users"></i>
                                        </div>
            <div class="action-title">Referrals</div>
            <div class="action-desc">Earn commissions</div>
        </a>

                <a href="transactions.php" class="action-btn transactions">
            <div class="action-icon gradient-bg">
                <i class="fas fa-history"></i>
                                        </div>
            <div class="action-title">Transactions</div>
            <div class="action-desc">View all activity</div>
                                            </a>
                                        </div>
                                    </div>

<!-- Content Grid -->
<div class="content-grid">
    <!-- Investment Plans -->
    <div class="investment-plans gradient-card">
        <h2 class="section-title gradient-text">
            <i class="fas fa-chart-pie"></i>
            Investment Plans
        </h2>
        <?php if (!empty($investmentSchemas)): ?>
            <?php foreach ($investmentSchemas as $schema): ?>
                <div class="plan-card">
                    <div class="plan-header">
                        <div class="plan-name"><?= htmlspecialchars($schema['name']) ?></div>
                        <div class="plan-rate"><?= number_format($schema['daily_rate'], 2) ?>% Daily</div>
                                </div>
                    <div class="plan-details">
                        <div class="plan-detail">
                            <div class="detail-value">$<?= number_format($schema['min_amount'], 2) ?></div>
                            <div class="detail-label">Min Investment</div>
                        </div>
                        <div class="plan-detail">
                            <div class="detail-value"><?= $schema['duration_days'] ?> Days</div>
                            <div class="detail-label">Duration</div>
                        </div>
                        <div class="plan-detail">
                            <div class="detail-value"><?= number_format($schema['total_return'] * 100, 1) ?>%</div>
                            <div class="detail-label">Total Return</div>
                        </div>
                    </div>
                    <a href="invest.php?plan=<?= $schema['id'] ?>" class="plan-action" style="display: inline-block; text-decoration: none;">
                        Invest Now
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <p>No investment plans available at the moment.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="recent-activity gradient-card">
        <h2 class="section-title gradient-text">
            <i class="fas fa-history"></i>
            Recent Activity
        </h2>
        <?php if (!empty($recentTransactions)): ?>
            <?php foreach ($recentTransactions as $transaction): ?>
                <div class="activity-item">
                    <div class="activity-icon <?= strtolower($transaction['type']) ?>">
                        <?php
                        switch (strtolower($transaction['type'])) {
                            case 'deposit':
                                echo '<i class="fas fa-plus"></i>';
                                break;
                            case 'withdrawal':
                                echo '<i class="fas fa-minus"></i>';
                                break;
                            case 'withdraw':
                                echo '<i class="fas fa-minus"></i>';
                                break;
                            case 'profit':
                                echo '<i class="fas fa-chart-line"></i>';
                                break;
                            default:
                                echo '<i class="fas fa-exchange-alt"></i>';
                        }
                        ?>
                </div>
                    <div class="activity-content">
                        <div class="activity-title"><?= htmlspecialchars(ucfirst($transaction['type'])) ?></div>
                        <div class="activity-time"><?= date('M j, Y g:i A', strtotime($transaction['created_at'])) ?></div>
                    </div>
                    <div class="activity-amount">
                        <span class="amount <?= $transaction['amount'] >= 0 ? 'positive' : 'negative' ?>">
                            <?= ($transaction['amount'] >= 0 ? '+' : '') ?>$<?= number_format(abs($transaction['amount']), 2) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-history fa-2x mb-3"></i>
                <p>No recent transactions.</p>
            </div>
        <?php endif; ?>
        </div>
    </div>

<?php include __DIR__ . '/includes/footer.php'; ?>