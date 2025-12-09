<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../db/connect.php';

$input = json_decode(file_get_contents("php://input"), true);
$patient_id = $input['patient_id'] ?? null;

if (empty($patient_id)) {
    echo json_encode([
        "success" => false,
        "message" => "缺少病患資訊"
    ]);
    exit;
}

try {
    $conn->begin_transaction();

    // 1. 找出目前住院中的 allocation_record
    $stmt = $conn->prepare("
        SELECT allocation_id, ward_id
        FROM allocation_record
        WHERE patient_id = ? AND leave_date IS NULL
        FOR UPDATE
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        throw new Exception("未找到病患當前的住院紀錄 (可能已出院)");
    }
    $row = $res->fetch_assoc();
    $allocation_id = $row['allocation_id'];
    $ward_id = $row['ward_id'];

    // 2. 結束 allocation_record
    $stmt = $conn->prepare("
        UPDATE allocation_record
        SET leave_date = NOW()
        WHERE allocation_id = ?
    ");
    $stmt->bind_param("i", $allocation_id);
    $stmt->execute();

    // 3. 更新病房狀態為 empty + 註記
    $stmt = $conn->prepare("
        UPDATE ward
        SET ward_status = 'empty',
            ward_record = '已出院，空床待清潔/待用'
        WHERE ward_id = ?
    ");
    $stmt->bind_param("i", $ward_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "病患 {$patient_id} 已成功出院，病房 {$ward_id} 已清空。"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => "辦理出院失敗：" . $e->getMessage()
    ]);
}
