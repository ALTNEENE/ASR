<?php
$base_path = '../';
$page_title = 'أدوات قياس السكري - A.S.R';
$figma_page = 'tools';
require_once __DIR__ . '/../google_sign_in/auth/auth_check.php';
include __DIR__ . '/../includes/header.php';
?>

<style>
  .main-grid {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 30px;
    margin-top: 20px;
  }

  @media (max-width: 900px) {
    .main-grid {
      grid-template-columns: 1fr;
    }
  }

  .form-group {
    margin-bottom: 20px;
  }

  .form-group label {
    display: block;
    color: var(--ink);
    font-weight: bold;
    margin-bottom: 8px;
    font-size: 0.95rem;
  }

  .input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
  }

  .form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 10px;
    font-size: 1rem;
    transition: border-color 0.2s;
    background: var(--surface-2);
    color: var(--ink);
    font-family: inherit;
  }

  .form-control:focus {
    outline: none;
    border-color: var(--green);
  }

  .btn-submit {
    background: linear-gradient(135deg, var(--green), var(--green-dark));
    color: white;
    width: 100%;
    padding: 15px;
    border: none;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    box-shadow: 0 4px 16px rgba(46,125,50,0.3);
  }

  .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(46,125,50,0.4);
  }

  /* History Table Styling */
  .table-wrap {
    max-height: 70vh;
    overflow-y: auto;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: var(--bg-card);
  }

  @media (min-width: 1024px) {
    .table-wrap {
      max-height: 520px;
    }
  }

  /* Scrollbar styling */
  .table-wrap::-webkit-scrollbar { width: 6px; }
  .table-wrap::-webkit-scrollbar-track { background: transparent; }
  .table-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

  .table-container {
    overflow-x: auto;
  }

  .history-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
  }

  .history-table thead th {
    position: sticky;
    top: 0;
    z-index: 5;
    background: var(--surface-2);
    padding: 15px;
    text-align: right;
    color: var(--ink);
    font-weight: 700;
    border-bottom: 2px solid var(--border);
    /* Prevent gap between sticky rows */
    box-shadow: 0 1px 0 var(--border);
  }

  .history-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    color: var(--ink);
  }

  .history-table tr:last-child td {
    border-bottom: none;
  }

  .status-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 700;
    display: inline-block;
  }

  .status-normal { background: rgba(46,125,50,0.15); color: var(--green); }
  .status-warning { background: rgba(230,81,0,0.15); color: #E65100; }
  .status-danger { background: rgba(211,47,47,0.15); color: var(--red); }

  /* Medication chip styles */
  .med-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    background: rgba(46,125,50,0.1);
    border: 1px solid rgba(46,125,50,0.2);
    font-size: 0.85rem;
    color: var(--ink);
    animation: chipIn 0.25s ease;
  }
  .med-chip .chip-remove {
    cursor: pointer;
    color: var(--red);
    font-weight: bold;
    font-size: 1rem;
    line-height: 1;
    margin-right: 2px;
  }
  .med-chip .chip-remove:hover { opacity: 0.8; }
  @keyframes chipIn {
    from { opacity: 0; transform: scale(0.85); }
    to { opacity: 1; transform: scale(1); }
  }

  /* Saved medications card */
  .saved-meds-card {
    margin-top: 30px;
  }
  .saved-med-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
  }
  .saved-med-item:hover { background: var(--surface-2); }
  .saved-med-item:last-child { border-bottom: none; }
  .saved-med-delete {
    cursor: pointer;
    color: var(--red);
    font-size: 0.85rem;
    padding: 4px 10px;
    border-radius: 6px;
    border: 1px solid rgba(211,47,47,0.2);
    background: rgba(211,47,47,0.06);
    transition: 0.2s;
  }
  .saved-med-delete:hover { background: rgba(211,47,47,0.15); }

  /* Links inside guidance / tips section */
  #link-tips a {
    color: var(--primary);
    text-decoration: underline;
    text-underline-offset: 2px;
  }
  [data-theme="dark"] #link-tips a {
    color: #4ade80;
  }
  #link-tips a:hover { opacity: 0.8; }
</style>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
  .flatpickr-calendar { font-family: 'Tajawal', sans-serif; direction: rtl; }
</style>

<main class="container" style="padding: 40px 0;">
  <a href="dashboard.php" class="page-back-btn" data-back-button>
    <span>←</span>
    <span>رجوع</span>
  </a>
  
  <div class="hero" style="margin-bottom: 40px; text-align: center; border: 1px solid var(--border); background: var(--bg-card); border-radius: 20px; padding: 40px; box-shadow: var(--card-shadow, 0 4px 24px rgba(0,0,0,.08));">
    <h1 style="font-size: 2.2rem; margin-bottom: 15px; color: var(--ink);">أدوات القياس الذكية</h1>
    <p class="lead" style="color: var(--muted); font-size: 1.1rem;">سجل قراءاتك بدقة واترك الباقي لنظامنا ليقوم بالتحليل والتوصية</p>
  </div>

  <div class="main-grid">
    
    <!-- Input Section -->
    <div class="glass-card" style="background: var(--bg-card); border-radius: 20px; padding: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow, 0 8px 32px rgba(0,0,0,.08)); height: fit-content;">
      <h3 style="margin-top:0; margin-bottom: 25px; color: var(--green); display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
        <span style="background: rgba(46,125,50,0.1); color: var(--green); padding: 8px; border-radius: 10px; display: inline-flex; font-size: 1.2rem;">📝</span> تسجيل قراءة جديدة
      </h3>
      
      <form id="glucoseForm">
        <div class="form-group">
          <label for="reading">قيمة السكر</label>
          <div style="display: flex; gap: 10px;">
            <input id="reading" type="number" name="reading" class="form-control" step="0.1" required style="flex: 2;" min="20" max="600" oninput="this.value = !!this.value && Math.abs(this.value) >= 0 ? Math.abs(this.value) : null">
            <select id="unit" name="unit" class="form-control" style="flex: 1;">
              <option value="mg">mg/dL</option>
              <option value="mmol">mmol/L</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="context">توقيت القياس</label>
          <select id="context" name="context" class="form-control" required>
            <option value="fasting">🌙 صائم</option>
            <option value="post">🍽️ بعد الأكل</option>
          </select>
        </div>

        <div class="form-group">
          <label for="psychological_status">الحالة النفسية (مهم جداً)</label>
          <select id="psychological_status" name="psychological_status" class="form-control" required>
            <option value="Normal">😐 طبيعي</option>
            <option value="Happy">🙂 سعيد / مرتاح</option>
            <option value="Stressed">😫 متوتر / قلق</option>
            <option value="Sad">😔 حزين</option>
            <option value="Sick">🤒 مريض / تعبان</option>
          </select>
        </div>

        <div class="form-group">
          <label for="reading-date">التاريخ</label>
          <input id="reading-date" type="text" placeholder="سنة / شهر / يوم" name="reading-date" class="form-control" required style="background: var(--surface-2);">
        </div>

        <div class="form-group">
          <label for="reading-note">ما الأعراض الحالية؟ (اختياري)</label>
          <textarea id="reading-note" name="note" class="form-control" rows="2"></textarea>
        </div>

        <div class="form-group">
          <label for="medications">💊 الأدوية المستخدمة (اختياري)</label>
          <input id="medications" type="text" name="medications" class="form-control" autocomplete="off">
          <!-- AI suggestion banner -->
          <div id="ai-suggestion" style="display:none; margin-top:8px; padding:10px 14px; border-radius:10px; background:linear-gradient(135deg, rgba(46,125,50,0.08), rgba(46,125,50,0.15)); border:1px solid rgba(46,125,50,0.25); cursor:pointer; transition:all 0.2s;">
            <span style="font-size:0.85rem; color:var(--green); font-weight:600;">🤖 اقتراح المساعد الذكي: </span>
            <span id="ai-suggestion-name" style="font-weight:700; color:var(--ink);"></span>
            <span id="ai-suggestion-reason" style="font-size:0.8rem; color:var(--muted); display:block; margin-top:2px;"></span>
          </div>
          <div id="ai-no-match" style="display:none; margin-top:8px; padding:8px 12px; border-radius:8px; background:rgba(211,47,47,0.08); border:1px solid rgba(211,47,47,0.2); font-size:0.85rem; color:var(--red);">
            ⚠️ ما لقيت مطابقة، اختار من القائمة
          </div>
          <!-- Selected medications as chips -->
          <div id="selected-medications" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;"></div>
        </div>

        <div class="form-group">
          <label for="last_medication_dose">آخر مرة أخذت فيها الدواء؟ (اختياري)</label>
          <select id="last_medication_dose" name="last_medication_dose" class="form-control">
            <option value="">— اختر —</option>
            <option value="الجرعة اليوم">✅ اليوم (خلال 24 ساعة)</option>
            <option value="الجرعة أمس">📅 أمس</option>
            <option value="منذ أسبوع">⏳ منذ 3 إلى 7 أيام</option>
            <option value="منذ أكثر من أسبوع">⚠️ منذ أكثر من أسبوع</option>
            <option value="منذ شهر فأكثر">🛑 منذ شهر أو أكثر</option>
            <option value="لا آخذ دواء">🚫 لا آخذ دواء حالياً</option>
          </select>
        </div>

        <div class="form-group">
          <label for="weight_kg">الوزن (كجم) — اختياري</label>
          <input id="weight_kg" type="number" name="weight_kg" class="form-control" min="20" max="300" step="0.1">
        </div>

        <button type="submit" class="btn-submit">تحليل وحفظ القراءة</button>
      </form>

      <!-- Saved Medications Section -->
      <div class="saved-meds-card">
        <h3 style="margin-top:25px; margin-bottom:15px; color:var(--green); display:flex; align-items:center; gap:10px; font-size:1.1rem;">
          <span>💊</span> الأدوية المستخدمة
        </h3>
        <div id="saved-meds-list" style="border:1px solid var(--border); border-radius:12px; overflow:hidden;">
          <div style="text-align:center; padding:20px; color:var(--muted);">جاري التحميل...</div>
        </div>
      </div>
    </div>

    <!-- Results Section -->
    <div class="glass-card" style="background: var(--bg-card); border-radius: 20px; padding: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow, 0 8px 32px rgba(0,0,0,.08)); height: fit-content;">
      <h3 style="margin-top:0; margin-bottom: 25px; color: var(--green); display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
        <span style="background: rgba(46,125,50,0.1); color: var(--green); padding: 8px; border-radius: 10px; display: inline-flex; font-size: 1.2rem;">📊</span> سجل المتابعة
      </h3>

      <div id="result" hidden style="padding: 20px; margin-bottom: 20px; border-radius: 12px;"></div>
      
      <div id="guidance" hidden style="background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 20px;">
        <h4 style="margin-top: 0; color: var(--green); border-bottom: 1px solid var(--border); padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">
          <span style="font-size: 1.2rem;">💡</span> توصيات وإرشادات
        </h4>
        
        <div style="display: grid; gap: 15px;">
          <div>
            <strong style="color: var(--green); display: block; margin-bottom: 5px;">✅ نصائح غذائية:</strong>
            <ul id="diet-tips" style="margin: 0; padding-right: 20px; color: var(--ink);"></ul>
          </div>
          
          <div>
            <strong style="color: var(--red); display: block; margin-bottom: 5px;">⚠️ تجنب ما يلي:</strong>
            <ul id="avoid-tips" style="margin: 0; padding-right: 20px; color: var(--ink);"></ul>
          </div>

          <div>
             <strong style="color: #2563eb; display: block; margin-bottom: 5px;">🔗 روابط هامة:</strong>
             <ul id="link-tips" style="margin: 0; padding-right: 20px; list-style: none;"></ul>
          </div>
        </div>
      </div>
      
      <!-- Report Actions -->
      <div class="report-actions" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 10px;">
             <!-- View Report Button -->
            <button id="view-report-btn" class="btn ghost" style="width: 100%; border: 1px dashed var(--green); background: var(--surface-2); color: var(--green); font-weight: bold; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 15px; border-radius: 12px; cursor: pointer;">
            <span>📄</span> عرض التقرير
            </button>
        </div>
      </div>

      <div class="table-wrap">
        <div class="table-container">
          <table class="history-table">
            <thead>
              <tr>
                <th>التاريخ</th>
                <th>الوقت</th>
                <th>القراءة</th>
                <th>التوقيت</th>
                <th>التقييم</th>
                <th>الحالة النفسية</th>
                <th>الأعراض</th>
                <th>الأدوية</th>
              </tr>
            </thead>
            <tbody id="history-body">
              <!-- Loading State -->
              <tr id="history-empty"><td colspan="7" style="text-align:center; padding: 30px; color: var(--muted);">جاري تحميل البيانات...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</main>

<script src="../assets/js/autocomplete.js"></script>
<script src="../assets/js/tools.js?v=<?php echo time(); ?>"></script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
<script>
// Button functionality for Follow-up Record section
document.addEventListener('DOMContentLoaded', function() {
  // Initialize Flatpickr
  flatpickr("#reading-date", {
    locale: "ar",
    dateFormat: "Y-m-d",
    maxDate: "today",
    disableMobile: "true"
  });
  // View Report Button
  const viewReportBtn = document.getElementById('view-report-btn');
  if (viewReportBtn) {
    viewReportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      window.open('../main/report.php', '_blank');
    });
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
