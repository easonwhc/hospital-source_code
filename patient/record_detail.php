<?php
session_start();
// 確保連線檔案路徑正確
require_once __DIR__ . '/../db/connect.php';

// 檢查資料庫連線是否成功
if (!$conn) {
    die("<h1>系統錯誤</h1><p>資料庫連線失敗，請稍後再試。</p>");
}

// ----------------------------------------------------
// 處理病患 ID (確保使用者已登入或有有效的查詢 ID)
// ----------------------------------------------------
$patientId = 0;
// 1. 從 Session 取得 patient_id (正式登入模式)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Patient' && isset($_SESSION['user_id'])) {
    $patientId = (int) $_SESSION['user_id'];
}
// 2. 從 URL 取得 patient_id (用於免登入查詢，優先於 Session)
elseif (isset($_GET['patient_id'])) {
    $patientId = (int) $_GET['patient_id'];
}

// 1. 取得 URL 中的 record_id
$recordId = isset($_GET['record_id']) ? (int) $_GET['record_id'] : 0;
if ($recordId <= 0 || $patientId <= 0) {
    // 修正：如果缺少 ID，導回首頁
    header("Location: index.php");
    exit();
}

// ----------------------------------------------------
// 2. 查詢：病歷頭部資訊（medical_record）與病人姓名 & 初步診斷
// **【核心修正區域】** 
// ----------------------------------------------------
$sqlMain = "
    SELECT 
        mr.record_id, 
        mr.visit_time, 
        mr.exam_result,
        mr.treatment_result,
        mr.exam_image_ids,
        p.name AS patient_name,
        dr.diagnosis AS preliminary_diagnosis,
        d.doctor_name AS doctor_name
    FROM medical_record mr
    JOIN patient p ON mr.patient_id = p.patient_id
    LEFT JOIN diagnosis_result dr ON mr.record_id = dr.record_id
    LEFT JOIN doctor d ON mr.doctor_id = d.doctor_id
    WHERE mr.record_id = ? AND mr.patient_id = ?
    LIMIT 1
";



$stmtMain = $conn->prepare($sqlMain);
$stmtMain->bind_param("ii", $recordId, $patientId);
$stmtMain->execute();
$rsMain = $stmtMain->get_result();

if ($rsMain->num_rows === 0) {
    die("<h1>錯誤</h1><p>找不到對應的病歷紀錄或您沒有權限查看。</p>");
}
$record = $rsMain->fetch_assoc();

// 解析檢驗圖片 ID
$existingImages = [];
if (!empty($record['exam_image_ids'])) {
    $existingImages = json_decode($record['exam_image_ids'], true) ?? [];
}


$stmtMain->close();

// ----------------------------------------------------
// 3. 查詢：診斷結果 (diagnosis_result) - 使用 record_id
// ----------------------------------------------------
$sqlDiagnosis = "
    SELECT 
        diagnosis_result_id, 
        diagnosis, 
        prescription, 
        medical_advice, 
        treatment_plan
    FROM diagnosis_result
    WHERE record_id = ?
";
$stmtDiagnosis = $conn->prepare($sqlDiagnosis);
$stmtDiagnosis->bind_param("i", $recordId);  // 不再需要使用 patient_id，根據 record_id 查詢
$stmtDiagnosis->execute();
$rsDiagnosis = $stmtDiagnosis->get_result();
$stmtDiagnosis->close();

// ----------------------------------------------------
// 4. 查詢：領藥清單 (medication_record) - 使用診斷結果的 record_id
// ----------------------------------------------------
$sqlRx = "
    SELECT 
        m.Medication_name, 
        mr.quantity,
        mr.diagnosis_result_id
    FROM medication_record mr
    JOIN medication m ON mr.Medication_id = m.Medication_id
    JOIN diagnosis_result dr ON mr.diagnosis_result_id = dr.diagnosis_result_id
    WHERE dr.record_id = ? 
";
$stmtRx = $conn->prepare($sqlRx);
$stmtRx->bind_param("i", $recordId);  // 根據 record_id 查詢
$stmtRx->execute();
$rsRx = $stmtRx->get_result();
$stmtRx->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($record['patient_name']) ?> 的病歷詳情 (ID: <?= $recordId ?>)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #e9ecef;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }

        .detail-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: #fff;
            padding: 30px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        h2 {
            color: #007bff;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        h3 {
            color: #28a745;
            margin-top: 0;
            border-bottom: 1px dashed #28a745;
            padding-bottom: 5px;
        }

        .summary p {
            margin: 8px 0;
        }

        .summary strong {
            color: #333;
            display: inline-block;
            width: 100px;
        }

        .diagnosis-list {
            list-style: none;
            padding: 0;
        }

        .diagnosis-list li {
            border-bottom: 1px solid #f0f0f0;
            padding: 15px 0;
        }

        .diagnosis-list li:last-child {
            border-bottom: none;
        }

        .diagnosis-list strong {
            color: #007bff;
        }

        #rxList {
            list-style: none;
            padding: 0;
        }

        #rxList li {
            padding: 8px 0;
            border-bottom: 1px dotted #ccc;
        }

        .button-group-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .action-button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            color: white;
            font-size: 16px;
        }

        .back-button {
            background-color: #6c757d;
        }

        .back-button:hover {
            background-color: #5a6268;
        }

        .home-button {
            background-color: #007bff;
        }

        .home-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <div class="detail-container">
        <h2>📋 病歷詳情 (ID: <?= $recordId ?>)</h2>

        <div class="card summary">
            <h3>📝 基本資訊</h3>
            <p><strong>病歷 ID:</strong> <?= (int) $record['record_id'] ?></p>
            <p><strong>病人姓名:</strong> <?= htmlspecialchars($record['patient_name']) ?></p>
            <p><strong>主治醫生:</strong> <?= htmlspecialchars($record['doctor_name'] ?? '未指定') ?></p>
            <p><strong>就診時間:</strong> <?= htmlspecialchars($record['visit_time']) ?></p>
            <p><strong>初步診斷:</strong> <strong
                    style="color: #dc3545;"><?= htmlspecialchars($record['preliminary_diagnosis'] ?? '尚未確立') ?></strong>
            </p>
            <hr>
            <p><strong>檢查結果:</strong> <?= nl2br(htmlspecialchars($record['exam_result'])) ?></p>
            <p><strong>治療結果:</strong> <?= nl2br(htmlspecialchars($record['treatment_result'])) ?></p>

            <?php if (!empty($existingImages)): ?>
                <hr>
                <h3>🖼️ 檢驗圖片</h3>
                <div style="border:1px solid #ddd; padding:10px; border-radius:8px;">

                    <?php foreach ($existingImages as $imgId): ?>
                        <?php
                        $imageSrc = "../image/view_image.php?id=" . urlencode($imgId);
                        ?>
                        <div style="display:inline-block; width:150px; margin:8px; text-align:center;">
                            <img src="<?= htmlspecialchars($imageSrc) ?>"
                                style="max-width:100%; border:1px solid #ccc; border-radius:6px;">
                            <p style="font-size:12px; color:#666;">ID: <?= substr($imgId, 0, 8) ?>...</p>
                        </div>
                    <?php endforeach; ?>

                </div>
            <?php endif; ?>

        </div>

        <div class="card diagnosis-card">
            <h3>🩺 診斷與處置結果 (完整清單)</h3>
            <?php if ($rsDiagnosis->num_rows === 0): ?>
                <p style="color: #dc3545; font-weight: bold;">本次就診尚無詳細診斷結果紀錄。</p>
            <?php else: ?>
                <ul class="diagnosis-list">
                    <?php while ($d = $rsDiagnosis->fetch_assoc()): ?>
                        <li>
                            <p><strong>診斷 ID:</strong> <?= (int) $d['diagnosis_result_id'] ?></p>
                            <p><strong>診斷名稱:</strong> <strong><?= htmlspecialchars($d['diagnosis']) ?></strong></p>
                            <p><strong>處方籤:</strong> <?= nl2br(htmlspecialchars($d['prescription'])) ?></p>
                            <p><strong>醫囑:</strong> <?= nl2br(htmlspecialchars($d['medical_advice'])) ?></p>
                            <p><strong>治療計畫:</strong> <?= nl2br(htmlspecialchars($d['treatment_plan'])) ?></p>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>💊 領藥清單 (Medication Record)</h3>
            <ul id="rxList">
                <?php if ($rsRx->num_rows === 0): ?>
                    <li style="color: #6c757d; border-bottom: none;">本次就診沒有開藥紀錄。</li>
                <?php else: ?>
                    <?php while ($rx = $rsRx->fetch_assoc()): ?>
                        <li>
                            💊 **<?= htmlspecialchars($rx['Medication_name']) ?>**：
                            數量 **<?= (int) $rx['quantity'] ?>** 顆/包
                            <span style="color: #999; font-size: 0.9em;">(針對診斷
                                ID：<?= (int) $rx['diagnosis_result_id'] ?>)</span>
                        </li>
                    <?php endwhile; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="button-group-footer">
            <button class="action-button back-button"
                onclick="location.href='patient_dashboard.php?patient_id=<?= $patientId ?>'">↩️ 返回病歷列表</button>
            <button class="action-button home-button" onclick="location.href='index.php'">🏠 回首頁</button>
        </div>
    </div>
</body>

</html>