<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../db/connect.php';

$input = json_decode(file_get_contents("php://input"), true);

$patient_id = $input['patient_id'] ?? null;
$new_ward_id = $input['new_ward_id'] ?? null;

if (empty($patient_id) || empty($new_ward_id)) {
    echo json_encode([
        "success" => false,
        "message" => "缺少病患或新病房資訊"
    ]);
    exit;
}

try {
    $conn->begin_transaction();

    // 1. 檢查新病房是否為 empty
    $stmt = $conn->prepare("
        SELECT ward_status FROM ward WHERE ward_id = ? FOR UPDATE
    ");
    $stmt->bind_param("i", $new_ward_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        throw new Exception("新病房不存在");
    }
    $row = $res->fetch_assoc();
    if ($row['ward_status'] !== 'empty') {
        throw new Exception("新病房不可用或不是空床");
    }

    // 2. 找出舊的 allocation_record（目前住院中的紀錄）
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
        throw new Exception("未找到病患當前的住院紀錄");
    }
    $old = $res->fetch_assoc();
    $old_allocation_id = $old['allocation_id'];
    $old_ward_id = $old['ward_id'];

    // 3. 結束舊紀錄
    $stmt = $conn->prepare("
        UPDATE allocation_record
        SET leave_date = NOW()
        WHERE allocation_id = ?
    ");
    $stmt->bind_param("i", $old_allocation_id);
    $stmt->execute();

    // 4. 新增新的 allocation_record
    $stmt = $conn->prepare("
        INSERT INTO allocation_record (patient_id, ward_id, allocation_date)
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param("ii", $patient_id, $new_ward_id);
    $stmt->execute();

    // 5. 更新舊病房為 empty + 註記
    $stmt = $conn->prepare("
        UPDATE ward
        SET ward_status = 'empty',
            ward_record = '空床待清潔/待用'
        WHERE ward_id = ?
    ");
    $stmt->bind_param("i", $old_ward_id);
    $stmt->execute();

    // 6. 更新新病房為 occupied + 註記
    $stmt = $conn->prepare("
        UPDATE ward
        SET ward_status = 'occupied',
            ward_record = '正常使用中 (轉入)'
        WHERE ward_id = ?
    ");
    $stmt->bind_param("i", $new_ward_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "病患已成功從 {$old_ward_id} 轉移到 {$new_ward_id}"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => "轉移病房失敗：" . $e->getMessage()
    ]);
}
