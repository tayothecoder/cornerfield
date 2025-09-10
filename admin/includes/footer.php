<!-- Footer -->
        <footer class="footer footer-transparent d-print-none">
            <div class="container-xl">
                <div class="row text-center align-items-center flex-row-reverse">
                    <div class="col-lg-auto ms-lg-auto">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item">
                                <a href="../system-check.php" target="_blank" class="link-secondary">
                                    System Check
                                </a>
                            </li>
                            <li class="list-inline-item">
                                <a href="settings.php" class="link-secondary">
                                    Settings
                                </a>
                            </li>
                            <li class="list-inline-item">
                                <a href="../users/dashboard.php" target="_blank" class="link-secondary">
                                    User Site
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item">
                                Copyright &copy; <?= date('Y') ?>
                                <a href="dashboard.php" class="link-secondary"><?= htmlspecialchars($siteName) ?></a>.
                                All rights reserved.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>

    <!-- Tabler JS -->
    <script src="../assets/tabler/dist/js/tabler.min.js"></script>
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <!-- Clean Admin JavaScript -->
    <script>
        // Sidebar functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.admin-sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        }
        
        function closeSidebar() {
            const sidebar = document.querySelector('.admin-sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.admin-sidebar');
            const sidebarToggle = document.querySelector('.admin-sidebar-toggle');
            
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                    closeSidebar();
                }
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                closeSidebar();
            }
        });
        
        // Simple animations for elements
        function animateElements() {
            const statsCards = document.querySelectorAll('.stats-card');
            const adminCards = document.querySelectorAll('.admin-card');
            
            // Animate stats cards
            statsCards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('fade-in');
                }, index * 100);
            });
            
            // Animate admin cards
            adminCards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('slide-in');
                }, index * 150);
            });
        }
        
        // Notification system
        function showNotification(message, type = 'info', duration = 3000) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = `
                top: 20px; 
                right: 20px; 
                z-index: 9999; 
                min-width: 300px; 
                border-radius: 0.5rem; 
                background: white;
                color: #495057;
                border: 1px solid #e9ecef;
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            `;
            
            const iconMap = {
                success: 'check-circle',
                danger: 'exclamation-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle'
            };
            
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${iconMap[type]} text-${type} me-2"></i>
                    <span>${message}</span>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after duration
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);
        }
        
        // Form validation
        function validateForm(form) {
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        }
        
        // Table interactions
        function initTableInteractions() {
            const tableRows = document.querySelectorAll('.admin-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'rgba(32, 107, 196, 0.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'transparent';
                });
            });
        }
        
        // Button effects
        function initButtonEffects() {
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-1px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        }
        
        // Search functionality
        function initSearch() {
            const searchInput = document.querySelector('.admin-search input');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const query = e.target.value.toLowerCase();
                    // Add search logic here
                    console.log('Searching for:', query);
                });
            }
        }
        
        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize animations
            animateElements();
            
            // Initialize interactions
            initTableInteractions();
            initButtonEffects();
            initSearch();
        });
        
        // Export functions for global use
        window.showNotification = showNotification;
        window.validateForm = validateForm;
        
        // Enhanced AJAX handler
        window.ajaxRequest = function(url, data, options = {}) {
            const defaults = {
                method: 'POST',
                showLoading: true,
                successMessage: null,
                errorMessage: 'An error occurred. Please try again.',
                onSuccess: null,
                onError: null
            };

            const config = Object.assign(defaults, options);

            if (config.showLoading) {
                showLoading();
            }

            const formData = new FormData();
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });
            formData.append('ajax', '1');

            return fetch(url, {
                method: config.method,
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (config.showLoading) {
                        hideLoading();
                    }

                    if (data.success) {
                        if (config.successMessage) {
                            showNotification(config.successMessage, 'success');
                        }
                        if (config.onSuccess) {
                            config.onSuccess(data);
                        }
                    } else {
                        const message = data.message || config.errorMessage;
                        showNotification(message, 'error');
                        if (config.onError) {
                            config.onError(data);
                        }
                    }

                    return data;
                })
                .catch(error => {
                    if (config.showLoading) {
                        hideLoading();
                    }

                    console.error('AJAX Error:', error);
                    showNotification(config.errorMessage, 'error');

                    if (config.onError) {
                        config.onError({ success: false, message: error.message });
                    }

                    throw error;
                });
        };

        // Loading overlay functions
        window.showLoading = function() {
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.id = 'admin-loading';
            overlay.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(overlay);
        };

        window.hideLoading = function() {
            const overlay = document.getElementById('admin-loading');
            if (overlay) {
                overlay.remove();
            }
        };

        // Currency formatting
        window.formatCurrency = function(amount, symbol = '$') {
            return symbol + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        };

        // Date formatting
        window.formatDate = function(date, format = 'MMM DD, YYYY') {
            const d = new Date(date);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            return format
                .replace('YYYY', d.getFullYear())
                .replace('MMM', months[d.getMonth()])
                .replace('DD', String(d.getDate()).padStart(2, '0'));
        };

        // Status color helper
        window.getStatusColor = function(status) {
            const statusColors = {
                'completed': 'success',
                'active': 'success',
                'pending': 'warning',
                'processing': 'info',
                'failed': 'danger',
                'cancelled': 'secondary',
                'rejected': 'danger',
                'expired': 'secondary'
            };

            return statusColors[status] || 'secondary';
        };

        // Modal utilities
        window.showModal = function(modalId, data = {}) {
            const modal = document.getElementById(modalId);
            if (modal) {
                // Populate modal with data
                Object.keys(data).forEach(key => {
                    const element = modal.querySelector(`[name="${key}"], #${key}, .${key}`);
                    if (element) {
                        if (element.type === 'checkbox') {
                            element.checked = Boolean(data[key]);
                        } else {
                            element.textContent = data[key];
                            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT') {
                                element.value = data[key];
                            }
                        }
                    }
                });

                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
                return bsModal;
            }
        };
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('.admin-search input');
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });
    </script>

    <?php if (isset($customJS)): ?>
        <script><?= $customJS ?></script>
    <?php endif; ?>

    <?php if (isset($pageSpecificJS)): ?>
        <?= $pageSpecificJS ?>
    <?php endif; ?>

</body>
</html>