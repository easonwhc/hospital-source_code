<?php
require_once __DIR__ . '/../db/connect.php';

$recordId = isset($_GET['record_id']) ? (int) $_GET['record_id'] : 0;
if ($recordId <= 0) {
    die("缺少 record_id");
}

$message = "";

// 當醫師送出開藥表單
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $diagnosisId = (int) ($_POST['diagnosis_result_id'] ?? 0);
    $medicationIds = $_POST['medication_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if ($diagnosisId <= 0 || empty($medicationIds)) {
        $message = "請選擇診斷並至少新增 1 筆藥品。";
    } else {
        $pharmacistId = 1; // 暫定藥師 ID

        $sqlInsert = "
            INSERT INTO medication_record (diagnosis_result_id, medication_id, pharmacist_id, quantity)
            VALUES (?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sqlInsert);

        $count = 0;
        for ($i = 0; $i < count($medicationIds); $i++) {

            $medId = (int) $medicationIds[$i];
            $qty = (int) $quantities[$i];

            if ($medId > 0 && $qty > 0) {
                $stmt->bind_param("iiii", $diagnosisId, $medId, $pharmacistId, $qty);
                $stmt->execute();
                $count++;
            }
        }

        $message = "開藥成功！共新增 {$count} 項藥品。";
    }
}

// 取得診斷結果
$sqlDiag = "
    SELECT diagnosis_result_id, diagnosis
    FROM diagnosis_result
    WHERE record_id = ?
";
$stmtDiag = $conn->prepare($sqlDiag);
$stmtDiag->bind_param("i", $recordId);
$stmtDiag->execute();
$rsDiag = $stmtDiag->get_result();

// 取得藥品清單
$sqlMed = "SELECT Medication_id, Medication_name FROM medication ORDER BY Medication_id ASC";
$rsMed = $conn->query($sqlMed);
$medArray = $rsMed->fetch_all(MYSQLI_ASSOC);
?>


<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>新增開藥（medication_record）</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f7f7f7;
        }

        h2 {
            color: #333;
        }

        .card,
        .section {
            padding: 15px;
            margin: 15px 0;
            background: white;
            border-radius: 10px;
            border: 1px solid #ddd;
        }

        label {
            font-weight: bold;
        }

        input,
        select {
            padding: 6px;
            margin: 5px 0 10px 0;
            width: 250px;
        }

        button {
            padding: 10px 16px;
            background: #3c6ff7;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #2f55d3;
        }

        .remove-btn {
            background: #e74c3c;
            margin-left: 10px;
        }

        .remove-btn:hover {
            background: #c0392b;
        }

        .back-btn {
            margin-top: 20px;
        }

        .drug-block {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
        }
    </style>
</head>

<body>

    <h2>新增開藥（medication_record）</h2>

    <!-- 顯示成功 / 失敗訊息 -->
    <?php if (!empty($message)): ?>
        <div style="
            background:#e6f7ff;
            padding:10px;
            border-left:5px solid #1890ff;
            margin-bottom:15px;
            font-size:16px;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>病歷基本資訊</h3>
        <p><strong>Record ID：</strong> <?= $recordId ?></p>
    </div>

    <div class="card">
        <h3>開藥內容</h3>

        <form method="post" action="add_prescription.php?record_id=<?= $recordId ?>">

            <!-- 選擇診斷 -->
            <label>選擇診斷：</label>
            <select name="diagnosis_result_id" required>
                <option value="">請選擇診斷</option>
                <?php while ($d = $rsDiag->fetch_assoc()): ?>
                    <option value="<?= $d['diagnosis_result_id'] ?>">
                        #<?= $d['diagnosis_result_id'] ?> - <?= htmlspecialchars($d['diagnosis']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <br><br>

            <!-- 動態新增藥品 -->
            <div id="drugContainer"></div>

            <button type="button" onclick="addDrug()">＋ 新增藥品</button>

            <br><br>
            <button type="submit">送出</button>
            <button type="button" onclick="history.back()">返回</button>

        </form>
    </div>

    <script>
        const medOptions = `
        <option value="">請選擇藥品</option>
        <?php foreach ($medArray as $m): ?>
            <option value="<?= $m['Medication_id'] ?>">
                <?= $m['Medication_id'] ?> - <?= htmlspecialchars($m['Medication_name']) ?>
            </option>
        <?php endforeach; ?>
    `;

        function addDrug() {
            const div = document.createElement("div");
            div.className = "drug-block";

            div.innerHTML = `
            <label>藥品：</label>
            <select name="medication_id[]" required>
                ${medOptions}
            </select>

            <label>數量：</label>
            <input type="number" name="quantity[]" value="1" min="1" required>

            <button type="button" class="remove-btn" onclick="this.parentElement.remove()">刪除</button>
        `;

            document.getElementById("drugContainer").appendChild(div);
        }

        // ⚠ 預設先建立一筆
        window.onload = function () {
            addDrug();
        };
    </script>

</html>