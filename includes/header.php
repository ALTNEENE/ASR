<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/figma_assets.php';

// Determine user status
$is_guest = isset($_SESSION['guest_mode']) && $_SESSION['guest_mode'] === true;
$is_logged_in = isset($_SESSION['user_id']);

// Helper function for restricted links (fallback if UI check fails)
function getRestrictedLink($url) {
    global $is_guest;
    if ($is_guest) {
        return "javascript:alert('عذراً، هذا القسم مخصص للمرضى المسجلين فقط.');";
    }
    return $url;
}

// Determine base path
$base_path = isset($base_path) ? $base_path : '../';

// Route "Home" for diabetic users to the diabetes main page.
$home_url = $base_path . 'index.php';
if ($is_logged_in && (($_SESSION['role'] ?? '') === 'diabetic')) {
    $home_url = $base_path . 'main/main_page.php';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo isset($page_title) ? $page_title : 'منصة A.S.R لمتابعة السكري'; ?></title>
  <link rel="stylesheet" href="<?php echo $base_path; ?>assets/style.css?v=1.9" />
  <script src="<?php echo $base_path; ?>assets/js/theme.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    /* ===== Figma Design System Overrides ===== */
    :root {
        --red:   #D32F2F;
        --red2:  #C62828;
        --green: #2E7D32;
        --green2:#388E3C;
        --white: #FFFFFF;
        --off-white: #FAFAFA;
        --ink:   #1A1A1A;
        --muted: #555;
        --border-color:#E0E0E0;
        --card-shadow: 0 4px 24px rgba(0,0,0,.08);
        --bg-page: #FAFAFA;
        --bg-card: #FFFFFF;
        --text-main: #1A1A1A;
        --text-muted: #555;
    }
    
    html[data-theme="dark"] body {
        --bg-page:     #0D1117;
        --bg-card:     #161B22;
        --text-main:   #E6EDF3;
        --text-muted:  #8B949E;
        --border-color:#30363D;
        --card-shadow: 0 4px 24px rgba(0,0,0,.35);
        background-color: var(--bg-page) !important;
        background-image: none !important;
        color: var(--text-main) !important;
    }
    html[data-theme="light"] body {
        background-color: var(--bg-page) !important;
        background-image: none !important;
        color: var(--text-main) !important;
    }
    
    body {
        transition: background .35s, color .35s;
        font-family: 'Tajawal', sans-serif !important;
    }
    * {
        font-family: 'Tajawal', sans-serif !important;
    }
    body::before, body::after { display: none !important; }

    /* ===== HEADER / NAV ===== */
    .site-header {
        position: fixed;
        top: 0; left: 0; right: 0;
        z-index: 200;
        padding: 0 48px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(8, 14, 22, 0.55) !important;
        backdrop-filter: blur(18px) saturate(1.6) !important;
        -webkit-backdrop-filter: blur(18px) saturate(1.6) !important;
        border-bottom: 1px solid rgba(255,255,255,.08) !important;
        transition: background .3s;
        box-shadow: none !important;
    }
    .site-header.scrolled {
        background: rgba(8, 14, 22, 0.92) !important;
    }
    html[data-theme="dark"] .site-header {
        background: rgba(8, 14, 22, 0.85) !important;
        border-bottom: 1px solid rgba(255,255,255,.05) !important;
    }

    /* Logo mark */
    .nav-logo {
        display: flex; align-items: center; gap: 12px; text-decoration: none;
    }
    .nav-logo-icon {
        width: 40px; height: 40px; position: relative; flex-shrink: 0; box-shadow: none !important; border: none !important; border-radius: 0 !important; background: transparent !important;
    }
    .nav-logo svg { width: 100%; height: 100%; }
    .nav-brand {
        display: flex; flex-direction: column; line-height: 1;
    }
    .nav-brand-name {
        font-family: 'Tajawal', sans-serif !important; font-size: 1.25rem; font-weight: 900; color: white !important; letter-spacing: 1.5px;
    }
    .nav-brand-sub {
        font-family: 'Tajawal', sans-serif !important; font-size: .65rem; color: rgba(255,255,255,.5) !important; letter-spacing: 2px; text-transform: uppercase; margin-top: 2px;
    }

    /* Nav links */
    .nav-links {
        display: flex !important; align-items: center; gap: 32px; list-style: none; margin: 0; padding: 0;
    }
    .nav-links li { margin: 0; padding: 0; }
    .nav-links a {
        font-size: .95rem; font-weight: 600; color: rgba(255,255,255,.75) !important; text-decoration: none; transition: color .2s; position: relative; background: transparent !important; padding: 0 !important; box-shadow: none !important;
    }
    .nav-links a::after {
        content: ''; position: absolute; bottom: -6px; left: 0; right: 0; height: 2px; background: var(--red); transform: scaleX(0); transition: transform .25s ease; border-radius: 2px;
    }
    .nav-links a:hover { color: white !important; transform: none !important; }
    .nav-links a:hover::after { transform: scaleX(1); }

    /* Nav actions */
    .nav-actions {
        display: flex; align-items: center; gap: 10px;
    }
    .nav-btn {
        display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px !important; border-radius: 10px !important; font-family: 'Tajawal', sans-serif !important; font-size: .9rem !important; font-weight: 700 !important; cursor: pointer; text-decoration: none; transition: all .2s !important; border: none !important;
    }
    .nav-btn-ghost {
        background: rgba(255,255,255,.1) !important; color: white !important; border: 1px solid rgba(255,255,255,.18) !important; box-shadow: none !important;
    }
    .nav-btn-ghost:hover {
        background: rgba(255,255,255,.18) !important; color: white !important; transform: translateY(-2px) !important;
    }
    .nav-btn-primary {
        background: linear-gradient(135deg, var(--red), var(--red2)) !important; color: white !important; box-shadow: 0 4px 16px rgba(211,47,47,.35) !important;
    }
    .nav-btn-primary:hover {
        transform: translateY(-2px) !important; box-shadow: 0 6px 22px rgba(211,47,47,.45) !important; color: white !important;
    }

    .page-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 20px;
        padding: 10px 18px;
        border-radius: 10px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        color: var(--ink);
        font-weight: 700;
        text-decoration: none;
        box-shadow: var(--card-shadow, 0 4px 18px rgba(0,0,0,.08));
        transition: transform .2s ease, border-color .2s ease, color .2s ease;
    }
    .page-back-btn:hover {
        transform: translateY(-2px);
        border-color: var(--green);
        color: var(--green);
    }
     
    /* User Profile Pill */
    .nav-user {
        background: rgba(255,255,255,.1) !important;
        color: white !important;
        padding: 6px 14px !important;
        border-radius: 999px !important;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        font-size: .9rem;
        text-decoration: none;
        transition: background .2s !important;
        border: 1px solid rgba(255,255,255,.1) !important;
        box-shadow: none !important;
    }
    .nav-user:hover {
        background: rgba(255,255,255,.2) !important; transform: none !important;
    }

    /* Premium Sky Toggle */
    .dark-toggle {
        width: 70px; height: 34px;
        background: linear-gradient(to right, #4facfe, #00f2fe) !important;
        border: 2px solid rgba(255,255,255,0.3) !important;
        border-radius: 999px !important;
        position: relative;
        cursor: pointer;
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
        overflow: hidden;
        display: flex;
        align-items: center;
        box-shadow: 0 8px 15px rgba(0,0,0,0.15) !important;
        padding: 0 !important;
        outline: none;
        flex-shrink: 0;
    }

    .dark-toggle .toggle-thumb {
        width: 25px; height: 25px;
        border-radius: 50%;
        background: #ffcc33 !important; /* Sun color */
        position: absolute;
        right: 4px;
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
        z-index: 10;
        box-shadow: 0 0 10px rgba(255, 204, 51, 0.5);
        display: flex; align-items: center; justify-content: center;
    }

    html[data-theme="dark"] .dark-toggle {
        background: linear-gradient(to right, #243b55, #141e30) !important;
        border-color: rgba(255,255,255,0.1) !important;
    }

    html[data-theme="dark"] .dark-toggle .toggle-thumb {
        transform: translateX(-37px) !important;
        background: #f5f5f5 !important; /* Moon color */
        box-shadow: 
            0 0 10px rgba(255, 255, 255, 0.3),
            inset -3px -3px 0 rgba(0,0,0,0.1);
    }

    /* Clouds/Stars Background Elements */
    .sky-elements {
        position: absolute;
        inset: 0;
        transition: opacity 0.5s;
        pointer-events: none;
    }

    .cloud {
        position: absolute;
        width: 12px; height: 12px;
        background: rgba(255,255,255,0.8);
        border-radius: 50%;
        transition: transform 0.5s;
    }
    .cloud::before, .cloud::after {
        content: '';
        position: absolute;
        background: rgba(255,255,255,0.8);
        border-radius: 50%;
    }
    .cloud::before { width: 8px; height: 8px; top: 4px; left: -6px; }
    .cloud::after { width: 10px; height: 10px; top: -3px; left: 5px; }

    .cloud-1 { top: 8px; left: 15px; }
    .cloud-2 { top: 18px; left: 35px; }

    html[data-theme="dark"] .cloud {
        transform: translateY(40px);
        opacity: 0;
    }

    .stars {
        position: absolute;
        inset: 0;
        opacity: 0;
        transition: opacity 0.5s;
        pointer-events: none;
    }

    .star {
        position: absolute;
        background: white;
        border-radius: 50%;
        animation: twinkle 2s infinite alternate;
    }
    .star-1 { width: 2px; height: 2px; top: 8px; right: 20px; }
    .star-2 { width: 1px; height: 1px; top: 18px; right: 40px; }
    .star-3 { width: 1.5px; height: 1.5px; top: 5px; right: 50px; }

    

    html[data-theme="dark"] .stars { opacity: 1; }

    @keyframes twinkle {
        from { opacity: 0.3; transform: scale(0.8); }
        to { opacity: 1; transform: scale(1.1); }
    }
    
    body { padding-top: 85px; } /* Prevent content hiding behind fixed nav */
  </style>
</head>
<body>

  <header class="site-header" id="siteHeader">
    <a href="<?php echo $home_url; ?>" class="nav-logo">
        <div class="nav-logo-icon">
            <svg viewBox="0 0 40 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 2 C20 2 4 18 4 30 C4 39.94 11.16 46 20 46 C28.84 46 36 39.94 36 30 C36 18 20 2 20 2Z" fill="url(#dropGrad)"/>
                <polyline points="8,31 13,31 15,25 17,37 19,28 21,34 23,31 32,31" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                <defs>
                    <linearGradient id="dropGrad" x1="0" y1="0" x2="40" y2="48" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#D32F2F"/>
                        <stop offset="100%" stop-color="#2E7D32"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="nav-brand">
            <span class="nav-brand-name">A.S.R</span>
            <span class="nav-brand-sub">Diabetes Monitoring</span>
        </div>
    </a>

    <ul class="nav-links">
        <li><a href="<?php echo $home_url; ?>">الرئيسية</a></li>
        <li><a href="<?php echo $base_path; ?>Awareness/assistant.php">المساعد الذكي</a></li>
        <li><a href="<?php echo $base_path; ?>Awareness/awareness.php">التوعية</a></li>
        <?php if ($is_logged_in && (isset($_SESSION['role']) && $_SESSION['role'] === 'diabetic')): ?>
            <li><a href="<?php echo $base_path; ?>main/dashboard.php">لوحة المتابعة</a></li>
            <li><a href="<?php echo $base_path; ?>main/tools.php">أدوات القياس</a></li>
        <?php endif; ?>
    </ul>

    <div class="nav-actions">
        <!-- Premium Sky Toggle -->
        <button class="dark-toggle" id="headerDarkToggle" title="تبديل الوضع الليلي">
            <div class="sky-elements">
                <div class="cloud cloud-1"></div>
                <div class="cloud cloud-2"></div>
                <div class="stars">
                    <div class="star star-1"></div>
                    <div class="star star-2"></div>
                    <div class="star star-3"></div>
                </div>
            </div>
            <div class="toggle-thumb" id="headerToggleThumb"></div>
        </button>

        <?php if (!$is_logged_in): ?>
            <a href="<?php echo $base_path; ?>questionnaire/questionnaire.php" class="nav-btn nav-btn-ghost">الرجوع للاستبيان</a>
        <?php else: ?>
            <a href="<?php echo $base_path; ?>main/profile.php" class="nav-user" title="الملف الشخصي">
                <span>👤</span>
                <span><?php echo htmlspecialchars(explode(' ', trim($_SESSION['full_name'] ?? ''))[0] ?: 'مستخدم'); ?></span>
            </a>
            <a href="<?php echo $base_path; ?>google_sign_in/auth/logout.php" class="nav-btn nav-btn-ghost" style="padding: 6px 12px; border-color: rgba(255,255,255,.18) !important;">خروج</a>
        <?php endif; ?>
    </div>
  </header>
  
  <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Global script to add red asterisk to required fields' labels
        const requiredFields = document.querySelectorAll('input[required], select[required], textarea[required]');
        requiredFields.forEach(field => {
            let label = null;
            if (field.id) label = document.querySelector(`label[for="${field.id}"]`);
            if (!label) {
                const parent = field.closest('.form-group') || field.closest('div');
                if (parent) label = parent.querySelector('label');
            }
            if (!label && field.previousElementSibling && field.previousElementSibling.tagName === 'LABEL') {
                label = field.previousElementSibling;
            }
            if (label && !label.classList.contains('has-required-star')) {
                label.classList.add('has-required-star');
                const star = document.createElement('span');
                star.style.color = '#D32F2F'; // Red color
                star.style.fontWeight = 'bold';
                star.style.fontSize = '1.3em';
                star.style.marginLeft = '4px';
                star.textContent = '*';
                label.insertBefore(star, label.firstChild);
            }
        });

        const hdr = document.getElementById('siteHeader');
        if (hdr) {
            window.addEventListener('scroll', () => {
                hdr.classList.toggle('scrolled', window.scrollY > 60);
            }, { passive: true });
        }

        document.querySelectorAll('[data-back-button], .btn-back, .btn-floating-back, .back-btn, .page-back-btn').forEach((backButton) => {
            backButton.addEventListener('click', (event) => {
                const fallback = backButton.getAttribute('href') || '<?php echo $home_url; ?>';
                if (window.history.length > 1) {
                    event.preventDefault();
                    window.history.back();
                    return;
                }
                if (!fallback || fallback === '#') {
                    event.preventDefault();
                    window.location.href = '<?php echo $home_url; ?>';
                }
            });
        });

        const btn = document.getElementById('headerDarkToggle');
        const thumb = document.getElementById('headerToggleThumb');
        if (btn && thumb) {
            const updateThumb = () => {
                // Animation handled by CSS
            };
            updateThumb();
            
            btn.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                localStorage.setItem('asr_dark', newTheme === 'dark' ? '1' : '0'); // Sync with welcome.php
                updateThumb();
            });
        }
    });
  </script>
  <?php
  ?>
