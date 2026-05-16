<?php
/**
 * Evaluate Glucose Reading based on age and diabetes type.
 * Uses the glucose_standards table.
 * 
 * @param float $value The reading value
 * @param string $unit 'mg' or 'mmol' (will be converted to mg/dL for comparison)
 * @param string $context 'fasting', 'ramadan', or 'post'
 * @param int $age User's age
 * @param string $diabetes_type User's diabetes type (e.g. 'type1', 'type2', 'gestational', 'none')
 * @param mysqli $conn Database connection
 * @return string 'منخفض', 'طبيعي', or 'مرتفع'
 */
function evaluateGlucose($value, $unit, $context, $age, $diabetes_type, $conn) {
    // Normalize value to mg/dL
    $value_mg = ($unit === 'mmol') ? $value * 18 : $value;
    
    // Normalize context
    $context = strtolower($context);
    if ($context === 'ramadan') {
        $context = 'fasting';
    }

    // Map the incoming diabetes_type to the enum in DB
    $mapped_type = 'any';
    $valid_types = ['type1', 'type2', 'gestational', 'prediabetes', 'none', 'any'];
    $dt_lower = strtolower($diabetes_type);

    if (in_array($dt_lower, $valid_types)) {
        $mapped_type = $dt_lower;
    } elseif ($dt_lower === 't1') {
         $mapped_type = 'type1';
    } elseif ($dt_lower === 't2') {
         $mapped_type = 'type2';
    } elseif ($dt_lower === 'gdm') {
         $mapped_type = 'gestational';
    }

    // Query the standards
    $query = "
        SELECT fasting_min, fasting_max, post2h_max 
        FROM glucose_standards 
        WHERE ? BETWEEN age_min AND age_max 
        AND (
            diabetes_type = ? 
            OR (diabetes_type = 'any' AND ? NOT IN ('type1', 'type2', 'gestational', 'none'))
        )
        ORDER BY 
            CASE WHEN diabetes_type = ? THEN 1 ELSE 2 END
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        // Fallback if query fails
        error_log("Failed to prepare glucose standards query: " . $conn->error);
        return 'غير معروف';
    }

    $stmt->bind_param("isss", $age, $mapped_type, $mapped_type, $mapped_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $fasting_min = (float)$row['fasting_min'];
        $fasting_max = (float)$row['fasting_max'];
        $post2h_max  = (float)$row['post2h_max'];
        
        if ($context === 'fasting') {
            if ($value_mg < $fasting_min) return 'منخفض';
            if ($value_mg <= $fasting_max) return 'طبيعي';
            return 'مرتفع';
        } elseif ($context === 'post') {
            if ($value_mg < 70) return 'منخفض'; // standard global min for post
            if ($value_mg <= $post2h_max) return 'طبيعي';
            return 'مرتفع';
        }
    }
    
    // Ultimate universal fallback if no standard matched
    if ($context === 'fasting') {
        if ($value_mg < 70) return 'منخفض';
        if ($value_mg <= 130) return 'طبيعي';
        return 'مرتفع';
    } else {
        if ($value_mg < 70) return 'منخفض';
        if ($value_mg <= 180) return 'طبيعي';
        return 'مرتفع';
    }
}
?>
