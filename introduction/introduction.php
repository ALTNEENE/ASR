<?php
$base_path = '../';
$page_title = 'عن المنصة - A.S.R';
$figma_page = 'introduction';
include __DIR__ . '/../includes/header.php';
?>

<main class="container" style="padding: 40px 0;">
  
  <!-- About Section -->
  <div class="card" style="margin-bottom: 30px;">
    <h1 style="margin: 0 0 20px 0; color: var(--teal);">عن منصة A.S.R</h1>
    <p style="font-size: 18px; line-height: 1.8;">
      منصة A.S.R هي نظام متكامل لمتابعة مرضى السكري عن بعد، مبني على منهجية بحثية تشمل الاستبيان التمهيدي، التقييم الآلي، والتوصيات المخصصة.
    </p>
    <p style="font-size: 16px; line-height: 1.8; color: var(--muted);">
      تم تطوير هذا النظام لتسهيل التواصل بين المريض ومقدم الرعاية الصحية، وتمكين المرضى من متابعة حالتهم بشكل يومي وفعال.
    </p>
  </div>

  <!-- How it Works -->
  <div class="card" style="margin-bottom: 30px;">
    <h2 style="color: var(--teal); margin-top: 0;">كيف يعمل النظام؟</h2>
    <div style="margin-top: 20px;">
        <div style="margin-bottom: 15px;">
            <h3 style="font-size: 18px; color: var(--teal-dark); margin-bottom: 5px;">1. الاستبيان التمهيدي</h3>
            <p style="margin: 0; color: var(--muted);">يحدد المسار المناسب للمستخدم (مريض أو زائر) بناءً على حالته الصحية.</p>
        </div>
        <div style="margin-bottom: 15px;">
            <h3 style="font-size: 18px; color: var(--teal-dark); margin-bottom: 5px;">2. إدخال البيانات</h3>
            <p style="margin: 0; color: var(--muted);">يقوم المرضى بإدخال قراءات السكر اليومية والأدوية المستخدمة.</p>
        </div>
        <div style="margin-bottom: 15px;">
            <h3 style="font-size: 18px; color: var(--teal-dark); margin-bottom: 5px;">3. التحليل الآلي</h3>
            <p style="margin: 0; color: var(--muted);">يقوم النظام بتحليل القراءات فوراً وتقديم تصنيف (طبيعي، مرتفع، منخفض) بالألوان.</p>
        </div>
        <div style="margin-bottom: 15px;">
            <h3 style="font-size: 18px; color: var(--teal-dark); margin-bottom: 5px;">4. التوصيات</h3>
            <p style="margin: 0; color: var(--muted);">تقديم نصائح غذائية وطبية مخصصة بناءً على نتيجة التحليل.</p>
        </div>
    </div>
  </div>

  <!-- Tracks -->
  <div class="card" style="margin-bottom: 30px;">
    <h2 style="color: var(--teal); margin-top: 0;">المسارات المتاحة</h2>
    <div style="display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
      <div style="padding: 20px; background: var(--bg); border-radius: 12px; border-right: 4px solid var(--teal);">
        <h3 style="margin: 0 0 10px 0; font-size: 20px;">مسار المرضى</h3>
        <p style="margin: 0; color: var(--muted); line-height: 1.6;">للمصابين بالسكري. يتيح:
        <ul style="margin-top: 5px; padding-right: 20px;">
            <li>تسجيل القراءات</li>
            <li>لوحة متابعة إحصائية</li>
            <li>سجل تاريخي</li>
        </ul>
        </p>
      </div>
      <div style="padding: 20px; background: var(--bg); border-radius: 12px; border-right: 4px solid var(--orange);">
        <h3 style="margin: 0 0 10px 0; font-size: 20px;">مسار الزوار</h3>
        <p style="margin: 0; color: var(--muted); line-height: 1.6;">لغير المصابين والمهتمين. يتيح:
        <ul style="margin-top: 5px; padding-right: 20px;">
            <li>المحتوى التوعوي</li>
            <li>معلومات عن السكري</li>
            <li>نصائح الوقاية</li>
        </ul>
        </p>
      </div>
    </div>
  </div>

  <!-- Call to Action -->
  <div style="text-align: center; margin-top: 40px;">
    <a href="../questionnaire/questionnaire.php" class="btn btn-primary" style="font-size: 18px; padding: 14px 32px; margin: 0 10px;">
      ابدأ الاستبيان الآن
    </a>
    <a href="../google_sign_in/auth/login.php" class="btn btn-secondary" style="font-size: 18px; padding: 14px 32px; margin: 0 10px;">
      تسجيل الدخول
    </a>
  </div>
  
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
