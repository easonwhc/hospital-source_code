<?php
session_start();

$loggedIn = false;
$role = $_SESSION['role'] ?? '';

// 檢查 Session，如果已登入且角色正確，直接導向對應的儀表板
if (isset($_SESSION['user_id']) && !empty($role)) {
    $loggedIn = true;
    
    if ($role === 'Patient') {
        header("Location: patient_dashboard.php");
        exit();
    } elseif ($role === 'Doctor') {
        // 如果有 doctor_dashboard.php
        header("Location: doctor_dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>醫院病人服務入口</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; color: #333; margin: 0; padding: 0; }
        .home-container { max-width: 960px; margin: 80px auto; padding: 40px; text-align: center; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        .home-container h1 { color: #007bff; font-size: 2.5em; margin-bottom: 20px; }
        .action-group { display: flex; justify-content: space-between; gap: 20px; margin-top: 40px; }
        .action-card { flex: 1; padding: 30px; background: #f8f9fa; border-radius: 8px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); transition: transform 0.3s, box-shadow 0.3s; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); }
        .action-card h3 { margin-top: 0; color: #333; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 15px; }
        .action-card p { color: #6c757d; font-size: 0.95em; margin-bottom: 25px; height: 40px; }
        .action-card button { width: 100%; padding: 12px 0; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em; font-weight: 600; color: white; transition: opacity 0.3s; }
        .card-query button { background: #00b050; }
        .card-appointment button { background: #3c6ff7; }
        .card-notification button { background: #ffc000; }
        .login-link { display: block; margin-top: 30px; font-size: 1.1em; color: #007bff; text-decoration: none; }
        @media (max-width: 768px) { .action-group { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="home-container">
        <h1>🏥 醫院病人服務入口</h1>
        <p>
            歡迎使用本院線上服務系統。請選擇您需要的服務項目。
        </p>

        <?php if ($loggedIn): ?>
            <p style="color:red; font-weight: bold;">⚠️ 您的登入狀態異常，請重新整理或登出。</p>
            <button onclick="location.href='logout.php'">登出</button>
        <?php else: ?>
            
            <div class="action-group">
                
                <div class="action-card card-query">
                    <h3>📋 病歷與診斷查詢</h3>
                    <p>查詢所有就診及診斷紀錄。</p>
                    <button onclick="location.href='patient_query.php'">
                        進入查詢
                    </button>
                </div>

                <div class="action-card">
                    <h3>📅 預約掛號</h3>
                    <p>查看門診時間並進行線上預約。</p>
                    <button onclick="location.href='appointment_query.php'" style="background: #3c6ff7;">
                        立即掛號
                    </button>
                </div>
                
                <div class="action-card card-notification">
                    <h3>🔔 最新通知</h3>
                    <p>查看醫院公告、門診異動等資訊。</p>
                    <button onclick="location.href='notification.php'">
                        查看通知
                    </button>
                </div>
                
            </div>
            

        <?php endif; ?>
    </div>
</body>
</html>