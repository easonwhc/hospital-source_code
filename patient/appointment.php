<?php
session_start();
// 確保連線檔案路徑正確
require_once('../db/connect.php');

// 檢查資料庫連線是否成功
if (!$conn) {
    die("<h1>系統錯誤</h1><p>資料庫連線失敗，請稍後再試。</p>");
}

// ⭐⭐⭐ 中文編碼最終修正：強制設定連線編碼為 utf8mb4 ⭐⭐⭐
// 這是解決中文亂碼問題的關鍵。
if (isset($conn)) {
    if ($conn->set_charset("utf8mb4") === false) {
        $conn->query("SET NAMES 'utf8mb4'");
    }
}
// ⭐⭐⭐ 修正結束 ⭐⭐⭐

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
    // 如果沒有有效的病人 ID，導向首頁
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

// 科別列表
$departments = ['內科', '外科', '兒科', '牙科', '眼科', '婦產科', '復健科', '皮膚科', '神經科', '耳鼻喉科', '骨科', '精神科'];

// 儲存所有醫生的數據，用於前端顯示
$allDoctorsData = [];

// ----------------------------------------------------
// 2. 查詢所有醫生 ID
// ----------------------------------------------------

$sqlDoctors = "SELECT doctor_id FROM doctor ORDER BY doctor_id";
$resultDoctors = $conn->query($sqlDoctors);

if ($resultDoctors) {
    while ($doc = $resultDoctors->fetch_assoc()) {
        $currentDoctorId = (int) $doc['doctor_id'];

        $allDoctorsData[] = [
            'id' => $currentDoctorId,
            'name' => "醫生 ID: {$currentDoctorId}", // 僅顯示 ID
            'department' => '未指定科別', // 留一個預設值，但前端不再顯示
        ];
    }
} else {
    $error = "無法查詢醫生 ID 資料: " . htmlspecialchars($conn->error);
}


// ----------------------------------------------------
// 3. 處理表單提交 (POST 請求)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $doctorId = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $department = trim($_POST['department']);

    $appointmentDate = trim($_POST['appointment_date']);
    $appointmentTimeOnly = trim($_POST['appointment_time_only']);
    $appointmentTime = "{$appointmentDate} {$appointmentTimeOnly}:00";

    // 接收單行文字輸入的就診原因
    $reason = trim($_POST['reason']);
    $status = 'Scheduled';

    if (!$doctorId || empty($department) || empty($appointmentDate) || empty($appointmentTimeOnly) || empty($reason)) {
        $error = "錯誤：請選擇醫生、日期、時間、科別與**填寫就診原因**。";
    } elseif (strtotime($appointmentTime) < time()) {
        $error = "錯誤：預約時間必須設定在未來。";
    } else {

        // 準備 INSERT 語句到 appointment 表
        $sqlInsert = "INSERT INTO appointment 
                      (patient_id, doctor_id, appointment_time, department, reason, status)
                      VALUES (?, ?, ?, ?, ?, ?)";

        $stmtInsert = $conn->prepare($sqlInsert);

        if ($stmtInsert === false) {
            $error = "資料庫準備錯誤: " . htmlspecialchars($conn->error);
        } else {
            // "iissss" 依序對應: patient_id(i), doctor_id(i), appointment_time(s), department(s), reason(s), status(s)
            $stmtInsert->bind_param(
                "iissss",
                $patientId,
                $doctorId,
                $appointmentTime,
                $department,
                $reason,
                $status
            );

            if ($stmtInsert->execute()) {
                $message = "✅ 預約成功！您的掛號已排定。預約編號: " . $stmtInsert->insert_id;
            } else {
                // 如果失敗，錯誤訊息通常會在這裡顯示資料庫的錯誤
                $error = "預約失敗，請重試: " . htmlspecialchars($stmtInsert->error);
            }
            $stmtInsert->close();
        }
    }
}

if (isset($conn)) {
    $conn->close();
}
// 將所有醫生資料轉換為 JSON 格式，供 JavaScript 使用
$doctorsJson = json_encode($allDoctorsData);
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>門診預約掛號</title>
    <style>
        /* 定義橘色調 */
        :root {
            --primary-blue: #3c6ff7;
            --secondary-orange: #ff9900;
            --secondary-orange-hover: #cc7a00;
            /* 深橘色 */
        }

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
            max-width: 600px;
            width: 100%;
        }

        .card {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: var(--primary-blue);
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

        /* 調整所有輸入/選擇框的樣式 */
        .form-group select,
        .form-group textarea,
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
        }

        .date-time-group {
            display: flex;
            gap: 10px;
        }

        .date-time-group>div {
            flex: 1;
        }

        /* 主要送出按鈕 (藍色實心) */
        .btn-submit {
            background-color: var(--primary-blue);
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

        /* ⭐ 修正後的：查看預約按鈕樣式 (橘色實心) ⭐ */
        .btn-check-appt {
            background-color: var(--secondary-orange);
            /* 橘色實心背景 */
            color: white;
            /* 白色文字 */
            border: none;
            /* 無邊框，確保與主按鈕尺寸相同 */

            /* 保持與主按鈕完全相同的尺寸 */
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 18px;
            font-weight: 600;

            transition: background-color 0.3s;
            display: block;
            text-align: center;
            margin-top: 15px;
            text-decoration: none;
            box-sizing: border-box;
        }

        .btn-check-appt:hover {
            background-color: var(--secondary-orange-hover);
            /* 深橘色 Hover 效果 */
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

        .back-link {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>門診預約掛號 (病人 ID: <?= $patientId ?>)</h2>
        <div class="card">
            <?php if ($message): ?>
                <div class="message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="appointment.php?patient_id=<?= $patientId ?>">

                <div class="form-group">
                    <label for="department_select">選擇科別</label>
                    <select id="department_select" name="department" required>
                        <option value="">-- 請選擇科別 --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="doctor_id_select">選擇醫生</label>
                    <select id="doctor_id_select" name="doctor_id" required>
                        <option value="">-- 請選擇醫生 --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>選擇預約日期與時間</label>
                    <div class="date-time-group">
                        <div>
                            <input type="date" id="appointment_date" name="appointment_date" required
                                min="<?= date('Y-m-d') ?>"> <!-- 限制日期選擇今天或以後 -->
                        </div>
                        <div>
                            <input type="time" id="appointment_time_only" name="appointment_time_only" required>
                        </div>
                    </div>
                </div>




                <div class="form-group">
                    <label for="reason">就診原因 (簡述)</label>
                    <input type="text" id="reason" name="reason" placeholder="例如：感冒、發燒持續三天、皮膚紅疹等" required>
                </div>

                <button type="submit" class="btn-submit">確認預約掛號</button>
            </form>

            <a href="patient_appointments.php?patient_id=<?= $patientId ?>" class="btn-check-appt">
                我的預約 / 查詢紀錄
            </a>

            <a href="index.php" class="back-link">回到首頁</a>

            <script>
                // 從 PHP 取得的醫生列表 (包含 ID)
                const allDoctors = <?= $doctorsJson ?>;

                const doctorSelect = document.getElementById('doctor_id_select');
                const appointmentDate = document.getElementById('appointment_date');

                // ------------------------------------
                // 步驟 1: 載入所有醫生到選單
                // ------------------------------------
                function loadAllDoctorOptions() {
                    doctorSelect.innerHTML = '<option value="">-- 請選擇醫生 --</option>';

                    if (allDoctors && allDoctors.length > 0) {
                        allDoctors.forEach(doc => {
                            const option = document.createElement('option');
                            option.value = doc.id;
                            // 保持名稱顯示為 "醫生 ID: X"
                            option.textContent = doc.name;
                            doctorSelect.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = '目前無醫生可預約 (請檢查 doctor 資料表)';
                        doctorSelect.appendChild(option);
                    }
                }

                // ------------------------------------
                // 步驟 2: 設定最小日期限制與初始化
                // ------------------------------------
                document.addEventListener('DOMContentLoaded', function () {
                    loadAllDoctorOptions();

                    // 設定日期最小限制為今天
                    const today = new Date();
                    const minDate = today.toISOString().split('T')[0]; // 取得今天的日期（YYYY-MM-DD）

                    appointmentDate.min = minDate; // 設置最小日期為今天
                });

            </script>
        </div>
    </div>
</body>

</html>