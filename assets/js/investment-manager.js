class InvestmentManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadInvestmentPlans();
    }
    
    bindEvents() {
        // Investment form submission
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('investment-form')) {
                e.preventDefault();
                this.handleInvestmentSubmission(e.target);
            }
        });
        
        // Investment modal triggers
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('invest-btn') || e.target.closest('.invest-btn')) {
                e.preventDefault();
                const btn = e.target.classList.contains('invest-btn') ? e.target : e.target.closest('.invest-btn');
                this.openInvestmentModal(btn);
            }
        });
        
        // Amount input validation
        document.addEventListener('input', (e) => {
            if (e.target.name === 'investment_amount') {
                this.validateInvestmentAmount(e.target);
            }
        });
    }
    
    async loadInvestmentPlans() {
        try {
            // This would typically load from your existing dashboard data
            // For now, we'll work with the plans already rendered on the page
            this.updatePlanCards();
        } catch (error) {
            console.error('Error loading investment plans:', error);
        }
    }
    
    openInvestmentModal(button) {
        const planId = button.dataset.planId;
        const planName = button.dataset.planName;
        const minAmount = parseFloat(button.dataset.minAmount);
        const maxAmount = parseFloat(button.dataset.maxAmount);
        const dailyRate = parseFloat(button.dataset.dailyRate);
        
        // Get or create modal
        let modal = document.getElementById('investmentModal');
        if (!modal) {
            modal = this.createInvestmentModal();
        }
        
        // Update modal content
        this.updateModalContent(modal, {
            planId,
            planName,
            minAmount,
            maxAmount,
            dailyRate
        });
        
        // Show modal (assuming you're using Bootstrap or similar)
        if (window.bootstrap) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        } else {
            modal.style.display = 'block';
        }
    }
    
    createInvestmentModal() {
        const modalHtml = `
            <div class="modal fade" id="investmentModal" tabindex="-1" aria-labelledby="investmentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="investmentModalLabel">
                                <i class="ti ti-coin text-warning me-2"></i>Make Investment
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form class="investment-form" id="investmentForm">
                                <input type="hidden" name="schema_id" id="modal_plan_id">
                                
                                <div class="mb-3">
                                    <label class="form-label">Investment Plan</label>
                                    <div class="card border-warning">
                                        <div class="card-body py-2">
                                            <h6 class="card-title mb-1" id="modal_plan_name">Bitcoin Starter</h6>
                                            <small class="text-muted" id="modal_plan_details">2% daily for 30 days</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="investment_amount" class="form-label">Investment Amount ($)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="investment_amount" 
                                           name="amount" 
                                           placeholder="Enter amount"
                                           step="0.01"
                                           min="1"
                                           required>
                                    <div class="form-text">
                                        Min: $<span id="modal_min_amount">50</span> - Max: $<span id="modal_max_amount">999</span>
                                    </div>
                                    <div class="invalid-feedback" id="amount_error"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="row">
                                        <div class="col-6">
                                            <label class="form-label">Daily Profit</label>
                                            <div class="text-success fw-bold" id="modal_daily_profit">$0.00</div>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Total Return</label>
                                            <div class="text-primary fw-bold" id="modal_total_return">$0.00</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="ti ti-info-circle me-2"></i>
                                    Your investment will start earning daily profits within 24 hours.
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" form="investmentForm" class="btn btn-warning" id="invest_submit_btn">
                                <i class="ti ti-coins me-2"></i>Invest Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        return document.getElementById('investmentModal');
    }
    
    updateModalContent(modal, planData) {
        const { planId, planName, minAmount, maxAmount, dailyRate } = planData;
        
        // Update form fields
        modal.querySelector('#modal_plan_id').value = planId;
        modal.querySelector('#modal_plan_name').textContent = planName;
        modal.querySelector('#modal_plan_details').textContent = `${dailyRate}% daily returns`;
        modal.querySelector('#modal_min_amount').textContent = minAmount.toLocaleString();
        modal.querySelector('#modal_max_amount').textContent = maxAmount.toLocaleString();
        
        // Set input limits
        const amountInput = modal.querySelector('#investment_amount');
        amountInput.min = minAmount;
        amountInput.max = maxAmount;
        amountInput.value = '';
        
        // Clear previous calculations
        modal.querySelector('#modal_daily_profit').textContent = '$0.00';
        modal.querySelector('#modal_total_return').textContent = '$0.00';
        
        // Store plan data for calculations
        this.currentPlan = planData;
    }
    
    validateInvestmentAmount(input) {
        const amount = parseFloat(input.value);
        const minAmount = parseFloat(input.min);
        const maxAmount = parseFloat(input.max);
        const errorDiv = document.getElementById('amount_error');
        
        // Clear previous errors
        input.classList.remove('is-invalid');
        errorDiv.textContent = '';
        
        if (isNaN(amount) || amount <= 0) {
            this.clearCalculations();
            return false;
        }
        
        if (amount < minAmount) {
            input.classList.add('is-invalid');
            errorDiv.textContent = `Minimum investment is ${minAmount.toLocaleString()}`;
            this.clearCalculations();
            return false;
        }
        
        if (amount > maxAmount) {
            input.classList.add('is-invalid');
            errorDiv.textContent = `Maximum investment is ${maxAmount.toLocaleString()}`;
            this.clearCalculations();
            return false;
        }
        
        // Calculate and display profits
        this.calculateProfits(amount);
        return true;
    }
    
    calculateProfits(amount) {
        if (!this.currentPlan) return;
        
        const dailyRate = this.currentPlan.dailyRate;
        const dailyProfit = (amount * dailyRate) / 100;
        
        // Get duration from plan data (you might need to add this to your plan data)
        const duration = this.getDurationFromPlan(this.currentPlan.planName);
        const totalReturn = dailyProfit * duration;
        
        // Update display
        document.getElementById('modal_daily_profit').textContent = `${dailyProfit.toFixed(2)}`;
        document.getElementById('modal_total_return').textContent = `${totalReturn.toFixed(2)}`;
    }
    
    getDurationFromPlan(planName) {
        // Map plan names to durations based on your database
        const durations = {
            'Bitcoin Starter': 30,
            'Crypto Silver': 45,
            'Digital Gold': 60,
            'Cornerfield Elite': 90
        };
        return durations[planName] || 30;
    }
    
    clearCalculations() {
        document.getElementById('modal_daily_profit').textContent = '$0.00';
        document.getElementById('modal_total_return').textContent = '$0.00';
    }
    
    async handleInvestmentSubmission(form) {
        const submitBtn = document.getElementById('invest_submit_btn');
        const originalText = submitBtn.innerHTML;
        
        try {
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            
            // Get form data
            const formData = new FormData(form);
            const investmentData = {
                schema_id: formData.get('schema_id'),
                amount: parseFloat(formData.get('amount'))
            };
            
            // Validate data
            if (!investmentData.schema_id || !investmentData.amount) {
                throw new Error('Please fill in all required fields');
            }
            
            // Submit investment
            const response = await fetch('invest.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(investmentData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.handleInvestmentSuccess(result);
            } else {
                this.handleInvestmentError(result.message);
            }
            
        } catch (error) {
            console.error('Investment submission error:', error);
            this.handleInvestmentError(error.message || 'An unexpected error occurred');
        } finally {
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
    
    handleInvestmentSuccess(result) {
        // Close modal
        const modal = document.getElementById('investmentModal');
        if (window.bootstrap) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            bsModal.hide();
        } else {
            modal.style.display = 'none';
        }
        
        // Show success message
        this.showNotification('success', 'Investment Created Successfully!', 
            `Your investment of ${result.data.invested_amount} in ${result.data.plan_name} has been created. You'll start earning ${result.data.daily_profit} daily.`);
        
        // Update dashboard data
        this.updateDashboardData(result.data);
        
        // Reset form
        document.getElementById('investmentForm').reset();
    }
    
    handleInvestmentError(message) {
        this.showNotification('error', 'Investment Failed', message);
    }
    
    showNotification(type, title, message) {
        // Create notification element
        const notificationHtml = `
            <div class="alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show" role="alert">
                <strong>${title}</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Find notification container or create one
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.className = 'position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        
        container.insertAdjacentHTML('beforeend', notificationHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            const alerts = container.querySelectorAll('.alert');
            if (alerts.length > 0) {
                alerts[0].remove();
            }
        }, 5000);
    }
    
    updateDashboardData(investmentData) {
        // Update balance display
        const balanceElement = document.getElementById('user-balance');
        if (balanceElement) {
            balanceElement.textContent = `${investmentData.new_balance.toFixed(2)}`;
        }
        
        // Update total invested
        const totalInvestedElement = document.getElementById('total-invested');
        if (totalInvestedElement) {
            const currentInvested = parseFloat(totalInvestedElement.textContent.replace(/[^0-9.-]+/g, ''));
            const newTotal = currentInvested + investmentData.invested_amount;
            totalInvestedElement.textContent = `${newTotal.toFixed(2)}`;
        }
        
        // Update active investments count
        const activeInvestmentsElement = document.getElementById('active-investments');
        if (activeInvestmentsElement) {
            const currentCount = parseInt(activeInvestmentsElement.textContent) || 0;
            activeInvestmentsElement.textContent = currentCount + 1;
        }
        
        // Refresh investments table if it exists
        this.refreshInvestmentsTable();
    }
    
    refreshInvestmentsTable() {
        // This would refresh the investments table on the dashboard
        // Implementation depends on how your dashboard displays investments
        const investmentsTable = document.getElementById('investments-table');
        if (investmentsTable) {
            // You might want to reload this section via AJAX
            // For now, we'll just add a reload prompt
            location.reload();
        }
    }
    
    updatePlanCards() {
        // Add click handlers to existing investment plan cards
        const investButtons = document.querySelectorAll('.invest-btn');
        investButtons.forEach(button => {
            // Ensure data attributes are set
            if (!button.dataset.planId) {
                const card = button.closest('.card');
                if (card) {
                    this.extractPlanDataFromCard(button, card);
                }
            }
        });
    }
    
    extractPlanDataFromCard(button, card) {
        // Extract plan data from the card HTML
        // This is a fallback for cases where data attributes aren't set
        const planTitle = card.querySelector('.card-title, h5, h6')?.textContent?.trim();
        const dailyRateText = card.querySelector('.text-success, .daily-rate')?.textContent;
        const amountRangeText = card.querySelector('.amount-range, .text-muted')?.textContent;
        
        if (planTitle) {
            button.dataset.planName = planTitle;
            
            // Extract daily rate
            const rateMatch = dailyRateText?.match(/(\d+\.?\d*)%/);
            if (rateMatch) {
                button.dataset.dailyRate = rateMatch[1];
            }
            
            // Extract amount range
            const rangeMatch = amountRangeText?.match(/\$?(\d+(?:,\d{3})*(?:\.\d{2})?)\s*-?\s*\$?(\d+(?:,\d{3})*(?:\.\d{2})?)/);
            if (rangeMatch) {
                button.dataset.minAmount = rangeMatch[1].replace(/,/g, '');
                button.dataset.maxAmount = rangeMatch[2].replace(/,/g, '');
            }
        }
    }
    
    // Utility method to format currency
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.investmentManager = new InvestmentManager();
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = InvestmentManager;
}