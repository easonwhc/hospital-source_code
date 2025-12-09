<?php
require_once __DIR__ . '/../db/connect.php';

// 檢查表單資料
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];  // 通知類型
    $content = $_POST['content'];  // 通知內容
    $scheduled_time = $_POST['scheduled_time'];  // 排程時間
    $patient_id = (int) $_POST['patient_id'];  // 病人 ID

    // 插入通知資料
    $sqlInsertNotification = "
        INSERT INTO notification (patient_id, type, content, scheduled_time, is_sent)
        VALUES (?, ?, ?, ?, 0)  -- is_sent 初始為 0，表示尚未發送
    ";

    $stmt = $conn->prepare($sqlInsertNotification);
    $stmt->bind_param("isss", $patient_id, $type, $content, $scheduled_time);
    $stmt->execute();

    // 檢查插入是否成功
    if ($stmt->affected_rows > 0) {
        echo "✅ 通知已成功發送。";
    } else {
        echo "❌ 發送通知時發生錯誤。";
    }

    $stmt->close();
}
?>