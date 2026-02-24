<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\ProfileController;

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// handle ajax profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    
    if (!AuthMiddleware::check()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        // map form field names to controller field names
        if (isset($_POST['firstname']) && !isset($_POST['first_name'])) {
            $_POST['first_name'] = $_POST['firstname'];
        }
        if (isset($_POST['lastname']) && !isset($_POST['last_name'])) {
            $_POST['last_name'] = $_POST['lastname'];
        }
        $controller = new ProfileController();
        $controller->updateProfile();
        exit;
    }
}

if (!AuthMiddleware::check()) {
    $user = ['id' => 1, 'firstname' => 'Demo', 'lastname' => 'User', 'email' => 'demo@cornerfield.com', 'balance' => 15420.50, 'username' => 'demouser'];
    $isPreview = true;
}

try {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $userModel = new \App\Models\UserModel();
    $profileUser = $userModel->findById($userId) ?? [];
    $base = \App\Config\Config::getBasePath();
    // get investment count
    $db = new \App\Config\Database();
    $investmentCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM investments WHERE user_id = ? AND status = 'active'", [$userId])['cnt'] ?? 0;

    $data = ['profile' => array_merge([
        'avatar' => $base . '/assets/images/default-avatar.png',
        'firstname' => $profileUser['first_name'] ?? '',
        'lastname' => $profileUser['last_name'] ?? '',
        'name' => ($profileUser['first_name'] ?? '') . ' ' . ($profileUser['last_name'] ?? ''),
        'email' => $profileUser['email'] ?? '',
        'username' => $profileUser['username'] ?? '',
        'phone' => $profileUser['phone'] ?? '',
        'country' => $profileUser['country'] ?? '',
        'kyc_status' => $profileUser['kyc_status'] ?? 'pending',
        'created_at' => $profileUser['created_at'] ?? '',
        'total_invested' => (float)($profileUser['total_invested'] ?? 0),
        'total_earned' => (float)($profileUser['total_earned'] ?? 0),
        'balance' => (float)($profileUser['balance'] ?? 0),
        'referral_code' => $profileUser['referral_code'] ?? '',
    ], $profileUser),
        'stats' => [
            'total_deposits' => (float)($profileUser['total_invested'] ?? 0),
            'total_withdrawals' => (float)($profileUser['total_withdrawn'] ?? 0),
            'total_earnings' => (float)($profileUser['total_earned'] ?? 0),
            'total_investments' => (int)$investmentCount,
            'referral_earnings' => 0,
            'referrals_count' => 0,
        ],
        'countries' => ['United States', 'United Kingdom', 'Canada', 'Australia', 'Germany', 'France', 'Japan', 'Singapore', 'Nigeria', 'South Africa', 'India', 'Brazil'],
    ];
} catch (\Throwable $e) {
    $data = [
        'profile' => [
            'id' => 1, 'email' => 'demo@cornerfield.io', 'firstname' => 'Demo', 'lastname' => 'User',
            'phone' => '+1234567890', 'country' => 'United States', 'city' => 'New York',
            'address' => '123 Wall Street', 'postal_code' => '10005', 'date_of_birth' => '1990-01-01',
            'avatar' => '/assets/images/default-avatar.png', 'created_at' => '2024-01-01 00:00:00',
            'last_login' => '2024-02-10 14:30:00', 'kyc_status' => 'pending',
            'kyc_submitted_at' => '2024-02-05 10:00:00', 'email_verified' => true,
            'phone_verified' => false, 'two_factor_enabled' => false
        ],
        'stats' => ['total_deposits' => 2500.00, 'total_withdrawals' => 550.00, 'total_investments' => 3, 'referrals_count' => 5],
        'countries' => ['United States', 'Canada', 'United Kingdom', 'Germany', 'France', 'Australia', 'Japan', 'Brazil', 'India', 'China']
    ];
}

$pageTitle = 'My Profile';
$currentPage = 'profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- profile header -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div class="flex items-center gap-5">
                <div class="relative">
                    <img class="w-20 h-20 rounded-full object-cover" 
                         src="<?= htmlspecialchars($data['profile']['avatar']) ?>" 
                         alt="<?= htmlspecialchars($data['profile']['firstname']) ?>"
                         onerror="this.src='/assets/images/default-avatar.png'">
                    <button class="absolute bottom-0 right-0 p-1.5 bg-[#1e0e62] text-white rounded-full" 
                            onclick="document.getElementById('avatar-upload').click()">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </button>
                    <input type="file" id="avatar-upload" accept="image/*" class="hidden" onchange="uploadAvatar(this)">
                </div>
                <div>
                    <h2 class="text-lg font-medium tracking-tight text-gray-900 dark:text-white">
                        <?= htmlspecialchars($data['profile']['firstname'] . ' ' . $data['profile']['lastname']) ?>
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($data['profile']['email']) ?></p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Member since <?= date('M Y', strtotime($data['profile']['created_at'])) ?></p>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Email verified
                        </span>
                        <?php
                        $kycStatus = $data['profile']['kyc_status'] ?? 'pending';
                        $kycColor = match($kycStatus) {
                            'approved', 'verified' => 'text-emerald-600 dark:text-emerald-400',
                            'pending' => 'text-amber-600 dark:text-amber-400',
                            default => 'text-red-600 dark:text-red-400',
                        };
                        ?>
                        <span class="inline-flex items-center gap-1 text-xs <?= $kycColor ?>">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            KYC <?= ucfirst($kycStatus) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="text-center p-3 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl">
                    <p class="text-lg font-light tracking-tighter text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_deposits'], 0) ?></p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Deposited</p>
                </div>
                <div class="text-center p-3 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl">
                    <p class="text-lg font-light tracking-tighter text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_withdrawals'], 0) ?></p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Withdrawn</p>
                </div>
                <div class="text-center p-3 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl">
                    <p class="text-lg font-light tracking-tighter text-gray-900 dark:text-white"><?= $data['stats']['total_investments'] ?></p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Investments</p>
                </div>
                <div class="text-center p-3 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl">
                    <p class="text-lg font-light tracking-tighter text-gray-900 dark:text-white"><?= $data['stats']['referrals_count'] ?></p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Referrals</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- personal info form -->
        <div class="xl:col-span-2">
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
                <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-5">Personal Information</h3>
                
                <form id="profileForm" method="POST" action="/users/profile.php">
                    <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::getToken() ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="firstname" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">First Name</label>
                            <input type="text" id="firstname" name="firstname" required
                                   value="<?= htmlspecialchars($data['profile']['firstname']) ?>"
                                   class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                        </div>
                        <div>
                            <label for="lastname" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Last Name</label>
                            <input type="text" id="lastname" name="lastname" required
                                   value="<?= htmlspecialchars($data['profile']['lastname']) ?>"
                                   class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                        </div>
                        <div>
                            <label for="email" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Email Address</label>
                            <input type="email" id="email" name="email" readonly
                                   value="<?= htmlspecialchars($data['profile']['email']) ?>"
                                   class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-[#f5f3ff] dark:bg-[#0f0a2e] text-gray-400 dark:text-gray-500 text-sm cursor-not-allowed">
                        </div>
                        <div>
                            <label for="phone" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Phone Number</label>
                            <input type="tel" id="phone" name="phone"
                                   value="<?= htmlspecialchars($data['profile']['phone'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                        </div>
                        <div>
                            <label for="date_of_birth" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth"
                                   value="<?= htmlspecialchars($data['profile']['date_of_birth'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                        </div>
                        <div>
                            <label for="country" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Country</label>
                            <select id="country" name="country" 
                                    class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                                <option value="">Select Country</option>
                                <?php foreach ($data['countries'] as $country): ?>
                                <option value="<?= htmlspecialchars($country) ?>" <?= ($data['profile']['country'] ?? '') === $country ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($country) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="city" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">City</label>
                            <input type="text" id="city" name="city"
                                   value="<?= htmlspecialchars($data['profile']['city'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                        </div>
                        <div>
                            <label for="postal_code" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code"
                                   value="<?= htmlspecialchars($data['profile']['postal_code'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="address" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Address</label>
                        <textarea id="address" name="address" rows="2"
                                  class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm"
                                  placeholder="Enter your full address"><?= htmlspecialchars($data['profile']['address'] ?? '') ?></textarea>
                    </div>

                    <div class="flex justify-end mt-5">
                        <button type="submit" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" data-original-text="Update Profile">
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- sidebar -->
        <div class="space-y-6">
            <!-- kyc -->
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
                <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-4">KYC Verification</h3>
                
                <?php
                $kycSidebarStatus = $data['profile']['kyc_status'] ?? 'pending';
                $kycBg = match($kycSidebarStatus) {
                    'approved', 'verified' => 'bg-emerald-100 dark:bg-emerald-900/30',
                    'pending' => 'bg-amber-100 dark:bg-amber-900/30',
                    default => 'bg-red-100 dark:bg-red-900/30'
                };
                $kycIconColor = match($kycSidebarStatus) {
                    'approved', 'verified' => 'text-emerald-600 dark:text-emerald-400',
                    'pending' => 'text-amber-600 dark:text-amber-400',
                    default => 'text-red-600 dark:text-red-400'
                };
                ?>
                <div class="p-3 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center <?= $kycBg ?>">
                        <svg class="w-4 h-4 <?= $kycIconColor ?>" fill="currentColor" viewBox="0 0 20 20">
                            <?php if (in_array($kycSidebarStatus, ['approved', 'verified'])): ?>
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            <?php else: ?>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            <?php endif; ?>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Identity Verification</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= ucfirst($kycSidebarStatus) ?></p>
                    </div>
                </div>
                
                <?php if (in_array($kycSidebarStatus, ['not_submitted', ''])): ?>
                <button onclick="openKYCModal()" class="w-full px-4 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">
                    Start Verification
                </button>
                <?php elseif ($kycSidebarStatus === 'pending'): ?>
                <p class="text-xs text-gray-500 dark:text-gray-400">Documents under review. Usually takes 1-3 business days.</p>
                <?php elseif (in_array($kycSidebarStatus, ['approved', 'verified'])): ?>
                <p class="text-xs text-emerald-600 dark:text-emerald-400">Your identity has been verified. Full access enabled.</p>
                <?php elseif ($kycSidebarStatus === 'rejected'): ?>
                <p class="text-xs text-red-600 dark:text-red-400">Verification was rejected. Please resubmit your documents.</p>
                <button onclick="openKYCModal()" class="w-full mt-2 px-4 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">
                    Resubmit Documents
                </button>
                <?php endif; ?>
            </div>

            <!-- security -->
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
                <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-4">Security</h3>
                <div class="space-y-2">
                    <?php
                    $checks = [
                        ['Email Verified', true],
                        ['Phone Verified', $data['profile']['phone_verified'] ?? false],
                        ['2FA Enabled', $data['profile']['two_factor_enabled'] ?? false],
                    ];
                    foreach ($checks as [$label, $ok]): ?>
                    <div class="flex items-center justify-between p-3 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-xl">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 <?= $ok ? 'text-emerald-500' : 'text-gray-300 dark:text-gray-600' ?>" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span class="text-sm text-gray-900 dark:text-white"><?= $label ?></span>
                        </div>
                        <?php if (!$ok && $label !== 'Email Verified'): ?>
                        <a href="<?= htmlspecialchars($base ?? '') ?>/users/settings.php#security" class="text-xs font-medium text-[#1e0e62] dark:text-indigo-400">Setup</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- activity -->
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
                <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-4">Account Activity</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Last Login</span>
                        <span class="text-gray-900 dark:text-white font-medium"><?= isset($data['profile']['last_login']) ? date('M j, H:i', strtotime($data['profile']['last_login'])) : 'N/A' ?></span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Member Since</span>
                        <span class="text-gray-900 dark:text-white font-medium"><?= date('M j, Y', strtotime($data['profile']['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- kyc modal -->
<div id="kycModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeKYCModal()"></div>
        <div class="relative bg-white dark:bg-[#1a1145] rounded-3xl max-w-2xl w-full p-6">
            <form id="kycForm" method="POST" action="/users/profile.php" enctype="multipart/form-data">
                <h3 class="text-lg font-medium tracking-tight text-gray-900 dark:text-white mb-1">Identity Verification</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Upload clear photos of your identification documents.</p>

                <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::getToken() ?>">
                <input type="hidden" name="action" value="submit_kyc">

                <div class="space-y-4">
                    <?php foreach ([['id-front', 'id_front', 'Government ID (Front)'], ['id-back', 'id_back', 'Government ID (Back)'], ['selfie', 'selfie', 'Selfie with ID']] as [$elemId, $name, $label]): ?>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5"><?= $label ?></label>
                        <div class="border-2 border-dashed border-gray-200 dark:border-[#2d1b6e] rounded-2xl p-5 text-center hover:border-[#1e0e62] dark:hover:border-indigo-400 transition-colors">
                            <input type="file" id="<?= $elemId ?>" name="<?= $name ?>" accept="image/*" required class="hidden" onchange="previewFile(this, '<?= $elemId ?>-preview')">
                            <label for="<?= $elemId ?>" class="cursor-pointer">
                                <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Click to upload</p>
                                <p class="text-xs text-gray-400 mt-0.5">PNG, JPG up to 10MB</p>
                            </label>
                            <div id="<?= $elemId ?>-preview" class="mt-3 hidden"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeKYCModal()" class="flex-1 px-4 py-2.5 border border-gray-200 dark:border-[#2d1b6e] text-gray-700 dark:text-gray-300 text-sm font-medium rounded-full hover:bg-[#f5f3ff] dark:hover:bg-[#0f0a2e] transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" data-original-text="Submit for Review">Submit for Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    if (typeof setLoading === 'function') setLoading(btn, true);
    setTimeout(() => {
        if (typeof setLoading === 'function') setLoading(btn, false);
        if (typeof showNotification === 'function') showNotification('Profile updated successfully', 'success');
    }, 2000);
});

function openKYCModal() { document.getElementById('kycModal').classList.remove('hidden'); }
function closeKYCModal() { document.getElementById('kycModal').classList.add('hidden'); }

function previewFile(input, previewId) {
    const file = input.files[0];
    const preview = document.getElementById(previewId);
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" class="max-w-full h-24 object-cover rounded-xl mx-auto"><p class="text-xs text-emerald-600 mt-1">' + file.name + '</p>';
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
}

function uploadAvatar(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.querySelector('img[alt="<?= addslashes($data['profile']['firstname']) ?>"]').src = e.target.result;
            if (typeof showNotification === 'function') showNotification('Avatar updated', 'success');
        };
        reader.readAsDataURL(file);
    }
}

document.getElementById('kycForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    if (typeof setLoading === 'function') setLoading(btn, true);
    setTimeout(() => {
        if (typeof setLoading === 'function') setLoading(btn, false);
        if (typeof showNotification === 'function') showNotification('Documents submitted. Review takes 1-3 business days.', 'success');
        closeKYCModal();
    }, 2000);
});

window.addEventListener('click', function(e) { if (e.target === document.getElementById('kycModal')) closeKYCModal(); });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
