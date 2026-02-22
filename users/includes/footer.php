            </main>
        </div>
    </div>
    
    <?php $base = $base ?? \App\Config\Config::getBasePath(); ?>
    <script src="<?= htmlspecialchars($base) ?>/assets/js/cornerfield.js"></script>
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
        
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
        }
        function formatDate(date) {
            return new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(date));
        }
    </script>
    
    <?php if (isset($pageScripts) && is_array($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
