<?php
require_once __DIR__ . '/../db/connect.php';

$recordId = isset($_GET['record_id']) ? (int) $_GET['record_id'] : 0;
if ($recordId <= 0) {
    die("缺少 record_id");
}



// 病人 + 病歷內容
$sqlRecord = "
    SELECT mr.*, p.name AS patient_name, p.identity_number, p.phone
    FROM medical_record mr
    JOIN patient p ON mr.patient_id = p.patient_id
    WHERE mr.record_id = ?
";
$stmtRecord = $conn->prepare($sqlRecord);
$stmtRecord->bind_param("i", $recordId);
$stmtRecord->execute();
$record = $stmtRecord->get_result()->fetch_assoc();

$existingImages = [];
if (!empty($record['exam_image_ids'])) {
    $existingImages = json_decode($record['exam_image_ids'], true) ?? [];
}

if (!$record) {
    die("找不到這筆病歷");
}

// 診斷結果列表
$sqlDiag = "
    SELECT dr.*
    FROM diagnosis_result dr
    WHERE dr.record_id = ?
    ORDER BY dr.diagnosis_result_id DESC
";
$stmtDiag = $conn->prepare($sqlDiag);
$stmtDiag->bind_param("i", $recordId);
$stmtDiag->execute();
$rsDiag = $stmtDiag->get_result();

// 開藥紀錄（透過 diagnosis_result 連到 medication_record）
$sqlRx = "
    SELECT mr.medication_record_id, mr.medication_id, m.Medication_name,
           mr.diagnosis_result_id
    FROM medication_record mr
    JOIN medication m ON mr.medication_id = m.Medication_id
    JOIN diagnosis_result dr ON mr.diagnosis_result_id = dr.diagnosis_result_id
    WHERE dr.record_id = ?
";
$stmtRx = $conn->prepare($sqlRx);
$stmtRx->bind_param("i", $recordId);
$stmtRx->execute();
$rsRx = $stmtRx->get_result();


?>


<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>病歷詳細頁</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* 診斷卡片容器 */
        .diagnosis-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: 0.2s;
        }

        .diagnosis-card:hover {
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
        }

        /* 標題列（診斷 ID + 修改按鈕） */
        .diagnosis-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .diagnosis-id {
            font-size: 13px;
            color: #777;
        }

        /* 修改診斷按鈕 */
        .edit-link {
            padding: 6px 14px;
            background: #4a90e2;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
        }

        .edit-link:hover {
            background: #357ABD;
        }

        /* 內容文字排版 */
        .diagnosis-card p {
            margin: 6px 0;
            line-height: 1.4;
        }

        .diagnosis-card strong {
            color: #333;
        }

        .med-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 14px 20px;
            margin-bottom: 14px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .med-card:hover {
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .med-title {
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
        }

        .med-info {
            margin: 4px 0;
            font-size: 14px;
            color: #444;
        }

        .med-id {
            font-size: 12px;
            color: #777;
        }

        .record-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .record-section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #333;
        }

        .record-item {
            margin: 10px 0;
            font-size: 15px;
        }

        .record-label {
            font-weight: 600;
            color: #444;
            margin-right: 8px;
        }

        .record-value {
            color: #222;
        }

        .record-empty {
            color: #999;
            font-style: italic;
        }
    </style>
</head>

<body>

    <h2>病歷詳細資訊</h2>

    <div class="section">
        <h3>病人資訊</h3>
        <p>姓名：<?= htmlspecialchars($record['patient_name']) ?></p>
        <p>身分證：<?= htmlspecialchars($record['identity_number']) ?></p>
        <p>聯絡電話：<?= htmlspecialchars($record['phone']) ?></p>
        <p>病人 ID：<?= (int) $record['patient_id'] ?></p>
    </div>

    <div class="record-card">

        <div class="record-section-title">📝 病歷內容（Medical Record）</div>

        <div class="record-item">
            <span class="record-label">病歷編號：</span>
            <span class="record-value"><?= (int) $record['record_id'] ?></span>
        </div>

        <div class="record-item">
            <span class="record-label">就診時間：</span>
            <span class="record-value"><?= htmlspecialchars($record['visit_time']) ?></span>
        </div>

        <div class="record-item">
            <span class="record-label">診療結果：</span>
            <span class="record-value">
                <?= $record['treatment_result'] ? nl2br(htmlspecialchars($record['treatment_result'])) : '<span class="record-empty">尚未填寫</span>' ?>
            </span>
        </div>

        <div class="record-item">
            <span class="record-label">檢驗結果：</span>
            <span class="record-value">
                <?= $record['exam_result'] ? nl2br(htmlspecialchars($record['exam_result'])) : '<span class="record-empty">尚未填寫</span>' ?>
            </span>
        </div>

        <?php if (!empty($existingImages)): ?>
            <div class="record-item">
                <span class="record-label">檢驗圖片：</span>
                <div style="border: 1px solid #eee; padding: 10px; margin-top: 10px; border-radius: 8px;">
                    <?php foreach ($existingImages as $imgId): ?>
                        <?php $imageSrc = "../image/view_image.php?id=" . urlencode($imgId); ?>
                        <div style="display:inline-block; width:150px; margin:8px; text-align:center; vertical-align:top;">
                            <img src="<?= htmlspecialchars($imageSrc) ?>"
                                style="max-width:100%; border:1px solid #ccc; border-radius:6px;">
                            <p style="font-size:12px; color:#777; margin-top:4px;">
                                ID: <?= substr($imgId, 0, 8) ?>...
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>


    </div>


    <div class="section">
        <h3>診斷結果（diagnosis_result）</h3>
        <ul id="diagnosisList">
            <?php if ($rsDiag->num_rows === 0): ?>
                <li>目前尚無診斷紀錄</li>
            <?php else: ?>
                <?php while ($d = $rsDiag->fetch_assoc()): ?>

                    <div class="diagnosis-card">

                        <div class="diagnosis-header">
                            <div class="diagnosis-id">
                                診斷結果 ID：<?= (int) $d['diagnosis_result_id'] ?>
                            </div>

                            <!-- 修改診斷按鈕 -->
                            <a href="diagnosis_detail.php?id=<?= $d['diagnosis_result_id'] ?>" class="edit-link">查看診斷</a>
                        </div>

                        <p><strong>診斷：</strong><?= nl2br(htmlspecialchars($d['diagnosis'])) ?></p>
                        <p><strong>醫囑：</strong><?= nl2br(htmlspecialchars($d['prescription'])) ?></p>
                        <p><strong>醫療建議：</strong><?= nl2br(htmlspecialchars($d['medical_advice'])) ?></p>
                        <p><strong>治療計畫：</strong><?= nl2br(htmlspecialchars($d['treatment_plan'])) ?></p>

                        <?php if ($d['status'] === 'Rejected'): ?>
                            <p style="color:red; font-weight:bold; margin-top:8px;">
                                ⚠️ 此處方已被藥師退回<br>
                                退回原因：<?= htmlspecialchars($d['reject_reason']) ?>
                            </p>
                        <?php endif; ?>

                    </div>

                <?php endwhile; ?>


            <?php endif; ?>
        </ul>
        <!-- 保留你原本的 add_diagnosis.html，並把 record_id 帶過去 -->
        <button onclick="location.href='add_diagnosis.php?record_id=<?= (int) $record['record_id'] ?>'">新增診斷</button>
    </div>

    <div class="section">
        <h3>處方紀錄（medication_record）</h3>
        <?php while ($rx = $rsRx->fetch_assoc()): ?>
            <div class="med-card">
                <div class="med-title">
                    藥品：<?= htmlspecialchars($rx['Medication_name']) ?>
                </div>

                <div class="med-info">
                    <strong>藥品 ID：</strong> <?= (int) $rx['medication_id'] ?>
                </div>

                <div class="med-info">
                    <strong>來源診斷：</strong> #<?= (int) $rx['diagnosis_result_id'] ?>
                </div>

                <div class="med-id">
                    配藥記錄 ID：<?= (int) $rx['medication_record_id'] ?>
                </div>
            </div>
        <?php endwhile; ?>

        <button onclick="location.href='add_prescription.php?record_id=<?= (int) $record['record_id'] ?>'">新增開藥</button>
    </div>


    <button onclick="history.back()">返回</button>

</body>

</html>