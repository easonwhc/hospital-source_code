<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../db/connect.php';

$input = json_decode(file_get_contents("php://input"), true);

$patient_id = $input['patient_id'] ?? null;
$ward_id = $input['ward_id'] ?? null;

if (empty($patient_id) || empty($ward_id)) {
    echo json_encode([
        "success" => false,
        "message" => "缺少病患或病房資訊"
    ]);
    exit;
}

try {
    $conn->begin_transaction();

    // 1. 檢查病房是否為 empty
    $stmt = $conn->prepare("SELECT ward_status FROM ward WHERE ward_id = ? FOR UPDATE");
    $stmt->bind_param("i", $ward_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        throw new Exception("病房不存在");
    }
    $row = $res->fetch_assoc();
    if ($row['ward_status'] !== 'empty') {
        throw new Exception("病房不可用或不是空床");
    }

    // 2. 建立 allocation_record
    $stmt = $conn->prepare("
        INSERT INTO allocation_record (patient_id, ward_id, allocation_date)
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param("ii", $patient_id, $ward_id);
    $stmt->execute();

    // 3. 更新病房狀態為 occupied
    $stmt = $conn->prepare("
        UPDATE ward SET ward_status = 'occupied', ward_record = '正常使用中'
        WHERE ward_id = ?
    ");
    $stmt->bind_param("i", $ward_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "病房分配成功"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => "病房分配失敗：" . $e->getMessage()
    ]);
}
