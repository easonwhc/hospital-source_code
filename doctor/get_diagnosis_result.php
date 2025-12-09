<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../db/connect.php';

if (!isset($_GET['id'])) {
    echo json_encode(["error" => "缺少診斷結果 ID"]);
    exit;
}

$id = (int) $_GET['id'];

// 查詢該筆診斷結果資料
$sql = "SELECT diagnosis, prescription, medical_advice, treatment_plan 
        FROM diagnosis_result 
        WHERE diagnosis_result_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo json_encode(["error" => "找不到資料"]);
    exit;
}

// 回傳 JSON 給前端
echo json_encode($data);
?>