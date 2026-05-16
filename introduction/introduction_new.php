<?php
$base_path = '../';
$page_title = 'منصة A.S.R لمتابعة السكري';
$figma_page = 'introduction';
include __DIR__ . '/../includes/header.php';
?>

  <!-- Hero Section -->
  <section class="hero">
    <div class="container hero-inner">
      
      <!-- Text content -->
      <div class="hero-content">
        <div class="hero-kicker">منصة بحثية للتثقيف والمتابعة عن بعد</div>
        <h1>متابعة ذكية لمرضى السكري مبنية على استبيان وتقييم آلي.</h1>
        <p>A.S.R نظام مراقبة ومتابعة عن بعد يحدد المسار المناسب للمستخدم، ثم يحلل القراءات ويقدم توصيات فورية مع سجل متابعة أسبوعي.</p>
        
        <div class="hero-actions">
          <a class="btn btn-primary" href="questionnaire/questionnaire.php">ابدأ الاستبيان</a>
          <?php if (!$is_logged_in && !$is_guest): ?>
            <a class="btn btn-secondary" href="google_sign_in/auth/login.php">أنشئ حساباً</a>
          <?php endif; ?>
        </div>

        <div class="hero-tags">
          <span class="tag">استبيان تمهيدي</span>
          <span class="tag">تحليل فوري</span>
          <span class="tag">سجل أسبوعي</span>
        </div>
      </div>

      <!-- Card content -->
      <div class="hero-visual">
        <div class="card">
          <h3>مراحل عمل النظام</h3>
          <ol>
            <li>تسجيل الدخول ثم استبيان تمهيدي لتحديد المسار.</li>
            <li>إدخال القراءات وتحليلها تلقائيًا.</li>
            <li>تصنيف الحالة وتوصيات مخصصة وسجل متابعة.</li>
          </ol>
        </div>
      </div>

    </div>
  </section>

  <!-- Features Section -->
  <section class="container" style="padding: 40px 0;">
    <div class="text-center mb-4">
      <h2 style="font-size: 32px; font-weight: 900; margin-bottom: 10px;">كيف يعمل النظام؟</h2>
      <p style="color: var(--muted);">تقييم أولي، ثم مسار متابعة أو توعية حسب حالة المستخدم.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
      <div class="card">
        <h3>استبيان تمهيدي</h3>
        <p>يجمع معلومات تعريفية وأسئلة مبدئية لتحديد المسار المناسب.</p>
      </div>

      <div class="card">
        <h3>مسار المتابعة للمصابين</h3>
        <p>إدخال القراءات، تحليل آلي، تصنيف فوري، وتوصيات غذائية وطبية.</p>
      </div>

      <div class="card">
        <h3>مسار التوعية لغير المصابين</h3>
        <p>محتوى وقائي مبسط لتقليل عوامل الخطورة وتعزيز الوعي الصحي.</p>
      </div>
    </div>
  </section>

  <?php if ($is_guest): ?>
  <!-- Guest CTA -->
  <section class="container" style="padding: 40px 0;">
    <div class="card text-center">
      <h2 style="color: var(--teal);">هل تريد الحصول على متابعة دقيقة؟</h2>
      <p>سجل حسابك الآن لتتمكن من استخدام أدوات القياس ولوحة المتابعة الشاملة.</p>
      <a class="btn btn-primary" href="google_sign_in/auth/login.php" style="margin-top: 10px;">تسجيل حساب جديد</a>
    </div>
  </section>
  <?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
