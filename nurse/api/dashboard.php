<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../db/connect.php';

// 取出所有病房 + 目前住院中的病人 + 該病人的最新診斷
$sql = "
    SELECT
        w.ward_id,
        w.ward_name,
        w.ward_status,
        p.patient_id,
        p.name AS patient_name,
        dr.status AS doctor_sign_status,
        dr.diagnosis,
        dr.prescription
    FROM ward w
    LEFT JOIN allocation_record ar
        ON w.ward_id = ar.ward_id AND ar.leave_date IS NULL
    LEFT JOIN patient p
        ON ar.patient_id = p.patient_id
    LEFT JOIN medical_record mr
        ON p.patient_id = mr.patient_id
    LEFT JOIN diagnosis_result dr
        ON mr.record_id = dr.record_id
       AND dr.diagnosis_result_id = (
            SELECT dr_sub.diagnosis_result_id
            FROM diagnosis_result dr_sub
            JOIN medical_record mr_sub ON dr_sub.record_id = mr_sub.record_id
            WHERE mr_sub.patient_id = p.patient_id
            ORDER BY dr_sub.diagnosis_result_id DESC
            LIMIT 1
       )
    ORDER BY w.ward_id ASC
";

$result = $conn->query($sql);
if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "查詢失敗",
        "error" => $conn->error
    ]);
    exit;
}

// 確保每個 ward_id 只回傳一筆（優先有病人的）
$processed = [];
while ($row = $result->fetch_assoc()) {
    $wid = $row['ward_id'];

    if ($row['patient_id'] !== null) {
        // 有病人 → 一定覆蓋
        $processed[$wid] = $row;
    } else {
        // 沒病人 → 只有第一次遇到這個 ward 才存
        if (!isset($processed[$wid])) {
            $processed[$wid] = $row;
        }
    }
}

echo json_encode([
    "success" => true,
    "data" => array_values($processed)
]);
