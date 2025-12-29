    </div><!-- .main-content -->
    
    <script>
        // Format currency
        function formatCurrency(num) {
            return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                background: ${type === 'success' ? 'linear-gradient(135deg, var(--emerald), #00c9a7)' : 'linear-gradient(135deg, var(--pink), #ff6090)'};
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                animation: slideIn 0.3s ease;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Confirm dialog
        function confirmDialog(message) {
            return confirm(message);
        }
        
        // Logout handler
        const logoutLinks = document.querySelectorAll('a[href*="logout"]');
        logoutLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href.includes('action=logout')) {
                    e.preventDefault();
                    if (confirmDialog('Bạn có chắc muốn đăng xuất?')) {
                        window.location.href = 'logout.php';
                    }
                }
            });
        });
    </script>
</body>
</html>
