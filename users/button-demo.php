<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\User;
use App\Utils\SessionManager;

// Start session and check authentication
SessionManager::start();

if (!SessionManager::get('user_logged_in')) {
    header('Location: ../login.php');
    exit;
}

$user_id = SessionManager::get('user_id');

try {
    $database = new Database();
    $userModel = new User($database);
    $currentUser = $userModel->findById($user_id);

    if (!$currentUser) {
        header('Location: ../login.php');
        exit;
    }

} catch (Exception $e) {
    if (Config::isDebug()) {
        die('Error: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

$pageTitle = 'Button Demo';
$currentPage = 'button-demo';

include __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto p-6">
    <!-- Header -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Beautiful Gradient Buttons</h1>
        <p class="text-gray-600">Inspired by Uiverse.io - Beautiful gradient buttons with smooth animations</p>
    </div>

    <!-- Button Examples -->
    <div class="space-y-8">
        <!-- Primary Buttons -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Primary Buttons</h2>
            
            <div class="flex flex-wrap gap-4">
                <button class="gradient-btn primary">
                    <span class="shadow-layer"></span>
                    <span class="bg-layer"></span>
                    <div class="content-layer">
                        <span>Start Session</span>
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path clip-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" fill-rule="evenodd"></path>
                        </svg>
                    </div>
                </button>

                <button class="gradient-btn primary">
                    <span class="shadow-layer"></span>
                    <span class="bg-layer"></span>
                    <div class="content-layer">
                        <span>Invest Now</span>
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </button>

                <button class="gradient-btn primary">
                    <span class="shadow-layer"></span>
                    <span class="bg-layer"></span>
                    <div class="content-layer">
                        <span>Get Started</span>
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </button>
            </div>
        </div>

        <!-- Success Buttons -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Success Buttons</h2>
            
            <div class="flex flex-wrap gap-4">
                <button class="gradient-btn success">
                    <span class="shadow-layer"></span>
                    <span class="bg-layer"></span>
                    <div class="content-layer">
                        <span>Deposit Funds</span>
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </button>

                <button class="gradient-btn success">
                    <span class="shadow-layer"></span>
                    <span class="bg-layer"></span>
                    <div class="content-layer">
                        <span>Confirm</span>
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </button>
            </div>
        </div>

        <!-- Warning Buttons -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Warning Buttons</h2>
            
            <div class="flex flex-wrap gap-4">
                <button class="gradient-btn warning">
                    <span class="shadow-layer"></span>
                    <span class="bg-layer"></span>
                    <div class="content-layer">
                        <span>Withdraw</span>
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </button>

                <button class="gradient-btn warning">
                    <span class="shadow-layer"></span>
                    <span class="bg-layer"></span>
                    <div class="content-layer">
                        <span>Edit Profile</span>
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                        </svg>
                    </div>
                </button>
            </div>
        </div>

        <!-- Danger Buttons -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Danger Buttons</h2>
            
            <div class="flex flex-wrap gap-4">
                <button class="gradient-btn danger">
                    <span class="shadow-layer"></span>
                    <span class="bg-layer"></span>
                    <div class="content-layer">
                        <span>Delete Account</span>
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd"></path>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </button>

                <button class="gradient-btn danger">
                    <span class="shadow-layer"></span>
                    <span class="bg-layer"></span>
                    <div class="content-layer">
                        <span>Cancel</span>
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </button>
            </div>
        </div>

        <!-- Info Buttons -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Info Buttons</h2>
            
            <div class="flex flex-wrap gap-4">
                <button class="gradient-btn info">
                    <span class="shadow-layer"></span>
                    <span class="bg-layer"></span>
                    <div class="content-layer">
                        <span>Learn More</span>
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </button>

                <button class="gradient-btn info">
                    <span class="shadow-layer"></span>
                    <span class="bg-layer"></span>
                    <div class="content-layer">
                        <span>Support</span>
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C15.759 8.071 16 9.007 16 10zm-5.165 3.913l1.58 1.58A5.98 5.98 0 0110 16a5.976 5.976 0 01-2.516-.552l1.562-1.562a4.006 4.006 0 001.789.027zm-4.677-2.796a3.996 3.996 0 01-.041-2.08l-.08.08A3.996 3.996 0 004 10c0 .993.241 1.929.668 2.754l1.524-1.525zm1.088-6.45A5.974 5.974 0 0110 4c.993 0 1.929.241 2.754.668l-1.525 1.525a3.997 3.997 0 00-2.183-.078l-1.562-1.562zM13.446 6.7a5.974 5.974 0 00-3.146 3.146l1.525 1.525a3.996 3.996 0 012.08-.041l.08-.08a3.996 3.996 0 00-.041-2.08l-1.525-1.525z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <!-- Usage Instructions -->
    <div class="bg-gray-50 rounded-xl p-8 mt-8">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">How to Use</h2>
        
        <div class="space-y-4 text-gray-700">
            <p><strong>Basic Usage:</strong></p>
            <div class="bg-gray-800 text-green-400 p-4 rounded-lg font-mono text-sm overflow-x-auto">
                <pre>&lt;button class="gradient-btn primary"&gt;
    &lt;span class="shadow-layer"&gt;&lt;/span&gt;
    &lt;span class="bg-layer"&gt;&lt;/span&gt;
    &lt;div class="content-layer"&gt;
        &lt;span&gt;Button Text&lt;/span&gt;
        &lt;svg viewBox="0 0 20 20" fill="currentColor"&gt;
            &lt;path d="..."&gt;&lt;/path&gt;
        &lt;/svg&gt;
    &lt;/div&gt;
&lt;/button&gt;</pre>
            </div>
            
            <p><strong>Available Variants:</strong></p>
            <ul class="list-disc list-inside space-y-1 ml-4">
                <li><code class="bg-gray-200 px-2 py-1 rounded">primary</code> - Orange to purple gradient</li>
                <li><code class="bg-gray-200 px-2 py-1 rounded">success</code> - Green gradient</li>
                <li><code class="bg-gray-200 px-2 py-1 rounded">warning</code> - Yellow/amber gradient</li>
                <li><code class="bg-gray-200 px-2 py-1 rounded">danger</code> - Red gradient</li>
                <li><code class="bg-gray-200 px-2 py-1 rounded">info</code> - Cyan gradient</li>
            </ul>
            
            <p><strong>Features:</strong></p>
            <ul class="list-disc list-inside space-y-1 ml-4">
                <li>Beautiful 3D shadow effect</li>
                <li>Smooth hover animations</li>
                <li>Active state feedback</li>
                <li>Icon support with animation</li>
                <li>Responsive design</li>
                <li>Multiple color variants</li>
            </ul>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
