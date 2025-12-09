<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../db/connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$diagnosis_result_id = $data["diagnosis_result_id"] ?? null;
$reject_reason = trim($data["reject_reason"] ?? "");

// --- 基本檢查 ---
if (!$diagnosis_result_id || $reject_reason === "") {
    echo json_encode([
        "success" => false,
        "error" => "缺少必要的診斷結果ID或退回原因"
    ]);
    exit;
}

try {
    // --- 先確認診斷結果是否存在 ---
    $check = $conn->prepare("SELECT diagnosis_result_id FROM diagnosis_result WHERE diagnosis_result_id = ?");
    $check->bind_param("i", $diagnosis_result_id);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows === 0) {
        echo json_encode(["success" => false, "error" => "診斷結果不存在"]);
        exit;
    }
    $check->close();

    // --- 更新狀態與退回原因 ---
    $sql = "UPDATE diagnosis_result 
            SET status = 'Rejected', reject_reason = ?
            WHERE diagnosis_result_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $reject_reason, $diagnosis_result_id);

    if (!$stmt->execute()) {
        throw new Exception("SQL 執行錯誤：" . $stmt->error);
    }

    echo json_encode([
        "success" => true,
        "message" => "處方已退回，原因已記錄。"
    ]);

    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}

$conn->close();
