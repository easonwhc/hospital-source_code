<?php
require_once __DIR__ . '/../db/connect.php';

$id = $_POST["id"];
$diagnosis = $_POST["diagnosis"];
$prescription = $_POST["prescription"];
$medical_advice = $_POST["medical_advice"];
$treatment_plan = $_POST["treatment_plan"];
$diagnosis_status = $_POST["diagnosis_status"];   // 新欄位

// UPDATE 語法包含 5 個欄位 + 1 個 WHERE，共 6 個 ?
$sql = "UPDATE diagnosis_result 
        SET diagnosis=?, 
            prescription=?, 
            medical_advice=?, 
            treatment_plan=?, 
            diagnosis_status=? 
        WHERE diagnosis_result_id=?";

$stmt = $conn->prepare($sql);

// 🔥 必須有 6 個變數對應 6 個問號
$stmt->bind_param(
    "sssssi",
    $diagnosis,
    $prescription,
    $medical_advice,
    $treatment_plan,
    $diagnosis_status,
    $id
);

$stmt->execute();

// 再查一次 record_id
$sql2 = "SELECT record_id FROM diagnosis_result WHERE diagnosis_result_id=?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $id);
$stmt2->execute();

$recordData = $stmt2->get_result()->fetch_assoc();

// 萬一查不到，避免報錯
if (!$recordData) {
    die("找不到 record_id");
}

$record_id = $recordData["record_id"];

// 跳回診斷詳情頁 或 病歷頁
header("Location: diagnosis_detail.php?id=$id");
exit;
