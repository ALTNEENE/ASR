<?php
$base_path = '../';
$page_title = 'التغذية السليمة - A.S.R';
$figma_page = 'awareness';
include __DIR__ . '/../includes/header.php';
?>

<style>
  body {
    background-color: var(--bg);
    color: var(--ink);
  }

  .awareness-container {
    max-width: 900px;
    margin: 40px auto;
    padding: 0 20px;
  }

  .page-header {
    text-align: center;
    margin-bottom: 40px;
  }

  .page-header h1 {
    color: var(--primary);
    font-size: 2.5rem;
    margin-bottom: 15px;
  }

  .page-header p {
    color: #64748b;
    font-size: 1.1rem;
    line-height: 1.6;
  }

  .content-card {
    background: var(--surface);
    border-radius: 16px;
    padding: 40px;
    box-shadow: var(--shadow-soft);
    border: 1px solid var(--border);
    margin-bottom: 30px;
  }

  .content-card h2 {
    color: var(--primary);
    font-size: 1.8rem;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .content-text {
    color: var(--ink);
    font-size: 1.1rem;
    line-height: 1.9;
    text-align: justify;
    margin-bottom: 25px;
  }

  .tips-box {
    background: #e6fffa;
    padding: 20px;
    border-radius: 12px;
    border-right: 4px solid var(--primary);
    margin-top: 20px;
    color: var(--ink);
  }

  [data-theme="dark"] .tips-box {
    background: rgba(45, 212, 191, 0.1);
    color: var(--ink);
  }

  .back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--surface);
    color: var(--primary);
    padding: 12px 24px;
    border-radius: 10px;
    text-decoration: none;
    border: 2px solid var(--primary);
    font-weight: 600;
    transition: all 0.2s;
  }

  .back-btn:hover {
    background: var(--primary);
    color: white;
    transform: translateX(5px);
  }
</style>

<main class="awareness-container">
  
  <div class="page-header">
    <h1>🥗 التغذية السليمة</h1>
    <p>دليلك الشامل للتغذية الصحية والوقاية من مرض السكري</p>
  </div>

  <div class="content-card">
    <h2>
      <span>📋</span>
      التغذية السليمة والوقاية من مرض السكري
    </h2>
    
    <p class="content-text">
      اتباع نظام غذائي صحي ومتوازن يلعب دورًا أساسيًا في الوقاية من مرض السكري، خاصة النوع الثاني. يُنصح بالإكثار من تناول الخضروات والفواكه الطازجة، والحبوب الكاملة مثل القمح الكامل والشوفان، مع تقليل استهلاك السكريات المضافة والمشروبات الغازية.
    </p>

    <p class="content-text">
      كما يُفضل اختيار الدهون الصحية مثل زيت الزيتون، وتجنب الأطعمة المصنعة والوجبات السريعة. التحكم في حجم الوجبات وتنظيم أوقات الأكل يساعد أيضًا في الحفاظ على مستوى السكر في الدم ضمن المعدلات الطبيعية.
    </p>

    <h2 style="margin-top: 30px;">
      <span>✅</span>
      نصائح عملية للتغذية الصحية
    </h2>

    <div class="tips-box">
      <ul style="margin: 0; padding-right: 25px; line-height: 2;">
        <li><strong>أكثر من الخضروات:</strong> املأ نصف طبقك بالخضروات الملونة</li>
        <li><strong>اختر الحبوب الكاملة:</strong> أرز بني، خبز أسمر، شوفان</li>
        <li><strong>قلل السكريات:</strong> تجنب المشروبات الغازية والحلويات</li>
        <li><strong>اشرب الماء:</strong> 8 أكواب يومياً على الأقل</li>
        <li><strong>نظم وجباتك:</strong> 3 وجبات رئيسية + وجبتين خفيفتين</li>
      </ul>
    </div>
  </div>

  <a href="awareness.php" class="back-btn">
    <span>←</span>
    العودة إلى التوعية
  </a>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
