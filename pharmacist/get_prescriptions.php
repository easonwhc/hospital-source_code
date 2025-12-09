<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../db/connect.php';

/*
    ★ 一個診斷結果只會顯示一列
    ★ 藥品使用 GROUP_CONCAT 合併
*/

$sql = "
    SELECT
        dr.diagnosis_result_id,
        p.name AS patient_name,
        mr.visit_time,
        dr.diagnosis,
        dr.prescription AS doctor_advice,
        dr.status AS med_status,
        dr.reject_reason,

        (
            SELECT GROUP_CONCAT(CONCAT(m.Medication_name, ' × ', rec.quantity) SEPARATOR '，')
            FROM medication_record rec
            JOIN medication m ON rec.medication_id = m.Medication_id
            WHERE rec.diagnosis_result_id = dr.diagnosis_result_id
        ) AS medications

    FROM diagnosis_result dr
    JOIN medical_record mr ON dr.record_id = mr.record_id
    JOIN patient p ON mr.patient_id = p.patient_id
    ORDER BY mr.visit_time DESC
";

$rs = $conn->query($sql);

$list = [];

while ($row = $rs->fetch_assoc()) {
    if ($row['medications'] === null)
        $row['medications'] = "尚未開藥";

    $list[] = $row;
}

echo json_encode([
    "success" => true,
    "prescriptions" => $list
], JSON_UNESCAPED_UNICODE);
exit;
?>