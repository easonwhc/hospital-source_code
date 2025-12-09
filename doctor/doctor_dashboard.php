<?php
require_once __DIR__ . '/../db/connect.php';

// 假設醫生登入 ID
$doctorId = 1;

// 今天日期
$today = date("Y-m-d");


// ---------------------------
// 查詢：今日預約
// ---------------------------
$sqlAppointments = "
    SELECT a.appointment_id, a.appointment_time, p.name AS patient_name
    FROM appointment a
    JOIN patient p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? 
      AND DATE(a.appointment_time) = ?
    ORDER BY a.appointment_time ASC
";

$stmtAppointments = $conn->prepare($sqlAppointments);
$stmtAppointments->bind_param("is", $doctorId, $today);
$stmtAppointments->execute();
$resultAppointments = $stmtAppointments->get_result();

// ---------------------------
// 查詢：最近 5 筆檢驗報告
// ---------------------------
$sqlLab = "
    SELECT mr.record_id, mr.visit_time, p.name, mr.exam_result
    FROM medical_record mr
    JOIN patient p ON mr.patient_id = p.patient_id
    WHERE mr.doctor_id = ?
    ORDER BY mr.visit_time DESC
    LIMIT 5
";

$stmtLab = $conn->prepare($sqlLab);
$stmtLab->bind_param("i", $doctorId);
$stmtLab->execute();
$resultLab = $stmtLab->get_result();



?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>醫生首頁</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <h2>醫生首頁（Doctor Dashboard）</h2>



    <!-- 今日預約 -->
    <div class="card">
        <h3>今日預約</h3>
        <ul id="appointmentList">
            <?php if ($resultAppointments->num_rows === 0): ?>
                <li>今天沒有預約紀錄</li>
            <?php else: ?>
                <?php while ($row = $resultAppointments->fetch_assoc()): ?>
                    <li>
                        <?= htmlspecialchars(date("H:i", strtotime($row["appointment_time"]))) ?>
                        <?= htmlspecialchars($row["patient_name"]) ?>
                        （Appointment ID: <?= (int) $row["appointment_id"] ?>）

                        <a href="create_or_view_record.php?appointment_id=<?= $row['appointment_id'] ?>">查看詳情</a>

                    </li>
                <?php endwhile; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- 最近檢驗報告 -->
    <div class="card">
        <h3>最近檢驗報告</h3>
        <ul id="unreadLab">
            <?php if ($resultLab->num_rows === 0): ?>
                <li>尚無檢驗報告</li>
            <?php else: ?>
                <?php while ($row = $resultLab->fetch_assoc()): ?>
                    <li>
                        MRN<?= (int) $row["record_id"] ?> –
                        <?= htmlspecialchars($row["name"]) ?>，
                        檢驗結果：<?= htmlspecialchars(mb_substr($row["exam_result"], 0, 20)) ?>...
                        <button onclick="location.href='record_detail.php?record_id=<?= (int) $row["record_id"] ?>'">
                            查看
                        </button>
                    </li>
                <?php endwhile; ?>
            <?php endif; ?>
        </ul>
    </div>


    <!-- 發送通知 -->
    <div class="card">
        <h3>發送通知給病人</h3>

        <!-- 通知類型 -->
        <div class="form-group">
            <label for="type">通知類型：</label>
            <select name="type" required>
                <option value="醫療提醒">醫療提醒</option>
                <option value="處方通知">處方通知</option>
                <option value="檢查結果">檢查結果</option>
            </select>
        </div>

        <!-- 通知內容 -->
        <div class="form-group">
            <label for="content">通知內容：</label>
            <textarea name="content" rows="4" required></textarea>
        </div>

        <!-- 排程時間 -->
        <div class="form-group">
            <label for="scheduled_time">排程時間：</label>
            <input type="datetime-local" name="scheduled_time" required>
        </div>

        <!-- 目標病人 -->
        <div class="form-group">
            <label for="patient_id">選擇病人：</label>
            <select name="patient_id" required>
                <!-- 從資料庫動態載入病人名單 -->
                <?php
                $sqlPatients = "SELECT patient_id, name FROM patient";
                $resultPatients = $conn->query($sqlPatients);
                while ($patient = $resultPatients->fetch_assoc()) {
                    echo "<option value='{$patient['patient_id']}'>" . htmlspecialchars($patient['name']) . "</option>";
                }
                ?>
            </select>
        </div>

        <!-- 發送按鈕 -->
        <div class="form-group">
            <button type="submit">發送通知</button>
        </div>
    </div>
    <button onclick="location.href='record_search.php'">🔍 進入病歷查詢</button>

</html>