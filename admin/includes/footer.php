            </main>
        </div>
    </div>

    <?php $base = $base ?? \App\Config\Config::getBasePath(); ?>
    <script src="<?= htmlspecialchars($base) ?>/assets/js/cornerfield.js"></script>
    <script>
        // shared modal helpers
        function showModal(id) {
            const m = document.getElementById(id);
            if (m) { m.classList.remove('hidden'); m.style.display = 'flex'; document.body.classList.add('modal-open'); }
        }
        function hideModal(id) {
            const m = document.getElementById(id);
            if (m) { m.classList.add('hidden'); m.style.display = 'none'; document.body.classList.remove('modal-open'); }
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            const openBtn = document.getElementById('sidebar-open');
            const closeBtn = document.getElementById('sidebar-close');

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('opacity-0', 'invisible');
            }
            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('opacity-0', 'invisible');
            }

            if (openBtn) openBtn.addEventListener('click', openSidebar);
            if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
            if (backdrop) backdrop.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) closeSidebar();
            });

            // dark mode
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    document.documentElement.classList.toggle('dark');
                    localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                });
            }
        });

        // utility functions
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
        }
        function formatDate(date) {
            return new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(date));
        }

        // notification helper
        function showNotification(message, type) {
            type = type || 'info';
            const colors = {
                success: 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                error: 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800',
                warning: 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
                info: 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800'
            };
            const el = document.createElement('div');
            el.className = 'fixed top-4 right-4 z-50 p-4 rounded-xl border text-sm font-medium shadow-sm transition-opacity duration-300 ' + (colors[type] || colors.info);
            el.textContent = message;
            document.body.appendChild(el);
            setTimeout(function() { el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 300); }, 4000);
        }

        // simple modal helper using native dialog or div
        function showModal(id) {
            var el = document.getElementById(id);
            if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
        }
        function hideModal(id) {
            var el = document.getElementById(id);
            if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
        }
    </script>

    <?php if (isset($customJS)): ?>
        <script><?= $customJS ?></script>
    <?php endif; ?>

    <?php if (isset($pageSpecificJS)): ?>
        <?= $pageSpecificJS ?>
    <?php endif; ?>
</body>
</html>
