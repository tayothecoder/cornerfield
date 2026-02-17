<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\ProfileController;

// Auth check (preview-safe)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!AuthMiddleware::check()) {
    $user = ['id' => 1, 'firstname' => 'Demo', 'lastname' => 'User', 'email' => 'demo@cornerfield.com', 'balance' => 15420.50, 'username' => 'demouser'];
    $isPreview = true;
}

// Initialize controller and get data
// For demo/preview: wrap in try/catch so pages render even without DB
try {

    $data = $controller->getProfileData();
} catch (\Throwable $e) {
    // Fallback demo data for preview
    $data = [
        'profile' => [
            'id' => 1,
            'email' => 'demo@cornerfield.io',
            'firstname' => 'Demo',
            'lastname' => 'User',
            'phone' => '+1234567890',
            'country' => 'United States',
            'city' => 'New York',
            'address' => '123 Wall Street',
            'postal_code' => '10005',
            'date_of_birth' => '1990-01-01',
            'avatar' => '/assets/images/default-avatar.png',
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => '2024-02-10 14:30:00',
            'kyc_status' => 'pending',
            'kyc_submitted_at' => '2024-02-05 10:00:00',
            'email_verified' => true,
            'phone_verified' => false,
            'two_factor_enabled' => false
        ],
        'stats' => [
            'total_deposits' => 2500.00,
            'total_withdrawals' => 550.00,
            'total_investments' => 3,
            'referrals_count' => 5
        ],
        'countries' => [
            'United States', 'Canada', 'United Kingdom', 'Germany', 'France', 
            'Australia', 'Japan', 'Brazil', 'India', 'China'
        ]
    ];
}

$pageTitle = 'My Profile';
$currentPage = 'profile';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Profile Content -->
<div class="space-y-6">
    <!-- Profile Header -->
    <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center space-x-6 mb-6 lg:mb-0">
                <!-- Avatar -->
                <div class="relative">
                    <img class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-lg" 
                         src="<?= htmlspecialchars($data['profile']['avatar']) ?>" 
                         alt="<?= htmlspecialchars($data['profile']['firstname']) ?>"
                         onerror="this.src='/assets/images/default-avatar.png'">
                    <button class="absolute bottom-0 right-0 p-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full shadow-lg transition-colors" 
                            onclick="document.getElementById('avatar-upload').click()">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </button>
                    <input type="file" id="avatar-upload" accept="image/*" class="hidden" onchange="uploadAvatar(this)">
                </div>

                <!-- User Info -->
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        <?= htmlspecialchars($data['profile']['firstname'] . ' ' . $data['profile']['lastname']) ?>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($data['profile']['email']) ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Member since <?= date('M Y', strtotime($data['profile']['created_at'])) ?>
                    </p>
                    <div class="flex items-center space-x-4 mt-2">
                        <!-- Verification Badges -->
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm text-green-600 dark:text-green-400">Email Verified</span>
                        </div>
                        
                        <div class="flex items-center">
                            <svg class="w-4 h-4 <?= $data['profile']['kyc_status'] === 'verified' ? 'text-green-500' : 'text-yellow-500' ?> mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm <?= $data['profile']['kyc_status'] === 'verified' ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' ?>">
                                KYC <?= ucfirst($data['profile']['kyc_status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_deposits'], 0) ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Deposited</div>
                </div>
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_withdrawals'], 0) ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Withdrawn</div>
                </div>
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= $data['stats']['total_investments'] ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Investments</div>
                </div>
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= $data['stats']['referrals_count'] ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Referrals</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Profile Form -->
        <div class="xl:col-span-2 space-y-6">
            <!-- Personal Information -->
            <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">Personal Information</h3>
                
                <form id="profileForm" method="POST" action="/users/profile.php" data-validate>
                    <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::generateToken() ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="firstname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                First Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="firstname" name="firstname" required
                                   value="<?= htmlspecialchars($data['profile']['firstname']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label for="lastname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Last Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="lastname" name="lastname" required
                                   value="<?= htmlspecialchars($data['profile']['lastname']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Email Address
                            </label>
                            <input type="email" id="email" name="email" readonly
                                   value="<?= htmlspecialchars($data['profile']['email']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Email cannot be changed</p>
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Phone Number
                            </label>
                            <input type="tel" id="phone" name="phone"
                                   value="<?= htmlspecialchars($data['profile']['phone'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label for="date_of_birth" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Date of Birth
                            </label>
                            <input type="date" id="date_of_birth" name="date_of_birth"
                                   value="<?= htmlspecialchars($data['profile']['date_of_birth'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Country
                            </label>
                            <select id="country" name="country" 
                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select Country</option>
                                <?php foreach ($data['countries'] as $country): ?>
                                <option value="<?= htmlspecialchars($country) ?>" <?= $data['profile']['country'] === $country ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($country) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                City
                            </label>
                            <input type="text" id="city" name="city"
                                   value="<?= htmlspecialchars($data['profile']['city'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Postal Code
                            </label>
                            <input type="text" id="postal_code" name="postal_code"
                                   value="<?= htmlspecialchars($data['profile']['postal_code'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>

                    <div class="mt-6">
                        <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Address
                        </label>
                        <textarea id="address" name="address" rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Enter your full address"><?= htmlspecialchars($data['profile']['address'] ?? '') ?></textarea>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="submit" 
                                class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors"
                                data-original-text="Update Profile">
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- KYC Verification -->
            <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">KYC Verification</h3>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 <?= $data['profile']['kyc_status'] === 'verified' ? 'bg-green-100 dark:bg-green-900' : ($data['profile']['kyc_status'] === 'pending' ? 'bg-yellow-100 dark:bg-yellow-900' : 'bg-red-100 dark:bg-red-900') ?> rounded-full flex items-center justify-center mr-3">
                                <svg class="w-4 h-4 <?= $data['profile']['kyc_status'] === 'verified' ? 'text-green-600 dark:text-green-400' : ($data['profile']['kyc_status'] === 'pending' ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>" fill="currentColor" viewBox="0 0 20 20">
                                    <?php if ($data['profile']['kyc_status'] === 'verified'): ?>
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    <?php elseif ($data['profile']['kyc_status'] === 'pending'): ?>
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                    <?php else: ?>
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    <?php endif; ?>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Identity Verification</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Status: <?= ucfirst($data['profile']['kyc_status']) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($data['profile']['kyc_status'] === 'not_submitted'): ?>
                    <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200 mb-3">
                            Complete your identity verification to increase withdrawal limits and access premium features.
                        </p>
                        <button onclick="openKYCModal()" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-colors">
                            Start Verification
                        </button>
                    </div>
                    <?php elseif ($data['profile']['kyc_status'] === 'pending'): ?>
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            Your documents are being reviewed. This usually takes 1-3 business days.
                        </p>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                            Submitted: <?= date('M j, Y', strtotime($data['profile']['kyc_submitted_at'])) ?>
                        </p>
                    </div>
                    <?php elseif ($data['profile']['kyc_status'] === 'verified'): ?>
                    <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <p class="text-sm text-green-800 dark:text-green-200">
                            ✅ Your identity has been verified! You now have access to all features and higher limits.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Security Status -->
            <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Security Status</h3>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm text-gray-900 dark:text-white">Email Verified</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 <?= $data['profile']['phone_verified'] ? 'text-green-500' : 'text-gray-400' ?> mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm text-gray-900 dark:text-white">Phone Verified</span>
                        </div>
                        <?php if (!$data['profile']['phone_verified']): ?>
                        <button class="text-indigo-600 hover:text-indigo-700 text-xs font-medium">Verify</button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 <?= $data['profile']['two_factor_enabled'] ? 'text-green-500' : 'text-gray-400' ?> mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm text-gray-900 dark:text-white">2FA Enabled</span>
                        </div>
                        <?php if (!$data['profile']['two_factor_enabled']): ?>
                        <a href="/users/settings.php#security" class="text-indigo-600 hover:text-indigo-700 text-xs font-medium">Enable</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Last Login -->
            <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Account Activity</h3>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-300">Last Login:</span>
                        <span class="text-gray-900 dark:text-white font-medium"><?= date('M j, Y H:i', strtotime($data['profile']['last_login'])) ?></span>
                    </div>
                    
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-300">Member Since:</span>
                        <span class="text-gray-900 dark:text-white font-medium"><?= date('M j, Y', strtotime($data['profile']['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KYC Modal -->
<div id="kycModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <form id="kycForm" method="POST" action="/users/profile.php" enctype="multipart/form-data" class="p-6" data-validate>
                <div class="mb-6">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Identity Verification</h3>
                    <p class="text-gray-600 dark:text-gray-300">Please upload clear photos of your identification documents.</p>
                </div>

                <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::generateToken() ?>">
                <input type="hidden" name="action" value="submit_kyc">

                <div class="space-y-6">
                    <!-- Front ID -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Government ID (Front) <span class="text-red-500">*</span>
                        </label>
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-indigo-400 transition-colors">
                            <input type="file" id="id-front" name="id_front" accept="image/*" required class="hidden" onchange="previewFile(this, 'id-front-preview')">
                            <label for="id-front" class="cursor-pointer">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="text-gray-600 dark:text-gray-300">Click to upload front of ID</p>
                                <p class="text-sm text-gray-400">PNG, JPG up to 10MB</p>
                            </label>
                            <div id="id-front-preview" class="mt-4 hidden"></div>
                        </div>
                    </div>

                    <!-- Back ID -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Government ID (Back) <span class="text-red-500">*</span>
                        </label>
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-indigo-400 transition-colors">
                            <input type="file" id="id-back" name="id_back" accept="image/*" required class="hidden" onchange="previewFile(this, 'id-back-preview')">
                            <label for="id-back" class="cursor-pointer">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="text-gray-600 dark:text-gray-300">Click to upload back of ID</p>
                                <p class="text-sm text-gray-400">PNG, JPG up to 10MB</p>
                            </label>
                            <div id="id-back-preview" class="mt-4 hidden"></div>
                        </div>
                    </div>

                    <!-- Selfie -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Selfie with ID <span class="text-red-500">*</span>
                        </label>
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-indigo-400 transition-colors">
                            <input type="file" id="selfie" name="selfie" accept="image/*" required class="hidden" onchange="previewFile(this, 'selfie-preview')">
                            <label for="selfie" class="cursor-pointer">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <p class="text-gray-600 dark:text-gray-300">Click to upload selfie holding your ID</p>
                                <p class="text-sm text-gray-400">PNG, JPG up to 10MB</p>
                            </label>
                            <div id="selfie-preview" class="mt-4 hidden"></div>
                        </div>
                    </div>

                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            📋 <strong>Requirements:</strong><br>
                            • Clear, well-lit photos<br>
                            • All text must be readable<br>
                            • No blurred or cropped images<br>
                            • Supported: Passport, Driver's License, National ID
                        </p>
                    </div>
                </div>

                <div class="flex space-x-3 mt-6">
                    <button type="button" onclick="closeKYCModal()" 
                        class="flex-1 bg-gray-300 hover:bg-gray-400 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-white font-medium py-3 px-4 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors"
                        data-original-text="Submit for Review">
                        Submit for Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Profile form submission
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    setLoading(submitBtn, true);
    
    // Simulate API call
    setTimeout(() => {
        setLoading(submitBtn, false);
        showNotification('Profile updated successfully!', 'success');
    }, 2000);
});

// KYC Modal functions
function openKYCModal() {
    document.getElementById('kycModal').classList.remove('hidden');
}

function closeKYCModal() {
    document.getElementById('kycModal').classList.add('hidden');
}

// File preview function
function previewFile(input, previewId) {
    const file = input.files[0];
    const preview = document.getElementById(previewId);
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <img src="${e.target.result}" class="max-w-full h-32 object-cover rounded-lg mx-auto">
                <p class="text-sm text-green-600 mt-2">${file.name} uploaded</p>
            `;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
}

// Avatar upload function
function uploadAvatar(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.querySelector('img[alt="' + '<?= addslashes($data['profile']['firstname']) ?>' + '"]').src = e.target.result;
            showNotification('Avatar updated successfully!', 'success');
        };
        reader.readAsDataURL(file);
    }
}

// KYC form submission
document.getElementById('kycForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    setLoading(submitBtn, true);
    
    // Simulate file upload
    setTimeout(() => {
        setLoading(submitBtn, false);
        showNotification('Documents submitted successfully! Review typically takes 1-3 business days.', 'success');
        closeKYCModal();
        
        // Update KYC status to pending
        setTimeout(() => {
            location.reload();
        }, 2000);
    }, 3000);
});

// Close modal on outside click
window.addEventListener('click', function(e) {
    const modal = document.getElementById('kycModal');
    if (e.target === modal) {
        closeKYCModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>