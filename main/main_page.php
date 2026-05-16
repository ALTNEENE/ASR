<?php
$base_level = 1;
include __DIR__ . '/../includes/auth_guard.php';
$base_path = '../';
$page_title = 'الرئيسية - مسار السكري | A.S.R';
$figma_page = 'dashboard';

// Define user name
$full_name = $_SESSION['full_name'] ?? 'مريض سكري';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 50px; padding-bottom: 50px;">
    
    <div style="text-align: center; margin-bottom: 50px;">
        <h1 style="font-size: 2.5rem; margin-bottom: 15px;">مرحباً بك، <?php echo htmlspecialchars($full_name); ?> 👋</h1>
        <p style="font-size: 1.2rem; color: var(--muted); max-width: 600px; margin: 0 auto;">
         صحتك هي أولويتنا.
            <br>
            <span style="font-size: 1rem; opacity: 0.8;">اختر الخدمة التي تود الوصول إليها الآن لبدء رحلة المتابعة الذكية.</span>
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px;">
        
        <!-- Dashboard Card -->
        <a href="dashboard.php" class="card" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 40px 30px; transition: transform 0.3s ease;">
            <div style="font-size: 4rem; margin-bottom: 20px; text-shadow: 0 10px 20px rgba(0,0,0,0.1);">📊</div>
            <h2 style="color: var(--primary-dark); margin-bottom: 15px;">لوحة المتابعة</h2>
            <p style="color: var(--muted); margin-bottom: 25px; line-height: 1.6;">
                شاهد إحصائياتك الأسبوعية، متوسط قراءات السكر، والنتائج السابقة في عرض بياني شامل.
            </p>
            <span class="btn btn-primary">عرض اللوحة</span>
        </a>

        <!-- Tools Card -->
        <a href="tools.php" class="card" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 40px 30px; transition: transform 0.3s ease;">
            <div style="font-size: 4rem; margin-bottom: 20px; text-shadow: 0 10px 20px rgba(0,0,0,0.1);">💉</div>
            <h2 style="color: var(--primary-dark); margin-bottom: 15px;">أدوات القياس</h2>
            <p style="color: var(--muted); margin-bottom: 25px; line-height: 1.6;">
                أدخل قراءات السكر الحالية، واحصل على تحليل فوري وتقييم لحالتك مع نصائح مخصصة.
            </p>
            <span class="btn btn-primary">ابدأ القياس</span>
        </a>

    </div>

    <div style="margin-top: 60px; text-align: center;">
        <p style="color: var(--muted);">
            تحتاج للمساعدة؟ يمكنك دائماً الرجوع لـ <a href="../Awareness/assistant.php" style="color: var(--accent); font-weight: bold; text-decoration: underline;">المساعد الذكي</a>
        </p>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
