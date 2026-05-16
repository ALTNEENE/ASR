<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/figma_assets.php';

// If already logged in, redirect
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Check if profile completed
    $uid = (int)$_SESSION['user_id'];
    $pCheck = $conn->prepare('SELECT id FROM user_profile WHERE user_id = ? LIMIT 1');
    $pCheck->bind_param('i', $uid);
    $pCheck->execute();
    if ($pCheck->get_result()->num_rows > 0) {
        app_redirect('main/dashboard.php');
    } else {
        app_redirect('questionnaire/questionnaire.php');
    }
    $pCheck->close();
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($phone) || !preg_match('/^[0-9+]+$/', $phone) || strlen($phone) < 9) {
        $error = 'الرجاء إدخال رقم هاتف صحيح.';
    } elseif (strlen($password) < 4) {
        $error = 'كلمة المرور يجب أن تتكون من 4 أرقام أو أحرف على الأقل.';
    } elseif ($password !== $confirm_password) {
        $error = 'كلمتا المرور غير متطابقتين.';
    } else {
        $check = $conn->prepare('SELECT id FROM users WHERE phone = ?');
        $check->bind_param('s', $phone);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "رقم الهاتف مسجل مسبقًا. <a href='login.php' style='color:var(--primary);'>تسجيل الدخول</a>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'diabetic';

            // We store the phone number in the 'email' column to avoid DB schema changes and support Google Login seamlessly
            $insert = $conn->prepare('INSERT INTO users (phone, password, role, survey_completed, created_at) VALUES (?, ?, ?, 0, NOW())');
            $insert->bind_param('sss', $phone, $hashed_password, $role);

            if ($insert->execute()) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $insert->insert_id;
                $_SESSION['role'] = $role;
                $_SESSION['full_name'] = 'مستخدم';
                $check->close();

                require_once __DIR__ . '/../../includes/profile_sync.php';
                $syncedFromTemp = dmproject_sync_temp_patient_data($conn, (int)$_SESSION['user_id']);
                $insert->close();

                if ($syncedFromTemp) {
                    app_redirect('main/dashboard.php');
                } else {
                    // No pre-auth survey data yet -> continue to questionnaire.
                    app_redirect('questionnaire/questionnaire.php');
                }
            }

            $error = 'فشل التسجيل. حاول مرة أخرى.';
            $insert->close();
        }

        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب جديد | A.S.R</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --red:   #D32F2F;
            --red2:  #C62828;
            --green: #2E7D32;
            --green-dark: #1B5E20;
            --white: #FFFFFF;
            --ink:   #1A1A1A;
            --muted: #555;
            --border:#E0E0E0;
            --bg-page:#FAFAFA;
            --bg-card:#FFFFFF;
            --shadow: 0 8px 32px rgba(0,0,0,0.08);
            --status-danger-bg: #fee2e2;
            --status-danger-text: #991b1b;
        }
        html[data-theme="dark"] body {
            --bg-page: #0D1117;
            --bg-card: #161B22;
            --ink:     #E6EDF3;
            --muted:   #8B949E;
            --border:  #30363D;
            --shadow:  0 8px 32px rgba(0,0,0,0.35);
            --status-danger-bg: rgba(211,47,47,0.15);
            --status-danger-text: #ef9a9a;
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
        .auth-layout { display: flex; width: 100%; min-height: 100vh; flex-direction: row-reverse; } /* Reverse for Register page */
        .auth-brand {
            flex: 1; display: none; flex-direction: column; justify-content: center;
            padding: 60px; position: relative; overflow: hidden;
            background: linear-gradient(135deg, var(--green-dark) 0%, var(--green) 40%, var(--red) 100%);
            color: white;
        }
        @media (min-width: 900px) { .auth-brand { display: flex; } }
        
        .auth-brand::before {
            content: ''; position: absolute; inset: 0;
            background: url('../../assets/img/card_exercise.png') center/cover no-repeat;
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
        .subtitle { text-align: center; color: var(--muted); margin: 0 0 24px; font-size: 0.95rem; }
        
        .error-box { background: var(--status-danger-bg); color: var(--status-danger-text); border: 1px solid var(--status-danger-text); padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9rem; text-align: center; }

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
        .form-control:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 4px rgba(46,125,50,0.1); }
        
        .eye-btn {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: var(--muted);
            font-size: 1.1rem; padding: 4px; display: flex; align-items: center; justify-content: center;
        }
        
        .password-hint { font-size: 0.8rem; color: var(--muted); margin-top: -10px; margin-bottom: 20px; }

        .btn-submit {
            width: 100%; padding: 16px; border: none; border-radius: 12px;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white; font-weight: 700; font-size: 1.05rem; font-family: inherit;
            cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 16px rgba(46,125,50,0.3);
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(46,125,50,0.4); }
        
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
        .auth-footer a { color: var(--green); font-weight: 700; }
        
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
            <h1>انضم إلينا اليوم</h1>
            <p>ابدأ رحلتك نحو حياة صحية أفضل من خلال منصة المتابعة الذكية المتكاملة.</p>
        </div>
    </div>
    
    <!-- Form Side -->
    <div class="auth-form-wrap">
        <div class="auth-card">
            <h2>إنشاء حساب</h2>
            <p class="subtitle">سجّل حسابك لبدء متابعة السكري</p>

            <?php if (!empty($error)): ?>
                <div class="error-box"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="tel" name="phone" class="form-control" required dir="ltr" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">كلمة المرور</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-control" required dir="ltr" autocomplete="new-password">
                        <button type="button" class="eye-btn" onclick="togglePwd('password', this)" aria-label="إظهار كلمة المرور">
                            <span class="eye-open">👁️</span>
                            <span class="eye-closed" style="display:none;">🙈</span>
                        </button>
                    </div>
                </div>
                <p class="password-hint">4 أرقام أو أحرف على الأقل</p>

                <div class="form-group">
                    <label class="form-label">تأكيد كلمة المرور</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required dir="ltr" autocomplete="new-password">
                        <button type="button" class="eye-btn" onclick="togglePwd('confirm_password', this)" aria-label="إظهار كلمة المرور">
                            <span class="eye-open">👁️</span>
                            <span class="eye-closed" style="display:none;">🙈</span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">إنشاء الحساب</button>
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
                أنشئ حسابك بـ Google
            </a>
            <?php else: ?>
                <div style="text-align:center; color: var(--status-danger-text); font-size: 0.85rem; padding: 10px; background: var(--status-danger-bg); border-radius: 8px;">
                    التسجيل عبر Google غير متاح حالياً.
                </div>
            <?php endif; ?>

            <div class="auth-footer">
                لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a>
            </div>
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

document.querySelectorAll('[data-back-button], .btn-back').forEach((backButton) => {
    backButton.addEventListener('click', (event) => {
        event.preventDefault();
        window.location.href = '../../welcome.php';
    });
});
</script>

</body>
</html>
