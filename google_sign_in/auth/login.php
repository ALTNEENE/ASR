<?php
// google_sign_in/auth/login.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/figma_assets.php';

// If already logged in, redirect appropriately.
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $uid = (int)$_SESSION['user_id'];
    $pCheck = $conn->prepare('SELECT id FROM user_profile WHERE user_id = ? LIMIT 1');
    if (!$pCheck) {
        error_log('login.php: failed to prepare profile check query: ' . $conn->error);
        // Fallback to questionnaire when profile check is unavailable.
        app_redirect('questionnaire/questionnaire.php');
    }
    $pCheck->bind_param('i', $uid);
    $pCheck->execute();
    if ($pCheck->get_result()->num_rows > 0) {
        app_redirect('main/dashboard.php');
    } else {
        app_redirect('questionnaire/questionnaire.php');
    }
    $pCheck->close();
}

$authNotice = $_SESSION['auth_notice'] ?? '';
$pendingLoginPhone = $_SESSION['pending_login_phone'] ?? '';
unset($_SESSION['auth_notice'], $_SESSION['pending_login_phone']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | A.S.R</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --red:   #D32F2F;
            --red2:  #C62828;
            --green: #2E7D32;
            --white: #FFFFFF;
            --ink:   #1A1A1A;
            --muted: #555;
            --border:#E0E0E0;
            --bg-page:#FAFAFA;
            --bg-card:#FFFFFF;
            --shadow: 0 8px 32px rgba(0,0,0,0.08);
        }
        html[data-theme="dark"] body {
            --bg-page: #0D1117;
            --bg-card: #161B22;
            --ink:     #E6EDF3;
            --muted:   #8B949E;
            --border:  #30363D;
            --shadow:  0 8px 32px rgba(0,0,0,0.35);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Tajawal', sans-serif;
            background: var(--bg-page); color: var(--ink);
            display: flex; min-height: 100vh;
            transition: background 0.3s, color 0.3s;
        }
        a { text-decoration: none; color: inherit; }

        .btn-back {
            position: absolute; top: 24px; right: 24px;
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--bg-card);
            padding: 10px 16px; border-radius: 12px;
            font-weight: 700; color: var(--ink); z-index: 10;
            border: 1px solid var(--border); transition: 0.2s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-back:hover { background: var(--bg-page); transform: translateY(-2px); }
        
        /* Layout */
        .auth-layout {
            display: flex; width: 100%; min-height: 100vh;
        }
        .auth-brand {
            flex: 1; display: none; flex-direction: column; justify-content: center;
            padding: 60px; position: relative; overflow: hidden;
            background: linear-gradient(135deg, var(--red2) 0%, var(--red) 40%, var(--green) 100%);
            color: white;
        }
        @media (min-width: 900px) { .auth-brand { display: flex; } }
        
        .auth-brand::before {
            content: ''; position: absolute; inset: 0;
            background: url('../../assets/img/card_doctor.png') center/cover no-repeat;
            opacity: 0.15; z-index: 1; mix-blend-mode: overlay;
        }
        .brand-content { position: relative; z-index: 2; max-width: 480px; }
        .brand-content h1 { font-size: 3.5rem; font-weight: 900; margin: 0 0 16px; line-height: 1.2; }
        .brand-content p { font-size: 1.2rem; opacity: 0.9; line-height: 1.6; }
        
        .auth-form-wrap {
            flex: 1; max-width: 600px; display: flex; align-items: center; justify-content: center;
            padding: 40px 24px; background: var(--bg-page); position: relative;
        }
        
        .auth-card {
            width: 100%; max-width: 420px;
            background: var(--bg-card); padding: 40px; border-radius: 20px;
            box-shadow: var(--shadow); border: 1px solid var(--border);
        }
        .auth-card h2 { font-size: 1.8rem; font-weight: 800; margin: 0 0 8px; color: var(--ink); text-align: center; }
        .auth-card > p { text-align: center; color: var(--muted); margin: 0 0 32px; font-size: 0.95rem; }
        .notice-box { margin-bottom: 18px; padding: 12px 14px; border-radius: 12px; background: rgba(46,125,50,0.1); border: 1px solid rgba(46,125,50,0.25); color: var(--green); text-align: center; font-weight: 600; }
        
        .form-group { margin-bottom: 20px; position: relative; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--ink); font-size: 0.9rem; }
        
        .password-wrapper { position: relative; display: block; }
        
        .form-control {
            width: 100%; padding: 14px 16px;
            background: var(--bg-page); border: 1px solid var(--border);
            border-radius: 12px; color: var(--ink); font-family: inherit; font-size: 1rem;
            transition: all 0.2s;
        }
        .password-wrapper .form-control {
            padding-left: 45px; /* Prevent text overlap with eye icon */
        }
        .form-control:focus { outline: none; border-color: var(--red); box-shadow: 0 0 0 4px rgba(211,47,47,0.1); }
        
        .eye-btn {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: var(--muted);
            font-size: 1.1rem; padding: 4px; display: flex; align-items: center; justify-content: center;
        }
        
        .remember-row { display: flex; align-items: center; gap: 8px; margin-bottom: 24px; }
        .remember-row input { margin: 0; width: 16px; height: 16px; accent-color: var(--red); }
        .remember-row label { color: var(--muted); font-size: 0.9rem; cursor: pointer; }
        
        .btn-submit {
            width: 100%; padding: 16px; border: none; border-radius: 12px;
            background: linear-gradient(135deg, var(--red), var(--red2));
            color: white; font-weight: 700; font-size: 1.05rem; font-family: inherit;
            cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 16px rgba(211,47,47,0.3);
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(211,47,47,0.4); }
        
        .divider {
            text-align: center; margin: 24px 0; color: var(--muted);
            position: relative; font-size: 0.9rem;
        }
        .divider::before {
            content: ''; position: absolute; top: 50%; left: 0; right: 0;
            height: 1px; background: var(--border); z-index: 1;
        }
        .divider span {
            background: var(--bg-card); padding: 0 16px; position: relative; z-index: 2;
        }
        
        .btn-google {
            display: flex; align-items: center; justify-content: center; gap: 12px;
            width: 100%; padding: 14px; border: 1px solid var(--border); border-radius: 12px;
            background: var(--bg-page); color: var(--ink); font-weight: 600;
            transition: 0.2s; cursor: pointer;
        }
        .btn-google:hover { background: var(--border); }
        .btn-google img { width: 22px; height: 22px; }
        
        .auth-footer { text-align: center; margin-top: 24px; color: var(--muted); font-size: 0.95rem; }
        .auth-footer a { color: var(--red); font-weight: 700; }
        
        /* Figma Asset display inside form if any */
        .auth-figma { margin-bottom: 20px; }
        .auth-figma img { width: 100%; border-radius: 12px; display: block; }
    </style>
</head>
<body>

<div class="auth-layout">
    <!-- Branding Side -->
    <div class="auth-brand">
        <a href="../../welcome.php" class="btn-back" data-back-button>
            <span>&rarr;</span> <span>العودة للرئيسية</span>
        </a>
        <div class="brand-content">
            <h1>مرحباً بعودتك</h1>
            <p>منصة A.S.R لمتابعة السكري توفر لك الأدوات والتحليلات الذكية لإدارة صحتك بفعالية وسهولة.</p>
        </div>
    </div>
    
    <!-- Form Side -->
    <div class="auth-form-wrap">
        <div class="auth-card">
            <h2>تسجيل الدخول</h2>
            <p>أدخل بياناتك للمتابعة إلى حسابك</p>

            <?php if ($authNotice !== ''): ?>
                <div class="notice-box"><?php echo htmlspecialchars($authNotice); ?></div>
            <?php endif; ?>

            <form method="POST" action="login_process.php" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="tel" name="phone" class="form-control" placeholder="أدخل رقم هاتفك" required dir="ltr" autocomplete="off">
                </div>

                <div class="form-group">
                    <label class="form-label">كلمة المرور</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="login-password" class="form-control" required dir="ltr">
                        <button type="button" class="eye-btn" onclick="togglePwd('login-password', this)" aria-label="إظهار كلمة المرور">
                            <span class="eye-open">👁️</span>
                            <span class="eye-closed" style="display:none;">🙈</span>
                        </button>
                    </div>
                </div>

                <div class="remember-row">
                    <input type="checkbox" name="remember_me" id="remember-me">
                    <label for="remember-me">تذكرني على هذا الجهاز</label>
                </div>

                <button type="submit" class="btn-submit">دخول</button>
            </form>

            <div class="divider"><span>أو</span></div>

            <?php
            $login_url = '';
            try {
                require_once __DIR__ . '/../config/google_config.php';
                if (isset($client)) {
                    $login_url = $client->createAuthUrl();
                }
            } catch (Throwable $e) {
                error_log("Google Login Error: " . $e->getMessage());
            }
            ?>
            
            <?php if (!empty($login_url)): ?>
            <a href="<?php echo htmlspecialchars($login_url); ?>" class="btn-google">
                <img src="https://cdn-icons-png.flaticon.com/512/300/300221.png" alt="Google">
                المتابعة باستخدام Google
            </a>
            <?php else: ?>
                <div style="text-align:center; color: #D32F2F; font-size: 0.85rem; padding: 10px; background: rgba(211,47,47,0.1); border-radius: 8px;">
                    تسجيل الدخول عبر Google غير متاح حالياً.
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const openIcon = btn.querySelector('.eye-open');
    const closedIcon = btn.querySelector('.eye-closed');
    if (input.type === 'password') {
        input.type = 'text';
        openIcon.style.display = 'none';
        closedIcon.style.display = 'inline';
    } else {
        input.type = 'password';
        openIcon.style.display = 'inline';
        closedIcon.style.display = 'none';
    }
    input.focus();
}

// Auto-sync dark mode from localStorage if set
const savedTheme = localStorage.getItem('theme');
if (savedTheme) {
    document.documentElement.setAttribute('data-theme', savedTheme);
}

const pendingLoginPhone = <?php echo json_encode((string)$pendingLoginPhone, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
if (pendingLoginPhone) {
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput && !phoneInput.value) {
        phoneInput.value = pendingLoginPhone;
    }
}

document.querySelectorAll('[data-back-button], .btn-back').forEach((backButton) => {
    backButton.addEventListener('click', (event) => {
        event.preventDefault();
        window.location.href = '../../welcome.php';
    });
});
</script>

</body>
</html>
