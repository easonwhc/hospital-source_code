<?php
require_once __DIR__ . '/../db/connect.php';

// 確保有傳遞 appointment_id
$appointmentId = isset($_GET['appointment_id']) ? (int) $_GET['appointment_id'] : 0;
if ($appointmentId <= 0) {
    die("錯誤：缺少 appointment_id");
}

// 步驟一：檢查是否已經有 record_id
$sqlCheck = "SELECT record_id, patient_id, doctor_id, visit_time FROM medical_record WHERE appointment_id = ?";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->bind_param("i", $appointmentId);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();

if ($resultCheck->num_rows > 0) {
    // 紀錄已存在，直接獲取 record_id
    $record = $resultCheck->fetch_assoc();
    $recordId = (int) $record['record_id'];

} else {
    // 紀錄不存在，需要創建新的病歷

    // 從 appointment 表格獲取 patient_id, doctor_id, visit_time (假設 appointment 表有這些欄位)
    // ***注意***: 您需要確認您的 appointment 表格中有這些欄位來創建 record
    $sqlApp = "SELECT patient_id, doctor_id, appointment_time FROM appointment WHERE appointment_id = ?";
    $stmtApp = $conn->prepare($sqlApp);
    $stmtApp->bind_param("i", $appointmentId);
    $stmtApp->execute();
    $resultApp = $stmtApp->get_result();

    if ($resultApp->num_rows === 0) {
        die("錯誤：找不到 ID 為 {$appointmentId} 的預約紀錄。");
    }

    $appointmentData = $resultApp->fetch_assoc();
    $patientId = $appointmentData['patient_id'];
    $doctorId = $appointmentData['doctor_id'];
    $visitTime = $appointmentData['appointment_time']; // 使用預約時間作為就診時間初值


    // 步驟二：插入新的 medical_record 紀錄
    // treatment_result 和 exam_result 初始設為空字串或 NULL
    $sqlInsert = "
        INSERT INTO medical_record 
        (patient_id, doctor_id, appointment_id, visit_time, treatment_result, exam_result) 
        VALUES (?, ?, ?, ?, '尚未填寫', '尚未填寫')
    ";
    $stmtInsert = $conn->prepare($sqlInsert);
    // 假設 patient_id, doctor_id 是 i (整數), visit_time 是 s (字串/日期)
    $stmtInsert->bind_param("iiis", $patientId, $doctorId, $appointmentId, $visitTime);

    if ($stmtInsert->execute()) {
        // 獲取新插入的 record_id
        $recordId = $conn->insert_id;
    } else {
        die("錯誤：創建病歷失敗。");
    }
}

// 步驟三：導向 record_detail.php
header("Location: record_detail.php?record_id=" . $recordId);
exit();
?>