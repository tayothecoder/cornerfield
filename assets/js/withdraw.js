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

// load withdrawal history on page load
document.addEventListener('DOMContentLoaded', function() {
    loadWithdrawalHistory();
});

async function loadWithdrawalHistory() {
    const tbody = document.getElementById('withdrawalHistory');
    if (!tbody) return;

    try {
        const response = await fetch('withdraw.php?action=history', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();

        if (result.success && result.data && result.data.withdrawals) {
            renderWithdrawalHistory(result.data.withdrawals);
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">No withdrawals found</td></tr>';
        }
    } catch (error) {
        console.error('Failed to load withdrawal history:', error);
        tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">No withdrawals found</td></tr>';
    }
}

function renderWithdrawalHistory(withdrawals) {
    const tbody = document.getElementById('withdrawalHistory');
    if (!withdrawals || withdrawals.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">No withdrawals found</td></tr>';
        return;
    }

    tbody.innerHTML = withdrawals.map(function(w) {
        var statusClasses = {
            'pending': 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
            'processing': 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
            'completed': 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
            'rejected': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            'failed': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
        };
        var cls = statusClasses[w.status] || statusClasses['pending'];
        var date = w.formatted_date || w.created_at || '';
        var amount = w.formatted_amount || ('$' + parseFloat(w.amount || 0).toFixed(2));
        var fee = w.formatted_fee || ('$' + parseFloat(w.fee || 0).toFixed(2));
        var currency = w.currency || 'USDT';
        var address = w.wallet_address || '-';
        var shortAddr = address.length > 16 ? address.substring(0, 8) + '...' + address.substring(address.length - 6) : address;
        var hash = w.transaction_hash || '-';
        var shortHash = hash.length > 16 ? hash.substring(0, 10) + '...' + hash.substring(hash.length - 6) : hash;
        var statusLabel = w.status ? w.status.charAt(0).toUpperCase() + w.status.slice(1) : 'Pending';

        return '<tr class="hover:bg-[#f5f3ff] dark:hover:bg-[#0f0a2e] transition-colors">' +
            '<td class="py-3 pr-4 text-sm text-gray-900 dark:text-white">' + date + '</td>' +
            '<td class="py-3 pr-4 text-sm font-medium text-gray-900 dark:text-white">' + amount + '</td>' +
            '<td class="py-3 pr-4 text-sm text-gray-500 dark:text-gray-400">' + fee + '</td>' +
            '<td class="py-3 pr-4 text-sm text-gray-900 dark:text-white">' + currency + '</td>' +
            '<td class="py-3 pr-4 text-sm text-gray-500 dark:text-gray-400 font-mono">' + shortAddr + '</td>' +
            '<td class="py-3 pr-4"><span class="px-2 py-0.5 text-xs font-medium rounded-full ' + cls + '">' + statusLabel + '</span></td>' +
            '<td class="py-3 text-sm text-gray-500 dark:text-gray-400 font-mono" style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + shortHash + '</td>' +
            '</tr>';
    }).join('');
}
