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

$pageTitle = 'Support';
$currentPage = 'support';

include __DIR__ . '/includes/header.php';
?>

<style>
    .support-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .support-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: center;
        box-shadow: var(--shadow-lg);
    }

    .support-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .support-subtitle {
        font-size: 1rem;
        opacity: 0.9;
    }

    .support-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .contact-card {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 2rem;
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

    .contact-methods {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .contact-method {
        display: flex;
        align-items: center;
        padding: 1rem;
        background: var(--bg-secondary);
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .contact-method:hover {
        background: var(--bg-tertiary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }

    .contact-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        color: white;
        font-size: 1rem;
    }

    .contact-method.email .contact-icon { background: var(--primary-color); }
    .contact-method.phone .contact-icon { background: var(--success-color); }
    .contact-method.telegram .contact-icon { background: #0088cc; }
    .contact-method.whatsapp .contact-icon { background: #25d366; }

    .contact-info {
        flex: 1;
    }

    .contact-label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .contact-value {
        color: var(--text-secondary);
        font-size: 0.875rem;
    }

    .ticket-form {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
        margin-bottom: 2rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        display: block;
    }

    .form-input {
        background: var(--bg-primary);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 1rem;
        font-size: 1rem;
        width: 100%;
        transition: all 0.3s ease;
        color: var(--text-primary);
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .form-textarea {
        min-height: 120px;
        resize: vertical;
    }

    .form-select {
        background: var(--bg-primary);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 1rem;
        font-size: 1rem;
        width: 100%;
        color: var(--text-primary);
        transition: all 0.3s ease;
    }

    .form-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .submit-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: var(--radius);
        padding: 1rem 2rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
    }

    .submit-btn:hover {
        background: var(--primary-hover);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .faq-section {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
    }

    .faq-item {
        border-bottom: 1px solid var(--border-color);
        padding: 1rem 0;
    }

    .faq-item:last-child {
        border-bottom: none;
    }

    .faq-question {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .faq-question:hover {
        color: var(--primary-color);
    }

    .faq-answer {
        color: var(--text-secondary);
        font-size: 0.9rem;
        line-height: 1.6;
        display: none;
    }

    .faq-answer.show {
        display: block;
    }

    .faq-icon {
        transition: transform 0.3s ease;
    }

    .faq-item.active .faq-icon {
        transform: rotate(180deg);
    }

    @media (max-width: 768px) {
        .support-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="support-container">
    <!-- Support Header -->
    <div class="support-header gradient-bg">
        <h1 class="support-title">Need Help? We're Here for You! 🤝</h1>
        <p class="support-subtitle">Get in touch with our support team or find answers to common questions</p>
    </div>

    <!-- Contact & Ticket Form -->
    <div class="support-grid">
        <!-- Contact Information -->
        <div class="contact-card gradient-card">
            <h2 class="section-title gradient-text">
                <i class="fas fa-headset"></i>
                Contact Us
            </h2>
            
            <div class="contact-methods">
                <div class="contact-method email">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-info">
                        <div class="contact-label">Email Support</div>
                        <div class="contact-value">support@cornerfield.com</div>
                    </div>
                </div>

                <div class="contact-method phone">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="contact-info">
                        <div class="contact-label">Phone Support</div>
                        <div class="contact-value">+1 (555) 123-4567</div>
                    </div>
                </div>

                <div class="contact-method telegram">
                    <div class="contact-icon">
                        <i class="fab fa-telegram-plane"></i>
                    </div>
                    <div class="contact-info">
                        <div class="contact-label">Telegram</div>
                        <div class="contact-value">@CornerFieldSupport</div>
                    </div>
                </div>

                <div class="contact-method whatsapp">
                    <div class="contact-icon">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div class="contact-info">
                        <div class="contact-label">WhatsApp</div>
                        <div class="contact-value">+1 (555) 123-4567</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Support Ticket Form -->
        <div class="ticket-form gradient-card">
            <h2 class="section-title gradient-text">
                <i class="fas fa-ticket-alt"></i>
                Submit a Ticket
            </h2>
            
            <form id="supportForm">
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <input type="text" class="form-input" name="subject" placeholder="Brief description of your issue" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category" required>
                        <option value="">Select a category</option>
                        <option value="technical">Technical Issue</option>
                        <option value="account">Account Problem</option>
                        <option value="payment">Payment Issue</option>
                        <option value="investment">Investment Question</option>
                        <option value="withdrawal">Withdrawal Problem</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select class="form-select" name="priority" required>
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea class="form-input form-textarea" name="message" placeholder="Please describe your issue in detail..." required></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane me-2"></i>
                    Submit Ticket
                </button>
            </form>
        </div>
    </div>

    <!-- FAQ Section -->
    <div class="faq-section gradient-card">
        <h2 class="section-title gradient-text">
            <i class="fas fa-question-circle"></i>
            Frequently Asked Questions
        </h2>
        
        <div class="faq-list">
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>How do I make a deposit?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    To make a deposit, go to the Deposit page and select your preferred cryptocurrency. Enter the amount you want to deposit and follow the instructions to complete the transaction. Deposits are usually processed within 10-30 minutes.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>How long do withdrawals take?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Withdrawals are typically processed within 24 hours during business days. For cryptocurrency withdrawals, the transaction will be sent to the blockchain and may take additional time depending on network congestion.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>What are the investment plans available?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    We offer several investment plans with different daily returns and durations. You can view all available plans on the Invest page. Each plan has a minimum investment amount and specific terms.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>How does the referral program work?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Our referral program allows you to earn 5% commission on your referrals' investment profits. Share your referral link with friends, and you'll earn commissions for as long as they remain active investors.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Is my account secure?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Yes, we use industry-standard security measures including SSL encryption, secure password hashing, and regular security audits. Your funds and personal information are protected with the highest security standards.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Can I change my investment plan?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Once you start an investment, you cannot change the plan for that specific investment. However, you can make new investments with different plans at any time.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFAQ(element) {
    const faqItem = element.parentElement;
    const answer = faqItem.querySelector('.faq-answer');
    
    // Close all other FAQ items
    document.querySelectorAll('.faq-item').forEach(item => {
        if (item !== faqItem) {
            item.classList.remove('active');
            item.querySelector('.faq-answer').classList.remove('show');
        }
    });
    
    // Toggle current FAQ item
    faqItem.classList.toggle('active');
    answer.classList.toggle('show');
}

// Support form submission
document.getElementById('supportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const button = this.querySelector('button[type="submit"]');
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    button.disabled = true;
    
    // Simulate form submission
    setTimeout(() => {
        alert('Support ticket submitted successfully! We will get back to you within 24 hours.');
        this.reset();
        button.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Ticket';
        button.disabled = false;
    }, 2000);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
