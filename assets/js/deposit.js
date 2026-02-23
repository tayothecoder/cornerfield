/**
 * Cornerfield Investment Platform
 * File: assets/js/deposit.js
 * Purpose: Deposit page interactivity and AJAX handling
 * Security Level: PUBLIC
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

class DepositManager {
    constructor() {
        this.selectedMethod = null;
        this.currentFee = 0;
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadDepositHistory();
        this.setupFileUpload();
    }

    bindEvents() {
        // Method selection
        document.querySelectorAll('.method-card').forEach(card => {
            card.addEventListener('click', (e) => this.selectMethod(e.target.closest('.method-card')));
        });

        // Form events
        const amountInput = document.getElementById('amount');
        const currencySelect = document.getElementById('currency');
        const networkSelect = document.getElementById('network');
        const depositForm = document.getElementById('depositForm');

        if (amountInput) {
            amountInput.addEventListener('input', debounce(() => this.calculateFee(), 300));
        }

        if (currencySelect) {
            currencySelect.addEventListener('change', () => this.updateNetworks());
        }

        if (networkSelect) {
            networkSelect.addEventListener('change', () => this.updateDepositAddress());
        }

        if (depositForm) {
            depositForm.addEventListener('submit', (e) => this.handleSubmit(e));
        }

        // Cancel button
        const cancelBtn = document.getElementById('cancelBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.resetForm());
        }
    }

    selectMethod(methodCard) {
        // Remove active state from all cards
        document.querySelectorAll('.method-card').forEach(card => {
            card.classList.remove('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900');
            card.classList.add('border-gray-200', 'dark:border-gray-600');
        });

        // Add active state to selected card
        methodCard.classList.remove('border-gray-200', 'dark:border-gray-600');
        methodCard.classList.add('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900');

        // Store selected method data
        this.selectedMethod = {
            id: parseInt(methodCard.dataset.methodId),
            name: methodCard.dataset.methodName,
            type: methodCard.dataset.methodType,
            minAmount: parseFloat(methodCard.dataset.minAmount),
            maxAmount: parseFloat(methodCard.dataset.maxAmount),
            feeType: methodCard.dataset.feeType,
            feeRate: parseFloat(methodCard.dataset.feeRate)
        };

        // Update form
        this.showDepositForm();
    }

    showDepositForm() {
        const formContainer = document.getElementById('depositFormContainer');
        const selectedMethodName = document.getElementById('selectedMethodName');
        const selectedMethodId = document.getElementById('selectedMethodId');
        const amountLimits = document.getElementById('amountLimits');
        const amountInput = document.getElementById('amount');

        if (!formContainer || !this.selectedMethod) return;

        // Update form visibility and data
        formContainer.style.display = 'block';
        selectedMethodName.textContent = `Deposit via ${this.selectedMethod.name}`;
        selectedMethodId.value = this.selectedMethod.id;
        
        // Update amount limits
        amountLimits.textContent = `Min: $${this.selectedMethod.minAmount.toFixed(2)} • Max: $${this.selectedMethod.maxAmount.toLocaleString()}`;
        
        // Set amount input constraints
        amountInput.min = this.selectedMethod.minAmount.toString();
        amountInput.max = this.selectedMethod.maxAmount.toString();

        // Show/hide relevant sections based on method type
        if (this.selectedMethod.type === 'manual') {
            document.getElementById('currencySection').style.display = 'block';
            document.getElementById('manualFields').style.display = 'block';
            document.getElementById('autoDepositInfo').style.display = 'none';
        } else {
            document.getElementById('currencySection').style.display = 'none';
            document.getElementById('manualFields').style.display = 'none';
            document.getElementById('autoDepositInfo').style.display = 'block';
        }

        // Scroll to form
        formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    calculateFee() {
        const amountInput = document.getElementById('amount');
        const amount = parseFloat(amountInput.value) || 0;

        if (!this.selectedMethod || amount <= 0) {
            document.getElementById('feeCalculation').style.display = 'none';
            return;
        }

        // Calculate fee
        let fee = 0;
        if (this.selectedMethod.feeType === 'percentage') {
            fee = amount * (this.selectedMethod.feeRate / 100);
        } else {
            fee = this.selectedMethod.feeRate;
        }

        const netAmount = amount - fee;
        this.currentFee = fee;

        // Update display
        document.getElementById('displayAmount').textContent = `$${amount.toFixed(2)}`;
        document.getElementById('displayFee').textContent = `$${fee.toFixed(2)}`;
        document.getElementById('displayNetAmount').textContent = `$${netAmount.toFixed(2)}`;
        document.getElementById('feeCalculation').style.display = 'block';

        // Validate amount against limits
        this.validateAmount(amount);
    }

    validateAmount(amount) {
        const amountInput = document.getElementById('amount');
        const submitBtn = document.getElementById('submitBtn');
        
        let isValid = true;
        let errorMessage = '';

        if (amount < this.selectedMethod.minAmount) {
            errorMessage = `Minimum amount is $${this.selectedMethod.minAmount.toFixed(2)}`;
            isValid = false;
        } else if (amount > this.selectedMethod.maxAmount) {
            errorMessage = `Maximum amount is $${this.selectedMethod.maxAmount.toLocaleString()}`;
            isValid = false;
        }

        // Update UI
        if (isValid) {
            amountInput.classList.remove('border-red-300', 'focus:ring-red-500', 'focus:border-red-500');
            amountInput.classList.add('border-gray-300', 'focus:ring-primary-500', 'focus:border-primary-500');
            submitBtn.disabled = false;
        } else {
            amountInput.classList.remove('border-gray-300', 'focus:ring-primary-500', 'focus:border-primary-500');
            amountInput.classList.add('border-red-300', 'focus:ring-red-500', 'focus:border-red-500');
            submitBtn.disabled = true;
        }

        // Show/hide error message
        let errorDiv = document.getElementById('amountError');
        if (errorMessage) {
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.id = 'amountError';
                errorDiv.className = 'mt-1 text-sm text-red-600';
                amountInput.parentNode.appendChild(errorDiv);
            }
            errorDiv.textContent = errorMessage;
        } else if (errorDiv) {
            errorDiv.remove();
        }
    }

    updateNetworks() {
        const currency = document.getElementById('currency').value;
        const networkSelect = document.getElementById('network');
        
        // Clear existing options
        networkSelect.innerHTML = '';
        
        // Define networks for each currency
        const networks = {
            'BTC': [{ value: 'BTC', label: 'Bitcoin Network' }],
            'ETH': [{ value: 'ERC20', label: 'Ethereum (ERC20)' }],
            'USDT': [
                { value: 'TRC20', label: 'Tron (TRC20)' },
                { value: 'ERC20', label: 'Ethereum (ERC20)' },
                { value: 'BEP20', label: 'BSC (BEP20)' }
            ],
            'LTC': [{ value: 'LTC', label: 'Litecoin Network' }]
        };

        const currencyNetworks = networks[currency] || [];
        currencyNetworks.forEach(network => {
            const option = document.createElement('option');
            option.value = network.value;
            option.textContent = network.label;
            networkSelect.appendChild(option);
        });

        // Select first option by default
        if (currencyNetworks.length > 0) {
            networkSelect.value = currencyNetworks[0].value;
            this.updateDepositAddress();
        }
    }

    updateDepositAddress() {
        if (this.selectedMethod?.type !== 'manual') return;

        const currency = document.getElementById('currency').value;
        const network = document.getElementById('network').value;

        if (!currency || !network) return;

        // For manual deposits, we would generate/get deposit address
        // This is a placeholder - in production, this would call the backend
        const depositAddressSection = document.getElementById('depositAddressSection');
        const displayDepositAddress = document.getElementById('displayDepositAddress');
        
        // Generate a placeholder address based on currency
        let address = '';
        switch (currency) {
            case 'BTC':
                address = '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa';
                break;
            case 'ETH':
            case 'USDT':
                address = '0x' + Array(40).fill(0).map(() => Math.floor(Math.random() * 16).toString(16)).join('');
                break;
            case 'LTC':
                address = 'L' + Array(33).fill(0).map(() => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'[Math.floor(Math.random() * 62)]).join('');
                break;
            default:
                address = 'Address will be provided after form submission';
        }

        displayDepositAddress.textContent = address;
        depositAddressSection.style.display = 'block';

        // Setup copy functionality
        document.getElementById('copyAddressBtn').onclick = () => {
            navigator.clipboard.writeText(address).then(() => {
                this.showNotification('Address copied to clipboard!', 'success');
            });
        };
    }

    setupFileUpload() {
        const fileInput = document.getElementById('proofOfPayment');
        if (!fileInput) return;

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            const fileNameDiv = document.getElementById('uploadedFileName');
            
            if (file) {
                // Validate file
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                const maxSize = 5 * 1024 * 1024; // 5MB

                if (!validTypes.includes(file.type)) {
                    this.showNotification('Invalid file type. Please upload an image or PDF.', 'error');
                    fileInput.value = '';
                    return;
                }

                if (file.size > maxSize) {
                    this.showNotification('File too large. Maximum size is 5MB.', 'error');
                    fileInput.value = '';
                    return;
                }

                fileNameDiv.textContent = `Selected: ${file.name}`;
                fileNameDiv.style.display = 'block';
            } else {
                fileNameDiv.style.display = 'none';
            }
        });
    }

    async handleSubmit(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');
        const submitSpinner = document.getElementById('submitSpinner');
        
        // Disable submit button
        submitBtn.disabled = true;
        submitText.textContent = 'Processing...';
        submitSpinner.classList.remove('hidden');

        try {
            const formData = new FormData(e.target);
            
            const response = await fetch('deposit.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccessModal(result.data);
                this.resetForm();
                this.loadDepositHistory();
            } else {
                this.showNotification(result.error || 'Failed to create deposit', 'error');
            }
        } catch (error) {
            console.error('Deposit creation error:', error);
            this.showNotification('Network error. Please try again.', 'error');
        } finally {
            // Re-enable submit button
            submitBtn.disabled = false;
            submitText.textContent = 'Create Deposit';
            submitSpinner.classList.add('hidden');
        }
    }

    async loadDepositHistory() {
        const tbody = document.getElementById('depositHistory');
        if (!tbody) return;

        try {
            const response = await fetch('deposit.php?action=history', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            if (result.success) {
                this.renderDepositHistory(result.data.deposits);
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-sm text-red-600">Failed to load deposit history</td></tr>';
            }
        } catch (error) {
            console.error('Failed to load deposit history:', error);
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-sm text-red-600">Error loading deposit history</td></tr>';
        }
    }

    renderDepositHistory(deposits) {
        const tbody = document.getElementById('depositHistory');
        if (!tbody) return;

        if (deposits.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No deposits found</td></tr>';
            return;
        }

        tbody.innerHTML = deposits.map(deposit => `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">${deposit.formatted_date}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">${deposit.method_name || 'N/A'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-300">${deposit.formatted_amount}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${deposit.formatted_fee}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getStatusClasses(deposit.status)}">
                        ${deposit.status.charAt(0).toUpperCase() + deposit.status.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button type="button" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300" onclick="showDepositDetails(${deposit.id})">
                        View
                    </button>
                </td>
            </tr>
        `).join('');
    }

    getStatusClasses(status) {
        const classes = {
            'pending': 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
            'processing': 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
            'completed': 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
            'rejected': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            'failed': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            'cancelled': 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
            'expired': 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
        };
        return classes[status] || classes['pending'];
    }

    showSuccessModal(data) {
        const modal = document.getElementById('successModal');
        const message = document.getElementById('successMessage');
        
        if (data.deposit_address) {
            message.innerHTML = `Your deposit request has been created successfully!<br><br>
                <strong>Deposit Address:</strong><br>
                <code class="bg-gray-100 p-2 rounded text-sm break-all">${data.deposit_address}</code><br><br>
                Please send your payment to the address above. Your account will be credited automatically once the transaction is confirmed.`;
        } else {
            message.textContent = data.message || 'Your deposit request has been created successfully!';
        }
        
        modal.classList.remove('hidden');
    }

    resetForm() {
        // Hide form
        document.getElementById('depositFormContainer').style.display = 'none';
        
        // Clear selection
        document.querySelectorAll('.method-card').forEach(card => {
            card.classList.remove('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900');
            card.classList.add('border-gray-200', 'dark:border-gray-600');
        });
        
        // Reset form data
        document.getElementById('depositForm').reset();
        document.getElementById('feeCalculation').style.display = 'none';
        document.getElementById('uploadedFileName').style.display = 'none';
        
        this.selectedMethod = null;
        this.currentFee = 0;
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 max-w-sm w-full bg-white shadow-lg rounded-lg border-l-4 p-4 z-50 transform transition-transform duration-300 translate-x-full ${
            type === 'success' ? 'border-green-500' : 
            type === 'error' ? 'border-red-500' : 
            type === 'warning' ? 'border-yellow-500' : 'border-blue-500'
        }`;
        
        notification.innerHTML = `
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 ${
                        type === 'success' ? 'text-green-400' : 
                        type === 'error' ? 'text-red-400' : 
                        type === 'warning' ? 'text-yellow-400' : 'text-blue-400'
                    }" fill="currentColor" viewBox="0 0 20 20">
                        ${type === 'success' ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>' : 
                         type === 'error' ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>' :
                         '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>'}
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-900">${message}</p>
                </div>
                <div class="ml-auto pl-3">
                    <div class="-mx-1.5 -my-1.5">
                        <button type="button" class="inline-flex bg-white rounded-md p-1.5 text-gray-400 hover:text-gray-500" onclick="this.parentElement.parentElement.parentElement.parentElement.remove()">
                            <span class="sr-only">Dismiss</span>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function closeSuccessModal() {
    document.getElementById('successModal').classList.add('hidden');
}

function showDepositDetails(depositId) {
    // Placeholder for deposit details modal
    
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new DepositManager();
});