<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../db/connect.php';

$ward_id = $_GET['ward_id'] ?? null;
$input = json_decode(file_get_contents("php://input"), true);
$status = $input['status'] ?? null;

$validStatuses = ['empty', 'occupied', 'isolated', 'cleaning'];

if (empty($ward_id) || empty($status) || !in_array($status, $validStatuses)) {
    echo json_encode([
        "success" => false,
        "message" => "參數錯誤或無效的病房狀態"
    ]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE ward
    SET ward_status = ?
    WHERE ward_id = ?
");
$stmt->bind_param("si", $status, $ward_id);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "更新狀態失敗",
        "error" => $stmt->error
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "病房狀態已更新"
]);
