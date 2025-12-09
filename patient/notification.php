<?php
// 確保連線檔案路徑正確
require_once __DIR__ . '/../db/connect.php';

$notifications = [];
$error_message = "";
$patientId = 0;
$isPatientLoggedIn = false;
$patientName = '訪客';
$identityNumber = '';
$phone = '';

if ($conn) {
    // ----------------------------------------------------
    // 1. 處理身分驗證表單
    // ----------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        $identityNumber = trim($_POST['identity_number'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!empty($identityNumber) && !empty($phone)) {
            // 查詢 patient 資料表以驗證身份
            // 查詢 patient_id, name, identity_number, phone
            $sqlValidate = "SELECT patient_id, name FROM patient WHERE identity_number = ? AND phone = ?";
            $stmtValidate = $conn->prepare($sqlValidate);

            if ($stmtValidate) {
                $stmtValidate->bind_param("ss", $identityNumber, $phone);
                $stmtValidate->execute();
                $resultValidate = $stmtValidate->get_result();

                if ($row = $resultValidate->fetch_assoc()) {
                    $patientId = (int) $row['patient_id'];
                    $patientName = htmlspecialchars($row['name']);
                    $isPatientLoggedIn = true;
                    $error_message = "✅ 驗證成功！您現在可以看到公開及您個人的通知。";
                } else {
                    $error_message = "身分證號或電話號碼錯誤，請檢查後再試。";
                }
                $stmtValidate->close();
            } else {
                $error_message = "資料庫驗證準備失敗：" . $conn->error;
            }
        } else {
            // 只有在明確點擊登入但欄位為空時才顯示錯誤
            if (!empty($_POST['identity_number']) || !empty($_POST['phone'])) {
                $error_message = "請輸入身分證號及電話號碼進行驗證。";
            }
        }
    }

    // ----------------------------------------------------
    // 2. 查詢通知
    // ----------------------------------------------------

    // 基礎 WHERE 條件：patient_id = 0 (公開)
    $whereClause = "patient_id = 0";
    $bindTypes = '';
    $bindParams = [];

    if ($patientId > 0) {
        // 如果病人已驗證登入，加入查詢該病人通知的條件
        $whereClause .= " OR patient_id = ?";
        $bindTypes = 'i';
        $bindParams = [$patientId];
    }

    $sql = "
        SELECT 
            type AS title, 
            content, 
            scheduled_time AS notification_date,
            patient_id  /* 取得 patient_id 以判斷是否為個人通知 */
        FROM 
            notification
        WHERE 
            " . $whereClause . "
        ORDER BY 
            scheduled_time DESC
        LIMIT 20
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($patientId > 0) {
            // 如果有 patientId 要綁定，則進行綁定
            // ...$bindParams 展開參數陣列
            $stmt->bind_param($bindTypes, ...$bindParams);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
    } else {
        $error_message = "SQL 查詢準備失敗：" . $conn->error;
    }

    // 關閉資料庫連線
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
} else {
    $error_message = "資料庫連線失敗。";
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>醫院通知中心</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }

        .card {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .notification-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
        }

        h2 {
            color: #007bff;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 25px;
            text-align: center;
        }

        h3 {
            margin-top: 0;
            font-weight: 600;
        }

        .notice-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
            text-align: left;
        }

        .notice-item:last-child {
            border-bottom: none;
        }

        .notice-item p {
            margin: 5px 0 0 0;
            color: #555;
        }

        .notice-date {
            font-size: 0.85em;
            color: #999;
            display: block;
            margin-bottom: 5px;
        }

        .notice-type {
            float: right;
            font-size: 0.8em;
            padding: 3px 8px;
            border-radius: 4px;
            color: white;
            margin-left: 10px;
        }

        .public-badge {
            background-color: #28a745;
        }

        /* 綠色 */
        .private-badge {
            background-color: #dc3545;
        }

        /* 紅色 */
        .action-button {
            padding: 10px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 16px;
            font-weight: 600;
            color: white;
            background-color: #007bff;
            display: block;
            margin: 25px auto 0 auto;
        }

        .action-button:hover {
            background-color: #0056b3;
        }

        .auth-form input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .auth-form button {
            width: 100%;
            padding: 10px;
            background-color: #ffc107;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: #333;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .auth-form button:hover {
            background-color: #e0a800;
        }

        .status-bar {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .status-guest {
            background-color: #e9ecef;
            color: #6c757d;
        }

        .status-patient {
            background-color: #d4edda;
            color: #155724;
        }

        .logout-form {
            display: inline-block;
        }
    </style>
</head>

<body>
    <div class="notification-container">
        <h2>🔔 醫院通知中心</h2>

        <div class="card auth-card">
            <?php if ($isPatientLoggedIn): ?>
                <div class="status-bar status-patient">
                    您好，<?= $patientName ?> (ID: <?= $patientId ?>)！您正在查看**個人化通知**。
                    <br>（包含公開公告及您的專屬通知）
                </div>
                <form method="GET" action="notification.php" class="logout-form">
                    <button type="submit" class="action-button"
                        style="background-color: #dc3545; margin: 0; width: auto;">切換為訪客模式</button>
                </form>
            <?php else: ?>

                <form method="POST" action="notification.php" class="auth-form">
                    <input type="hidden" name="action" value="login">
                    <h3>驗證身份</h3>
                    <input type="text" name="identity_number" placeholder="身分證號碼" required
                        value="<?= htmlspecialchars($identityNumber) ?>">
                    <input type="text" name="phone" placeholder="電話號碼" required value="<?= htmlspecialchars($phone) ?>">
                    <button type="submit">驗證並查看我的通知</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($error_message)): ?>
            <p
                style="color: <?= $isPatientLoggedIn ? 'green' : 'red' ?>; padding: 10px; border: 1px dashed <?= $isPatientLoggedIn ? 'green' : 'red' ?>;">
                <?= $error_message ?>
            </p>
        <?php endif; ?>

        <div class="card">
            <h3><?= $isPatientLoggedIn ? '公開及個人通知列表' : '公開公告列表' ?></h3>
            <?php if (empty($notifications)): ?>
                <p>目前沒有最新的通知紀錄。</p>
            <?php else: ?>
                <div class="notice-list">
                    <?php foreach ($notifications as $notice): ?>
                        <div class="notice-item">
                            <?php
                            // 判斷是公開(patient_id=0)還是個人(patient_id>0)
                            $isPublic = (int) $notice['patient_id'] === 0;
                            $badgeClass = $isPublic ? 'public-badge' : 'private-badge';
                            $badgeText = $isPublic ? '公開公告' : '個人通知';
                            ?>
                            <span class="notice-type <?= $badgeClass ?>"><?= $badgeText ?></span>
                            <span class="notice-date"><?= htmlspecialchars($notice['notification_date']) ?></span>
                            <h3><?= htmlspecialchars($notice['title']) ?></h3>
                            <p><?= nl2br(htmlspecialchars($notice['content'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <button class="action-button" onclick="location.href='index.php'">返回首頁</button>
    </div>
</body>

</html>