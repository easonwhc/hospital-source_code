<?php
require_once __DIR__ . '/../db/connect.php';

if (!isset($_GET['id']))
    die("缺少診斷 ID");

$id = (int) $_GET['id'];

// 取診斷資料
$sql = "SELECT dr.*, mr.record_id, p.name AS patient_name
        FROM diagnosis_result dr
        JOIN medical_record mr ON dr.record_id = mr.record_id
        JOIN patient p ON mr.patient_id = p.patient_id
        WHERE dr.diagnosis_result_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$diag = $stmt->get_result()->fetch_assoc();

if (!$diag)
    die("找不到診斷資料");

$record_id = $diag["record_id"];

// ⭐ 取病歷資料
$sqlRecord = "SELECT treatment_result, exam_result, exam_image_ids 
              FROM medical_record 
              WHERE record_id = ?";
$stmtRecord = $conn->prepare($sqlRecord);
$stmtRecord->bind_param("i", $record_id);
$stmtRecord->execute();
$record = $stmtRecord->get_result()->fetch_assoc();

$existingImages = [];
if (!empty($record['exam_image_ids'])) {
    $existingImages = json_decode($record['exam_image_ids'], true) ?? [];
}



// 取得使用藥物
$sqlMed = "
    SELECT m.Medication_name, mr.quantity
    FROM medication_record mr
    JOIN medication m ON mr.medication_id = m.Medication_id
    WHERE mr.diagnosis_result_id = ?
";
$stmtMed = $conn->prepare($sqlMed);
$stmtMed->bind_param("i", $id);
$stmtMed->execute();
$medications = $stmtMed->get_result();

// 取得病患目前住院紀錄
$sqlAlloc = "
    SELECT ar.*, w.ward_name
    FROM allocation_record ar
    JOIN ward w ON ar.ward_id = w.ward_id
    WHERE ar.patient_id = (
        SELECT patient_id FROM medical_record WHERE record_id = ?
    )
    ORDER BY allocation_date ASC
";
$stmtAlloc = $conn->prepare($sqlAlloc);
$stmtAlloc->bind_param("i", $record_id);
$stmtAlloc->execute();
$allocations = $stmtAlloc->get_result();


if (!$diag)
    die("找不到診斷資料");

$record_id = $diag["record_id"];
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>診斷詳細資訊</title>

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 30px;
        }

        .page-container {
            max-width: 1200px;
            margin: auto;
        }

        /* 卡片共用樣式 */
        .card {
            background: #fff;
            padding: 20px 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        .two-column {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .column {
            flex: 1;
        }

        h2 {
            margin-top: 0;
        }

        .btn {
            display: inline-block;
            background: #4A90E2;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 15px;
        }

        .btn:hover {
            background: #357ABD;
        }

        /* 住院表格 */
        .table-card {
            margin-top: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }

        th {
            background: #4A90E2;
            color: white;
            padding: 12px;
            text-align: left;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        tr:last-child td {
            border-bottom: none;
        }
    </style>

</head>

<body>
    <div class="page-container">

        <!-- ⭐ 上方兩欄：病歷內容 + 診斷資訊 -->
        <div class="two-column">

            <!-- 左：病歷內容 -->
            <div class="card column">
                <h2>📝 病歷內容（Medical Record）</h2>

                <p><strong>診療結果：</strong>
                    <?= !empty($record['treatment_result']) ? nl2br(htmlspecialchars($record['treatment_result'])) : '尚未填寫'; ?>
                </p>

                <p><strong>檢驗結果：</strong>
                    <?= !empty($record['exam_result']) ? nl2br(htmlspecialchars($record['exam_result'])) : '尚未填寫'; ?>
                </p>

                <?php if (!empty($existingImages)): ?>
                    <h3>檢驗圖片：</h3>
                    <div style="border:1px solid #ddd; padding:10px; border-radius:8px; margin-bottom:15px;">
                        <?php foreach ($existingImages as $imgId): ?>
                            <?php $imageSrc = "../image/view_image.php?id=" . urlencode($imgId); ?>
                            <div style="display:inline-block; width:150px; margin:5px; text-align:center;">
                                <img src="<?= htmlspecialchars($imageSrc) ?>"
                                    style="max-width:100%; border:1px solid #ccc; border-radius:6px;">
                                <p style="font-size:12px; color:#555;">ID: <?= substr($imgId, 0, 8) ?>...</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>



                <a class="btn" href="edit_medical_record.php?record_id=<?= $record_id ?>">修改病歷</a>
            </div>

            <!-- 右：診斷資訊 -->
            <div class="card column">
                <?php
                $statusMap = [
                    'ongoing' => '治療中',
                    'completed' => '治療完成',
                    'followup' => '需回診'
                ];

                $displayStatus = $statusMap[$diag['diagnosis_status']] ?? '未設定';
                ?>

                <h2>診斷詳細資訊</h2>

                <p><strong>病人：</strong><?= htmlspecialchars($diag['patient_name']) ?></p>
                <p><strong>診斷：</strong><?= htmlspecialchars($diag['diagnosis']) ?></p>
                <p><strong>醫囑：</strong><?= htmlspecialchars($diag['prescription']) ?></p>
                <p><strong>醫療建議：</strong><?= htmlspecialchars($diag['medical_advice']) ?></p>
                <p><strong>治療計畫：</strong><?= htmlspecialchars($diag['treatment_plan']) ?></p>

                <hr>

                <h3>治療狀態</h3>
                <p><?= $displayStatus ?></p>


                <h3>使用藥物</h3>
                <?php if ($medications->num_rows > 0): ?>
                    <ul>
                        <?php while ($m = $medications->fetch_assoc()): ?>
                            <li><?= htmlspecialchars($m['Medication_name']) ?> × <?= (int) $m['quantity'] ?></li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>尚無藥物紀錄</p>
                <?php endif; ?>

                <a class="btn" href="edit_diagnosis.php?id=<?= $id ?>">修改診斷</a>
            </div>

        </div>

        <!-- ⭐ 下方：住院流程紀錄 -->
        <div class="card table-card">
            <h2>🏥 住院流程紀錄</h2>

            <?php if ($allocations->num_rows > 0): ?>
                <table>
                    <tr>
                        <th>病房</th>
                        <th>入住時間</th>
                        <th>退房時間</th>
                    </tr>
                    <?php foreach ($allocations as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['ward_name']) ?> (ID: <?= $a['ward_id'] ?>)</td>
                            <td><?= $a['allocation_date'] ?></td>
                            <td><?= $a['leave_date'] ? $a['leave_date'] : "<span style='color:green;'>目前入住中</span>" ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>尚無住院紀錄</p>
            <?php endif; ?>

        </div>

    </div>
</body>


</html>