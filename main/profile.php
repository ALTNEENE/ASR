<?php
session_start();
require_once __DIR__ . '/../includes/auth_restore.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../google_sign_in/auth/login.php");
    exit;
}

$base_path = '../';
$page_title = 'الملف الشخصي - A.S.R';
$figma_page = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../google_sign_in/config/db.php';

// Fetch Patient Data (patient_data table)
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM patient_data WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Also fetch from user_profile (questionnaire saves gender here)
$up = null;
$up_stmt = $conn->prepare("SELECT full_name, age, gender, weight, height, diagnosis_date, treatment_type, diabetes_type, phone FROM user_profile WHERE user_id = ? LIMIT 1");
if ($up_stmt) {
    $up_stmt->bind_param("i", $user_id);
    $up_stmt->execute();
    $up = $up_stmt->get_result()->fetch_assoc();
    $up_stmt->close();
}

// Fallback to questionnaire session data when DB row does not exist yet
if (!$patient && isset($_SESSION['temp_patient_data']) && is_array($_SESSION['temp_patient_data'])) {
    $tmp = $_SESSION['temp_patient_data'];
    $patient = [
        'name'              => $tmp['full_name'] ?? '',
        'age'               => $tmp['age'] ?? '',
        'weight'            => $tmp['weight'] ?? '',
        'height'            => $tmp['height'] ?? '',
        'gender'            => $tmp['gender'] ?? '',
        'therapy_type'      => $tmp['therapy_type'] ?? 'none',
        'date_of_diagnosis' => $tmp['date_of_diagnosis'] ?? '',
        'drug_dose_map'     => $tmp['drug_dose_map'] ?? '[]'
    ];
}

// Default values â€” use user_profile.gender as fallback (questionnaire source)
$name           = $patient['name']   ?? $up['full_name'] ?? $_SESSION['full_name'] ?? '';
$age            = $patient['age']    ?? $up['age']  ?? '';
$weight         = $patient['weight'] ?? $up['weight'] ?? '';
$height         = $patient['height'] ?? $up['height'] ?? '';
$gender         = !empty($patient['gender']) ? $patient['gender']
                : (!empty($up['gender'])     ? $up['gender'] : '');
$therapy_type   = $patient['therapy_type'] ?? $up['treatment_type'] ?? 'none';
$diagnosis_date = $patient['date_of_diagnosis'] ?? $up['diagnosis_date'] ?? '';
$medications    = !empty($patient['drug_dose_map']) ? json_decode($patient['drug_dose_map'], true) : [];

// Phone number logic
$u_stmt = $conn->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
$user_table_phone = '';
if ($u_stmt) {
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $u_row = $u_stmt->get_result()->fetch_assoc();
    $user_table_phone = $u_row['phone'] ?? '';
    $u_stmt->close();
}

$actual_phone = '';
if (!empty($user_table_phone) && strpos($user_table_phone, '@') === false) {
    $actual_phone = $user_table_phone;
} else {
    $actual_phone = $up['phone'] ?? '';
}
?>

<div class="container" style="padding-top: 40px; padding-bottom: 60px;">
    
    <div class="hero" style="margin-bottom: 40px; text-align: center; border: 1px solid var(--border); background: var(--bg-card); border-radius: 20px; padding: 40px; box-shadow: var(--card-shadow, 0 4px 24px rgba(0,0,0,.08));">
        <h1 style="color: var(--ink); margin-bottom: 15px; font-size: 2.2rem;">إعدادات الحساب</h1>
        <p style="color: var(--muted); font-size: 1.1rem;">أهلاً بك، <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'مستخدم'); ?></p>
    </div>

    <div class="glass-card" style="max-width: 600px; margin: 0 auto; padding: 40px; margin-bottom: 40px; background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border); box-shadow: var(--card-shadow, 0 8px 32px rgba(0,0,0,.08));">
        <h2 style="color: var(--ink); border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 25px; font-size: 1.5rem; display: flex; align-items: center; gap: 10px;">
            <span style="background: rgba(46,125,50,0.1); color: #2E7D32; padding: 8px; border-radius: 10px; display: inline-flex;">👤</span> البيانات الشخصية
        </h2>

        <div id="profileMessage" style="display: none; padding: 15px; border-radius: 12px; margin-bottom: 20px;"></div>

        <form id="profileForm">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--ink);">الاسم الكامل</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($name); ?>" required
                       style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: var(--surface-2); color: var(--ink); font-family: inherit;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--ink);">رقم الهاتف</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($actual_phone); ?>" placeholder="أدخل رقم الهاتف"
                       style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: var(--surface-2); color: var(--ink); font-family: inherit;" dir="ltr">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--ink);">العمر</label>
                    <input type="number" name="age" value="<?php echo htmlspecialchars($age); ?>" required min="1" oninput="this.value = !!this.value && Math.abs(this.value) >= 0 ? Math.abs(this.value) : null"
                           style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: var(--surface-2); color: var(--ink); font-family: inherit;">

                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--ink);">الوزن (kg)</label>
                    <input type="number" name="weight" step="0.1" value="<?php echo htmlspecialchars($weight); ?>" required min="2" oninput="this.value = !!this.value && Math.abs(this.value) >= 0 ? Math.abs(this.value) : null"
                           style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: var(--surface-2); color: var(--ink); font-family: inherit;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--ink);">الطول (cm)</label>
                    <input type="number" name="height" value="<?php echo htmlspecialchars($height); ?>" required min="50" oninput="this.value = !!this.value && Math.abs(this.value) >= 0 ? Math.abs(this.value) : null"
                           style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: var(--surface-2); color: var(--ink); font-family: inherit;">
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--ink);">نوع العلاج</label>
                    <select name="therapy_type" required style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: var(--surface-2); color: var(--ink); font-family: inherit;">
                        <option value="none" <?php echo $therapy_type === 'none' ? 'selected' : ''; ?>>بدون علاج / حمية فقط</option>
                        <option value="tablets" <?php echo $therapy_type === 'tablets' ? 'selected' : ''; ?>>أقراص (حبوب)</option>
                        <option value="insulin" <?php echo $therapy_type === 'insulin' ? 'selected' : ''; ?>>إنسولين</option>
                        <option value="both" <?php echo $therapy_type === 'both' ? 'selected' : ''; ?>>أقراص وإنسولين معاً</option>
                    </select>
                </div>
            </div>


            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                 <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--ink);">الجنس</label>
                    <select name="gender" required style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: var(--surface-2); color: var(--ink); font-family: inherit;">
                        <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>ذكر</option>
                        <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>أنثى</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--ink);">تاريخ التشخيص</label>
                    <div style="display:flex; align-items:stretch; border:2px solid var(--border); border-radius:10px; overflow:hidden; background:var(--surface-2); transition:border-color 0.2s;" id="prof-date-group">
                        <button type="button" onclick="triggerProfileDatePicker()" style="width:44px; border:none; border-left:1px solid var(--border); background:rgba(46,125,50,0.06); color:#2E7D32; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0;" title="فتح التقويم">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </button>
                        <input type="text" id="prof-date-display"
                               placeholder="يوم / شهر / سنة"
                               maxlength="14" autocomplete="off" inputmode="numeric"
                               style="flex:1; padding:12px; border:none; background:transparent; color:var(--ink); font-family:inherit; font-size:1rem; direction:ltr; letter-spacing:0.06em; outline:none;">
                    </div>
                    <div id="prof-date-error" style="display:none; font-size:0.82rem; color:#D32F2F; margin-top:4px; padding:4px 8px; background:rgba(211,47,47,0.08); border-radius:6px;">تاريخ غير صحيح. الحد (1970 - اليوم).</div>
                    <input type="hidden" name="date_of_diagnosis" id="prof-date-hidden" value="<?php echo htmlspecialchars($diagnosis_date); ?>">
                </div>
            </div>

            <h3 style="color: #2E7D32; margin-top: 30px; margin-bottom: 15px; font-size: 1.2rem; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.4rem;">💊</span> الأدوية المستخدمة
            </h3>
            <div id="medications-container">
                <?php if (!empty($medications)): ?>
                    <?php foreach ($medications as $med): ?>
                        <div class="med-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <input type="text" name="drug[]" placeholder="اسم الدواء" value="<?php echo htmlspecialchars($med['drug']); ?>" style="flex: 2; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-2); color: var(--ink);">
                            <input type="text" name="dose[]" placeholder="الجرعة" value="<?php echo htmlspecialchars($med['dose']); ?>" style="flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-2); color: var(--ink);">
                            <button type="button" onclick="this.parentElement.remove()" style="background: var(--status-danger-bg); color: var(--status-danger-text); border: none; padding: 0 10px; border-radius: 8px; cursor: pointer;">✕</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" onclick="addMedRow()" style="background: rgba(46,125,50,0.05); border: 1px dashed #2E7D32; color: #2E7D32; padding: 12px; width: 100%; border-radius: 10px; cursor: pointer; margin-bottom: 25px; font-weight: bold; transition: 0.2s; font-family: inherit;">+ إضافة دواء</button>

            <button type="submit" class="btn-submit" style="width: 100%; padding: 15px; border-radius: 12px; border: none; background: linear-gradient(135deg, #2E7D32, #1B5E20); color: white; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 16px rgba(46,125,50,0.3); font-family: inherit;">
                حفظ البيانات
            </button>
        </form>
    </div>

    <div class="glass-card" style="max-width: 600px; margin: 0 auto; padding: 40px; background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border); box-shadow: var(--card-shadow, 0 8px 32px rgba(0,0,0,.08));">
        <h2 style="color: var(--ink); border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 25px; font-size: 1.5rem; display: flex; align-items: center; gap: 10px;">
            <span style="background: rgba(211,47,47,0.1); color: #D32F2F; padding: 8px; border-radius: 10px; display: inline-flex;">🔒</span> الأمان وكلمة المرور
        </h2>

        <div id="message" style="display: none; padding: 15px; border-radius: 12px; margin-bottom: 20px;"></div>

        <form id="passwordForm">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--ink);">كلمة المرور الحالية</label>
                <input type="password" name="old_password" placeholder="اتركها فارغة إذا كنت قد سجلت عبر Google" 
                       style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: var(--surface-2); color: var(--ink); font-family: inherit;">
                <small style="color: var(--muted); display: block; margin-top: 5px; font-size: 0.85rem;">
                    * لمستخدمي Google: لا تحتاج لكلمة مرور قديمة لتعيين كلمة مرور جديدة لأول مرة.
                </small>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--ink);">كلمة المرور الجديدة</label>
                <input type="password" name="new_password" required minlength="8" 
                       pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                       title="يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل، وتتضمن حرفاً كبيراً، حرفاً صغيراً، رقماً، ورمزاً خاصاً"
                       placeholder="أدخل كلمة مرور قوية" 
                       style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: var(--surface-2); color: var(--ink); font-family: inherit;">
                <small style="color: var(--muted); font-size: 0.8rem;">
                    * يجب أن تحتوي على حرف كبير، حرف صغير، رقم، ورمز خاص.
                </small>
            </div>


            <div class="form-group" style="margin-bottom: 30px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--ink);">تأكيد كلمة المرور</label>
                <input type="password" name="confirm_password" required minlength="6" placeholder="أعد كتابة كلمة المرور" 
                       style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: var(--surface-2); color: var(--ink); font-family: inherit;">
            </div>

            <button type="submit" class="btn-submit" style="width: 100%; padding: 15px; border-radius: 12px; border: none; background: linear-gradient(135deg, #D32F2F, #C62828); color: white; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 16px rgba(211,47,47,0.3); font-family: inherit;">
                تحديث كلمة المرور
            </button>
        </form>
    </div>

</div>

    <script src="../assets/js/autocomplete.js"></script>

    <script>
    // ── Profile Date Masking ──
    (function() {
        const display = document.getElementById('prof-date-display');
        const hidden  = document.getElementById('prof-date-hidden');
        const errDiv  = document.getElementById('prof-date-error');
        const group   = document.getElementById('prof-date-group');
        if (!display || !hidden) return;

        const TODAY = new Date();
        TODAY.setHours(23, 59, 59);
        const MIN = new Date('1970-01-01');

        if (hidden.value) {
            const p = hidden.value.split('-');
            if (p.length === 3) display.value = p[2] + ' / ' + p[1] + ' / ' + p[0];
        }

        display.addEventListener('focus', () => { if(group) group.style.borderColor='#2E7D32'; });
        display.addEventListener('blur',  () => { if(group) group.style.borderColor=''; });

        function validate(d, m, y) {
            const date = new Date(`${y}-${m}-${d}`);
            if (isNaN(date.getTime())) return false;
            if (date.getDate() !== parseInt(d, 10)) return false;
            return date >= MIN && date <= TODAY;
        }

        display.addEventListener('input', function() {
            let raw = this.value.replace(/\D/g, '').substring(0, 8);
            let out = '';
            if (raw.length > 0) out = raw.substring(0, 2);
            if (raw.length > 2) out += ' / ' + raw.substring(2, 4);
            if (raw.length > 4) out += ' / ' + raw.substring(4, 8);
            this.value = out;
            errDiv.style.display = 'none';
            hidden.value = '';
            if (raw.length === 8) {
                const d = raw.substring(0, 2), m = raw.substring(2, 4), y = raw.substring(4, 8);
                if (validate(d, m, y)) { hidden.value = `${y}-${m}-${d}`; }
                else errDiv.style.display = 'block';
            }
        });

        window.triggerProfileDatePicker = function() {
            let picker = document.getElementById('__prof_date_picker__');
            if (!picker) {
                picker = document.createElement('input');
                picker.type = 'date';
                picker.id = '__prof_date_picker__';
                picker.style.cssText = 'position:fixed;opacity:0;width:0;height:0;pointer-events:none;top:0;left:0;';
                document.body.appendChild(picker);
                picker.addEventListener('change', function() {
                    if (!this.value) return;
                    hidden.value = this.value;
                    const p = this.value.split('-');
                    display.value = p[2] + ' / ' + p[1] + ' / ' + p[0];
                    errDiv.style.display = 'none';
                });
            }
            picker.min = '1970-01-01';
            picker.max = TODAY.toISOString().split('T')[0];
            picker.value = hidden.value || '';
            picker.showPicker();
        };
    })();

    // Initialize Autocomplete for existing inputs
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('input[name="drug[]"]').forEach(function(input) {
            enableAutocomplete(input, 'api/search_drugs.php');
        });
    });


    // Profile Form Submission
    document.getElementById('profileForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const msgDiv = document.getElementById('profileMessage');
        const btn = this.querySelector('button[type="submit"]');
    
        btn.disabled = true;
        btn.textContent = 'جاري الحفظ...';
    
        try {
            const response = await fetch('api/update_profile.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            msgDiv.style.display = 'block';
            if (data.success) {
                msgDiv.style.background = '#d1fae5';
                msgDiv.style.color = '#065f46';
                msgDiv.textContent = '✅ تم حفظ البيانات بنجاح!';
                setTimeout(() => {
                    msgDiv.style.display = 'none';
                }, 3000);
            } else {
                msgDiv.style.background = '#fee2e2';
                msgDiv.style.color = '#ef4444';
                msgDiv.textContent = '❌ ' + (data.error || 'حدث خطأ ما');
            }
    
        } catch (error) {
            msgDiv.style.display = 'block';
            msgDiv.style.background = '#fee2e2';
            msgDiv.style.color = '#ef4444';
            msgDiv.textContent = 'حدث خطأ في الاتصال';
            console.error(error);
        } finally {
            btn.disabled = false;
            btn.textContent = 'حفظ البيانات';
        }
    });

    // Add Medication Row Function
    function addMedRow() {
        const container = document.getElementById('medications-container');
        const newRow = document.createElement('div');
        newRow.className = 'med-row';
        newRow.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px;';
        newRow.innerHTML = `
            <input type="text" name="drug[]" placeholder="اسم الدواء" style="flex: 2; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-2); color: var(--ink);">
            <input type="text" name="dose[]" placeholder="الجرعة" style="flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-2); color: var(--ink);">
            <button type="button" onclick="this.parentElement.remove()" style="background: var(--status-danger-bg); color: var(--status-danger-text); border: none; padding: 0 10px; border-radius: 8px; cursor: pointer;">✕</button>
        `;
        container.appendChild(newRow);
        
        // Enable Autocomplete for new row
        enableAutocomplete(newRow.querySelector('input[name="drug[]"]'), 'api/search_drugs.php');
    }



// Password Form Submission
document.getElementById('passwordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const msgDiv = document.getElementById('message');
    const btn = this.querySelector('button[type="submit"]');

    // Basic Validation
    const newPass = formData.get('new_password');
    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

    if (!regex.test(newPass)) {
        msgDiv.style.display = 'block';
        msgDiv.style.background = '#fee2e2';
        msgDiv.style.color = '#ef4444';
        msgDiv.textContent = 'كلمة المرور ضعيفة. يجب أن تحتوي على أرقام وحروف (كبيرة وصغيرة) ورموز.';
        return;
    }

    if (newPass !== formData.get('confirm_password')) {
        msgDiv.style.display = 'block';
        msgDiv.style.background = '#fee2e2';
        msgDiv.style.color = '#ef4444';
        msgDiv.textContent = 'كلمة المرور الجديدة غير متطابقة';
        return;
    }


    btn.disabled = true;
    btn.textContent = 'جاري الحفظ...';

    try {
        const response = await fetch('api/update_password.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        msgDiv.style.display = 'block';
        if (data.success) {
            msgDiv.style.background = '#d1fae5';
            msgDiv.style.color = '#065f46';
            msgDiv.textContent = '✅ تم تحديث كلمة المرور بنجاح! يمكنك الآن الدخول يدوياً.';
            this.reset();
        } else {
            msgDiv.style.background = '#fee2e2';
            msgDiv.style.color = '#ef4444';
            msgDiv.textContent = '❌ ' + (data.error || 'حدث خطأ ما');
        }

    } catch (error) {
        msgDiv.style.display = 'block';
        msgDiv.style.background = '#fee2e2';
        msgDiv.style.color = '#ef4444';
        msgDiv.textContent = 'حدث خطأ في الاتصال';
        console.error(error);
    } finally {
        btn.disabled = false;
        btn.textContent = 'حفظ التغييرات';
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
