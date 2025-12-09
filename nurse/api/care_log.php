<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../db/connect.php';

$input = json_decode(file_get_contents("php://input"), true);

$nurse_id = $input['nurse_id'] ?? null;
$patient_id = $input['patient_id'] ?? null;
$log_content = $input['log_content'] ?? '';
$doctor_sign_status = $input['doctor_sign_status'] ?? '';

if ($doctor_sign_status !== 'Completed') {
    echo json_encode([
        "success" => false,
        "message" => "拒絕操作：醫師尚未簽核，無法執行照護。"
    ]);
    exit;
}

if (empty($nurse_id) || empty($patient_id) || $log_content === '') {
    echo json_encode([
        "success" => false,
        "message" => "缺少必要欄位。"
    ]);
    exit;
}

$sql = "INSERT INTO care_log (nurse_id, patient_id, log_content)
        VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $nurse_id, $patient_id, $log_content);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "寫入紀錄失敗",
        "error" => $stmt->error
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "照護紀錄已成功儲存"
]);
