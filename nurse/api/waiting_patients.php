<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../db/connect.php';

$sql = "
    SELECT
        p.patient_id,
        p.name
    FROM patient p
    LEFT JOIN allocation_record ar
        ON p.patient_id = ar.patient_id
       AND ar.leave_date IS NULL
    LEFT JOIN medical_record mr
        ON p.patient_id = mr.patient_id
    LEFT JOIN diagnosis_result dr
        ON mr.record_id = dr.record_id
       AND dr.diagnosis_result_id = (
            SELECT dr_sub.diagnosis_result_id
            FROM medical_record mr_sub
            JOIN diagnosis_result dr_sub ON mr_sub.record_id = dr_sub.record_id
            WHERE mr_sub.patient_id = p.patient_id
            ORDER BY dr_sub.diagnosis_result_id DESC
            LIMIT 1
       )
    WHERE
        ar.allocation_id IS NULL   -- 目前沒有住院中紀錄
        AND dr.hospitalized = 1    -- 最新診斷要求住院
        AND dr.diagnosis_result_id IS NOT NULL
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

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    "success" => true,
    "data" => $data
]);
