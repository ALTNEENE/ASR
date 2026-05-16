<?php
session_start();
require_once __DIR__ . '/../google_sign_in/config/db.php';
require_once __DIR__ . '/../includes/figma_assets.php';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Check if profile already done → redirect to dashboard
$profileDone = false;
if ($user_id > 0) {
    $profileCheck = $conn->prepare('SELECT user_id FROM user_profile WHERE user_id = ? LIMIT 1');
    $profileCheck->bind_param('i', $user_id);
    $profileCheck->execute();
    $profileDone = $profileCheck->get_result()->num_rows > 0;
    $profileCheck->close();
}

// Fetch existing data for prefill
$existing = [];
if ($profileDone && $user_id > 0) {
    $pf = $conn->prepare('SELECT * FROM user_profile WHERE user_id = ? LIMIT 1');
    $pf->bind_param('i', $user_id);
    $pf->execute();
    $existing = $pf->get_result()->fetch_assoc();
    $pf->close();
}

// If survey was filled before login, reuse the temporary data for prefill.
if (!$profileDone && isset($_SESSION['temp_patient_data']) && is_array($_SESSION['temp_patient_data'])) {
    $tmp = $_SESSION['temp_patient_data'];
    $existing = [
        'full_name' => $tmp['full_name'] ?? ($_SESSION['full_name'] ?? ''),
        'age' => $tmp['age'] ?? '',
        'gender' => $tmp['gender'] ?? 'male',
        'weight' => $tmp['weight'] ?? '',
        'height' => $tmp['height'] ?? '',
        'diagnosis_date' => $tmp['date_of_diagnosis'] ?? '',
        'treatment_type' => $tmp['therapy_type'] ?? ($tmp['treatment_type'] ?? 'none'),
        'diabetes_type' => $tmp['diabetes_type'] ?? 'none'
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diabetic = $_POST['diabetic'] ?? '';

    if ($diabetic === 'unsure') {
        header('Location: ../Awareness/assistant.php?entry=intake');
        exit;
    }

    if ($diabetic === 'no') {
        // Non-diabetic path → Awareness
        if ($user_id > 0) {
            $roleUp = $conn->prepare('UPDATE users SET role = ?, survey_completed=1, profile_completed_at=NOW() WHERE id=?');
            $role = 'awareness';
            $roleUp->bind_param('si', $role, $user_id);
            $roleUp->execute();
            $roleUp->close();
        }
        $_SESSION['user_path'] = 'awareness';
        $_SESSION['role'] = 'awareness';
        $_SESSION['guest_mode'] = true;
        header('Location: ../Awareness/index.php');
        exit;
    }

    if ($diabetic !== 'yes') {
        header('Location: questionnaire.php');
        exit;
    }

    // Collect form data
    $fullName = trim($_POST['fullName'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $weight = (float)($_POST['weight'] ?? 0);
    $height = null;
    $genderRaw = $_POST['gender'] ?? 'male';
    $diagnosisDate = $_POST['date_of_diagnosis'] ?? null;
    if ($diagnosisDate !== null) {
        $diagnosisDate = trim((string)$diagnosisDate);
    }
    $therapy = $_POST['therapy'] ?? 'none';
    $diabetesType = $_POST['diabetes_type'] ?? 'none';
    if ($diabetesType === 'GDM' && !($genderRaw === 'female' && $age >= 11 && $age <= 55)) {
        $diabetesType = ($age > 0 && $age < 11) ? 'child' : 'none';
    }

    // Validate
    if ($diagnosisDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$diagnosisDate)) {
        $error = 'اكتب تاريخ التشخيص بصيغة صحيحة مثل: 2024-05-17.';
    } elseif ($diagnosisDate && strtotime($diagnosisDate) > strtotime('today')) {
        $error = 'لا يمكن اختيار تاريخ تشخيص في المستقبل.';
    } elseif ($fullName === '' || $age <= 0 || $weight <= 0) {
        $error = 'الرجاء ملء جميع الحقول المطلوبة بشكل صحيح.';
    } else {
        $_SESSION['user_path'] = 'diabetic';
        $_SESSION['role'] = 'diabetic';
        unset($_SESSION['guest_mode']);

        if ($user_id === 0) {
            // Save in session until user logs in/registers
            $drugDoseMap = [];
            $_SESSION['temp_patient_data'] = [
                'full_name' => $fullName,
                'age' => $age,
                'gender' => $genderRaw,
                'weight' => $weight,
                'height' => null,
                'therapy_type' => $therapy,
                'date_of_diagnosis' => $diagnosisDate,
                'diabetes_type' => $diabetesType,
                'drug_dose_map' => json_encode($drugDoseMap, JSON_UNESCAPED_UNICODE)
            ];

            $hasAccount = $_POST['has_account'] ?? 'no';
            if ($hasAccount === 'yes') {
                header('Location: ../google_sign_in/auth/login.php');
            } else {
                header('Location: ../google_sign_in/auth/register.php');
            }
            exit;
        }

        // Save to user_profile (INSERT or UPDATE)
        $genderForProfile = ($genderRaw === 'female') ? 'أنثى' : 'ذكر';
        if ($profileDone) {
            $stmt = $conn->prepare('UPDATE user_profile SET full_name=?, age=?, gender=?, weight=?, height=?, diagnosis_date=?, treatment_type=?, diabetes_type=?, updated_at=NOW() WHERE user_id=?');
            $stmt->bind_param('sisddsssi', $fullName, $age, $genderForProfile, $weight, $height, $diagnosisDate, $therapy, $diabetesType, $user_id);
        } else {
            $stmt = $conn->prepare('INSERT INTO user_profile (user_id, full_name, age, gender, weight, height, diagnosis_date, treatment_type, diabetes_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->bind_param('isisddsss', $user_id, $fullName, $age, $genderForProfile, $weight, $height, $diagnosisDate, $therapy, $diabetesType);
        }
        $stmt->execute();
        $stmt->close();

        // Sync to patient_data for backward compatibility
        $pdCheck = $conn->prepare('SELECT user_id FROM patient_data WHERE user_id = ? LIMIT 1');
        $pdCheck->bind_param('i', $user_id);
        $pdCheck->execute();
        $pdExists = $pdCheck->get_result()->num_rows > 0;
        $pdCheck->close();

        $drugDoseMap = [];
        $jsonDrugs = json_encode($drugDoseMap, JSON_UNESCAPED_UNICODE);

        if ($pdExists) {
            $pdStmt = $conn->prepare('UPDATE patient_data SET name=?, age=?, gender=?, weight=?, height=?, therapy_type=?, date_of_diagnosis=?, drug_dose_map=?, updated_at=NOW() WHERE user_id=?');
            $pdStmt->bind_param('sisddsssi', $fullName, $age, $genderRaw, $weight, $height, $therapy, $diagnosisDate, $jsonDrugs, $user_id);
        } else {
            $pdStmt = $conn->prepare('INSERT INTO patient_data (user_id, name, age, gender, weight, height, therapy_type, date_of_diagnosis, drug_dose_map, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $pdStmt->bind_param('isisddsss', $user_id, $fullName, $age, $genderRaw, $weight, $height, $therapy, $diagnosisDate, $jsonDrugs);
        }
        $pdStmt->execute();
        $pdStmt->close();

        // Mark survey completed
        $flag = $conn->prepare('UPDATE users SET role = ?, survey_completed=1, profile_completed_at=NOW() WHERE id=?');
        $role = 'diabetic';
        $flag->bind_param('si', $role, $user_id);
        $flag->execute();
        $flag->close();

        $_SESSION['role'] = 'diabetic';
        $_SESSION['full_name'] = $fullName;

        header('Location: ../main/dashboard.php');
        exit;
    }
}

// Prefill values
$pf_name = $existing['full_name'] ?? $_SESSION['full_name'] ?? '';
if ($pf_name === 'مستخدم') {
    $pf_name = ''; // لا نعرض 'مستخدم' كاسم افتراضي
}
$pf_age = $existing['age'] ?? '';
$pf_gender = $existing['gender'] ?? 'male';
$pf_weight = $existing['weight'] ?? '';
$pf_diagnosis = $existing['diagnosis_date'] ?? '';
$pf_therapy = $existing['treatment_type'] ?? 'none';
$pf_dtype = $existing['diabetes_type'] ?? 'none';

$pf_gender_norm = strtolower(trim((string)$pf_gender));
if ($pf_gender_norm === 'أنثى') $pf_gender_norm = 'female';
elseif ($pf_gender_norm === 'ذكر') $pf_gender_norm = 'male';
if ($pf_gender_norm !== 'male' && $pf_gender_norm !== 'female') $pf_gender_norm = 'male';
$pf_gender = $pf_gender_norm;

$pf_age_num = is_numeric($pf_age) ? (int)$pf_age : 0;
$pf_dtype_raw = strtoupper(trim((string)$pf_dtype));
if (!in_array($pf_dtype_raw, ['T1', 'T2', 'GDM', 'CHILD'], true)) $pf_dtype_raw = 'none';
$pf_dtype_raw = ($pf_dtype_raw === 'CHILD') ? 'child' : $pf_dtype_raw;
$pf_dtype = $pf_dtype_raw;
$maxDiagnosisDate = date('Y-m-d');

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المعلومات التمهيدية | A.S.R</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        :root {
            --red:   #D32F2F;
            --red2:  #C62828;
            --green: #2E7D32;
            --white: #FFFFFF;
            --ink:   #1A1A1A;
            --muted: #555;
            --border:#E0E0E0;
            --bg-page:#FAFAFA;
            --bg-card:#FFFFFF;
            --surface-2:#F5F5F5;
            --shadow: 0 8px 32px rgba(0,0,0,0.08);
        }
        html[data-theme="dark"] body {
            --bg-page: #0D1117;
            --bg-card: #161B22;
            --surface-2:#24292E;
            --ink:     #E6EDF3;
            --muted:   #8B949E;
            --border:  #30363D;
            --shadow:  0 8px 32px rgba(0,0,0,0.35);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Tajawal', sans-serif;
            background: var(--bg-page); color: var(--ink);
            display: flex; min-height: 100vh;
            transition: background 0.3s, color 0.3s;
        }
        a { text-decoration: none; color: inherit; }

        /* Premium Sky Toggle */
        .dark-toggle {
            width: 70px; height: 34px;
            background: linear-gradient(to right, #4facfe, #00f2fe);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 999px;
            position: relative;
            cursor: pointer;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
            display: flex;
            align-items: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        html[data-theme="dark"] .dark-toggle {
            background: linear-gradient(to right, #243b55, #141e30);
            border-color: rgba(255,255,255,0.1);
        }

        /* Thumb (Sun/Moon) */
        .toggle-thumb {
            width: 25px; height: 25px;
            border-radius: 50%;
            background: #ffcc33; /* Sun color */
            position: absolute;
            right: 4px;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 10;
            box-shadow: 0 0 10px rgba(255, 204, 51, 0.5);
        }

        html[data-theme="dark"] .toggle-thumb {
            transform: translateX(-37px);
            background: #f5f5f5; /* Moon color */
            box-shadow: 
                0 0 10px rgba(255, 255, 255, 0.3),
                inset -3px -3px 0 rgba(0,0,0,0.1);
        }

        /* Clouds/Stars Background Elements */
        .sky-elements {
            position: absolute;
            inset: 0;
            transition: opacity 0.5s;
        }

        .cloud {
            position: absolute;
            width: 12px; height: 12px;
            background: rgba(255,255,255,0.8);
            border-radius: 50%;
            transition: transform 0.5s;
        }
        .cloud::before, .cloud::after {
            content: '';
            position: absolute;
            background: rgba(255,255,255,0.8);
            border-radius: 50%;
        }
        .cloud::before { width: 8px; height: 8px; top: 4px; left: -6px; }
        .cloud::after { width: 10px; height: 10px; top: -3px; left: 5px; }

        .cloud-1 { top: 8px; left: 15px; }
        .cloud-2 { top: 18px; left: 35px; }

        html[data-theme="dark"] .cloud {
            transform: translateY(40px);
            opacity: 0;
        }

        .stars {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.5s;
            pointer-events: none;
        }

        .star {
            position: absolute;
            background: white;
            border-radius: 50%;
            animation: twinkle 2s infinite alternate;
        }
        .star-1 { width: 2px; height: 2px; top: 8px; right: 20px; }
        .star-2 { width: 1px; height: 1px; top: 18px; right: 40px; }
        .star-3 { width: 1.5px; height: 1.5px; top: 5px; right: 50px; }

        html[data-theme="dark"] .stars { opacity: 1; }

        @keyframes twinkle {
            from { opacity: 0.3; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1.1); }
        }

        .btn-back {
            position: absolute; top: 24px; right: 24px;
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--bg-card);
            padding: 10px 18px; border-radius: 12px;
            font-weight: 700; color: var(--ink); z-index: 150;
            border: 1px solid var(--border); transition: 0.2s;
            box-shadow: var(--shadow);
            font-size: 0.95rem;
        }
        .btn-back:hover { 
            transform: translateY(-2px); 
            background: var(--surface-2);
            border-color: var(--green);
            color: var(--green);
        }
        .btn-back svg { transition: transform 0.2s; }
        .btn-back:hover svg { transform: translateX(4px); }

        /* Layout */
        .auth-layout { display: flex; width: 100%; min-height: 100vh; }
        .auth-brand {
            flex: 1; display: none; flex-direction: column; justify-content: center;
            padding: 60px; position: relative; overflow: hidden;
            background: linear-gradient(160deg, #0d1117 0%, #1a0a1e 40%, #0a1f0d 75%, #1a2e0a 100%);
            color: white; max-width: 45%;
        }
        @media (min-width: 1024px) { .auth-brand { display: flex; } }
        
        .auth-brand::before {
            content: ''; position: absolute; inset: 0;
            background: url('../assets/img/brand_vision.png') center 20%/cover no-repeat;
            opacity: 0.22; z-index: 1;
            filter: saturate(1.4) brightness(0.9);
        }
        /* Decorative radial glow bottom-left */
        .auth-brand::after {
            content: '';
            position: absolute;
            width: 380px; height: 380px;
            left: -80px; bottom: -100px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(46,125,50,0.35) 0%, transparent 68%);
            filter: blur(24px);
            z-index: 1;
            animation: brandFloat 10s ease-in-out infinite;
        }
        /* Extra top-right glow for depth */
        .auth-brand .brand-glow-tr {
            position: absolute;
            top: -60px; right: -80px;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(200,16,46,0.28) 0%, transparent 68%);
            filter: blur(28px);
            z-index: 1;
            animation: brandFloat 13s ease-in-out infinite reverse;
        }
        .brand-content {
            position: relative;
            z-index: 2;
            padding: 34px 32px;
            border-radius: 30px;
            background: linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.06));
            border: 1px solid rgba(255,255,255,0.16);
            box-shadow: 0 28px 60px rgba(0,0,0,0.16);
            backdrop-filter: blur(10px);
        }
        .brand-content::before {
            content: '';
            position: absolute;
            inset: 14px;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.08);
            pointer-events: none;
        }
        .brand-kicker {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            margin-bottom: 20px;
            border-radius: 999px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.18);
            font-size: 0.98rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            box-shadow: 0 10px 24px rgba(0,0,0,0.12);
        }
        .brand-kicker::before {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #fff0c2;
            box-shadow: 0 0 14px rgba(255,240,194,0.75);
        }
        .brand-content h1 {
            font-size: clamp(3.6rem, 5vw, 5.1rem);
            font-weight: 900;
            margin: 0 0 20px;
            line-height: 1.1;
            text-shadow: 0 8px 24px rgba(0,0,0,0.16);
        }
        .brand-content p {
            font-size: clamp(1.28rem, 1.55vw, 1.55rem);
            opacity: 0.96;
            line-height: 1.9;
            margin-bottom: 28px;
            max-width: 32rem;
        }
        .brand-metrics {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 30px;
        }
        .metric-pill {
            min-width: 120px;
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(255,255,255,0.13);
            border: 1px solid rgba(255,255,255,0.14);
            box-shadow: 0 16px 30px rgba(0,0,0,0.1);
        }
        .metric-pill strong {
            display: block;
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .metric-pill span {
            display: block;
            font-size: 0.92rem;
            opacity: 0.9;
        }

        .trust-feature {
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 18px;
            padding: 16px 18px;
            border-radius: 22px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            transition: transform 0.25s ease, background 0.25s ease, border-color 0.25s ease;
        }
        .trust-feature:hover {
            transform: translateY(-3px);
            background: rgba(255,255,255,0.12);
            border-color: rgba(255,255,255,0.18);
        }
        .trust-icon { 
            width: 56px; height: 56px; border-radius: 16px; background: rgba(255,255,255,0.15); 
            display: flex; align-items: center; justify-content: center; font-size: 1.65rem; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.2);
        }
        .trust-feature h3 { margin: 0 0 8px; font-size: clamp(1.45rem, 1.8vw, 1.85rem); line-height: 1.35; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        .trust-feature p { margin: 0; font-size: clamp(1.08rem, 1.2vw, 1.22rem); color: rgba(255,255,255,0.95); line-height: 1.8; max-width: 30rem; }

        .auth-form-wrap {
            flex: 1.5; display: flex; align-items: flex-start; justify-content: center;
            padding: 60px 24px; background: var(--bg-page); position: relative;
            overflow-y: auto;
        }
        
        .auth-card {
            width: 100%; max-width: 650px;
            background: var(--bg-card); padding: 40px; border-radius: 20px;
            box-shadow: var(--shadow); border: 1px solid var(--border);
        }
        .auth-card h2 { font-size: clamp(2.15rem, 2.8vw, 2.5rem); font-weight: 800; margin: 0 0 12px; color: var(--ink); text-align: center; }
        .auth-card > p.sub { text-align: center; color: var(--muted); margin: 0 0 34px; font-size: 1.08rem; line-height: 1.8;}
        .journey-progress {
            margin-bottom: 26px;
            padding: 18px 20px;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(46,125,50,0.09), rgba(211,47,47,0.08));
            border: 1px solid rgba(46,125,50,0.16);
        }
        .journey-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 12px;
        }
        .journey-label {
            display: block;
            color: var(--muted);
            font-size: 0.88rem;
            margin-bottom: 4px;
        }
        .journey-head strong {
            display: block;
            color: var(--ink);
            font-size: 1.04rem;
            line-height: 1.6;
        }
        .journey-percent {
            flex-shrink: 0;
            min-width: 58px;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(255,255,255,0.76);
            color: var(--green);
            font-weight: 800;
            text-align: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }
        .journey-track {
            position: relative;
            width: 100%;
            height: 10px;
            margin-bottom: 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.7);
            overflow: hidden;
        }
        .journey-track span {
            position: absolute;
            inset: 0 auto 0 0;
            width: 0%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--green), #46a049, var(--red));
            transition: width 0.35s ease;
        }
        .journey-steps {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        .journey-step {
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.62);
            color: var(--muted);
            font-size: 0.9rem;
            font-weight: 700;
            transition: background 0.25s ease, color 0.25s ease, transform 0.25s ease;
        }
        .journey-step.is-active {
            background: rgba(46,125,50,0.16);
            color: var(--green);
            transform: translateY(-1px);
        }
        .journey-step.is-done {
            background: rgba(46,125,50,0.92);
            color: #fff;
        }
        .journey-tip {
            margin: 0;
            color: var(--ink);
            font-size: 0.94rem;
            line-height: 1.7;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--ink); font-size: 0.95rem; }
        
        .form-control, .form-select {
            width: 100%; padding: 14px 16px;
            background: var(--surface-2); border: 1px solid var(--border);
            border-radius: 12px; color: var(--ink); font-family: inherit; font-size: 1rem;
            transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 4px rgba(46,125,50,0.15); }
        
        /* ── Custom Date Widget ── */
        .date-input-group {
            display: flex;
            align-items: stretch;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: var(--surface-2);
        }
        .date-input-group:focus-within {
            border-color: var(--green);
            box-shadow: 0 0 0 4px rgba(46,125,50,0.15);
        }
        .date-text-input {
            flex: 1;
            padding: 14px 16px;
            background: transparent;
            border: none;
            color: var(--ink);
            font-family: inherit;
            font-size: 1rem;
            direction: ltr;
            letter-spacing: 0.06em;
            outline: none;
        }
        .date-text-input::placeholder {
            direction: rtl;
            letter-spacing: normal;
            color: var(--muted);
            opacity: 1;
        }
        .date-cal-btn {
            width: 48px;
            background: rgba(46,125,50,0.06);
            border: none;
            border-right: 1px solid var(--border);
            color: var(--green);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .date-cal-btn:hover { background: rgba(46,125,50,0.14); }
        .date-error {
            font-size: 0.82rem;
            color: #D32F2F;
            margin-top: 5px;
            display: none;
            padding: 4px 8px;
            background: rgba(211,47,47,0.08);
            border-radius: 6px;
        }
        
        .grid-two { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 600px) { .grid-two { grid-template-columns: 1fr; } }
        
        .btn-submit {
            width: 100%; padding: 16px; border: none; border-radius: 12px;
            background: linear-gradient(135deg, var(--green), #1B5E20);
            color: white; font-weight: 700; font-size: 1.1rem; font-family: inherit;
            cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 16px rgba(46,125,50,0.3); margin-top: 10px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(46,125,50,0.4); }

        .form-check {
            display: flex; gap: 20px; padding: 10px 0; margin-bottom: 20px;
        }
        .radio-btn {
            display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--ink); font-weight: 500;
        }
        .radio-btn input { accent-color: var(--green); transform: scale(1.2); }

        .error-box, .welcome-note {
            padding: 15px; border-radius: 12px; margin-bottom: 25px; text-align: center; font-weight: 500;
        }
        .error-box { background: rgba(211,47,47,0.1); color: var(--red); border: 1px solid rgba(211,47,47,0.2); }
        .welcome-note { background: rgba(46,125,50,0.1); color: var(--green); border: 1px solid rgba(46,125,50,0.2); }
        
        #diabetic-fields {
            transition: opacity 0.3s ease;
        }
        .hidden { display: none !important; }

        @media (max-width: 1200px) {
            .brand-content h1 { font-size: 2.85rem; }
            .brand-content p { font-size: 1.18rem; line-height: 1.8; }
            .trust-feature h3 { font-size: 1.35rem; }
            .trust-feature p { font-size: 1rem; }
        }
        @media (max-width: 640px) {
            .journey-head {
                flex-direction: column;
                align-items: stretch;
            }
            .journey-percent {
                align-self: flex-start;
            }
        }
        
        @keyframes brandFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-18px); }
        }
        
        .required-star-mark {
            color: var(--red) !important;
            font-weight: 800 !important;
            margin-left: 6px;
            font-size: 1.25rem;
            line-height: 1;
        }
    </style>
</head>
<body>

<!-- Standardized Theme Toggle (Premium Sky) -->
<div style="position: absolute; top: 24px; left: 24px; z-index: 100;">
    <button class="dark-toggle" id="themeToggle" title="تبديل الوضع الليلي">
        <div class="sky-elements">
            <div class="cloud cloud-1"></div>
            <div class="cloud cloud-2"></div>
            <div class="stars">
                <div class="star star-1"></div>
                <div class="star star-2"></div>
                <div class="star star-3"></div>
            </div>
        </div>
        <div class="toggle-thumb" id="themeToggleThumb"></div>
    </button>
</div>

<a href="../welcome.php" class="btn-back" data-back-button>
    <span>رجوع</span>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 19 12 12 5"></polyline>
    </svg>
</a>

<div class="auth-layout">
    <!-- Branding Side -->
    <div class="auth-brand">
        <!-- Decorative top-right glow orb -->
        <div class="brand-glow-tr"></div>
        <div class="brand-content">
            <div class="brand-kicker">نحلم بصحة أفضل لكل إنسان</div>

            <!-- Premium Brand Vision Image -->
            <div style="position:relative; margin-bottom:24px; border-radius:20px; overflow:hidden; box-shadow:0 20px 50px rgba(0,0,0,0.4); border:1px solid rgba(255,255,255,0.12);">
                <img src="../assets/img/brand_vision.png" alt="رؤية صحية" style="width:100%; display:block; object-fit:cover; height:200px; filter:saturate(1.3) contrast(1.05);">
                <!-- Gradient overlay for text readability -->
                <div style="position:absolute; inset:0; background:linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 55%);"></div>
                <!-- Floating badge inside image -->
                <div style="position:absolute; bottom:12px; right:12px; background:rgba(255,255,255,0.12); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,0.2); border-radius:12px; padding:8px 14px; display:flex; align-items:center; gap:8px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:#4ade80;box-shadow:0 0 8px rgba(74,222,128,0.7);flex-shrink:0;"></span>
                    <span style="font-size:0.82rem;font-weight:700;">نعمل على رؤيتك الصحية</span>
                </div>
            </div>

            <h1 style="background: linear-gradient(135deg, #FFD700 0%, #FDB931 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">نتطلع إلى<br>مستقبلك الصحي</h1>
            <p>نحلم أن تُدار كل حالة بعناية واهتمام. خطواتك القادمة تبدأ من هنا — نتطلع أن نكون شريكك الموثوق في كل محطة من رحلتك.</p>

            <div class="brand-metrics">
                <div class="metric-pill">
                    <strong>دقيقة واحدة</strong>
                    <span>بداية سريعة وواضحة</span>
                </div>
                <div class="metric-pill">
                    <strong>خصوصية عالية</strong>
                    <span>حماية لبياناتك الطبية</span>
                </div>
                <div class="metric-pill">
                    <strong>توجيه أذكى</strong>
                    <span>نتائج أدق وخطوات أوضح</span>
                </div>
            </div>

            <div class="trust-feature">
                <div>
                    <h3>نسعى لرعاية حقيقية</h3>
                    <p>نرجو أن يصبح كل قرار صحي تتخذه أكثر وضوحاً ودقة، مدعوماً بمعطيات موثوقة وتوجيه ذكي.</p>
                </div>
            </div>

            <div class="trust-feature">
                <div>
                    <h3>نعمل على فهمك بعمق</h3>
                    <p>نتطلع أن تشعر أن المنصة صُممت خصيصاً لك — لأن كل تفصيلة تشاركها معنا تُشكّل تجربة أذكى وأدق.</p>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Form Side -->
    <div class="auth-form-wrap">
        <div class="auth-card">
            <div class="journey-progress" id="journey-progress">
                <div class="journey-head">
                    <div>
                        <span class="journey-label">رحلتك الآن</span>
                        <strong id="journey-title">لن تستغرق سوى دقيقة تقريباً</strong>
                    </div>
                    <span class="journey-percent" id="journey-percent">0%</span>
                </div>
                <div class="journey-track">
                    <span id="journey-fill"></span>
                </div>
                <div class="journey-steps">
                    <span class="journey-step is-active" data-step="1">التعرّف على حالتك</span>
                    <span class="journey-step" data-step="2">تجهيز ملفك</span>
                    <span class="journey-step" data-step="3">بدء المتابعة</span>
                </div>
                <p class="journey-tip" id="journey-tip">ابدأ بالسؤال الأول، وسنرتب بقية المسار لك بشكل أوضح.</p>
            </div>
            <h2>المعلومات التمهيدية</h2>
            <p class="sub">أدخل بياناتك الأساسية لبدء المتابعة الذكية</p>

            <?php if ($profileDone): ?>
                <div class="welcome-note">
                    بياناتك محفوظة مسبقاً. يمكنك تعديلها أو الانتقال مباشرة
                    <a href="../main/dashboard.php" style="text-decoration: underline; font-weight: 700;">للوحة المتابعة</a>.
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-box"><?php echo htmlspecialchars((string)$error); ?></div>
            <?php endif; ?>

            <form method="POST" id="questionnaire-form">
                <div class="form-group">
                    <label class="form-label">هل أنت مصاب بالسكري؟</label>
                    <div class="form-check">
                        <label class="radio-btn">
                            <input type="radio" name="diabetic" value="yes" onclick="toggleDiabeticFields('yes')" <?php echo $profileDone ? 'checked' : ''; ?> required>
                            نعم
                        </label>
                        <label class="radio-btn">
                            <input type="radio" name="diabetic" value="no" onclick="toggleDiabeticFields('no')">
                            لا
                        </label>
                        <label class="radio-btn">
                            <input type="radio" name="diabetic" value="unsure" onclick="toggleDiabeticFields('unsure')">
                            غير متأكد
                        </label>
                    </div>
                </div>

                <div id="diabetic-fields" class="<?php echo $profileDone ? '' : 'hidden'; ?>">
                    <div class="form-group">
                        <label class="form-label" for="fullName">الاسم الكامل</label>
                        <input type="text" name="fullName" id="fullName" class="form-control" autocomplete="off" placeholder="أدخل اسمك هنا" value="<?php echo htmlspecialchars((string)$pf_name); ?>">
                    </div>

                    <div class="grid-two">
                        <div class="form-group">
                            <label class="form-label" for="q-age">العمر</label>
                            <input type="number" name="age" id="q-age" class="form-control" min="1" max="120" value="<?php echo htmlspecialchars((string)$pf_age); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="q-gender">الجنس</label>
                            <select name="gender" id="q-gender" class="form-select" required>
                                <option value="male" <?php echo ($pf_gender === 'male') ? 'selected' : ''; ?>>ذكر</option>
                                <option value="female" <?php echo ($pf_gender === 'female') ? 'selected' : ''; ?>>أنثى</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="q-weight">الوزن التقريبي (كجم)</label>
                        <input type="number" name="weight" id="q-weight" step="0.1" min="2" max="500" class="form-control" value="<?php echo htmlspecialchars((string)$pf_weight); ?>">
                        <div id="weight-error-msg" style="display:none; color:#D32F2F; font-size:0.87rem; margin-top:6px; padding:6px 10px; background:rgba(211,47,47,0.08); border-radius:8px; border:1px solid rgba(211,47,47,0.2);"></div>
                    </div>

                    <div class="grid-two">
                        <div class="form-group">
                            <label class="form-label">تاريخ التشخيص</label>
                            <div class="date-input-group">
                                <button type="button" class="date-cal-btn" id="date-cal-btn-q" title="فتح التقويم" onclick="triggerDatePicker('diagnosis-date-hidden')">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </button>
                                <input type="text" id="diagnosis-date-display" class="date-text-input"
                                       placeholder="يوم / شهر / سنة"
                                       maxlength="14" autocomplete="off" inputmode="numeric">
                            </div>
                            <div class="date-error" id="diagnosis-date-error">تاريخ غير صحيح. الحد (1970 - اليوم).</div>
                            <input type="hidden" id="diagnosis-date-hidden" name="date_of_diagnosis"
                                   value="<?php echo htmlspecialchars((string)$pf_diagnosis); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">نوع العلاج الحالي</label>
                            <select name="therapy" class="form-select">

                                <option value="diet" <?php echo ($pf_therapy === 'diet') ? 'selected' : ''; ?>>حمية</option>
                                <option value="none" <?php echo ($pf_therapy === 'none') ? 'selected' : ''; ?>>بدون علاج</option>
                                <option value="regulator_no_metf" <?php echo ($pf_therapy === 'regulator_no_metf') ? 'selected' : ''; ?>>منظم لكن ليس ميتفورمين</option>
                                <option value="tablets" <?php echo ($pf_therapy === 'tablets') ? 'selected' : ''; ?>>علاج بالأقراص</option>
                                <option value="insulin" <?php echo ($pf_therapy === 'insulin') ? 'selected' : ''; ?>>إبر الإنسولين</option>
                                <option value="both" <?php echo ($pf_therapy === 'both') ? 'selected' : ''; ?>>أقراص وإنسولين معاً</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">نوع السكري</label>
                        <select name="diabetes_type" id="q-diabetes-type" class="form-select">
                            <option value="none" <?php echo ($pf_dtype === 'none') ? 'selected' : ''; ?>>غير متأكد / غير محدد</option>
                            <option value="child" id="child-diabetes-option" <?php echo ($pf_dtype === 'child') ? 'selected' : ''; ?>>سكري الأطفال</option>
                            <option value="T1" <?php echo ($pf_dtype === 'T1') ? 'selected' : ''; ?>>النوع الأول (T1)</option>
                            <option value="T2" <?php echo ($pf_dtype === 'T2') ? 'selected' : ''; ?>>النوع الثاني (T2)</option>
                            <option value="GDM" id="gdm-option" <?php echo ($pf_dtype === 'GDM') ? 'selected' : ''; ?>>سكري الحمل (GDM)</option>
                        </select>
                    </div>

                    <?php if ($user_id === 0): ?>
                    <div class="form-group" id="has-account-group">
                        <label class="form-label">هل لديك حساب؟</label>
                        <div class="form-check">
                            <label class="radio-btn">
                                <input type="radio" name="has_account" value="yes" id="has_account_yes" onchange="handleHasAccount(this.value)">
                                نعم، لدي حساب
                            </label>
                            <label class="radio-btn">
                                <input type="radio" name="has_account" value="no" id="has_account_no" onchange="handleHasAccount(this.value)" checked>
                                لا، سأنشئ حساباً جديداً
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-submit">حفظ ومتابعة المسار</button>
            </form>
        </div>
    </div>
</div>

<script>
    /* ── التحقق المنطقي من الوزن بالنسبة للعمر ── */
    function validateWeightByAge(age, weight) {
        age = parseFloat(age);
        weight = parseFloat(weight);

        if (isNaN(age) || age <= 0 || isNaN(weight) || weight <= 0) {
            return { valid: false, message: 'يرجى إدخال العمر والوزن بصورة صحيحة' };
        }

        let minW, maxW;
        if (age < 1)        { minW = 2;  maxW = 15; }
        else if (age <= 5)  { minW = 5;  maxW = 30; }
        else if (age <= 12) { minW = 10; maxW = 60; }
        else if (age <= 18) { minW = 20; maxW = 120; }
        else                { minW = 25; maxW = 300; }

        if (weight < minW || weight > maxW) {
            return {
                valid: false,
                message: `الوزن المدخل غير منطقي للعمر ${age}. الوزن المقبول تقريباً بين ${minW} و ${maxW} كغم`
            };
        }

        return { valid: true, message: 'الوزن مقبول منطقياً' };
    }

    function checkWeightField() {
        const ageInput    = document.getElementById('q-age');
        const weightInput = document.getElementById('q-weight');
        const msgBox      = document.getElementById('weight-error-msg');
        if (!ageInput || !weightInput || !msgBox) return true;

        const age    = ageInput.value.trim();
        const weight = weightInput.value.trim();

        if (age === '' || weight === '') {
            msgBox.style.display = 'none';
            return true; // لا تعطي خطأ إذا لم يُملأ الحقل بعد
        }

        const result = validateWeightByAge(age, weight);
        if (!result.valid) {
            msgBox.textContent = '⚠️ ' + result.message;
            msgBox.style.display = 'block';
            return false;
        } else {
            msgBox.style.display = 'none';
            return true;
        }
    }

    /* ── ربط الأحداث بعد تحميل الصفحة ── */
    function isTrackableFieldVisible(field) {
        if (!field) return false;
        if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button') return false;
        if (field.closest('.hidden')) return false;
        return true;
    }

    function getJourneyProgressState() {
        const form = document.getElementById('questionnaire-form');
        if (!form) return { total: 0, filled: 0, percent: 0 };

        const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter(isTrackableFieldVisible);
        const radioGroups = new Map();
        let total = 0;
        let filled = 0;

        fields.forEach((field) => {
            if (field.type === 'radio') {
                if (radioGroups.has(field.name)) return;
                const group = form.querySelectorAll(`input[name="${field.name}"]`);
                const visibleGroup = Array.from(group).filter(isTrackableFieldVisible);
                if (visibleGroup.length === 0) return;
                radioGroups.set(field.name, true);
                total += 1;
                if (Array.from(visibleGroup).some((item) => item.checked)) {
                    filled += 1;
                }
                return;
            }

            total += 1;
            if (field.tagName === 'SELECT') {
                if (field.value.trim() !== '') {
                    filled += 1;
                }
                return;
            }

            if (field.value.trim() !== '') {
                filled += 1;
            }
        });

        const percent = total > 0 ? Math.max(0, Math.min(100, Math.round((filled / total) * 100))) : 0;
        return { total, filled, percent };
    }

    function updateJourneyProgress() {
        const percentEl = document.getElementById('journey-percent');
        const fillEl = document.getElementById('journey-fill');
        const tipEl = document.getElementById('journey-tip');
        const titleEl = document.getElementById('journey-title');
        const steps = document.querySelectorAll('.journey-step');
        if (!percentEl || !fillEl || !tipEl || !titleEl || steps.length === 0) return;

        const state = getJourneyProgressState();
        percentEl.textContent = `${state.percent}%`;
        fillEl.style.width = `${state.percent}%`;

        let stepIndex = 1;
        let title = 'لن تستغرق سوى دقيقة تقريباً';
        let tip = 'ابدأ بالسؤال الأول، وسنرتب بقية المسار لك بشكل أوضح.';

        if (state.percent >= 34 && state.percent < 67) {
            stepIndex = 2;
            title = 'أنت تمضي بشكل ممتاز';
            tip = 'ممتاز، بقيت خطوات بسيطة ليصبح ملفك أوضح وأكثر دقة.';
        } else if (state.percent >= 67 && state.percent < 100) {
            stepIndex = 3;
            title = 'لم يتبق إلا القليل';
            tip = 'أنت قريب من الانتهاء، وبعدها تبدأ المتابعة بخطة أوضح.';
        } else if (state.percent === 100) {
            stepIndex = 3;
            title = 'جاهز لبدء المتابعة';
            tip = 'كل شيء مكتمل. اضغط حفظ ومتابعة المسار للانتقال للخطوة التالية.';
        }

        titleEl.textContent = title;
        tipEl.textContent = tip;

        steps.forEach((step) => {
            const current = Number(step.dataset.step || '0');
            step.classList.toggle('is-active', current === stepIndex);
            step.classList.toggle('is-done', current < stepIndex || (state.percent === 100 && current === stepIndex));
        });
    }

    function toggleDiabeticFields(val) {
        var box = document.getElementById('diabetic-fields');
        if (!box) return;
        
        const form = document.getElementById('questionnaire-form');
        const assistantUrl = '../Awareness/assistant.php?entry=intake';
        const requiredIds = ['fullName', 'q-age', 'q-weight', 'q-gender'];

        if (val === 'yes') {
            box.classList.remove('hidden');
            // Add required attribute and stars
            requiredIds.forEach(id => {
               const el = document.getElementById(id);
               if(el) {
                   el.setAttribute('required', 'required');
                   addRequiredStar(el);
               }
            });
        } else {
            box.classList.add('hidden');
            // Remove required attribute - otherwise browser blocks submit even if hidden
            requiredIds.forEach(id => {
               const el = document.getElementById(id);
               if(el) {
                   el.removeAttribute('required');
                   removeRequiredStar(el);
               }
            });
            updateJourneyProgress();

            if (val === 'no') {
                setTimeout(() => {
                    if(form) form.submit();
                }, 180);
                return;
            }

            if (val === 'unsure') {
                setTimeout(() => {
                    window.location.href = assistantUrl;
                }, 180);
                return;
            }

            return;
        }

        updateJourneyProgress();
    }

    function addRequiredStar(field) {
        const parent = field.closest('.form-group');
        if (!parent) return;
        const label = parent.querySelector('label');
        if (label && !label.querySelector('.required-star-mark')) {
            const star = document.createElement('span');
            star.className = 'required-star-mark';
            star.textContent = '*';
            label.insertBefore(star, label.firstChild);
        }
    }

    function removeRequiredStar(field) {
        const parent = field.closest('.form-group');
        if (!parent) return;
        const label = parent.querySelector('label');
        if (label) {
            const star = label.querySelector('.required-star-mark');
            if (star) star.remove();
        }
    }

    function updateGdmVisibility() {
        const genderEl = document.getElementById('q-gender');
        const ageEl = document.getElementById('q-age');
        const dTypeEl = document.getElementById('q-diabetes-type');
        const gdmOpt = document.getElementById('gdm-option');
        const childOpt = document.getElementById('child-diabetes-option');
        if (!genderEl || !dTypeEl || !gdmOpt || !childOpt) return;

        const gender = genderEl.value;
        const age = parseInt(ageEl ? ageEl.value : '0') || 0;

        const isChild = age > 0 && age < 11;
        const isGdmLogical = (gender === 'female' && age >= 11 && age <= 55);

        childOpt.style.display = isChild ? 'block' : 'none';
        childOpt.disabled = !isChild;
        if (!isChild && dTypeEl.value === 'child') {
            dTypeEl.value = 'none';
        }

        if (!isGdmLogical) {
            if (dTypeEl.value === 'GDM') {
                dTypeEl.value = isChild ? 'child' : 'none';
            }
            gdmOpt.style.display = 'none';
            gdmOpt.disabled = true;
        } else {
            gdmOpt.style.display = 'block';
            gdmOpt.disabled = false;
        }
    }

    function handleHasAccount(val) {
        if (val === 'yes') {
            // توجيه مباشر لصفحة الدخول عبر المتصفح لتخطي التحقق الإلزامي من الحقول
            window.location.href = '../google_sign_in/auth/login.php';
        }
    }

    // Initialize visibility based on checked radio
    window.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-back-button], .btn-back').forEach((backButton) => {
            backButton.addEventListener('click', (event) => {
                event.preventDefault();
                window.location.href = '../welcome.php';
            });
        });

        var selected = document.querySelector('input[name="diabetic"]:checked');
        if(selected) {
            toggleDiabeticFields(selected.value);
        }

        // ── ربط التحقق من الوزن بحقلي العمر والوزن ──
        const ageInput    = document.getElementById('q-age');
        const weightInput = document.getElementById('q-weight');

        if (ageInput && weightInput) {
            // التحقق عند مغادرة أي من الحقلين أو تغيير قيمته
            weightInput.addEventListener('input',  checkWeightField);
            weightInput.addEventListener('blur',   checkWeightField);
            ageInput.addEventListener('input',     checkWeightField);
            ageInput.addEventListener('blur',      checkWeightField);
        }

        const genderEl = document.getElementById('q-gender');
        if (genderEl) {
            genderEl.addEventListener('change', updateGdmVisibility);
        }
        if (ageInput) {
            ageInput.addEventListener('input', updateGdmVisibility);
            ageInput.addEventListener('change', updateGdmVisibility);
        }
        updateGdmVisibility(); // Initial check

        // منع الإرسال إذا كان الوزن غير منطقي
        const form = document.getElementById('questionnaire-form');
        if (form) {
            form.querySelectorAll('input, select, textarea').forEach((field) => {
                field.addEventListener('input', updateJourneyProgress);
                field.addEventListener('change', updateJourneyProgress);
            });

            form.addEventListener('submit', function(e) {
                if (!checkWeightField()) {
                    e.preventDefault();
                    document.getElementById('q-weight').focus();
                }
            });
        }

        updateJourneyProgress();

        // Theme Toggle Logic (Standardized)
        const themeToggle = document.getElementById('themeToggle');
        const themeThumb = document.getElementById('themeToggleThumb');
        if (themeToggle && themeThumb) {
            const updateUI = (isDark) => {
                // thumb animation handled by CSS
            };
            
            const currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.dataset.theme = currentTheme;
            updateUI(currentTheme === 'dark');

            themeToggle.addEventListener('click', () => {
                const isDark = document.documentElement.dataset.theme === 'dark';
                const newTheme = isDark ? 'light' : 'dark';
                document.documentElement.dataset.theme = newTheme;
                localStorage.setItem('theme', newTheme);
                updateUI(newTheme === 'dark');
            });
        }

        // ── Custom Date Masking Logic ──
        (function() {
            const display  = document.getElementById('diagnosis-date-display');
            const hidden   = document.getElementById('diagnosis-date-hidden');
            const errDiv   = document.getElementById('diagnosis-date-error');
            if (!display || !hidden) return;

            const TODAY = new Date();
            TODAY.setHours(23, 59, 59);
            const MIN_DATE = new Date('1970-01-01');

            // If pre-filled from PHP: yyyy-mm-dd → dd / mm / yyyy
            if (hidden.value) {
                const p = hidden.value.split('-');
                if (p.length === 3) display.value = p[2] + ' / ' + p[1] + ' / ' + p[0];
            }

            function validate(d, m, y) {
                const date = new Date(`${y}-${m}-${d}`);
                if (isNaN(date.getTime())) return false;
                if (date.getDate() !== parseInt(d, 10)) return false; // Leap/overflow check
                return date >= MIN_DATE && date <= TODAY;
            }

            display.addEventListener('input', function(e) {
                let raw = this.value.replace(/\D/g, '').substring(0, 8);
                let out = '';
                if (raw.length > 0) out = raw.substring(0, 2);
                if (raw.length > 2) out += ' / ' + raw.substring(2, 4);
                if (raw.length > 4) out += ' / ' + raw.substring(4, 8);
                this.value = out;

                errDiv.style.display = 'none';
                hidden.value = '';

                if (raw.length === 8) {
                    const d = raw.substring(0, 2);
                    const m = raw.substring(2, 4);
                    const y = raw.substring(4, 8);
                    if (validate(d, m, y)) {
                        hidden.value = `${y}-${m}-${d}`;
                    } else {
                        errDiv.style.display = 'block';
                    }
                }
            });

            // Calendar picker trigger (opens a hidden native date input)
            window.triggerDatePicker = function(hiddenId) {
                let picker = document.getElementById('__native_date_picker__');
                if (!picker) {
                    picker = document.createElement('input');
                    picker.type = 'date';
                    picker.id = '__native_date_picker__';
                    picker.style.cssText = 'position:fixed;opacity:0;width:0;height:0;pointer-events:none;top:0;left:0;';
                    picker.min = '1970-01-01';
                    picker.max = TODAY.toISOString().split('T')[0];
                    document.body.appendChild(picker);
                    picker.addEventListener('change', function() {
                        if (!this.value) return;
                        const h2 = document.getElementById('diagnosis-date-hidden');
                        const d2 = document.getElementById('diagnosis-date-display');
                        if (h2) h2.value = this.value;
                        if (d2) {
                            const p = this.value.split('-');
                            d2.value = p[2] + ' / ' + p[1] + ' / ' + p[0];
                        }
                        const err2 = document.getElementById('diagnosis-date-error');
                        if (err2) err2.style.display = 'none';
                    });
                }
                picker.min = '1970-01-01';
                picker.max = TODAY.toISOString().split('T')[0];
                picker.value = document.getElementById(hiddenId)?.value || '';
                picker.showPicker();
            };
        })();

    });
</script>
</body>
</html>
