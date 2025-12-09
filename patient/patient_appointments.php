<?php
session_start();
// 確保連線檔案路徑正確
require_once __DIR__ . '/../db/connect.php';

// 檢查資料庫連線是否成功
if (!$conn) {
    die("<h1>系統錯誤</h1><p>資料庫連線失敗，請稍後再試。</p>");
}

// 設定連線編碼
if (isset($conn)) {
    if ($conn->set_charset("utf8mb4") === false) {
        $conn->query("SET NAMES 'utf8mb4'");
    }
}

// ----------------------------------------------------
// 1. 檢查並獲取當前病人的 ID
// ----------------------------------------------------
$patientId = 0;
// 優先從 Session 取得 (登入模式)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Patient' && isset($_SESSION['user_id'])) {
    $patientId = (int) $_SESSION['user_id'];
}
// 其次從 URL 取得 (免登入查詢模式)
elseif (isset($_GET['patient_id']) && (int) $_GET['patient_id'] > 0) {
    $patientId = (int) $_GET['patient_id'];
}

if ($patientId <= 0) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';
$appointments = [];

// ----------------------------------------------------
// 2. 處理取消預約請求 (POST 請求)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_appointment_id'])) {
    $cancelId = filter_input(INPUT_POST, 'cancel_appointment_id', FILTER_VALIDATE_INT);

    if ($cancelId) {
        // 使用預處理語句來更新 appointment 狀態
        $sqlCancel = "UPDATE appointment 
                      SET status = 'Canceled' 
                      WHERE appointment_id = ? AND patient_id = ? AND status = 'Scheduled'";

        $stmtCancel = $conn->prepare($sqlCancel);
        $stmtCancel->bind_param("ii", $cancelId, $patientId);

        if ($stmtCancel->execute() && $stmtCancel->affected_rows > 0) {
            $message = "✅ 預約編號 {$cancelId} 已成功取消。";
        } elseif ($stmtCancel->affected_rows === 0) {
            $error = "警告：預約編號 {$cancelId} 可能已經取消或找不到對應的排程紀錄。";
        } else {
            $error = "取消預約失敗: " . htmlspecialchars($stmtCancel->error);
        }
        $stmtCancel->close();
    }
}


// ----------------------------------------------------
// 3. 查詢病患所有預約列表 (包含已排程、已完成、已取消)
// ----------------------------------------------------
$sqlAppointments = "SELECT 
                        a.appointment_id, 
                        a.appointment_time, 
                        a.department, 
                        a.reason, 
                        a.status,
                        a.doctor_id 
                    FROM 
                        appointment a
                    WHERE 
                        a.patient_id = ? 
                    ORDER BY 
                        a.appointment_time DESC"; // 依時間倒序排列

$stmtAppointments = $conn->prepare($sqlAppointments);
$stmtAppointments->bind_param("i", $patientId);
$stmtAppointments->execute();
$resultAppointments = $stmtAppointments->get_result();

while ($row = $resultAppointments->fetch_assoc()) {
    $appointments[] = $row;
}

$stmtAppointments->close();
$conn->close();

/**
 * Helper function to determine badge style based on status
 */
function getStatusBadgeClass($status)
{
    switch ($status) {
        case 'Scheduled':
            return 'badge-scheduled'; // 藍色
        case 'Completed':
            return 'badge-completed'; // 綠色
        case 'Canceled':
            return 'badge-canceled'; // 紅色
        default:
            return 'badge-default';
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>我的預約紀錄</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f9;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            width: 90%;
            margin: 40px auto;
        }

        .card {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #3c6ff7;
            text-align: center;
            margin-bottom: 25px;
        }

        /* 狀態訊息 */
        .message,
        .error {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 600;
        }

        .message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* 表格樣式 */
        .appointment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .appointment-table th,
        .appointment-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 15px;
            vertical-align: middle;
        }

        .appointment-table th {
            background-color: #f0f3f6;
            color: #333;
            font-weight: 700;
            text-transform: uppercase;
        }

        .appointment-table tr:hover {
            background-color: #f9f9f9;
        }

        /* 徽章 (Badge) 狀態樣式 */
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }

        .badge-scheduled {
            background-color: #e0f7fa;
            color: #00796b;
        }

        /* 綠松石 */
        .badge-completed {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        /* 綠色 */
        .badge-canceled {
            background-color: #ffebee;
            color: #d32f2f;
        }

        /* 紅色 */
        .badge-default {
            background-color: #f5f5f5;
            color: #757575;
        }

        /* 取消按鈕 */
        .btn-cancel {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn-cancel:hover {
            background-color: #c82333;
        }

        /* 回到預約按鈕 */
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            color: white;
            background-color: #3c6ff7;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .back-link:hover {
            background-color: #315cd8;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>我的預約紀錄 (病人 ID: <?= $patientId ?>)</h2>
        <div class="card">
            <?php if ($message): ?>
                <div class="message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (empty($appointments)): ?>
                <p style="text-align: center; color: #666;">您目前沒有任何預約紀錄。</p>
            <?php else: ?>
                <table class="appointment-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>時間</th>
                            <th>科別</th>
                            <th>醫生 ID</th>
                            <th>狀態</th>
                            <th>原因</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appt): ?>
                            <tr>
                                <td><?= htmlspecialchars($appt['appointment_id']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($appt['appointment_time'])) ?></td>
                                <td><?= htmlspecialchars($appt['department']) ?></td>
                                <td><?= htmlspecialchars($appt['doctor_id']) ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($appt['status']) ?>">
                                        <?= htmlspecialchars($appt['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($appt['reason']) ?></td>
                                <td>
                                    <?php
                                    $isFuture = strtotime($appt['appointment_time']) > time();
                                    $isScheduled = $appt['status'] === 'Scheduled';

                                    if ($isScheduled && $isFuture): ?>
                                        <form method="POST"
                                            onsubmit="return confirm('確定要取消這筆預約嗎？\n預約時間: <?= date('Y-m-d H:i', strtotime($appt['appointment_time'])) ?>');">
                                            <input type="hidden" name="cancel_appointment_id"
                                                value="<?= $appt['appointment_id'] ?>">
                                            <button type="submit" class="btn-cancel">取消預約</button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <a href="appointment.php?patient_id=<?= $patientId ?>" class="back-link">
                ← 返回預約掛號頁面
            </a>
        </div>
    </div>
</body>

</html>