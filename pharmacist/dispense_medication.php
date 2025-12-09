<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "../db/connect.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data["diagnosis_result_id"]) || !isset($data["pharmacist_id"])) {
    exit(json_encode(["success" => false, "error" => "無效的請求參數"]));
}

$diagnosis_result_id = $data["diagnosis_result_id"];
$pharmacist_id = $data["pharmacist_id"];

$conn->begin_transaction();

try {
    // 1. 取得所有開藥項目（這裡使用 medication_record 的 quantity，而不是解析文字）
    $sql = "
        SELECT mr.medication_record_id, mr.medication_id, mr.quantity,
               m.Medication_name, m.remain_amount
        FROM medication_record mr
        JOIN medication m ON mr.medication_id = m.Medication_id
        WHERE mr.diagnosis_result_id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $diagnosis_result_id);
    $stmt->execute();
    $items = $stmt->get_result();

    if ($items->num_rows === 0) {
        throw new Exception("找不到任何開藥紀錄，無法配藥。");
    }

    // 2. 一筆一筆扣庫存
    while ($row = $items->fetch_assoc()) {

        $med_id = $row["medication_id"];
        $qty = $row["quantity"];
        $remain = $row["remain_amount"];
        $name = $row["Medication_name"];

        if ($remain < $qty) {
            throw new Exception("藥品『{$name}』庫存不足 (剩餘 {$remain}, 需求 {$qty})");
        }

        // 扣庫存
        $sql_update = "
            UPDATE medication 
            SET remain_amount = remain_amount - ?
            WHERE Medication_id = ?
        ";

        $stmt_up = $conn->prepare($sql_update);
        $stmt_up->bind_param("ii", $qty, $med_id);
        $stmt_up->execute();
        $stmt_up->close();
    }

    // 3. 更新診斷結果狀態
    $sql_status = "UPDATE diagnosis_result SET status = 'Dispensed' WHERE diagnosis_result_id = ?";
    $stmt_st = $conn->prepare($sql_status);
    $stmt_st->bind_param("i", $diagnosis_result_id);
    $stmt_st->execute();
    $stmt_st->close();

    // 配藥完成後更新診斷結果狀態
    $updateStatus = $conn->prepare("
    UPDATE diagnosis_result 
    SET status = 'Completed', reject_reason = NULL
    WHERE diagnosis_result_id = ?
");
    $updateStatus->bind_param("i", $diagnosis_result_id);
    $updateStatus->execute();

    $conn->commit();
    echo json_encode(["success" => true, "message" => "配藥完成！"]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>