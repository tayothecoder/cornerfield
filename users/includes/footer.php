            </main>
        </div>
    </div>
    
    <!-- Scripts -->
    <?php $base = $base ?? \App\Config\Config::getBasePath(); ?>
    <script src="<?= htmlspecialchars($base) ?>/assets/js/cornerfield.js"></script>
    <script>
        // Sidebar functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebar-backdrop');
            const sidebarOpen = document.getElementById('sidebar-open');
            const sidebarClose = document.getElementById('sidebar-close');
            
            // Open sidebar
            if (sidebarOpen) {
                sidebarOpen.addEventListener('click', function() {
                    sidebar.classList.remove('-translate-x-full');
                    sidebarBackdrop.classList.remove('opacity-0', 'invisible');
                });
            }
            
            // Close sidebar
            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                sidebarBackdrop.classList.add('opacity-0', 'invisible');
            }
            
            if (sidebarClose) {
                sidebarClose.addEventListener('click', closeSidebar);
            }
            
            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', closeSidebar);
            }
            
            // Close sidebar on window resize if desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    closeSidebar();
                }
            });
            
            // Dark mode functionality
            const themeToggle = document.getElementById('theme-toggle');
            const html = document.documentElement;
            
            // Check for saved theme or default to light
            const currentTheme = localStorage.getItem('theme') || 'light';
            if (currentTheme === 'dark') {
                html.classList.add('dark');
            }
            
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    if (html.classList.contains('dark')) {
                        html.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                    } else {
                        html.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                    }
                });
            }
            
            // Smooth animations for cards
            const cards = document.querySelectorAll('.cf-card');
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const cardObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fade-in-up');
                    }
                });
            }, observerOptions);
            
            cards.forEach(function(card) {
                cardObserver.observe(card);
            });
            
            // Add hover effects to interactive elements
            const interactiveElements = document.querySelectorAll('[data-hover]');
            interactiveElements.forEach(function(element) {
                element.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.1)';
                });
                
                element.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '';
                });
            });
            
            // Auto-hide alerts
            const alerts = document.querySelectorAll('.alert-auto-hide');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
            
            // Form validation helpers
            const forms = document.querySelectorAll('form[data-validate]');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(function(field) {
                        if (!field.value.trim()) {
                            field.classList.add('border-red-500', 'focus:ring-red-500');
                            field.classList.remove('border-gray-300', 'focus:ring-indigo-500');
                            isValid = false;
                        } else {
                            field.classList.remove('border-red-500', 'focus:ring-red-500');
                            field.classList.add('border-gray-300', 'focus:ring-indigo-500');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        showNotification('Please fill in all required fields', 'error');
                    }
                });
            });
            
            // Initialize tooltips
            const tooltips = document.querySelectorAll('[data-tooltip]');
            tooltips.forEach(function(element) {
                element.addEventListener('mouseenter', function() {
                    const tooltipText = this.getAttribute('data-tooltip');
                    const tooltip = document.createElement('div');
                    tooltip.className = 'absolute z-50 px-2 py-1 text-sm text-white bg-gray-900 rounded shadow-lg tooltip';
                    tooltip.textContent = tooltipText;
                    
                    document.body.appendChild(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
                });
                
                element.addEventListener('mouseleave', function() {
                    const tooltip = document.querySelector('.tooltip');
                    if (tooltip) {
                        tooltip.remove();
                    }
                });
            });
            
            // Copy to clipboard functionality
            const copyButtons = document.querySelectorAll('[data-copy]');
            copyButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const textToCopy = this.getAttribute('data-copy');
                    navigator.clipboard.writeText(textToCopy).then(function() {
                        showNotification('Copied to clipboard!', 'success');
                    }).catch(function() {
                        // Fallback for older browsers
                        const textArea = document.createElement('textarea');
                        textArea.value = textToCopy;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        showNotification('Copied to clipboard!', 'success');
                    });
                });
            });
        });
        
        // Utility functions
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white transform translate-x-full transition-transform duration-300 alert-auto-hide`;
            
            switch (type) {
                case 'success':
                    notification.classList.add('bg-green-500');
                    break;
                case 'error':
                    notification.classList.add('bg-red-500');
                    break;
                case 'warning':
                    notification.classList.add('bg-yellow-500');
                    break;
                default:
                    notification.classList.add('bg-blue-500');
            }
            
            notification.textContent = message;
            document.body.appendChild(notification);
            
            // Trigger animation
            setTimeout(function() {
                notification.classList.remove('translate-x-full');
            }, 100);
            
            // Auto remove
            setTimeout(function() {
                notification.classList.add('translate-x-full');
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 5000);
        }
        
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        }
        
        function formatDate(date) {
            return new Intl.DateTimeFormat('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(new Date(date));
        }
        
        // Loading state helper
        function setLoading(element, loading = true) {
            if (loading) {
                element.disabled = true;
                element.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Loading...';
            } else {
                element.disabled = false;
                element.innerHTML = element.getAttribute('data-original-text') || 'Submit';
            }
        }
        
        // Chart helpers (for dashboard)
        function createMiniChart(canvas, data, color = '#667eea') {
            const ctx = canvas.getContext('2d');
            const width = canvas.width;
            const height = canvas.height;
            
            ctx.clearRect(0, 0, width, height);
            ctx.strokeStyle = color;
            ctx.lineWidth = 2;
            ctx.fillStyle = color + '20';
            
            const max = Math.max(...data);
            const min = Math.min(...data);
            const range = max - min || 1;
            
            ctx.beginPath();
            data.forEach((point, index) => {
                const x = (index / (data.length - 1)) * width;
                const y = height - ((point - min) / range) * height;
                
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
            
            ctx.stroke();
            
            // Fill area under curve
            ctx.lineTo(width, height);
            ctx.lineTo(0, height);
            ctx.closePath();
            ctx.fill();
        }
        
        // Initialize charts if present
        document.addEventListener('DOMContentLoaded', function() {
            const miniCharts = document.querySelectorAll('.mini-chart');
            miniCharts.forEach(function(canvas) {
                const data = JSON.parse(canvas.getAttribute('data-chart') || '[1,2,1,3,2,4,3]');
                const color = canvas.getAttribute('data-color') || '#667eea';
                createMiniChart(canvas, data, color);
            });
        });
    </script>
    
    <!-- Add any page-specific scripts here -->
    <?php if (isset($pageScripts) && is_array($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>