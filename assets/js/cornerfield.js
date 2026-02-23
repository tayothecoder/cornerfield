/**
 * Cornerfield Investment Platform
 * File: assets/js/cornerfield.js
 * Purpose: Main JavaScript functionality - theme toggle, validation, CSRF, notifications
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

'use strict';

/**
 * Cornerfield Main Application
 */
class CornerfieldApp {
    constructor() {
        this.theme = localStorage.getItem('cf-theme') || localStorage.getItem('theme') || 'light';
        this.init();
    }

    /**
     * Initialize the application
     */
    init() {
        this.setTheme(this.theme);
        this.initThemeToggle();
        this.initNotifications();
        this.initFormHelpers();
        this.initCSRFHandling();
        
    }

    /**
     * Theme Management
     */
    setTheme(theme) {
        this.theme = theme;
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('cf-theme', theme);
        // sync with tailwind dark class and localStorage.theme
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        localStorage.setItem('theme', theme);
        
        // Update theme toggle icon if it exists
        const toggleIcon = document.querySelector('.cf-theme-toggle-icon');
        if (toggleIcon) {
            toggleIcon.innerHTML = theme === 'dark' ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
        }
    }

    toggleTheme() {
        const newTheme = this.theme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
        
        // Show notification
        this.showNotification(
            `Switched to ${newTheme} mode`,
            'info',
            2000
        );
    }

    initThemeToggle() {
        // Create theme toggle button if it doesn't exist
        let themeToggle = document.querySelector('.cf-theme-toggle');
        
        if (!themeToggle) {
            themeToggle = document.createElement('button');
            themeToggle.className = 'cf-theme-toggle';
            themeToggle.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:50;background:none;border:none;cursor:pointer;padding:0.5rem;opacity:0.6;transition:opacity 0.2s';
            themeToggle.onmouseenter = () => themeToggle.style.opacity = '1';
            themeToggle.onmouseleave = () => themeToggle.style.opacity = '0.6';
            const sunSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
            const moonSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
            themeToggle.innerHTML = `<span class="cf-theme-toggle-icon">${this.theme === 'dark' ? sunSvg : moonSvg}</span>`;
            themeToggle.setAttribute('aria-label', 'Toggle theme');
            themeToggle.setAttribute('title', 'Toggle dark/light mode');
            document.body.appendChild(themeToggle);
        }

        themeToggle.addEventListener('click', () => this.toggleTheme());
    }

    /**
     * Notification System
     */
    initNotifications() {
        // Create notification container if it doesn't exist
        if (!document.querySelector('.cf-notifications')) {
            const container = document.createElement('div');
            container.className = 'cf-notifications';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
            `;
            document.body.appendChild(container);
        }
    }

    showNotification(message, type = 'info', duration = 5000) {
        const container = document.querySelector('.cf-notifications');
        if (!container) return;

        const notification = document.createElement('div');
        notification.className = `cf-notification cf-notification-${type}`;
        notification.style.cssText = `
            background: var(--cf-${type === 'error' ? 'danger' : type}-50);
            color: var(--cf-${type === 'error' ? 'danger' : type});
            border: 1px solid var(--cf-${type === 'error' ? 'danger' : type});
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            max-width: 320px;
            box-shadow: var(--cf-shadow-lg);
            backdrop-filter: blur(10px);
            animation: slideInRight 0.3s ease-out;
            pointer-events: auto;
            cursor: pointer;
            position: relative;
        `;

        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '×';
        closeBtn.style.cssText = `
            position: absolute;
            top: 4px;
            right: 8px;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: currentColor;
            opacity: 0.7;
        `;

        closeBtn.addEventListener('click', () => this.removeNotification(notification));
        
        notification.innerHTML = message;
        notification.appendChild(closeBtn);
        container.appendChild(notification);

        // Auto-remove notification
        if (duration > 0) {
            setTimeout(() => this.removeNotification(notification), duration);
        }

        // Add click to dismiss
        notification.addEventListener('click', () => this.removeNotification(notification));

        // Add animation styles if not already added
        if (!document.querySelector('#cf-notification-styles')) {
            const style = document.createElement('style');
            style.id = 'cf-notification-styles';
            style.textContent = `
                @keyframes slideInRight {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOutRight {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
                
                .cf-notification-removing {
                    animation: slideOutRight 0.3s ease-out forwards;
                }
            `;
            document.head.appendChild(style);
        }
    }

    removeNotification(notification) {
        if (!notification.parentNode) return;
        
        notification.classList.add('cf-notification-removing');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    /**
     * Form Validation Helpers
     */
    initFormHelpers() {
        // Enhanced form validation
        document.addEventListener('input', (e) => {
            if (e.target.matches('input[type="email"]')) {
                this.validateEmail(e.target);
            } else if (e.target.matches('input[name="username"]')) {
                this.validateUsername(e.target);
            } else if (e.target.matches('input[type="password"]')) {
                this.validatePassword(e.target);
            } else if (e.target.matches('input[name="confirm_password"]')) {
                this.validatePasswordConfirmation(e.target);
            }
        });

        // Form submission enhancements
        document.addEventListener('submit', (e) => {
            if (e.target.matches('form[data-cf-validate]')) {
                if (!this.validateForm(e.target)) {
                    e.preventDefault();
                }
            }
        });
    }

    validateEmail(input) {
        const email = input.value.trim();
        const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        
        this.setFieldValidation(input, isValid, 'Please enter a valid email address');
        return isValid;
    }

    validateUsername(input) {
        const username = input.value.trim();
        const isValid = /^[a-zA-Z0-9_]{3,20}$/.test(username);
        
        this.setFieldValidation(
            input, 
            isValid, 
            'Username must be 3-20 characters, letters, numbers and underscores only'
        );
        return isValid;
    }

    validatePassword(input) {
        // skip strict validation on login page — server handles auth
        const path = window.location.pathname;
        if (path.endsWith('login.php') || path.endsWith('forgot-password.php')) {
            this.setFieldValidation(input, true, '');
            return true;
        }

        const password = input.value;
        const minLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSymbol = /[^a-zA-Z0-9]/.test(password);
        
        const isValid = minLength && hasUpper && hasLower && hasNumber && hasSymbol;
        
        this.setFieldValidation(
            input, 
            isValid, 
            'Password must be at least 8 characters with mixed case, numbers, and symbols'
        );

        // Update password strength if indicator exists
        const strengthIndicator = document.getElementById('password-strength');
        if (strengthIndicator) {
            this.updatePasswordStrength(password, strengthIndicator);
        }

        return isValid;
    }

    validatePasswordConfirmation(input) {
        const password = document.querySelector('input[name="password"]')?.value || '';
        const confirmPassword = input.value;
        const isValid = password === confirmPassword;
        
        this.setFieldValidation(input, isValid, 'Passwords do not match');
        return isValid;
    }

    setFieldValidation(input, isValid, errorMessage) {
        const errorElement = document.getElementById(input.name + '-error');
        
        if (isValid) {
            input.classList.remove('cf-form-input-error');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
        } else if (input.value.length > 0) {
            input.classList.add('cf-form-input-error');
            if (errorElement) {
                errorElement.textContent = errorMessage;
                errorElement.style.display = 'block';
            }
        }
    }

    updatePasswordStrength(password, container) {
        if (!password) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';

        let score = 0;
        let feedback = [];

        // Check criteria
        if (password.length >= 8) score += 1;
        else feedback.push('8+ characters');

        if (/[A-Z]/.test(password)) score += 1;
        else feedback.push('uppercase letter');

        if (/[a-z]/.test(password)) score += 1;
        else feedback.push('lowercase letter');

        if (/[0-9]/.test(password)) score += 1;
        else feedback.push('number');

        if (/[^a-zA-Z0-9]/.test(password)) score += 1;
        else feedback.push('special character');

        const percentage = (score / 5) * 100;
        const strengthFill = container.querySelector('.cf-strength-fill');
        const strengthText = container.querySelector('.cf-strength-text');

        if (strengthFill) {
            strengthFill.style.width = percentage + '%';

            // Update strength class
            strengthFill.className = 'cf-strength-fill';
            if (score >= 5) strengthFill.classList.add('cf-strength-strong');
            else if (score >= 3) strengthFill.classList.add('cf-strength-medium');
            else strengthFill.classList.add('cf-strength-weak');
        }

        if (strengthText) {
            if (score >= 5) {
                strengthText.textContent = 'Strong password!';
                strengthText.className = 'cf-strength-text cf-strength-strong';
            } else {
                strengthText.textContent = 'Add: ' + feedback.join(', ');
                strengthText.className = 'cf-strength-text cf-strength-' + (score >= 3 ? 'medium' : 'weak');
            }
        }
    }

    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required]');

        inputs.forEach(input => {
            if (!input.value.trim()) {
                this.setFieldValidation(input, false, 'This field is required');
                isValid = false;
            }
        });

        return isValid;
    }

    /**
     * CSRF Token Management
     */
    initCSRFHandling() {
        // Add CSRF token to all AJAX requests
        const originalFetch = window.fetch;
        window.fetch = (...args) => {
            const [url, options = {}] = args;
            
            // Add CSRF token to POST/PUT/PATCH/DELETE requests
            if (options.method && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method.toUpperCase())) {
                const csrfToken = this.getCSRFToken();
                if (csrfToken) {
                    options.headers = {
                        ...options.headers,
                        'X-CSRF-Token': csrfToken
                    };
                }
            }

            return originalFetch.call(window, url, options);
        };
    }

    getCSRFToken() {
        // Try to get from meta tag first
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            return metaToken.getAttribute('content');
        }

        // Try to get from hidden input
        const inputToken = document.querySelector('input[name="csrf_token"]');
        if (inputToken) {
            return inputToken.value;
        }

        return null;
    }

    /**
     * Utility Functions
     */
    formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 8
        }).format(amount);
    }

    formatNumber(number, decimals = 2) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    formatDate(date, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        return new Intl.DateTimeFormat('en-US', { ...defaultOptions, ...options }).format(new Date(date));
    }

    /**
     * API Helper Functions
     */
    async apiRequest(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers
            }
        };

        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            }
            
            return await response.text();
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    /**
     * Local Storage Helpers
     */
    setStorage(key, value) {
        try {
            localStorage.setItem(`cf_${key}`, JSON.stringify(value));
            return true;
        } catch (error) {
            console.error('Failed to save to localStorage:', error);
            return false;
        }
    }

    getStorage(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(`cf_${key}`);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.error('Failed to read from localStorage:', error);
            return defaultValue;
        }
    }

    removeStorage(key) {
        try {
            localStorage.removeItem(`cf_${key}`);
            return true;
        } catch (error) {
            console.error('Failed to remove from localStorage:', error);
            return false;
        }
    }
}

/**
 * Global Helper Functions
 */

// Password toggle functionality
function togglePassword(targetId) {
    const passwordInput = document.getElementById(targetId);
    const toggleIcon = document.getElementById(targetId + '-icon');
    
    if (!passwordInput) return;
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        if (toggleIcon) toggleIcon.className = 'cf-icon-eye-slash';
    } else {
        passwordInput.type = 'password';
        if (toggleIcon) toggleIcon.className = 'cf-icon-eye';
    }
}

// Copy to clipboard
function copyToClipboard(text, successMessage = 'Copied to clipboard!') {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            window.cf.showNotification(successMessage, 'success', 2000);
        }).catch(err => {
            console.error('Failed to copy: ', err);
            fallbackCopyTextToClipboard(text, successMessage);
        });
    } else {
        fallbackCopyTextToClipboard(text, successMessage);
    }
}

function fallbackCopyTextToClipboard(text, successMessage) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.top = '0';
    textArea.style.left = '0';
    textArea.style.position = 'fixed';
    textArea.style.opacity = '0';
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        window.cf.showNotification(successMessage, 'success', 2000);
    } catch (err) {
        console.error('Fallback copy failed: ', err);
        window.cf.showNotification('Failed to copy to clipboard', 'error', 3000);
    }
    
    document.body.removeChild(textArea);
}

// Debounce function
function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            timeout = null;
            if (!immediate) func(...args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func(...args);
    };
}

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Initialize the main application
    window.cf = new CornerfieldApp();
    
    // Initialize common functionality
    initCommonFeatures();
});

function initCommonFeatures() {
    // Auto-hide flash messages
    const flashMessages = document.querySelectorAll('.cf-alert');
    flashMessages.forEach(message => {
        setTimeout(() => {
            if (message.parentNode) {
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.remove();
                    }
                }, 300);
            }
        }, 5000);
    });

    // Initialize tooltips (simple implementation)
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });

    // Initialize confirm dialogs
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'cf-tooltip';
    tooltip.textContent = e.target.getAttribute('data-tooltip');
    tooltip.style.cssText = `
        position: absolute;
        background: var(--cf-gray-900);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 10000;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s ease;
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
    
    setTimeout(() => tooltip.style.opacity = '1', 10);
    
    e.target._tooltip = tooltip;
}

function hideTooltip(e) {
    if (e.target._tooltip) {
        e.target._tooltip.remove();
        delete e.target._tooltip;
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { CornerfieldApp, togglePassword, copyToClipboard, debounce };
}