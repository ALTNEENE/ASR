<?php
$base_path = '../';
$page_title = 'التوعية - A.S.R';
$figma_page = 'awareness';
require_once __DIR__ . '/../google_sign_in/config/db.php';
require_once __DIR__ . '/../includes/awareness_articles.php';

awareness_ensure_table_and_seed($conn);
$awarenessArticles = awareness_fetch_articles($conn);
$awarenessModalData = [];
foreach ($awarenessArticles as $article) {
    $awarenessModalData[$article['slug']] = [
        'title' => $article['title_ar'],
        'content' => $article['body_html_ar'],
    ];
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">

<!-- Markdown Rendering Libraries -->
<script src="https://cdn.jsdelivr.net/npm/marked@11.1.1/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.8/dist/purify.min.js"></script>

<style>
  body { margin: 0; padding: 0; }

  .chat-container {
    background: var(--bg-card);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 30px -5px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-top: 0;
  }
  .chat-header {
    background: linear-gradient(135deg, rgba(47,127,122,0.08), rgba(47,127,122,0.15));
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
  }

  .chat-title {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
  }

  .chat-title h2 {
    margin: 0;
    font-size: 1.4rem;
    color: var(--primary);
    font-weight: 700;
  }

  .robot-icon {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
  }

  .chat-actions { display: flex; gap: 8px; }

  .action-btn {
    padding: 7px 14px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-main);
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
  }
  .action-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-1px);
  }

  /* Messages Area */
  .messages-area {
    height: 460px;
    overflow-y: auto;
    padding: 20px;
    background: var(--bg-page);
    scroll-behavior: smooth;
  }
  .messages-area::-webkit-scrollbar { width: 6px; }
  .messages-area::-webkit-scrollbar-track { background: transparent; }
  .messages-area::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; opacity: 0.4; }

  /* Message Bubble */
  .message {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    animation: slideIn 0.25s ease-out;
  }
  @keyframes slideIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .message.user { justify-content: flex-end; }

  .message-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.1rem;
  }
  .message.assistant .message-avatar { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
  .message.user .message-avatar { background: linear-gradient(135deg, #3b82f6, #1d4ed8); order: 2; }

  .message-content {
    max-width: 72%;
    padding: 12px 16px;
    border-radius: 16px;
  }
  .message.assistant .message-content {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    border-radius: 4px 16px 16px 16px;
  }
  .message.user .message-content {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-radius: 16px 4px 16px 16px;
    order: 1;
  }

  .message-text {
    font-size: 0.92rem;
    line-height: 1.7;
    margin: 0;
  }
  .message-text p { margin: 0 0 6px 0; }
  .message-text p:last-child { margin-bottom: 0; }

  .message-text strong {
    font-weight: 700;
  }

  /* Links inside AI responses — must be visible in both modes */
  .message-text a {
    color: var(--primary);
    text-decoration: underline;
    text-underline-offset: 2px;
  }
  [data-theme="dark"] .message-text a {
    color: #4ade80;  /* bright green readable on dark background */
  }
  .message-text a:hover {
    opacity: 0.8;
  }

  .message-timestamp {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 4px;
    text-align: left;
  }
  .message.user .message-timestamp { text-align: right; color: rgba(255,255,255,0.65); }

  /* Typing Indicator */
  .typing-indicator { display: none; align-items: center; gap: 10px; }
  .typing-indicator.active { display: flex; }
  .typing-dots {
    display: flex; gap: 4px;
    padding: 10px 14px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
  }
  .typing-dot {
    width: 7px; height: 7px;
    background: var(--primary);
    border-radius: 50%;
    animation: bounce 1.4s infinite;
  }
  .typing-dot:nth-child(2) { animation-delay: 0.2s; }
  .typing-dot:nth-child(3) { animation-delay: 0.4s; }
  @keyframes bounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-7px); }
  }

  /* Quick chips */
  .quick-chips {
    padding: 12px 20px;
    background: var(--bg-card);
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  .chip {
    padding: 6px 14px;
    background: linear-gradient(135deg, rgba(47,127,122,0.08), rgba(47,127,122,0.15));
    border: 1px solid rgba(47,127,122,0.3);
    border-radius: 20px;
    color: var(--primary);
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    font-family: inherit;
    font-weight: 600;
  }
  .chip:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(47,127,122,0.3);
  }

  /* Input Area */
  .input-area {
    padding: 16px 20px;
    background: var(--bg-card);
    border-top: 1px solid var(--border-color);
  }
  .input-form { display: flex; gap: 10px; align-items: flex-end; }
  .input-wrapper { flex: 1; }

  #user-input {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg-page);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-main);
    font-size: 0.95rem;
    font-family: inherit;
    resize: none;
    min-height: 46px;
    max-height: 120px;
    transition: all 0.2s;
    box-sizing: border-box;
  }
  #user-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(47,127,122,0.1);
  }
  #user-input::placeholder { color: var(--text-muted); }

  .send-btn {
    padding: 12px 22px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border: none;
    border-radius: 12px;
    color: white;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: inherit;
    box-shadow: 0 4px 12px rgba(47,127,122,0.3);
  }
  .send-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(47,127,122,0.4);
  }
  .send-btn:disabled { opacity: 0.5; cursor: not-allowed; }

  /* Disclaimer */
  .disclaimer {
    margin: 0;
    padding: 12px 20px;
    background: rgba(251,191,36,0.08);
    border-top: 1px solid rgba(251,191,36,0.25);
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .disclaimer-icon { font-size: 1.2rem; flex-shrink: 0; }
  .disclaimer-text { color: #b45309; font-size: 0.85rem; margin: 0; }

  @media (max-width: 768px) {
    .messages-area { height: 360px; padding: 14px; }
    .message-content { max-width: 88%; }
    .send-btn { padding: 12px 22px; font-size: 1rem; }
  }

  .btn-floating-back {
    position: fixed;
    top: 85px;
    right: 24px;
    z-index: 150;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-card);
    padding: 10px 18px;
    border-radius: 12px;
    font-weight: 700;
    color: var(--text-main);
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-decoration: none;
    transition: all 0.2s;
    font-size: 0.95rem;
  }
  .btn-floating-back:hover {
    transform: translateY(-2px);
    background: var(--primary);
    color: white;
    border-color: var(--primary);
  }
  .btn-floating-back svg { transition: transform 0.2s; }
  .btn-floating-back:hover svg { transform: translateX(4px); }
</style>

<!-- Awareness Section (Above Chat) -->
<main class="container" style="padding: 40px 0 20px 0;">
  <a href="../welcome.php" class="btn-floating-back">
    <span>رجوع</span>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 19 12 12 5"></polyline>
    </svg>
  </a>
  
  <div class="card" style="margin-bottom: 30px; background: var(--bg-card); color: var(--text-main);">
    <h1 style="margin: 0 0 10px 0; color: var(--primary);">التوعية الصحية</h1>
    <p style="color: var(--text-muted); margin: 0; font-size: 18px;">معلومات وإرشادات صحية موثوقة لمساعدة مرضى السكري على عيش حياة صحية.</p>
  </div>

  <div class="card" style="margin-bottom: 30px; background: linear-gradient(135deg, rgba(47,127,122,0.08), rgba(47,127,122,0.16)); border: 1px solid rgba(47,127,122,0.22);">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
      <div>
        <h2 style="margin: 0 0 8px; color: var(--primary);">ابدأ بالمساعد الذكي أولاً</h2>
        <p style="margin: 0; color: var(--text-main); line-height: 1.8;">إذا أردت سؤالاً سريعاً أو توجيهاً مباشراً، افتح صفحة المساعد الذكي أولاً ثم ارجع إلى بطاقات التوعية لقراءة التفاصيل الكاملة.</p>
      </div>
      <a href="assistant.php" class="btn btn-primary" style="white-space: nowrap;">فتح المساعد الذكي</a>
    </div>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px;">
    <?php foreach ($awarenessArticles as $article): ?>
      <div class="card" style="display: flex; flex-direction: column; background: var(--bg-card);">
        <h3 style="color: var(--primary); margin-top: 0;"><?php echo htmlspecialchars((string)$article['title_ar']); ?></h3>
        <p style="color: var(--text-main); flex-grow: 1;"><?php echo htmlspecialchars((string)$article['summary_ar']); ?></p>
        <button class="btn btn-primary" onclick="openModal('<?php echo htmlspecialchars((string)$article['slug']); ?>')" style="margin-top: 15px; text-align: center;">اقرأ المزيد</button>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (false): ?>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px;">
    
    <!-- Diabetes Types (Modal) -->
    <div class="card" style="display: flex; flex-direction: column; background: var(--bg-card);">
      <h3 style="color: var(--primary); margin-top: 0;">أنواع السكري</h3>
      <p style="color: var(--text-main); flex-grow: 1;">تعرف على الفروقات الأساسية بين النوع الأول، النوع الثاني، وسكري الحمل، وكيفية التعامل مع كل نوع.</p>
      <button class="btn btn-primary" onclick="openModal('types')" style="margin-top: 15px; text-align: center;">اقرأ المزيد</button>
    </div>

    <!-- Nutrition (Modal) -->
    <div class="card" style="display: flex; flex-direction: column; background: var(--bg-card);">
      <h3 style="color: var(--primary); margin-top: 0;">التغذية السليمة</h3>
      <p style="color: var(--text-main); flex-grow: 1;">أهمية النظام الغذائي المتوازن، حساب الكربوهيدرات، والأطعمة المناسبة لضبط مستوى السكر.</p>
      <button class="btn btn-primary" onclick="openModal('nutrition')" style="margin-top: 15px; text-align: center;">اقرأ المزيد</button>
    </div>

    <!-- Physical Activity (Modal) -->
    <div class="card" style="display: flex; flex-direction: column; background: var(--bg-card);">
      <h3 style="color: var(--primary); margin-top: 0;">النشاط البدني</h3>
      <p style="color: var(--text-main); flex-grow: 1;">دور الرياضة في تحسين حساسية الإنسولين وضبط الوزن، ونصائح لممارسة الرياضة بأمان.</p>
      <button class="btn btn-primary" onclick="openModal('activity')" style="margin-top: 15px; text-align: center;">اقرأ المزيد</button>
    </div>

    <!-- Blood Sugar Levels (Modal) -->
    <div class="card" style="display: flex; flex-direction: column; background: var(--bg-card);">
      <h3 style="color: var(--primary); margin-top: 0;">معدل السكر حسب العمر</h3>
      <p style="color: var(--text-main); flex-grow: 1;">تعرف على مستويات السكر الطبيعية في الدم بناءً على العمر والحالة الصحية، وجدول لتتبع السكر التراكمي.</p>
      <button class="btn btn-primary" onclick="openModal('sugar_levels')" style="margin-top: 15px; text-align: center;">اقرأ المزيد</button>
    </div>

    <!-- Organs Impact (Modal) -->
    <div class="card" style="display: flex; flex-direction: column; background: var(--bg-card);">
      <h3 style="color: var(--primary); margin-top: 0;">تأثير السكري على الأعضاء</h3>
      <p style="color: var(--text-main); flex-grow: 1;">تعرف على المضاعفات المحتملة للسكري على أجهزة الجسم المختلفة (القلب، الكلى، العين، وغيرها) وكيفية الوقاية منها.</p>
      <button class="btn btn-primary" onclick="openModal('organs_impact')" style="margin-top: 15px; text-align: center;">اقرأ المزيد</button>
    </div>

  </div>
  <?php endif; ?>
</main>

<!-- FLOATING MODAL OVERLAY -->
<div id="floating-modal" class="modal-overlay" style="display: none;">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="modal-title" style="margin: 0; color: var(--primary);"></h2>
      <button onclick="closeModal()" class="close-btn" aria-label="إغلاق">&times;</button>
    </div>
    <div id="modal-body" class="modal-body">
      <!-- Text content will be injected here -->
    </div>
    <div class="modal-footer">
      <button onclick="closeModal()" class="btn btn-secondary">إغلاق</button>
    </div>
  </div>
</div>

<style>
  /* Modal Styles */
  .modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
  }

  .modal-overlay.active {
    opacity: 1;
    visibility: visible;
  }

  .modal-content {
    background: var(--bg-card);
    width: 90%;
    max-width: 700px;
    border-radius: 16px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    transform: scale(0.9);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border-color);
  }

  .modal-overlay.active .modal-content {
    transform: scale(1);
  }

  .modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .close-btn {
    background: none;
    border: none;
    font-size: 2rem;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
  }

  .close-btn:hover {
    color: #ef4444;
  }

  .modal-body {
    padding: 25px;
    overflow-y: auto;
    font-size: 1.1rem;
    line-height: 1.8;
    color: var(--text-main);
    text-align: justify;
  }

  .modal-footer {
    padding: 15px 25px;
    border-top: 1px solid var(--border-color);
    text-align: left;
    background: var(--bg-page);
    border-radius: 0 0 16px 16px;
  }

  .btn-secondary {
    background: var(--muted);
    color: white;
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
  }
</style>

<script>
/* =========================================
   MODAL LOGIC FOR AWARENESS CARDS
   ========================================= */
const modalOverlay = document.getElementById('floating-modal');
const modalTitle = document.getElementById('modal-title');
const modalBody = document.getElementById('modal-body');

const awarenessContent = <?php echo json_encode($awarenessModalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

// Legacy inline content retained for reference
const awarenessContentLegacy = {
  types: {
    title: "الأنواع الرئيسية للسكري",
    content: `
      <div style='margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);'>
        <h3 style='color: var(--primary); margin-top: 0;'>النوع الأول (Type 1)</h3>
        <p>نقص شديد أو انعدام تام في إنتاج الإنسولين من البنكرياس. يحدث غالباً في سن مبكرة ويحتاج المريض عادةً لحقن الإنسولين مدى الحياة والمتابعة الدقيقة لمستويات السكر.</p>
      </div>
      
      <div style='margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);'>
        <h3 style='color: var(--primary); margin-top: 0;'>النوع الثاني (Type 2)</h3>
        <p>يحدث بسبب مقاومة الجسم للإنسولين أو عدم كفاية إفرازه. يرتبط بشكل كبير بنمط الحياة والوراثة والوزن الزائد. يمكن التحكم به غالباً عبر النظام الغذائي، الرياضة، والأدوية الفموية.</p>
      </div>
      
      <div style='margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);'>
        <h3 style='color: var(--primary); margin-top: 0;'>سكري الحمل</h3>
        <p>ارتفاع سكر الدم الذي يظهر لأول مرة أثناء الحمل. يحتاج لمراقبة دقيقة للحفاظ على صحة الأم والجنين، وغالباً ما يختفي بعد الولادة لكنه قد يزيد خطر الإصابة بالسكري مستقبلاً.</p>
      </div>
      
      <div>
        <h3 style='color: var(--primary); margin-top: 0;'>أنواع أخرى خاصة (Other Specific Types)</h3>
        <ul style="padding-right: 20px; color: var(--text-main); line-height: 1.9;">
          <li><strong>خلل وراثي في وظيفة خلايا بيتا (MODY):</strong> ويُعرف بسكري الشبان الناضجين، ويتميز بظهور السكر في سن مبكرة (قبل 25 سنة).</li>
          <li><strong>السكري الوليدي (Neonatal Diabetes):</strong> يظهر قبل عمر 6 أشهر وقد يكون عابراً أو دائماً.</li>
          <li><strong>أمراض البنكرياس الخارجية:</strong> مثل الالتهابات، التليف، أو الاستئصال الجراحي للبنكرياس.</li>
          <li><strong>السكري الناتج عن الأدوية:</strong> مثل الاستخدام المزمن للستيرويدات (الكورتيزون) أو الثيازيد.</li>
          <li><strong>السكري المناعي الكامن لدى الكبار (LADA):</strong> نوع يشبه النوع الأول لكنه يظهر في سن متأخرة عند البالغين.</li>
        </ul>
      </div>
    `
  },
  nutrition: {
    title: "التغذية السليمة والوقاية من مرض السكري",
    content: `
      <p style="color: var(--text-main);">اتباع نظام غذائي صحي ومتوازن يلعب دوراً أساسياً في الوقاية من مرض السكري، خاصة النوع الثاني. يُنصح بالإكثار من تناول الخضروات والفواكه الطازجة، والحبوب الكاملة مثل القمح الكامل والشوفان، مع تقليل استهلاك السكريات المضافة والمشروبات الغازية.</p>
      <p style="color: var(--text-main);">كما يُفضل اختيار الدهون الصحية مثل زيت الزيتون، وتجنب الأطعمة المصنعة والوجبات السريعة. التحكم في حجم الوجبات وتنظيم أوقات الأكل يساعد أيضاً في الحفاظ على مستوى السكر في الدم ضمن المعدلات الطبيعية.</p>
    `
  },
  activity: {
    title: "أهمية النشاط البدني للوقاية من السكري",
    content: `
      <p style="color: var(--text-main);">النشاط البدني المنتظم يساعد الجسم على استخدام الإنسولين بكفاءة أكبر، مما يقلل من خطر الإصابة بمرض السكري. توصي المنظمات الصحية بممارسة ما لا يقل عن 150 دقيقة من النشاط البدني المعتدل أسبوعياً، مثل المشي السريع أو ركوب الدراجة.</p>
      <p style="color: var(--text-main);">إدخال الحركة في الحياة اليومية، مثل صعود السلالم أو المشي بدلاً من استخدام السيارة، يساهم في تحسين الصحة العامة والحفاظ على وزن صحي.</p>
    `
  },
  sugar_levels: {
    title: "جدول معدل السكر حسب العمر",
    content: `
      <p style="color: var(--text-main);">يُعتبر مرض السكري من أكثر الأمراض المزمنة انتشاراً في العالم، ويعتمد التحكم في هذا المرض بشكل كبير على مراقبة مستويات السكر في الدم بانتظام. تختلف مستويات السكر الطبيعية في الدم بناءً على العمر والحالة الصحية العامة للفرد.</p>

      <h3 style='color: var(--primary); margin-top: 20px;'>جدول معدل السكر الطبيعي حسب العمر</h3>
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; text-align: right; min-width: 500px; color: var(--text-main);">
          <tr style="background-color: rgba(47,127,122,0.1); border-bottom: 2px solid var(--primary);">
            <th style="padding: 10px; border: 1px solid var(--border-color);">العمر</th>
            <th style="padding: 10px; border: 1px solid var(--border-color);">قبل الأكل (mg/dL)</th>
            <th style="padding: 10px; border: 1px solid var(--border-color);">بعد الأكل بساعتين (mg/dL)</th>
            <th style="padding: 10px; border: 1px solid var(--border-color);">السكر التراكمي (%)</th>
          </tr>
          <tr>
            <td style="padding: 10px; border: 1px solid var(--border-color);">20-30 سنة</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">70-110</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">أقل من 140</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">أقل من 5.7</td>
          </tr>
          <tr>
            <td style="padding: 10px; border: 1px solid var(--border-color);">31-40 سنة</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">70-110</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">أقل من 140</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">أقل من 5.7</td>
          </tr>
          <tr>
            <td style="padding: 10px; border: 1px solid var(--border-color);">41-50 سنة</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">70-110</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">أقل من 140</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">أقل من 5.7</td>
          </tr>
          <tr>
            <td style="padding: 10px; border: 1px solid var(--border-color);">51-60 سنة</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">70-120</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">أقل من 140</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">أقل من 5.7</td>
          </tr>
          <tr>
            <td style="padding: 10px; border: 1px solid var(--border-color);">فوق 60 سنة</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">70-130</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">أقل من 140</td>
            <td style="padding: 10px; border: 1px solid var(--border-color);">أقل من 6.0</td>
          </tr>
        </table>
      </div>

      <h3 style='color: var(--primary); margin-top: 20px;'>معدل السكر الطبيعي تفصيلياً حسب الفئات العمرية</h3>
      <p style="color: var(--text-main);"><strong>في سن الثلاثين والأربعين:</strong> من المهم الحفاظ على معدل السكر في الدم ضمن النطاق الطبيعي لتجنب المشاكل الصحية. مع التقدم في العمر وتجاوز الأربعين، يزداد العرضة، فيجب أن يكون مستوى السكر قبل الأكل بين 70-110 وبعد الأكل أقل من 140.</p>
      <p style="color: var(--text-main);"><strong>في سن الخمسين والستين:</strong> تصبح الحاجة للمراقبة أكبر. في سن الستين وما فوق، يمكن أن تكون مستويات السكر المسموحة أعلى قليلاً (70-130 قبل الأكل). من الضروري الالتزام بالعلاج الطبي المناسب.</p>

      <h3 style='color: var(--primary); margin-top: 20px;'>العوامل المؤثرة على مستويات السكر</h3>
      <ul style="padding-right: 20px; color: var(--text-main);">
        <li style="margin-bottom: 8px;"><strong>النظام الغذائي:</strong> تناول الأطعمة الغنية بالكربوهيدرات يؤدي لارتفاع السكر.</li>
        <li style="margin-bottom: 8px;"><strong>النشاط البدني:</strong> يحسن حساسية الجسم للإنسولين.</li>
        <li style="margin-bottom: 8px;"><strong>الإجهاد:</strong> يزيد من إفراز الهرمونات التي ترفع السكر بالدم.</li>
        <li style="margin-bottom: 8px;"><strong>الأدوية والأمراض:</strong> الالتهابات وبعض الأدوية تسبب ارتفاعاً طبيعياً.</li>
      </ul>

      <h3 style='color: var(--primary); margin-top: 20px;'>نسبة السكر الطبيعية على الريق وبعد الأكل</h3>
      <p style="color: var(--text-main);">يُعد قياس السكر <strong>على الريق</strong> من أهم الفحوصات ويجب أن يتراوح بين 70 إلى 110 للبالغين الصحيين. أما <strong>بعد الأكل بساعتين</strong>، فيرتفع المستوى بشكل طبيعي نتيجة الهضم، ولكنه يجب ألا يتجاوز حد 140 للحفاظ على الاستقرار.</p>
    `
  },
  organs_impact: {
    title: "تأثير السكري على أعضاء الجسم",
    content: `
      <h3 style='color: var(--primary); margin-top: 0;'>القلب والأوعية الدموية</h3>
      <ul style="padding-right: 20px; color: var(--text-main); margin-bottom: 20px;">
        <li>يزيد خطر الإصابة بأمراض القلب التاجية والسكتة الدماغية.</li>
        <li>يسبب تصلب الشرايين وارتفاع ضغط الدم.</li>
      </ul>

      <h3 style='color: var(--primary); margin-top: 0;'>الكلى (اعتلال الكلية السكري)</h3>
      <ul style="padding-right: 20px; color: var(--text-main); margin-bottom: 20px;">
        <li>يؤدي إلى تلف الأوعية الدقيقة في الكلى.</li>
        <li>قد يتطور إلى فشل كلوي مزمن يحتاج لغسيل أو زراعة كلية.</li>
      </ul>

      <h3 style='color: var(--primary); margin-top: 0;'>العينين (اعتلال الشبكية)</h3>
      <ul style="padding-right: 20px; color: var(--text-main); margin-bottom: 20px;">
        <li>يسبب اعتلال الشبكية السكري نتيجة تلف الأوعية الدقيقة.</li>
        <li>قد يؤدي إلى ضعف البصر أو العمى التدريجي إذا لم يُعالج.</li>
      </ul>

      <h3 style='color: var(--primary); margin-top: 0;'>الأعصاب الطرفية</h3>
      <ul style="padding-right: 20px; color: var(--text-main); margin-bottom: 20px;">
        <li>يسبب تنميل، وخز، أو فقدان الإحساس في الأطراف (القدمين واليدين).</li>
        <li>يزيد من خطر قرح القدم وبتر الأطراف لعدم الشعور بالجروح.</li>
      </ul>

      <h3 style='color: var(--primary); margin-top: 0;'>الجلد والمناعة</h3>
      <ul style="padding-right: 20px; color: var(--text-main); margin-bottom: 20px;">
        <li>بطء التئام الجروح وزيادة القابلية للعدوى.</li>
        <li>التهابات جلدية وفطرية أكثر شيوعاً.</li>
      </ul>

      <h3 style='color: var(--primary); margin-top: 0;'>الأسنان والفم</h3>
      <ul style="padding-right: 20px; color: var(--text-main); margin-bottom: 20px;">
        <li>أمراض اللثة أكثر شيوعاً بسبب ضعف المناعة.</li>
        <li>ارتفاع السكر في اللعاب يزيد من خطر التسوس.</li>
        <li>جفاف الفم يؤدي إلى تقرحات ورائحة كريهة وعدوى فطرية.</li>
      </ul>

      <div style="background: rgba(47,127,122,0.1); padding: 20px; border-radius: 12px; margin-top: 30px; border: 1px solid rgba(47,127,122,0.3);">
        <h3 style="color: var(--primary); margin-top: 0;">نصائح وقائية هامة</h3>
        <ul style="padding-right: 20px; color: var(--text-main); margin-bottom: 0px;">
          <li>ضبط مستوى السكر في الدم باستمرار.</li>
          <li>اتبع نظاماً غذائياً متكاملاً والنشاط البدني بانتظام.</li>
          <li>الفحص الدوري المتواصل (العين، الكلى، القدمين، الأسنان).</li>
        </ul>
      </div>
    `
  }
};

function openModal(type) {
  if (awarenessContent[type]) {
    modalTitle.textContent = awarenessContent[type].title;
    modalBody.innerHTML = awarenessContent[type].content;
    
    modalOverlay.style.display = 'flex';
    setTimeout(() => {
      modalOverlay.classList.add('active');
    }, 10);
    
    document.body.style.overflow = 'hidden';
  } else if (awarenessContentLegacy[type]) {
    modalTitle.textContent = awarenessContentLegacy[type].title;
    modalBody.innerHTML = awarenessContentLegacy[type].content;
    
    modalOverlay.style.display = 'flex';
    setTimeout(() => {
      modalOverlay.classList.add('active');
    }, 10);
    
    document.body.style.overflow = 'hidden';
  }
}

function closeModal() {
  modalOverlay.classList.remove('active');
  setTimeout(() => {
    modalOverlay.style.display = 'none';
    document.body.style.overflow = '';
  }, 300);
}

// Close on outside click
modalOverlay.addEventListener('click', (e) => {
  if (e.target === modalOverlay) {
    closeModal();
  }
});

// Close on ESC key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && modalOverlay.classList.contains('active')) {
    closeModal();
  }
});

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
