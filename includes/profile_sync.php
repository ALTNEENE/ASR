<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('dmproject_normalize_profile_payload')) {
    /**
     * Normalize incoming profile payload into one shape used by user_profile and patient_data.
     */
    function dmproject_normalize_profile_payload(array $source): ?array
    {
        $fullName = trim((string)($source['full_name'] ?? $source['name'] ?? ''));
        $age = (int)($source['age'] ?? 0);
        $weight = (float)($source['weight'] ?? 0);

        if ($fullName === '' || $age <= 0 || $weight <= 0) {
            return null;
        }

        $gender = strtolower((string)($source['gender'] ?? 'male'));
        if (!in_array($gender, ['male', 'female'], true)) {
            $gender = 'male';
        }

        $therapy = strtolower((string)($source['therapy_type'] ?? $source['treatment_type'] ?? 'none'));
        if (!in_array($therapy, ['none', 'tablets', 'insulin', 'both'], true)) {
            $therapy = 'none';
        }

        $diabetesTypeRaw = strtoupper(trim((string)($source['diabetes_type'] ?? 'none')));
        if (!in_array($diabetesTypeRaw, ['T1', 'T2', 'GDM', 'CHILD', 'NONE'], true)) {
            $diabetesType = 'none';
        } else {
            $diabetesType = ($diabetesTypeRaw === 'NONE') ? 'none' : strtolower($diabetesTypeRaw);
            if ($diabetesType === 't1') $diabetesType = 'T1';
            if ($diabetesType === 't2') $diabetesType = 'T2';
            if ($diabetesType === 'gdm') $diabetesType = 'GDM';
            if ($diabetesType === 'child') $diabetesType = 'child';
        }
        if ($diabetesType === 'GDM' && !($gender === 'female' && $age >= 11 && $age <= 55)) {
            $diabetesType = ($age > 0 && $age < 11) ? 'child' : 'none';
        }

        $height = null;
        if (isset($source['height']) && $source['height'] !== '' && is_numeric($source['height'])) {
            $height = (float)$source['height'];
            if ($height <= 0) {
                $height = null;
            }
        }

        $diagnosisDate = trim((string)($source['date_of_diagnosis'] ?? $source['diagnosis_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $diagnosisDate)) {
            $diagnosisDate = '';
        }

        $drugDoseMap = [];
        if (isset($source['drug_dose_map'])) {
            if (is_array($source['drug_dose_map'])) {
                $drugDoseMap = $source['drug_dose_map'];
            } else {
                $decoded = json_decode((string)$source['drug_dose_map'], true);
                if (is_array($decoded)) {
                    $drugDoseMap = $decoded;
                }
            }
        }
        $drugDoseJson = json_encode($drugDoseMap, JSON_UNESCAPED_UNICODE);
        if (!is_string($drugDoseJson)) {
            $drugDoseJson = '[]';
        }

        return [
            'full_name' => $fullName,
            'age' => $age,
            'gender' => $gender,
            'weight' => $weight,
            'height' => $height,
            'therapy_type' => $therapy,
            'diagnosis_date' => $diagnosisDate,
            'diabetes_type' => $diabetesType,
            'drug_dose_map' => $drugDoseJson
        ];
    }
}

if (!function_exists('dmproject_upsert_linked_profile_data')) {
    /**
     * Upsert profile data in user_profile + patient_data and mark users survey as completed.
     */
    function dmproject_upsert_linked_profile_data(mysqli $conn, int $userId, array $payload): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $data = dmproject_normalize_profile_payload($payload);
        if ($data === null) {
            return false;
        }

        $fullName = $data['full_name'];
        $age = $data['age'];
        $gender = $data['gender'];
        $weight = $data['weight'];
        $heightForSql = $data['height'] ?? 0.0;
        $diagnosisDate = $data['diagnosis_date'];
        $therapyType = $data['therapy_type'];
        $diabetesType = $data['diabetes_type'];
        $drugDoseMap = $data['drug_dose_map'];
        $role = 'diabetic';

        $conn->begin_transaction();

        try {
            $profileCheck = $conn->prepare('SELECT id FROM user_profile WHERE user_id = ? LIMIT 1');
            if (!$profileCheck) {
                throw new RuntimeException('Unable to prepare user_profile check.');
            }
            $profileCheck->bind_param('i', $userId);
            $profileCheck->execute();
            $profileExists = $profileCheck->get_result()->num_rows > 0;
            $profileCheck->close();

            if ($profileExists) {
                $profileStmt = $conn->prepare('UPDATE user_profile SET full_name=?, age=?, gender=?, weight=?, height=NULLIF(?, 0), diagnosis_date=NULLIF(?, \'\'), treatment_type=?, diabetes_type=?, updated_at=NOW() WHERE user_id=?');
                if (!$profileStmt) {
                    throw new RuntimeException('Unable to prepare user_profile update.');
                }
                $profileStmt->bind_param('sisddsssi', $fullName, $age, $gender, $weight, $heightForSql, $diagnosisDate, $therapyType, $diabetesType, $userId);
            } else {
                $profileStmt = $conn->prepare('INSERT INTO user_profile (user_id, full_name, age, gender, weight, height, diagnosis_date, treatment_type, diabetes_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, \'\'), ?, ?, NOW(), NOW())');
                if (!$profileStmt) {
                    throw new RuntimeException('Unable to prepare user_profile insert.');
                }
                $profileStmt->bind_param('isisddsss', $userId, $fullName, $age, $gender, $weight, $heightForSql, $diagnosisDate, $therapyType, $diabetesType);
            }
            if (!$profileStmt->execute()) {
                throw new RuntimeException('user_profile upsert failed: ' . $profileStmt->error);
            }
            $profileStmt->close();

            $patientCheck = $conn->prepare('SELECT id FROM patient_data WHERE user_id = ? LIMIT 1');
            if (!$patientCheck) {
                throw new RuntimeException('Unable to prepare patient_data check.');
            }
            $patientCheck->bind_param('i', $userId);
            $patientCheck->execute();
            $patientExists = $patientCheck->get_result()->num_rows > 0;
            $patientCheck->close();

            if ($patientExists) {
                $patientStmt = $conn->prepare('UPDATE patient_data SET name=?, age=?, gender=?, weight=?, height=NULLIF(?, 0), therapy_type=?, date_of_diagnosis=NULLIF(?, \'\'), drug_dose_map=?, updated_at=NOW() WHERE user_id=?');
                if (!$patientStmt) {
                    throw new RuntimeException('Unable to prepare patient_data update.');
                }
                $patientStmt->bind_param('sisddsssi', $fullName, $age, $gender, $weight, $heightForSql, $therapyType, $diagnosisDate, $drugDoseMap, $userId);
            } else {
                $patientStmt = $conn->prepare('INSERT INTO patient_data (user_id, name, age, gender, weight, height, therapy_type, date_of_diagnosis, drug_dose_map, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), ?, NULLIF(?, \'\'), ?, NOW(), NOW())');
                if (!$patientStmt) {
                    throw new RuntimeException('Unable to prepare patient_data insert.');
                }
                $patientStmt->bind_param('isisddsss', $userId, $fullName, $age, $gender, $weight, $heightForSql, $therapyType, $diagnosisDate, $drugDoseMap);
            }
            if (!$patientStmt->execute()) {
                throw new RuntimeException('patient_data upsert failed: ' . $patientStmt->error);
            }
            $patientStmt->close();

            $flagStmt = $conn->prepare('UPDATE users SET role=?, survey_completed=1, profile_completed_at=NOW() WHERE id=?');
            if ($flagStmt) {
                $flagStmt->bind_param('si', $role, $userId);
                if (!$flagStmt->execute()) {
                    throw new RuntimeException('Failed to update survey_completed status: ' . $flagStmt->error);
                }
                $flagStmt->close();
            } else {
                $roleOnly = $conn->prepare('UPDATE users SET role=? WHERE id=?');
                if (!$roleOnly) {
                    throw new RuntimeException('Unable to prepare users role update.');
                }
                $roleOnly->bind_param('si', $role, $userId);
                if (!$roleOnly->execute()) {
                    throw new RuntimeException('Failed to update user role: ' . $roleOnly->error);
                }
                $roleOnly->close();
            }

            $conn->commit();
            $_SESSION['role'] = 'diabetic';
            $_SESSION['full_name'] = $fullName;
            $_SESSION['user_path'] = 'diabetic';
            unset($_SESSION['guest_mode']);
            return true;
        } catch (Throwable $e) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                // Ignore rollback failure and keep original error.
            }
            error_log('Profile sync failed for user ' . $userId . ': ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('dmproject_sync_temp_patient_data')) {
    /**
     * Link pre-auth questionnaire data (stored in session) to the authenticated user.
     */
    function dmproject_sync_temp_patient_data(mysqli $conn, int $userId): bool
    {
        if ($userId <= 0 || !isset($_SESSION['temp_patient_data']) || !is_array($_SESSION['temp_patient_data'])) {
            return false;
        }

        // Do not overwrite an already completed profile with stale temporary session data.
        $profileCheck = $conn->prepare('SELECT id FROM user_profile WHERE user_id = ? LIMIT 1');
        if ($profileCheck) {
            $profileCheck->bind_param('i', $userId);
            $profileCheck->execute();
            $alreadyHasProfile = $profileCheck->get_result()->num_rows > 0;
            $profileCheck->close();
            if ($alreadyHasProfile) {
                unset($_SESSION['temp_patient_data']);
                return false;
            }
        }

        $synced = dmproject_upsert_linked_profile_data($conn, $userId, $_SESSION['temp_patient_data']);
        if ($synced) {
            unset($_SESSION['temp_patient_data']);
        }

        return $synced;
    }
}
