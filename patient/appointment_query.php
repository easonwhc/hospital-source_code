<?php
session_start();
// 確保連線檔案路徑正確
require_once('../db/connect.php');


// 檢查資料庫連線是否成功
if (!$conn) {
    die("<h1>系統錯誤</h1><p>資料庫連線失敗，請稍後再試。</p>");
}

// ----------------------------------------------------
// 步驟一：處理身份證號碼和電話輸入
// ----------------------------------------------------
$patientId = 0;
$query_message = '';
$queryIdentity = ''; // 用於保留使用者輸入
$queryPhone = '';    // 用於保留使用者輸入

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['query_identity']) && isset($_POST['query_phone'])) {
    $queryIdentity = trim($_POST['query_identity']);
    $queryPhone = trim($_POST['query_phone']);

    if (empty($queryIdentity) || empty($queryPhone)) {
        $query_message = "請完整輸入您的身份證字號/號碼和電話號碼。";
    } else {
        // 查詢 patient_id 必須同時透過 identity_number 和 phone
        $sqlCheck = "SELECT patient_id FROM patient WHERE identity_number = ? AND phone = ?";
        $stmtCheck = $conn->prepare($sqlCheck);

        if ($stmtCheck) {
            // 身份證號碼和電話號碼都使用字串 'ss'
            $stmtCheck->bind_param("ss", $queryIdentity, $queryPhone);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();

            if ($resultCheck->num_rows > 0) {
                // 成功：取得 patient_id
                $patientId = (int) $resultCheck->fetch_assoc()['patient_id'];

                // ❗ 導向預約頁面 appointment.php，並傳遞 patient_id ❗
                // 這裡通常會將 patient_id 存入 Session 以確保身份
                $_SESSION['patient_id'] = $patientId;
                $_SESSION['identity_verified'] = true;

                header("Location: appointment.php?patient_id=" . $patientId);
                exit();
            } else {
                $query_message = "身份證字號/號碼或電話號碼不正確，請檢查後再試。";
            }
            $stmtCheck->close();
        } else {
            $query_message = "系統錯誤，請稍後再試。(" . $conn->error . ")";
        }
    }
}
$conn->close();

// ----------------------------------------------------
// 步驟二：顯示身份證號碼和電話輸入表單
// ----------------------------------------------------
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>預約掛號 - 身份確認</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f9;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            max-width: 450px;
            width: 100%;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e3a8a;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
            text-transform: uppercase;
        }

        .btn-submit {
            background-color: #3c6ff7;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 18px;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .btn-submit:hover {
            background-color: #315cd8;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 600;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>📅 預約掛號 - 身份確認</h2>
        <div class="card">
            <?php if ($query_message): ?>
                <div class="message"><?= htmlspecialchars($query_message) ?></div>
            <?php endif; ?>

            <form method="POST" action="appointment_query.php">
                <div class="form-group">
                    <label for="query_identity">請輸入您的身份證字號/號碼</label>
                    <input type="text" id="query_identity" name="query_identity" placeholder="例如: A123456789" required
                        maxlength="10" value="<?= htmlspecialchars($queryIdentity) ?>">
                </div>

                <div class="form-group">
                    <label for="query_phone">請輸入您的電話號碼</label>
                    <input type="text" id="query_phone" name="query_phone" placeholder="例如: 0912345678" required
                        value="<?= htmlspecialchars($queryPhone) ?>">
                </div>

                <button type="submit" class="btn-submit">確認身份並進入預約</button>
            </form>

            <a href="index.php" class="back-link">返回首頁</a>
        </div>
    </div>
</body>

</html>