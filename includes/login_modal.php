
<!-- Login Modal Component -->
<div class="modal-overlay" id="loginModalOverlay">
    <article class="modal-card">
        <button class="modal-close" id="closeLoginModal" aria-label="أغلق القائمة">&times;</button>
        
        <h2>مرحباً بك مجدداً</h2>
        <p>سجل دخولك للوصول إلى أدوات متابعة السكري</p>

        <form id="globalLoginForm" action="<?php echo isset($base_path) ? $base_path : './'; ?>google_sign_in/auth/login_process.php" method="POST">
            <div class="form-group">
                <label for="login-email">البريد الإلكتروني</label>
                <input type="email" id="login-email" name="email" placeholder="example@asr.com" required>
            </div>

            <div class="form-group">
                <label for="login-password">كلمة المرور</label>
                <input type="password" id="login-password" name="password" placeholder="••••••••" required>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" id="remember-me" name="remember_me" style="width: auto; margin: 0;">
                <label for="remember-me" style="margin: 0; font-weight: normal; cursor: pointer;">تذكرني</label>
            </div>

            <button type="submit" class="btn-login">دخول</button>
        </form>

        <?php
        $google_login_url = '';
        try {
            require_once __DIR__ . '/../google_sign_in/config/google_config.php';
            if (isset($client)) {
                $google_login_url = $client->createAuthUrl();
            }
        } catch (Throwable $e) {
            error_log('Google Login Error: ' . $e->getMessage());
        }
        ?>
        
        <div style="margin: 20px 0; position: relative; text-align: center;">
            <span style="background: var(--surface, white); padding: 0 10px; position: relative; z-index: 1; color: var(--muted, #888); font-size: 14px;">أو</span>
            <div style="position: absolute; top: 50%; left: 0; width: 100%; height: 1px; background: var(--border, #eee); z-index: 0;"></div>
        </div>

        <?php if ($google_login_url !== ''): ?>
        <a href="<?php echo htmlspecialchars($google_login_url); ?>" class="google-btn" style="display: flex; align-items: center; justify-content: center; gap: 10px; background: var(--surface, white); border: 1px solid var(--border, #ddd); padding: 12px; border-radius: 12px; text-decoration: none; color: var(--ink, #333); font-weight: bold; width: 100%; box-sizing: border-box; transition: 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 15px;">
            <img src="https://cdn-icons-png.flaticon.com/512/300/300221.png" alt="Google" width="20" height="20">
            تسجيل الدخول عبر Google
        </a>
        <?php endif; ?>

        <div class="modal-footer">
            ليس لديك حساب؟ <a href="<?php echo isset($base_path) ? $base_path : './'; ?>google_sign_in/auth/register.php">سجل الآن</a>
        </div>
    </article>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('loginModalOverlay');
    const closeBtn = document.getElementById('closeLoginModal');
    
    // Function to open modal (can be called globally)
    window.openLoginModal = function() {
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    // Function to close modal
    window.closeLoginModal = function() {
        overlay.classList.remove('active');
        document.body.style.overflow = 'auto';
    };

    if (closeBtn) closeBtn.addEventListener('click', closeLoginModal);

    // Close on outside click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) window.closeLoginModal();
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('active')) {
            window.closeLoginModal();
        }
    });
});
</script>
