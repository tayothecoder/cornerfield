/**
 * Cornerfield Investment Platform
 * File: assets/js/withdraw.js
 * Purpose: Withdrawal page interactivity and AJAX handling
 */

(function() {
    const form = document.getElementById('withdrawalForm');
    const amountInput = document.getElementById('amount');
    const feeCalc = document.getElementById('feeCalculation');
    const feeRate = 5.0;

    function calculateFee() {
        const amount = parseFloat(amountInput.value) || 0;
        if (amount <= 0) {
            feeCalc.style.display = 'none';
            return;
        }
        feeCalc.style.display = 'block';
        const fee = amount * (feeRate / 100);
        const net = amount - fee;
        const total = amount;
        document.getElementById('displayAmount').textContent = '$' + amount.toFixed(2);
        document.getElementById('displayFee').textContent = '$' + fee.toFixed(2);
        document.getElementById('displayTotalDeduction').textContent = '$' + total.toFixed(2);
        document.getElementById('displayNetAmount').textContent = '$' + net.toFixed(2);
    }

    if (amountInput) {
        amountInput.addEventListener('input', calculateFee);
    }

    // confirmation modal handling
    let pendingFormData = null;

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            // show confirmation modal with details
            const amount = parseFloat(amountInput.value) || 0;
            const fee = amount * (feeRate / 100);
            document.getElementById('confirmAmount').textContent = '$' + amount.toFixed(2);
            document.getElementById('confirmFee').textContent = '$' + fee.toFixed(2);
            document.getElementById('confirmTotal').textContent = '$' + (amount).toFixed(2);
            document.getElementById('confirmCurrency').textContent = document.getElementById('currency').value;
            document.getElementById('confirmNetwork').textContent = document.getElementById('network').value;
            document.getElementById('confirmAddress').textContent = document.getElementById('walletAddress').value;

            pendingFormData = new FormData(form);
            document.getElementById('confirmationModal').classList.remove('hidden');
        });
    }

    const confirmBtn = document.getElementById('confirmWithdrawal');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', async function() {
            if (!pendingFormData) return;

            document.getElementById('confirmationModal').classList.add('hidden');

            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const submitSpinner = document.getElementById('submitSpinner');

            submitBtn.disabled = true;
            submitText.textContent = 'Processing...';
            submitSpinner.classList.remove('hidden');

            try {
                const response = await fetch('withdraw.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: pendingFormData
                });
                const result = await response.json();

                if (result.success) {
                    document.getElementById('successMessage').textContent =
                        result.data?.message || 'Your withdrawal request has been submitted and will be processed within 1-24 hours.';
                    document.getElementById('successModal').classList.remove('hidden');
                    form.reset();
                    feeCalc.style.display = 'none';
                } else {
                    showNotification(result.error || 'Failed to create withdrawal', 'error');
                }
            } catch (error) {
                console.error('Withdrawal error:', error);
                showNotification('Network error. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitText.textContent = 'Create Withdrawal';
                submitSpinner.classList.add('hidden');
                pendingFormData = null;
            }
        });
    }

    // cancel button
    const cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            form.reset();
            feeCalc.style.display = 'none';
        });
    }
})();

function closeConfirmationModal() {
    document.getElementById('confirmationModal').classList.add('hidden');
}

function closeSuccessModal() {
    document.getElementById('successModal').classList.add('hidden');
}

function showNotification(message, type) {
    // use cornerfield.js notification if available
    if (window.CornerfieldUI && window.CornerfieldUI.showNotification) {
        window.CornerfieldUI.showNotification(message, type);
        return;
    }
    alert(message);
}
