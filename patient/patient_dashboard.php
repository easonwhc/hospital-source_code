<?php
session_start();
// 確保連線檔案路徑正確
require_once __DIR__ . '/../db/connect.php';

// 檢查資料庫連線是否成功
if (!$conn) {
    die("<h1>系統錯誤</h1><p>資料庫連線失敗，請稍後再試。</p>");
}

$patientId = 0;
$isLoggedIn = false;

// 1. 優先從 Session 取得 (用於正式登入)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Patient' && isset($_SESSION['user_id'])) {
    $patientId = (int) $_SESSION['user_id'];
    $isLoggedIn = true;
}
// 2. 其次從 URL 取得 (用於免登入查詢)
elseif (isset($_GET['patient_id'])) {
    $tempId = filter_var($_GET['patient_id'], FILTER_VALIDATE_INT);
    if ($tempId !== false && $tempId > 0) {
        $patientId = $tempId;
        $isLoggedIn = false;
    }
}

// 3. 如果找不到有效的 ID，則導向首頁
if ($patientId === 0) {
    header("Location: index.php");
    exit();
}

// --- 資料庫操作 ---

// 查詢病人姓名
$sqlPatient = "SELECT name FROM patient WHERE patient_id = ?";
$stmtPatient = $conn->prepare($sqlPatient);

if (!$stmtPatient) {
    die("<h1>系統錯誤</h1><p>查詢病人姓名準備失敗：" . htmlspecialchars($conn->error) . "</p>");
}

$stmtPatient->bind_param("i", $patientId);
$stmtPatient->execute();
$resultPatient = $stmtPatient->get_result();

$patientName = '病人';
if ($resultPatient->num_rows > 0) {
    $patientName = $resultPatient->fetch_assoc()['name'];
} else {
    // ID 無效，導向首頁
    header("Location: index.php");
    exit();
}
$stmtPatient->close();


// 查詢病歷紀錄 (medical_record) - 使用安全 SQL，避免 JOIN 失敗
$sqlRecords = "
    SELECT 
        mr.record_id, 
        mr.visit_time, 
        d.doctor_name AS doctor_name,
        (
            SELECT dr.diagnosis 
            FROM diagnosis_result dr 
            WHERE dr.record_id = mr.record_id 
            ORDER BY dr.diagnosis_result_id DESC 
            LIMIT 1
        ) AS diagnosis
    FROM medical_record mr
    LEFT JOIN doctor d ON mr.doctor_id = d.doctor_id
    WHERE mr.patient_id = ? 
    ORDER BY mr.visit_time DESC
";

$stmtRecords = $conn->prepare($sqlRecords);

if (!$stmtRecords) {
    die("<h1>系統錯誤</h1><p>查詢病歷準備失敗：" . htmlspecialchars($conn->error) . "</p>");
}

$stmtRecords->bind_param("i", $patientId);
$stmtRecords->execute();
$resultRecords = $stmtRecords->get_result();
$stmtRecords->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($patientName) ?>的病人儀表板</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* ... (CSS 樣式與之前提供的一致，這裡省略) ... */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            max-width: 1000px;
            width: 100%;
            margin: 0 auto;
            padding: 0 15px;
        }

        .card {
            background: #fff;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .card h3 {
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-top: 0;
            color: #333;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.95em;
        }

        .data-table thead th {
            background-color: #007bff;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
        }

        .data-table tbody td {
            padding: 10px 15px;
            border-bottom: 1px solid #ddd;
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 16px;
            color: white;
        }

        .detail-button {
            background-color: #28a745;
        }

        .action-button {
            background-color: #007bff;
        }

        .logout-button {
            background-color: #dc3545;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h2>👤 歡迎，<?= htmlspecialchars($patientName) ?>！</h2>
            <p class="mode-info">您的 ID: <?= $patientId ?>。目前處於 **<?= $isLoggedIn ? '帳號登入' : '免登入查詢' ?>** 模式。</p>
        </header>

        <div class="card records-card">
            <h3>📜 您的所有就診紀錄</h3>

            <?php if ($resultRecords->num_rows === 0): ?>
                <p class="no-records">目前尚無就診紀錄。</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>病歷 ID</th>
                            <th>就診時間</th>
                            <th>主治醫生</th>
                            <th>初步診斷</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $resultRecords->fetch_assoc()):
                            $diagnosis = $row['diagnosis'] ?? '（尚無診斷）';
                            if (mb_strlen($diagnosis) > 15) {
                                $displayDiagnosis = htmlspecialchars(mb_substr($diagnosis, 0, 15)) . '...';
                            } else {
                                $displayDiagnosis = htmlspecialchars($diagnosis);
                            }
                            ?>
                            <tr>
                                <td data-label="病歷 ID"><?= (int) $row['record_id'] ?></td>
                                <td data-label="就診時間"><?= htmlspecialchars($row['visit_time']) ?></td>
                                <td data-label="主治醫生"><?= htmlspecialchars($row['doctor_name']) ?></td>
                                <td data-label="初步診斷" class="diagnosis-text"><?= $displayDiagnosis ?></td>
                                <td data-label="操作">
                                    <button class="detail-button"
                                        onclick="location.href='record_detail.php?record_id=<?= (int) $row['record_id'] ?>&patient_id=<?= $patientId ?>'">查看詳情</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card quick-links">
            <h3>🔗 快速連結</h3>
            <div class="button-group">
                <button class="action-button"
                    onclick="location.href='appointment.php?patient_id=<?= $patientId ?>'">門診預約掛號</button>

                <?php if ($isLoggedIn): ?>
                    <button class="action-button logout-button" onclick="location.href='logout.php'">登出</button>
                <?php else: ?>
                    <button class="action-button" onclick="location.href='index.php'">系統首頁</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>