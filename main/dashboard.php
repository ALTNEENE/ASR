<?php
$base_level = 1;
include __DIR__ . '/../includes/auth_guard.php';
$base_path = '../';
$page_title = 'لوحة المتابعة - A.S.R';
$figma_page = 'dashboard';
$full_name = $_SESSION['full_name'] ?? 'زائر';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="container" style="padding: 40px 0;">
  <a href="../welcome.php" class="page-back-btn" data-back-button>
    <span>←</span>
    <span>رجوع</span>
  </a>
  
  <div class="hero" style="margin-bottom: 40px; text-align: center; border: 1px solid var(--border); background: var(--bg-card); border-radius: 20px; padding: 40px; box-shadow: var(--card-shadow, 0 4px 24px rgba(0,0,0,.08));">
    <h1 style="font-size: 2.2rem; margin-bottom: 15px; color: var(--ink);">مرحباً، <?php echo htmlspecialchars($full_name); ?> 👋</h1>
    <p class="lead" style="color: var(--muted); font-size: 1.1rem;">إليك ملخص سريع لحالتك الصحية وأحدث القراءات.</p>
  </div>

  <!-- AI Diagnosis Card -->
  <div class="glass-card" id="ai-diagnosis-card" style="margin-bottom: 40px; padding: 30px; border-right: 5px solid var(--red); background: var(--bg-card); border-radius: 20px; box-shadow: var(--card-shadow, 0 4px 24px rgba(0,0,0,.08)); position: relative; overflow: hidden;">
    <div style="position: absolute; top:0; left:0; width: 100%; height: 5px; background: linear-gradient(90deg, var(--red), var(--green));"></div>
    <h3 class="section-title" style="margin-bottom: 15px; color: var(--red); margin-top: 5px;">🩺 الحالة الصحية (تشخيص مبدئي)</h3>
    <div id="diagnosis-loading" style="padding: 30px; text-align: center; color: var(--muted);">
      <div style="font-size: 2rem; margin-bottom: 10px;">⏳</div>
      جاري تحليل بياناتك...
    </div>
    <div id="diagnosis-content" style="display: none;">
      <div style="margin-bottom: 14px;">
        <span id="status-level-badge" style="display:inline-block; padding: 6px 12px; border-radius: 20px; font-weight: 700; background: var(--surface-2); color: var(--ink);"></span>
      </div>
      <div style="margin-bottom: 20px; padding: 15px; background: rgba(46,125,50,0.05); border-radius: 12px; border: 1px solid rgba(46,125,50,0.1);">
        <strong style="display: block; color: var(--green); margin-bottom: 8px;">📋 التقييم العام:</strong>
        <p id="general-assessment" style="margin: 0; line-height: 1.6; color: var(--ink);"></p>
      </div>

      <div id="measurement-insights-section" style="margin-bottom: 20px; display: none;">
        <strong style="display: block; color: var(--green); margin-bottom: 8px;">📈 مؤشرات القياسات:</strong>
        <ul id="measurement-insights-list" style="padding-right: 20px; line-height: 1.8; color: var(--ink);"></ul>
      </div>

      <div id="age-notes-section" style="margin-bottom: 20px; display: none;">
        <strong style="display: block; color: var(--red); margin-bottom: 8px;">👤 ملاحظات حسب الفئة العمرية:</strong>
        <ul id="age-notes-list" style="padding-right: 20px; line-height: 1.8; color: var(--ink);"></ul>
      </div>

      <div id="risk-section" style="margin-bottom: 20px; display: none; padding: 15px; background: rgba(211,47,47,0.08); border-radius: 12px; border-right: 4px solid var(--red);">
        <strong style="display: block; color: var(--red); margin-bottom: 8px;">⚠️ مؤشرات المخاطر:</strong>
        <ul id="risk-list" style="padding-right: 20px; line-height: 1.8; color: var(--red);"></ul>
      </div>

      <div id="recommendations-section" style="margin-bottom: 15px; display: none;">
        <strong style="display: block; color: var(--green); margin-bottom: 8px;">💡 الخطوات العامة الآمنة:</strong>
        <ul id="recommendations-list" style="padding-right: 20px; line-height: 1.8; color: var(--ink);"></ul>
      </div>
      
      <div id="disclaimer" style="padding: 12px; background: rgba(230,81,0,0.1); border-radius: 8px; font-size: 0.9rem; color: #E65100; text-align: center; margin-top: 15px;"></div>
    </div>
    <div id="diagnosis-error" style="display: none; padding: 20px; text-align: center; color: var(--status-danger-text);">
      <div style="font-size: 1.5rem; margin-bottom: 10px;">⚠️</div>
      <p id="error-message"></p>
    </div>
  </div>



  <h3 class="section-title">نظرة عامة</h3>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;" id="stats-container">
    <div class="card stat" style="text-align: center; background: var(--bg-card); border-radius: 16px; padding: 24px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
      <span class="stat-value" id="latest-reading" style="display: block; font-size: 2.5rem; font-weight: 800; color: var(--green); margin-bottom: 5px;">--</span>
      <span class="stat-label" style="color: var(--muted); font-size: 1rem;">آخر قراءة السكر</span>
    </div>
    <a href="report.php?tab=readings" class="card stat" style="display: block; text-decoration: none; text-align: center; background: var(--bg-card); border-radius: 16px; padding: 24px; border: 1px solid var(--border); box-shadow: var(--card-shadow); transition: transform 0.2s;">
      <span class="stat-value" id="total-readings" style="display: block; font-size: 2.5rem; font-weight: 800; color: var(--ink); margin-bottom: 5px;">--</span>
      <span class="stat-label" style="color: var(--muted); font-size: 1rem;">عدد القراءات</span>
    </a>
    <div class="card stat" style="text-align: center; background: var(--bg-card); border-radius: 16px; padding: 24px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
      <span class="stat-value" id="high-count" style="display: block; font-size: 2.5rem; font-weight: 800; color: var(--red); margin-bottom: 5px;">--</span>
      <span class="stat-label" style="color: var(--muted); font-size: 1rem;">قراءات مرتفعة</span>
    </div>
    <div class="card stat" style="text-align: center; background: var(--bg-card); border-radius: 16px; padding: 24px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
      <span class="stat-value" id="low-count" style="display: block; font-size: 2.5rem; font-weight: 800; color: #E65100; margin-bottom: 5px;">--</span>
      <span class="stat-label" style="color: var(--muted); font-size: 1rem;">قراءات منخفضة</span>
    </div>
  </div>

  <h3 class="section-title">تحكم سريع</h3>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px;">
    <a href="tools.php" class="card" style="text-decoration: none; display: flex; align-items: center; gap: 15px; transition: transform 0.2s; background: var(--bg-card); border-radius: 16px; padding: 20px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
      <div style="width: 50px; height: 50px; background: rgba(46,125,50,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--green);">💉</div>
      <div>
        <h4 style="margin: 0; font-size: 1.1rem; color: var(--ink); font-weight: 700;">إضافة قراءة جديدة</h4>
        <span style="font-size: 0.9rem; color: var(--muted);">سجل قياسك الآن</span>
      </div>
    </a>
    
    <a href="../Awareness/assistant.php" class="card" style="text-decoration: none; display: flex; align-items: center; gap: 15px; transition: transform 0.2s; background: var(--bg-card); border-radius: 16px; padding: 20px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
      <div style="width: 50px; height: 50px; background: rgba(211,47,47,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--red);">📚</div>
      <div>
        <h4 style="margin: 0; font-size: 1.1rem; color: var(--ink); font-weight: 700;">المساعد الذكي</h4>
        <span style="font-size: 0.9rem; color: var(--muted);">ابدأ بالسؤال ثم انتقل للتوعية</span>
      </div>
    </a>
  </div>

  <!-- Patient Info Section -->
  <h3 class="section-title">المعلومات الشخصية</h3>
  <div class="glass-card" id="patient-info-card" style="margin-bottom: 40px; background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
    <div style="padding: 40px; text-align: center; color: var(--muted);">
      جاري تحميل البيانات...
    </div>
  </div>

  <!-- Medications Section -->
  <div id="medications-section" style="display: none; margin-bottom: 40px;">
    <h3 class="section-title">الأدوية الحالية</h3>
    <div class="glass-card" id="medications-list" style="background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
      <div style="padding: 20px; color: var(--muted); text-align: center;">
        لا توجد أدوية مسجلة
      </div>
    </div>
  </div>

  <!-- Recent Section -->
  <h3 class="section-title">آخر النشاطات</h3>
  <div class="glass-card" id="recent-readings" style="padding: 0; overflow: hidden; background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
    <div style="padding: 40px; text-align: center; color: var(--muted);">
      جاري تحميل البيانات...
    </div>
  </div>

  <!-- Dynamic Health Tips Section -->
  <h3 class="section-title" style="margin-top: 40px;">💡 نصائح مخصصة لك</h3>
  <div class="glass-card" id="health-tips-card" style="margin-bottom: 40px; background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
      <div style="padding: 30px;" id="health-tips-container">
          <div style="text-align: center; color: var(--muted);">جاري تحميل النصائح المخصصة...</div>
      </div>
  </div>


</main>

<script>
// Load dashboard data logic
async function loadDashboard() {
  try {
    const response = await fetch('api/get_readings.php?limit=5');
    const data = await response.json();

    if (data.success) {
      const stats = data.statistics || {};
      const readings = Array.isArray(data.readings) ? data.readings : [];
      const sevenDaysAgo = new Date();
      sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);

      const readingDateTime = (reading) => {
        const datePart = reading.reading_date || '';
        const timePart = reading.reading_time || '00:00:00';
        const raw = datePart ? `${datePart}T${timePart}` : (reading.created_at || '');
        const parsed = new Date(raw);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
      };

      const recentReadings = readings.filter((reading) => {
        const parsed = readingDateTime(reading);
        return parsed && parsed >= sevenDaysAgo;
      });

      document.getElementById('total-readings').textContent = stats.total_readings || 0;
      document.getElementById('high-count').textContent = stats.high_count || 0;
      document.getElementById('low-count').textContent = stats.low_count || 0;

      const latestReadingEl = document.getElementById('latest-reading');
      if (latestReadingEl) {
        if (recentReadings.length > 0) {
          const latest = recentReadings[0];
          const unit = latest.reading_unit === 'mg' ? 'mg/dL' : 'mmol/L';
          const value = Number(latest.reading_value);
          latestReadingEl.textContent = Number.isFinite(value) ? (value + ' ' + unit) : '--';
        } else {
          latestReadingEl.textContent = 'لا توجد خلال 7 أيام';
        }
      }

      const recentDiv = document.getElementById('recent-readings');
      if (recentReadings.length > 0) {
        recentDiv.innerHTML = recentReadings.map(r => `
          <div style="padding: 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 15px;">
              <div style="background: rgba(46,125,50,0.1); width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">📝</div>
              <div>
                <strong style="display: block; color: var(--ink); font-size: 1.1rem;">${r.reading_value} ${r.reading_unit === 'mg' ? 'mg/dL' : 'mmol/L'}</strong>
                <span style="font-size: 0.9rem; color: var(--muted);">${r.reading_context === 'fasting' ? 'صائم' : 'بعد الأكل'}</span>
              </div>
            </div>
            <div style="text-align: left;">
              <span class="status-badge" style="background: ${getClassificationColorBg(r.classification)}; color: ${getClassificationColorText(r.classification)}; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700;">
                ${r.classification}
              </span>
              <div style="font-size: 0.85rem; color: var(--muted); margin-top: 5px;">${r.reading_date}</div>
            </div>
          </div>
        `).join('');
      } else {
        recentDiv.innerHTML = '<div style="padding:40px; text-align:center; color:var(--muted);">لا توجد قراءات خلال آخر 7 أيام.</div>';
      }
    }
  } catch (error) {
    console.error('Error loading dashboard:', error);
  }
}

function getClassificationColorBg(c) {
  if (c && c.includes('طبيعي')) return 'rgba(46,125,50,0.15)';
  if (c && c.includes('منخفض')) return 'rgba(230,81,0,0.15)';
  if (c && c.includes('ما قبل')) return 'rgba(230,81,0,0.15)';
  return 'rgba(211,47,47,0.15)';
}
function getClassificationColorText(c) {
  if (c && c.includes('طبيعي')) return 'var(--green)';
  if (c && c.includes('منخفض')) return '#E65100';
  if (c && c.includes('ما قبل')) return '#E65100';
  return 'var(--red)';
}

// Load Patient Data
async function loadPatientData() {
  try {
    const response = await fetch('api/get_patient_data.php');
    const raw = await response.text();
    const clean = raw.replace(/^\uFEFF/, '').trim();
    let data;
    try {
      data = JSON.parse(clean);
    } catch (parseError) {
      throw new Error('استجابة بيانات المريض ليست JSON صالحاً');
    }
    
    if (data.success && data.patient) {
      const patient = data.patient;
      const infoCard = document.getElementById('patient-info-card');
      const genderRaw = String(patient.gender || '').trim().toLowerCase();
      const genderText = (genderRaw === 'male' || String(patient.gender).trim() === 'ذكر')
        ? 'ذكر'
        : (genderRaw === 'female' || String(patient.gender).trim() === 'أنثى')
          ? 'أنثى'
          : '-';
      
      // Display patient info
      infoCard.innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; padding: 20px;">
          <div style="text-align: center;">
            <div style="font-size: 0.9rem; color: var(--muted); margin-bottom: 5px;">الاسم</div>
            <div style="font-size: 1.1rem; font-weight: bold; color: var(--ink);">${patient.name || 'غير محدد'}</div>
          </div>
          <div style="text-align: center;">
            <div style="font-size: 0.9rem; color: var(--muted); margin-bottom: 5px;">العمر</div>
            <div style="font-size: 1.1rem; font-weight: bold; color: var(--ink);">${patient.age || '-'} سنة</div>
          </div>
          <div style="text-align: center;">
            <div style="font-size: 0.9rem; color: var(--muted); margin-bottom: 5px;">الجنس</div>
            <div style="font-size: 1.1rem; font-weight: bold; color: var(--ink);">${genderText}</div>
          </div>
          <div style="text-align: center;">
            <div style="font-size: 0.9rem; color: var(--muted); margin-bottom: 5px;">الوزن</div>
            <div style="font-size: 1.1rem; font-weight: bold; color: var(--ink);">${patient.weight || '-'} كجم</div>
          </div>
          <div style="text-align: center;">
            <div style="font-size: 0.9rem; color: var(--muted); margin-bottom: 5px;">تاريخ التشخيص</div>
            <div style="font-size: 1.1rem; font-weight: bold; color: var(--ink);">${patient.date_of_diagnosis || 'غير محدد'}</div>
          </div>
          <div style="text-align: center;">
            <div style="font-size: 0.9rem; color: var(--muted); margin-bottom: 5px;">البريد الإلكتروني</div>
            <div style="font-size: 1.1rem; font-weight: bold; color: var(--ink);">${patient.email || 'غير محدد'}</div>
          </div>
        </div>
      `;
      
      // Display medications if available
      if (patient.medications && Array.isArray(patient.medications) && patient.medications.length > 0) {
        const medsSection = document.getElementById('medications-section');
        const medsList = document.getElementById('medications-list');
        medsSection.style.display = 'block';
        
        medsList.innerHTML = patient.medications.map(med => `
          <div style="padding: 15px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 15px;">
              <div style="width: 40px; height: 40px; background: rgba(46,125,50,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">💊</div>
              <div>
                <strong style="display: block; color: var(--ink); font-size: 1rem;">${med.drug || 'دواء'}</strong>
                <span style="font-size: 0.9rem; color: var(--muted);">الجرعة: ${med.dose || 'غير محددة'}</span>
              </div>
            </div>
          </div>
        `).join('');
      }

      // Optional fields if present in template
      const healthStatusEl = document.getElementById('health-status-text');
      if (healthStatusEl) {
        healthStatusEl.textContent = patient.health_status || 'غير محدد';
      }
      const therapyTypeEl = document.getElementById('therapy-type-text');
      if (therapyTypeEl) {
        const therapyMap = {
          'none': 'بدون علاج / حمية',
          'tablets': 'أقراص (حبوب)',
          'insulin': 'إنسولين',
          'both': 'أقراص وإنسولين'
        };
        therapyTypeEl.textContent = therapyMap[patient.therapy_type] || patient.therapy_type || 'غير محدد';
      }

    } else {
      // No patient data
      document.getElementById('patient-info-card').innerHTML = `
        <div style="padding: 40px; text-align: center; color: var(--muted);">
          <p style="margin-bottom: 15px;">لم يتم إدخال المعلومات الشخصية بعد</p>
          <a href="../questionnaire/questionnaire.php" style="color: var(--green); text-decoration: none; font-weight: bold;">إضافة المعلومات الآن</a>
        </div>
      `;
    }
  } catch (error) {
    console.error('Error loading patient data:', error);
    const infoCard = document.getElementById('patient-info-card');
    if (infoCard) {
      infoCard.innerHTML = `
        <div style="padding: 40px; text-align: center; color: var(--status-danger-text);">
          حدث خطأ أثناء تحميل المعلومات الشخصية
        </div>
      `;
    }
  }
}
// Load AI Diagnosis
async function loadAIDiagnosis() {
  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');

  const renderList = (sectionId, listId, items) => {
    const section = document.getElementById(sectionId);
    const list = document.getElementById(listId);
    if (!section || !list) return;
    if (Array.isArray(items) && items.length > 0) {
      section.style.display = 'block';
      list.innerHTML = items.map(item => `<li>${escapeHtml(item)}</li>`).join('');
    } else {
      section.style.display = 'none';
      list.innerHTML = '';
    }
  };

  try {
    const response = await fetch('api/initial_assessment.php');
    const raw = await response.text();
    const clean = raw.replace(/^\uFEFF/, '').trim();
    let result;
    try {
      result = JSON.parse(clean);
    } catch (parseError) {
      throw new Error('Invalid JSON response from assessment API');
    }

    const loadingEl = document.getElementById('diagnosis-loading');
    const contentEl = document.getElementById('diagnosis-content');
    const errorEl = document.getElementById('diagnosis-error');

    if (result.success && result.data) {
      const data = result.data;
      loadingEl.style.display = 'none';
      contentEl.style.display = 'block';

      // Risk level badge
      const badge = document.getElementById('status-level-badge');
      if (badge) {
        const level = String(data.risk_level || 'متوسط');
        badge.textContent = level;
        if (level === 'مرتفع') {
          badge.style.background = 'rgba(211,47,47,0.15)';
          badge.style.color = 'var(--red)';
        } else if (level === 'منخفض') {
          badge.style.background = 'rgba(46,125,50,0.15)';
          badge.style.color = 'var(--green)';
        } else {
          badge.style.background = 'rgba(230,81,0,0.15)';
          badge.style.color = '#E65100';
        }
      }

      // Summary
      const summaryEl = document.getElementById('general-assessment');
      if (summaryEl) summaryEl.textContent = data.summary || '';

      // Key indicators
      renderList('measurement-insights-section', 'measurement-insights-list', data.key_indicators || []);

      // Age group tips
      const ageNotesSection = document.getElementById('age-notes-section');
      const ageNotesList    = document.getElementById('age-notes-list');
      const ageLabelEl      = document.getElementById('age-notes-label');
      if (ageLabelEl && data.age_group_label) ageLabelEl.textContent = data.age_group_label;
      renderList('age-notes-section', 'age-notes-list', data.age_group_tips || []);

      // Risk signals
      renderList('risk-section', 'risk-list', data.risk_signals || []);

      // General advice
      renderList('recommendations-section', 'recommendations-list', data.general_advice || []);

      // Disclaimer
      const disclaimerEl = document.getElementById('disclaimer');
      if (disclaimerEl) disclaimerEl.textContent = '⚠️ هذا تقييم تعليمي أولي وليس تشخيصاً طبياً. راجع طبيبك المختص.';

    } else {
      loadingEl.style.display = 'none';
      errorEl.style.display = 'block';
      document.getElementById('error-message').textContent = result.error || 'فشل تحميل التقييم';
    }
  } catch (error) {
    console.error('Error loading AI diagnosis:', error);
    document.getElementById('diagnosis-loading').style.display = 'none';
    document.getElementById('diagnosis-error').style.display = 'block';
    document.getElementById('error-message').textContent = 'حدث خطأ في الاتصال';
  }
}

// Load dynamic health tips
async function loadHealthTips() {
  const container = document.getElementById('health-tips-container');
  try {
    const response = await fetch('api/get_health_tips.php');
    const data = await response.json();
    
    if (data.ok && data.tips && data.tips.length > 0) {
      container.innerHTML = data.tips.map(tip => `
        <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px dashed var(--border);">
          <h4 style="color: var(--green); margin-top: 0; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.2rem;">📌</span> ${tip.title_ar}
          </h4>
          <p style="color: var(--ink); line-height: 1.6; margin: 0; padding-right: 15px; border-right: 3px solid var(--red); background: rgba(211, 47, 47, 0.03); padding-top: 5px; padding-bottom: 5px;">
            ${tip.body_ar}
          </p>
        </div>
      `).join('');
    } else {
      container.innerHTML = '<div style="text-align: center; color: var(--muted);">لا توجد نصائح مخصصة حالياً لعمرك وحالتك.</div>';
    }
  } catch (error) {
    console.error('Error loading health tips:', error);
    container.innerHTML = '<div style="text-align: center; color: var(--status-danger-text);">حدث خطأ أثناء جلب النصائح.</div>';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  loadAIDiagnosis();
  loadPatientData();
  loadDashboard();
  loadHealthTips();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
