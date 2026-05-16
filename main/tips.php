<?php
$base_path = '../';
$page_title = 'التوعية الصحية | A.S.R';
$figma_page = 'awareness';
require_once __DIR__ . '/../google_sign_in/auth/auth_check.php';
include __DIR__ . '/../includes/header.php';
?>

<main class="container" style="padding: 40px 0;">
  <section class="hero" style="margin-bottom: 40px; text-align: center; border: 1px solid var(--border); background: var(--bg-card); border-radius: 20px; padding: 40px; box-shadow: var(--card-shadow, 0 4px 24px rgba(0,0,0,.08));">
    <p class="eyebrow" style="color: var(--green); font-weight: bold; margin-bottom: 10px;">مسار التوعية لغير المصابين</p>
    <h1 style="font-size: 2.2rem; margin-bottom: 15px; color: var(--ink);">نصائح وقائية تقلل عوامل الخطورة وتحميك مبكرًا.</h1>
    <p class="lead" style="color: var(--muted); font-size: 1.1rem; max-width: 600px; margin: 0 auto 30px;">هذه الصفحة مخصصة لغير المصابين أو المعرّضين للخطر، وتهدف لتعزيز الوعي الصحي اليومي.</p>
    <div style="display: flex; gap: 15px; justify-content: center;">
      <a href="../questionnaire/questionnaire.php" class="btn-submit" style="padding: 12px 25px; border-radius: 12px; background: linear-gradient(135deg, var(--green), var(--green-dark)); color: white; font-weight: bold; text-decoration: none; display: inline-block; transition: 0.3s;">العودة للاستبيان</a>
    </div>
  </section>

  <section class="section">
    <div style="text-align: center; margin-bottom: 40px;">
      <h2 style="color: var(--green); margin-bottom: 10px;">محاور التوعية الأساسية</h2>
      <p style="color: var(--muted);">خطوات بسيطة للوقاية وتقليل احتمالات الإصابة.</p>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
      
      <article class="glass-card" style="background: var(--bg-card); border-radius: 20px; padding: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow, 0 8px 32px rgba(0,0,0,.08));">
        <h3 style="color: var(--ink); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
           <span style="background: rgba(46,125,50,0.1); color: var(--green); padding: 8px; border-radius: 10px; font-size: 1.2rem;">🥗</span> التغذية
        </h3>
        <ul style="color: var(--muted); line-height: 1.8; padding-right: 20px;">
          <li>قسّم الوجبات على مدار اليوم.</li>
          <li>اختر الكربوهيدرات المعقّدة.</li>
          <li>راقب أحجام الحصص.</li>
        </ul>
      </article>

      <article class="glass-card" style="background: var(--bg-card); border-radius: 20px; padding: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow, 0 8px 32px rgba(0,0,0,.08));">
        <h3 style="color: var(--ink); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
           <span style="background: rgba(46,125,50,0.1); color: var(--green); padding: 8px; border-radius: 10px; font-size: 1.2rem;">🏃‍♂️</span> النشاط البدني
        </h3>
        <ul style="color: var(--muted); line-height: 1.8; padding-right: 20px;">
          <li>30 دقيقة مشي معتدل يوميًا.</li>
          <li>ابدأ بالتدريج واحمِ المفاصل.</li>
          <li>راقب السكر قبل وبعد التمرين.</li>
        </ul>
      </article>

      <article class="glass-card" style="background: var(--bg-card); border-radius: 20px; padding: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow, 0 8px 32px rgba(0,0,0,.08));">
        <h3 style="color: var(--ink); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
           <span style="background: rgba(46,125,50,0.1); color: var(--green); padding: 8px; border-radius: 10px; font-size: 1.2rem;">📊</span> المتابعة والقياس
        </h3>
        <ul style="color: var(--muted); line-height: 1.8; padding-right: 20px;">
          <li>قِس السكر في أوقات ثابتة.</li>
          <li>سجّل القراءات والأعراض.</li>
          <li>شارك تقريرك مع الطبيب.</li>
        </ul>
      </article>

      <article class="glass-card" style="background: var(--bg-card); border-radius: 20px; padding: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow, 0 8px 32px rgba(0,0,0,.08));">
        <h3 style="color: var(--ink); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
           <span style="background: rgba(46,125,50,0.1); color: var(--green); padding: 8px; border-radius: 10px; font-size: 1.2rem;">😴</span> النوم والضغط النفسي
        </h3>
        <ul style="color: var(--muted); line-height: 1.8; padding-right: 20px;">
          <li>حافظ على 7-8 ساعات نوم.</li>
          <li>خفف التوتر بتمارين التنفس.</li>
          <li>تجنب الشاشات قبل النوم.</li>
        </ul>
      </article>

      <article class="glass-card" style="background: var(--bg-card); border-radius: 20px; padding: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow, 0 8px 32px rgba(0,0,0,.08));">
        <h3 style="color: var(--ink); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
           <span style="background: rgba(46,125,50,0.1); color: var(--green); padding: 8px; border-radius: 10px; font-size: 1.2rem;">💊</span> الأدوية
        </h3>
        <ul style="color: var(--muted); line-height: 1.8; padding-right: 20px;">
          <li>التزم بالمواعيد والجرعات.</li>
          <li>لا تعدّل الجرعة بدون طبيب.</li>
          <li>احمل بطاقة تعريف طبية.</li>
        </ul>
      </article>

      <article class="glass-card" style="background: var(--bg-card); border-radius: 20px; padding: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow, 0 8px 32px rgba(0,0,0,.08));">
        <h3 style="color: var(--ink); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
           <span style="background: rgba(211,47,47,0.1); color: var(--red); padding: 8px; border-radius: 10px; font-size: 1.2rem;">⚠️</span> إشارات تحتاج انتباهًا
        </h3>
        <ul style="color: var(--muted); line-height: 1.8; padding-right: 20px;">
          <li>دوخة شديدة أو تعرّق.</li>
          <li>عطش مفرط أو جفاف.</li>
          <li>تكرار الهبوط الحاد.</li>
        </ul>
      </article>

    </div>
  </section>
</main>

<script>
  // Add simple animation for cards
  document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.glass-card');
    cards.forEach((card, index) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      setTimeout(() => {
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, 100 * index);
    });
  });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
