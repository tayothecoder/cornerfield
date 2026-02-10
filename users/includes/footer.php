                <!-- End of Main Content Wrapper -->
                </div>
            </main>
        </div>
    </div>

    <!-- Mobile Sidebar Overlay -->
    <div class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden" id="sidebarOverlay"></div>

    <!-- Quick Investment Modal -->
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="quickInvestModal">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-slate-600">
                <h5 class="text-lg font-semibold text-gray-900 dark:text-white">Quick Investment</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors" onclick="closeQuickInvestModal()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <form id="quickInvestForm" class="space-y-4">
                    <?= Security::getCsrfTokenInput() ?>
                    
                    <div>
                        <label for="quick_schema_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Investment Plan</label>
                        <select id="quick_schema_id" name="schema_id" class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-xl text-gray-900 dark:text-white focus:border-cf-primary focus:ring-4 focus:ring-cf-primary/20 transition-all outline-none" required>
                            <option value="">Select a plan...</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="quick_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Investment Amount</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                            <input type="number" id="quick_amount" name="amount" class="w-full pl-8 pr-16 py-3 bg-gray-50 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-xl text-gray-900 dark:text-white focus:border-cf-primary focus:ring-4 focus:ring-cf-primary/20 transition-all outline-none" 
                                   min="50" step="0.01" required placeholder="0.00">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">USD</span>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-cf-primary/10 to-cf-secondary/10 rounded-xl p-4 hidden" id="quickInvestmentPreview">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Daily Profit:</span>
                                <span class="block font-semibold text-cf-success" id="quickDailyProfit">$0.00</span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Total Return:</span>
                                <span class="block font-semibold text-cf-primary" id="quickTotalReturn">$0.00</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="flex gap-3 p-6 border-t border-gray-200 dark:border-slate-600">
                <button type="button" class="flex-1 px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-xl transition-colors" onclick="closeQuickInvestModal()">Cancel</button>
                <button type="submit" form="quickInvestForm" class="flex-1 px-4 py-2 bg-gradient-to-r from-cf-primary to-cf-secondary hover:from-cf-primary-dark hover:to-cf-primary text-white rounded-xl transition-all transform hover:scale-105">Create Investment</button>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="logoutModal">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-sm w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-cf-danger/10 rounded-full">
                    <svg class="w-6 h-6 text-cf-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-center mb-2">Confirm Logout</h3>
                <p class="text-gray-600 dark:text-gray-300 text-center mb-6">Are you sure you want to log out?</p>
                <div class="flex gap-3">
                    <button type="button" class="flex-1 px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-xl transition-colors" onclick="closeLogoutModal()">Cancel</button>
                    <button type="button" class="flex-1 px-4 py-2 bg-cf-danger hover:bg-red-600 text-white rounded-xl transition-colors" onclick="confirmLogout()">Logout</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="/assets/js/cornerfield.js"></script>
    <script src="/assets/js/dashboard.js"></script>

    <script>
        // Global variables
        window.CORNERFIELD = {
            user: {
                id: <?= (int)$currentUser['id'] ?>,
                name: <?= json_encode($currentUser['first_name'] ?? $currentUser['username']) ?>,
                balance: <?= (float)$currentUser['balance'] ?>
            },
            csrf: {
                token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            api: {
                base: '/api'
            }
        };

        // Initialize dashboard functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize theme toggle
            initializeThemeToggle();
            
            // Initialize mobile menu
            initializeMobileMenu();
            
            // Initialize dropdowns
            initializeDropdowns();
            
            // Initialize notifications
            initializeNotifications();
            
            // Initialize quick invest modal
            initializeQuickInvest();
            
            // Auto-refresh user balance every 5 minutes
            setInterval(refreshUserBalance, 300000);
            
            // Check for flash messages in URL parameters
            checkFlashMessages();
            
            console.log('🚀 Dashboard initialized');
        });

        // Theme toggle functionality
        function initializeThemeToggle() {
            // Load theme from localStorage or session
            const savedTheme = localStorage.getItem('cf-theme') || '<?= $_SESSION['theme'] ?? 'light' ?>';
            setTheme(savedTheme);
        }

        function toggleTheme() {
            const currentTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
            
            // Update session
            fetch('/api/user/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CORNERFIELD.csrf.token
                },
                body: JSON.stringify({ theme: newTheme })
            });
            
            showNotification(`Switched to ${newTheme} mode`, 'info', 2000);
        }

        function setTheme(theme) {
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            localStorage.setItem('cf-theme', theme);
        }

        // Mobile menu functionality
        function initializeMobileMenu() {
            const mobileToggle = document.getElementById('mobileToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (mobileToggle && sidebar && overlay) {
                mobileToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('-translate-x-full');
                    overlay.classList.toggle('hidden');
                });
                
                overlay.addEventListener('click', function() {
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('hidden');
                });
            }
        }

        // Dropdown functionality
        function initializeDropdowns() {
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                const dropdowns = document.querySelectorAll('[id$="-dropdown"]');
                dropdowns.forEach(dropdown => {
                    if (!dropdown.contains(e.target)) {
                        const menu = dropdown.querySelector('[id$="-menu"]');
                        if (menu) {
                            menu.classList.add('hidden');
                        }
                    }
                });
            });
        }

        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            if (!dropdown) return;
            
            const menu = dropdown.querySelector('[id$="-menu"]');
            if (!menu) return;
            
            // Close other dropdowns
            const allDropdowns = document.querySelectorAll('[id$="-dropdown"]');
            allDropdowns.forEach(other => {
                if (other.id !== dropdownId) {
                    const otherMenu = other.querySelector('[id$="-menu"]');
                    if (otherMenu) {
                        otherMenu.classList.add('hidden');
                    }
                }
            });
            
            // Toggle current dropdown
            menu.classList.toggle('hidden');
        }

        // Notifications functionality
        function initializeNotifications() {
            loadNotifications();
            
            // Refresh notifications every 2 minutes
            setInterval(loadNotifications, 120000);
        }

        function loadNotifications() {
            fetch('/api/user/notifications', {
                headers: {
                    'X-CSRF-Token': window.CORNERFIELD.csrf.token
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationsList(data.notifications || []);
                    updateNotificationCount(data.unread_count || 0);
                }
            })
            .catch(error => {
                console.error('Failed to load notifications:', error);
            });
        }

        function updateNotificationsList(notifications) {
            const notificationList = document.getElementById('notificationList');
            if (!notificationList) return;
            
            if (notifications.length === 0) {
                notificationList.innerHTML = `
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-3.5-3.5c-.83-.83-1.21-2.02-1.01-3.17L16 4a4.992 4.992 0 00-8 0L8.5 10.33c.2 1.15-.18 2.34-1.01 3.17L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <p>No new notifications</p>
                    </div>
                `;
                return;
            }
            
            notificationList.innerHTML = notifications.map(notification => `
                <div class="p-4 border-b border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors ${notification.read ? 'opacity-75' : ''}" data-id="${notification.id}">
                    <div class="flex items-start gap-3">
                        <div class="flex-1">
                            <h6 class="font-medium text-gray-900 dark:text-white text-sm">${escapeHtml(notification.title)}</h6>
                            <p class="text-gray-600 dark:text-gray-300 text-sm mt-1">${escapeHtml(notification.message)}</p>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-2 block">${notification.time_ago}</span>
                        </div>
                        ${!notification.read ? '<div class="w-2 h-2 bg-cf-primary rounded-full mt-2"></div>' : ''}
                    </div>
                </div>
            `).join('');
        }

        function updateNotificationCount(count) {
            const badge = document.getElementById('notificationCount');
            if (!badge) return;
            
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        function markAllNotificationsRead() {
            fetch('/api/user/notifications/mark-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CORNERFIELD.csrf.token
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                    showNotification('All notifications marked as read', 'success');
                }
            })
            .catch(error => {
                console.error('Failed to mark notifications as read:', error);
            });
        }

        // Quick invest modal functionality
        function initializeQuickInvest() {
            loadInvestmentPlans();
            
            const form = document.getElementById('quickInvestForm');
            const schemaSelect = document.getElementById('quick_schema_id');
            const amountInput = document.getElementById('quick_amount');
            
            if (form && schemaSelect && amountInput) {
                form.addEventListener('submit', handleQuickInvest);
                
                // Update preview when inputs change
                [schemaSelect, amountInput].forEach(input => {
                    input.addEventListener('change', updateInvestmentPreview);
                    input.addEventListener('input', updateInvestmentPreview);
                });
            }
        }

        function showQuickInvestModal() {
            const modal = document.getElementById('quickInvestModal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closeQuickInvestModal() {
            const modal = document.getElementById('quickInvestModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        function loadInvestmentPlans() {
            fetch('/api/investment/plans', {
                headers: {
                    'X-CSRF-Token': window.CORNERFIELD.csrf.token
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('quick_schema_id');
                    if (select) {
                        select.innerHTML = '<option value="">Select a plan...</option>' +
                            (data.data?.plans || []).map(plan => 
                                `<option value="${plan.id}" data-rate="${plan.daily_rate}" data-return="${plan.total_return}" data-min="${plan.min_amount}" data-max="${plan.max_amount}">
                                    ${escapeHtml(plan.name)} - ${plan.formatted_daily_rate}% daily
                                </option>`
                            ).join('');
                    }
                }
            })
            .catch(error => {
                console.error('Failed to load investment plans:', error);
            });
        }

        function updateInvestmentPreview() {
            const schemaSelect = document.getElementById('quick_schema_id');
            const amountInput = document.getElementById('quick_amount');
            const preview = document.getElementById('quickInvestmentPreview');
            
            if (!schemaSelect || !amountInput || !preview) return;
            
            const selectedOption = schemaSelect.options[schemaSelect.selectedIndex];
            const amount = parseFloat(amountInput.value) || 0;
            
            if (selectedOption.value && amount > 0) {
                const dailyRate = parseFloat(selectedOption.dataset.rate) || 0;
                const totalReturn = parseFloat(selectedOption.dataset.return) || 0;
                
                const dailyProfit = amount * (dailyRate / 100);
                const totalReturnAmount = amount + (amount * (totalReturn / 100));
                
                document.getElementById('quickDailyProfit').textContent = `$${dailyProfit.toFixed(2)}`;
                document.getElementById('quickTotalReturn').textContent = `$${totalReturnAmount.toFixed(2)}`;
                
                preview.classList.remove('hidden');
            } else {
                preview.classList.add('hidden');
            }
        }

        function handleQuickInvest(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const submitBtn = e.target.querySelector('button[type="submit"]');
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<svg class="animate-spin w-4 h-4 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
            }
            
            fetch('/api/investment/invest', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': window.CORNERFIELD.csrf.token
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Investment created successfully!', 'success');
                    closeQuickInvestModal();
                    
                    // Refresh page data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.error || 'Investment failed', 'error');
                }
            })
            .catch(error => {
                console.error('Quick invest error:', error);
                showNotification('Connection error. Please try again.', 'error');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Create Investment';
                }
            });
        }

        // Logout functionality
        function logout() {
            const modal = document.getElementById('logoutModal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closeLogoutModal() {
            const modal = document.getElementById('logoutModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        function confirmLogout() {
            window.location.href = '/logout.php';
        }

        // Utility functions
        function refreshUserBalance() {
            fetch('/api/user/balance', {
                headers: {
                    'X-CSRF-Token': window.CORNERFIELD.csrf.token
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const balanceElements = document.querySelectorAll('.user-balance, .balance-value');
                    balanceElements.forEach(element => {
                        if (element.classList.contains('user-balance')) {
                            element.textContent = `$${data.balance.available_balance.toFixed(2)}`;
                        }
                    });
                    
                    window.CORNERFIELD.user.balance = data.balance.available_balance;
                }
            })
            .catch(error => {
                console.error('Failed to refresh balance:', error);
            });
        }

        function checkFlashMessages() {
            const params = new URLSearchParams(window.location.search);
            
            if (params.has('success')) {
                showNotification(decodeURIComponent(params.get('success')), 'success');
            }
            
            if (params.has('error')) {
                showNotification(decodeURIComponent(params.get('error')), 'error');
            }
            
            if (params.has('warning')) {
                showNotification(decodeURIComponent(params.get('warning')), 'warning');
            }
            
            // Clean URL without reloading
            if (params.has('success') || params.has('error') || params.has('warning')) {
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        }

        function showNotification(message, type = 'info', duration = 5000) {
            const container = getNotificationContainer();
            
            const notification = document.createElement('div');
            const typeClasses = {
                success: 'bg-cf-success text-white',
                error: 'bg-cf-danger text-white',
                warning: 'bg-cf-warning text-white',
                info: 'bg-cf-info text-white'
            };
            
            notification.className = `${typeClasses[type] || typeClasses.info} px-4 py-3 rounded-xl shadow-lg transform transition-all duration-300 translate-x-full opacity-0 pointer-events-auto`;
            notification.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="flex-1">${escapeHtml(message)}</span>
                    <button class="text-white/80 hover:text-white transition-colors" onclick="this.parentElement.parentElement.remove()">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
            
            container.appendChild(notification);
            
            // Trigger animation
            setTimeout(() => {
                notification.classList.remove('translate-x-full', 'opacity-0');
            }, 10);
            
            // Auto remove
            if (duration > 0) {
                setTimeout(() => {
                    notification.classList.add('translate-x-full', 'opacity-0');
                    setTimeout(() => notification.remove(), 300);
                }, duration);
            }
        }

        function getNotificationContainer() {
            let container = document.getElementById('notificationContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'notificationContainer';
                container.className = 'fixed top-4 right-4 z-50 space-y-2 pointer-events-none';
                document.body.appendChild(container);
            }
            return container;
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Show loading overlay
        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.classList.remove('hidden');
            }
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.classList.add('hidden');
            }
        }

        // Make functions globally available
        window.showQuickInvestModal = showQuickInvestModal;
        window.closeQuickInvestModal = closeQuickInvestModal;
        window.logout = logout;
        window.closeLogoutModal = closeLogoutModal;
        window.confirmLogout = confirmLogout;
        window.showLoading = showLoading;
        window.hideLoading = hideLoading;
        window.markAllNotificationsRead = markAllNotificationsRead;
        window.toggleDropdown = toggleDropdown;
        window.toggleTheme = toggleTheme;
        window.showNotification = showNotification;
    </script>

    <!-- Page-specific scripts will be included here -->
    <?php if (isset($pageScripts)): ?>
        <?= $pageScripts ?>
    <?php endif; ?>
</body>
</html>