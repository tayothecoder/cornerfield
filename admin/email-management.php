<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Initialize session
\App\Utils\SessionManager::start();

// Page setup
$pageTitle = 'Email Management - ' . \App\Config\Config::getSiteName();
$currentPage = 'email-management';

try {
    $database = new \App\Config\Database();
    $adminController = new \App\Controllers\AdminController($database);
    $emailService = new \App\Services\EmailService($database);
} catch (Exception $e) {
    if (\App\Config\Config::isDebug()) {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

// Check if admin is logged in
if (!$adminController->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentAdmin = $adminController->getCurrentAdmin();
$success = '';
$error = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'test_email':
                try {
                    $result = $emailService->testConfiguration();
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Test configuration error: ' . $e->getMessage()]);
                }
                break;
                
            case 'send_test_email':
                $to = $_POST['to_email'] ?? '';
                $subject = $_POST['subject'] ?? 'Test Email';
                $message = $_POST['message'] ?? 'This is a test email from CornerField admin panel.';
                
                if (empty($to)) {
                    echo json_encode(['success' => false, 'message' => 'Recipient email is required']);
                    break;
                }
                
                $result = $emailService->sendEmail($to, $subject, $message, true);
                echo json_encode($result);
                break;
                
            case 'send_template_email':
                $to = $_POST['to_email'] ?? '';
                $template = $_POST['template'] ?? '';
                $data = json_decode($_POST['data'] ?? '{}', true);
                
                if (empty($to) || empty($template)) {
                    echo json_encode(['success' => false, 'message' => 'Recipient email and template are required']);
                    break;
                }
                
                $result = $emailService->sendTemplateEmail($to, $template, $data);
                echo json_encode($result);
                break;
                
            case 'get_email_stats':
                $stats = $emailService->getEmailStats();
                echo json_encode(['success' => true, 'stats' => $stats]);
                break;
                
            case 'get_template_content':
                $template = $_POST['template'] ?? '';
                if (empty($template)) {
                    echo json_encode(['success' => false, 'message' => 'Template name is required']);
                    break;
                }
                
                $content = $emailService->getTemplateContent($template);
                $variables = $emailService->getTemplateVariables($template);
                echo json_encode(['success' => true, 'content' => $content, 'variables' => $variables]);
                break;
                
            case 'save_template':
                $template = $_POST['template'] ?? '';
                $content = $_POST['content'] ?? '';
                
                if (empty($template) || empty($content)) {
                    echo json_encode(['success' => false, 'message' => 'Template name and content are required']);
                    break;
                }
                
                $result = $emailService->saveTemplate($template, $content);
                echo json_encode($result);
                break;
                
            case 'preview_template':
                $template = $_POST['template'] ?? '';
                $customData = json_decode($_POST['custom_data'] ?? '{}', true);
                
                if (empty($template)) {
                    echo json_encode(['success' => false, 'message' => 'Template name is required']);
                    break;
                }
                
                $result = $emailService->previewTemplate($template, $customData);
                echo json_encode(['success' => true, 'preview' => $result]);
                break;
                
            case 'save_email_config':
                $smtp_host = $_POST['smtp_host'] ?? '';
                $smtp_port = $_POST['smtp_port'] ?? '';
                $smtp_username = $_POST['smtp_username'] ?? '';
                $smtp_password = $_POST['smtp_password'] ?? '';
                $smtp_encryption = $_POST['smtp_encryption'] ?? '';
                $from_email = $_POST['from_email'] ?? '';
                $from_name = $_POST['from_name'] ?? '';
                
                if (empty($smtp_host) || empty($smtp_port) || empty($from_email) || empty($from_name)) {
                    echo json_encode(['success' => false, 'message' => 'Required fields cannot be empty']);
                    break;
                }
                
                try {
                    // Save email configuration to database
                    $emailSettings = [
                        'email_smtp_host' => $smtp_host,
                        'email_smtp_port' => $smtp_port,
                        'email_smtp_username' => $smtp_username,
                        'email_smtp_password' => $smtp_password,
                        'email_smtp_encryption' => $smtp_encryption,
                        'email_from_email' => $from_email,
                        'email_from_name' => $from_name
                    ];
                    
                    foreach ($emailSettings as $key => $value) {
                        $database->query("INSERT INTO admin_settings (setting_key, setting_value, setting_type, description) 
                                        VALUES (?, ?, 'string', ?) 
                                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", 
                                        [$key, $value, 'Email configuration']);
                    }
                    
                    // Reinitialize the email service with new configuration
                    $emailService->reinitializeMailer();
                    
                    echo json_encode(['success' => true, 'message' => 'Email configuration saved successfully']);
                    
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to save email configuration: ' . $e->getMessage()]);
                }
                break;
                
            case 'get_email_config':
                try {
                    $emailSettings = $database->fetchAll("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key LIKE 'email_%'");
                    $config = [];
                    
                    foreach ($emailSettings as $setting) {
                        $config[$setting['setting_key']] = $setting['setting_value'];
                    }
                    
                    echo json_encode(['success' => true, 'config' => $config]);
                    
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to load email configuration: ' . $e->getMessage()]);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Get email statistics
$emailStats = $emailService->getEmailStats();

// Get recent email logs
try {
    $recentEmails = $database->fetchAll("
        SELECT * FROM email_logs 
        ORDER BY sent_at DESC 
        LIMIT 10
    ");
} catch (Exception $e) {
    $recentEmails = [];
    $error = 'Failed to load email logs: ' . $e->getMessage();
}

// Get available templates
$availableTemplates = [
    'welcome' => 'Welcome Email',
    'password_reset' => 'Password Reset',
    'investment_confirmation' => 'Investment Confirmation',
    'withdrawal_confirmation' => 'Withdrawal Confirmation'
];

include __DIR__ . '/includes/header.php';
?>

<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title">Email Management</h2>
                    <div class="text-muted mt-1">Manage email settings and send emails</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="page-body">
        <div class="container-xl">
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <!-- Email Statistics -->
            <div class="row row-deck row-cards mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Total Emails</div>
                            </div>
                            <div class="h1 mb-3 text-primary"><?= number_format($emailStats['total_emails']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Sent Successfully</div>
                            </div>
                            <div class="h1 mb-3 text-success"><?= number_format($emailStats['sent_emails']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Failed</div>
                            </div>
                            <div class="h1 mb-3 text-danger"><?= number_format($emailStats['failed_emails']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Success Rate</div>
                            </div>
                            <div class="h1 mb-3 text-info">
                                <?= $emailStats['total_emails'] > 0 ? round(($emailStats['sent_emails'] / $emailStats['total_emails']) * 100, 1) : 0 ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Email Configuration -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Email Configuration</h3>
                        </div>
                        <div class="card-body">
                            <form id="emailConfigForm">
                                <div class="mb-3">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" name="smtp_host" value="localhost" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" name="smtp_port" value="587" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="text" class="form-control" name="smtp_username" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" name="smtp_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Encryption</label>
                                    <select class="form-select" name="smtp_encryption">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="">None</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">From Email</label>
                                    <input type="email" class="form-control" name="from_email" value="noreply@cornerfield.local" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">From Name</label>
                                    <input type="text" class="form-control" name="from_name" value="CornerField" required>
                                </div>
                                <div class="mb-3">
                                    <button type="button" class="btn btn-primary" onclick="testEmailConfig()">Test Configuration</button>
                                    <button type="submit" class="btn btn-success">Save Configuration</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Send Test Email -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Send Test Email</h3>
                        </div>
                        <div class="card-body">
                            <form id="testEmailForm">
                                <div class="mb-3">
                                    <label class="form-label">To Email</label>
                                    <input type="email" class="form-control" name="to_email" placeholder="recipient@example.com" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Subject</label>
                                    <input type="text" class="form-control" name="subject" value="Test Email from CornerField" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Message</label>
                                    <textarea class="form-control" name="message" rows="4" placeholder="Enter your test message here...">This is a test email to verify your email configuration is working properly.</textarea>
                                </div>
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary">Send Test Email</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Template Emails -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Send Template Emails</h3>
                        </div>
                        <div class="card-body">
                            <form id="templateEmailForm">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">To Email</label>
                                            <input type="email" class="form-control" name="to_email" placeholder="recipient@example.com" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Template</label>
                                            <select class="form-select" name="template" required>
                                                <option value="">Select Template</option>
                                                <?php foreach ($availableTemplates as $key => $label): ?>
                                                    <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Recipient Name</label>
                                            <input type="text" class="form-control" name="user_name" placeholder="John Doe" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-success">Send Template Email</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Email Logs -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Email Logs</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-vcenter">
                                    <thead>
                                        <tr>
                                            <th>To</th>
                                            <th>Subject</th>
                                            <th>Status</th>
                                            <th>Sent At</th>
                                            <th>Error</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentEmails as $email): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($email['to_email']) ?></td>
                                                <td><?= htmlspecialchars($email['subject']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $email['status'] === 'sent' ? 'success' : ($email['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                                        <?= ucfirst($email['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y g:i A', strtotime($email['sent_at'])) ?></td>
                                                <td>
                                                    <?php if ($email['error_message']): ?>
                                                        <span class="text-danger" title="<?= htmlspecialchars($email['error_message']) ?>">
                                                            <?= htmlspecialchars(substr($email['error_message'], 0, 50)) ?>...
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Email Template Editor -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📝 Email Template Editor</h3>
                        <p class="card-subtitle">Edit and customize email templates</p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Select Template</label>
                                    <select class="form-select" id="templateSelector">
                                        <option value="">Choose a template...</option>
                                        <?php 
                                        $availableTemplates = $emailService->getAvailableTemplates();
                                        foreach ($availableTemplates as $key => $label): 
                                        ?>
                                            <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div id="templateVariables" class="mb-3" style="display: none;">
                                    <label class="form-label">Available Variables</label>
                                    <div class="alert alert-info">
                                        <small>
                                            <strong>Template Variables:</strong><br>
                                            <span id="variablesList"></span>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <button type="button" class="btn btn-primary" onclick="loadTemplate()" id="loadBtn" disabled>Load Template</button>
                                    <button type="button" class="btn btn-success" onclick="saveTemplate()" id="saveBtn" disabled>Save Template</button>
                                    <button type="button" class="btn btn-info" onclick="previewTemplate()" id="previewBtn" disabled>Preview</button>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Template Content (HTML)</label>
                                    <textarea class="form-control" id="templateContent" rows="25" style="font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.4;" placeholder="Select a template to edit..."></textarea>
                                    <small class="form-text text-muted">Use {{variable_name}} for dynamic content. HTML is supported.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Template Preview Modal -->
        <div id="previewModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
            <div class="modal-content" style="position: relative; background: white; margin: 2% auto; padding: 20px; width: 90%; max-width: 900px; border-radius: 8px; max-height: 96vh; overflow-y: auto;">
                <div class="modal-header" style="border-bottom: 1px solid #ddd; padding-bottom: 15px; margin-bottom: 20px; position: sticky; top: 0; background: white; z-index: 1;">
                    <h5 class="modal-title">Template Preview</h5>
                    <button type="button" class="btn-close" onclick="closePreviewModal()" style="float: right; background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                </div>
                <div class="modal-body" style="overflow-y: auto;">
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" id="previewSubject" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Preview</label>
                        <div id="previewContent" style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9; min-height: 300px; max-height: 500px; overflow-y: auto;"></div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #ddd; padding-top: 15px; text-align: right; position: sticky; bottom: 0; background: white;">
                    <button type="button" class="btn btn-secondary" onclick="closePreviewModal()">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Test email configuration
function testEmailConfig() {
    fetch('email-management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax=1&action=test_email'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

// Save email configuration
function saveEmailConfig() {
    const form = document.getElementById('emailConfigForm');
    const formData = new FormData(form);
    
    // Convert FormData to URL-encoded string
    const data = new URLSearchParams();
    for (let [key, value] of formData.entries()) {
        data.append(key, value);
    }
    data.append('ajax', '1');
    data.append('action', 'save_email_config');
    
    fetch('email-management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

// Load email configuration
function loadEmailConfig() {
    fetch('email-management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax=1&action=get_email_config'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Populate form fields with current values
            document.querySelector('input[name="smtp_host"]').value = data.config.email_smtp_host || 'localhost';
            document.querySelector('input[name="smtp_port"]').value = data.config.email_smtp_port || '587';
            document.querySelector('input[name="smtp_username"]').value = data.config.email_smtp_username || '';
            document.querySelector('input[name="smtp_password"]').value = data.config.email_smtp_password || '';
            document.querySelector('select[name="smtp_encryption"]').value = data.config.email_smtp_encryption || 'tls';
            document.querySelector('input[name="from_email"]').value = data.config.email_from_email || 'noreply@cornerfield.local';
            document.querySelector('input[name="from_name"]').value = data.config.email_from_name || 'CornerField';
        }
    })
    .catch(error => {
        console.error('Error loading email config:', error);
    });
}

// Email configuration form submission
document.getElementById('emailConfigForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveEmailConfig();
});

// Send test email
document.getElementById('testEmailForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('ajax', '1');
    formData.append('action', 'send_test_email');
    
    fetch('email-management.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            this.reset();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
});

// Send template email
document.getElementById('templateEmailForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const template = formData.get('template');
    const user_name = formData.get('user_name');
    
    // Prepare template data
    const data = {
        user_name: user_name,
        username: 'testuser',
        email: formData.get('to_email'),
        login_url: window.location.origin + '/users/login.php'
    };
    
    formData.append('ajax', '1');
    formData.append('action', 'send_template_email');
    formData.append('data', JSON.stringify(data));
    
    fetch('email-management.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            this.reset();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
});

// Auto-refresh email stats every 30 seconds
setInterval(function() {
    fetch('email-management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax=1&action=get_email_stats'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update stats display
            location.reload();
        }
    });
}, 30000);

// Template Editor Functions
let currentTemplate = '';

function loadTemplate() {
    const template = document.getElementById('templateSelector').value;
    
    if (!template) {
        alert('Please select a template first');
        return;
    }
    
    currentTemplate = template;
    
    // Enable buttons
    document.getElementById('loadBtn').disabled = false;
    document.getElementById('saveBtn').disabled = false;
    document.getElementById('previewBtn').disabled = false;
    
    // Load template content
    fetch('email-management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax=1&action=get_template_content&template=' + encodeURIComponent(template)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('templateContent').value = data.content;
            
            // Show variables
            const variables = data.variables;
            let variablesHtml = '';
            for (const [key, desc] of Object.entries(variables)) {
                variablesHtml += `<code>{{${key}}}</code> - ${desc}<br>`;
            }
            document.getElementById('variablesList').innerHTML = variablesHtml;
            document.getElementById('templateVariables').style.display = 'block';
        } else {
            alert('Error loading template: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function saveTemplate() {
    if (!currentTemplate) {
        alert('Please select a template first');
        return;
    }
    
    const content = document.getElementById('templateContent').value;
    
    if (!content.trim()) {
        alert('Template content cannot be empty');
        return;
    }
    
    if (confirm('Are you sure you want to save this template? This will overwrite the existing template.')) {
        fetch('email-management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'ajax=1&action=save_template&template=' + encodeURIComponent(currentTemplate) + '&content=' + encodeURIComponent(content)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
}

function previewTemplate() {
    if (!currentTemplate) {
        alert('Please select a template first');
        return;
    }
    
    const content = document.getElementById('templateContent').value;
    if (!content.trim()) {
        alert('Template content cannot be empty');
        return;
    }
    
    // Preview with sample data
    fetch('email-management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax=1&action=preview_template&template=' + encodeURIComponent(currentTemplate) + '&custom_data={}'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('previewSubject').value = data.preview.subject;
            document.getElementById('previewContent').innerHTML = data.preview.message;
            
            // Show modal
            document.getElementById('previewModal').style.display = 'block';
        } else {
            alert('Error previewing template: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

// Close preview modal
function closePreviewModal() {
    document.getElementById('previewModal').style.display = 'none';
}

// Initialize template selector change event
document.getElementById('templateSelector').addEventListener('change', function() {
    const template = this.value;
    if (template) {
        loadTemplate();
    } else {
        // Reset form
        document.getElementById('templateContent').value = '';
        document.getElementById('templateVariables').style.display = 'none';
        document.getElementById('loadBtn').disabled = true;
        document.getElementById('saveBtn').disabled = true;
        document.getElementById('previewBtn').disabled = true;
        currentTemplate = '';
    }
});

// Load email configuration when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadEmailConfig();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
