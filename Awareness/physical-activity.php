<?php
$base_path = '../';
$page_title = 'النشاط البدني - A.S.R';
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
    <h1>🏃 النشاط البدني</h1>
    <p>أهمية الحركة والرياضة في الوقاية من مرض السكري</p>
  </div>

  <div class="content-card">
    <h2>
      <span>💪</span>
      أهمية النشاط البدني للوقاية من السكري
    </h2>
    
    <p class="content-text">
      النشاط البدني المنتظم يساعد الجسم على استخدام الإنسولين بكفاءة أكبر، مما يقلل من خطر الإصابة بمرض السكري. توصي المنظمات الصحية بممارسة ما لا يقل عن 150 دقيقة من النشاط البدني المعتدل أسبوعيًا، مثل المشي السريع أو ركوب الدراجة.
    </p>

    <p class="content-text">
      إدخال الحركة في الحياة اليومية، مثل صعود السلالم أو المشي بدلًا من استخدام السيارة، يساهم في تحسين الصحة العامة والحفاظ على وزن صحي.
    </p>

    <h2 style="margin-top: 30px;">
      <span>🎯</span>
      نصائح عملية للنشاط البدني
    </h2>

    <div class="tips-box">
      <ul style="margin: 0; padding-right: 25px; line-height: 2;">
        <li><strong>ابدأ تدريجياً:</strong> 10 دقائق يومياً ثم زد المدة</li>
        <li><strong>المشي السريع:</strong> 30 دقيقة، 5 أيام في الأسبوع</li>
        <li><strong>تمارين المقاومة:</strong> 2-3 مرات أسبوعياً</li>
        <li><strong>اختر ما تحب:</strong> سباحة، دراجة، رقص، أي نشاط تستمتع به</li>
        <li><strong>استشر طبيبك:</strong> قبل البدء ببرنامج رياضي جديد</li>
      </ul>
    </div>

    <h2 style="margin-top: 30px;">
      <span>📊</span>
      فوائد النشاط البدني
    </h2>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
      <div style="background: #f0fdf4; padding: 15px; border-radius: 10px; text-align: center;">
        <div style="font-size: 2rem; margin-bottom: 10px;">❤️</div>
        <strong>صحة القلب</strong>
        <p style="font-size: 0.9rem; margin: 5px 0 0 0; color: #666;">يقوي القلب والأوعية الدموية</p>
      </div>
      <div style="background: #fef3c7; padding: 15px; border-radius: 10px; text-align: center;">
        <div style="font-size: 2rem; margin-bottom: 10px;">⚖️</div>
        <strong>الوزن الصحي</strong>
        <p style="font-size: 0.9rem; margin: 5px 0 0 0; color: #666;">يساعد في الحفاظ على وزن مثالي</p>
      </div>
      <div style="background: #e0e7ff; padding: 15px; border-radius: 10px; text-align: center;">
        <div style="font-size: 2rem; margin-bottom: 10px;">😊</div>
        <strong>الصحة النفسية</strong>
        <p style="font-size: 0.9rem; margin: 5px 0 0 0; color: #666;">يقلل التوتر ويحسن المزاج</p>
      </div>
    </div>
  </div>

  <a href="awareness.php" class="back-btn">
    <span>←</span>
    العودة إلى التوعية
  </a>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
