<?php
require_once __DIR__ . '/../db/connect.php';

if (!isset($_GET['id'])) {
    die("缺少診斷結果 ID");
}

$id = (int) $_GET['id'];

$sql = "SELECT * FROM diagnosis_result WHERE diagnosis_result_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("找不到資料");
}

$record_id = $data["record_id"];
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>修改診斷</title>

    <style>
        body {
            background: #f5f6fa;
            font-family: "Segoe UI", sans-serif;
        }

        .edit-container {
            width: 480px;
            background: #fff;
            margin: 40px auto;
            padding: 24px 28px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.10);
        }

        h2 {
            margin-bottom: 18px;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        label {
            display: block;
            font-weight: 600;
            margin-top: 12px;
            color: #444;
        }

        .form-input {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            margin-top: 6px;
            font-size: 15px;
        }

        .submit-btn {
            width: 100%;
            background: #4a90e2;
            padding: 12px;
            margin-top: 20px;
            border: none;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            font-size: 16px;
            transition: 0.2s;
        }

        .submit-btn:hover {
            background: #357ABD;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: #6c5ce7;
            text-decoration: none;
            font-size: 15px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        select.form-select {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            background: #f7f7f7;
            font-size: 15px;
            color: #333;
            margin-top: 6px;
            appearance: none;

        }

        select.form-select:focus {
            outline: none;
            border-color: #999;
            background: #f2f2f2;
        }
    </style>

</head>

<body>

    <div class="edit-container">

        <h2>修改診斷結果</h2>

        <form method="POST" action="update_diagnosis_result.php">

            <input type="hidden" name="id" value="<?= $id ?>">

            <label>診斷：</label>
            <input class="form-input" name="diagnosis" value="<?= htmlspecialchars($data['diagnosis']) ?>" required>

            <label>醫囑：</label>
            <input class="form-input" name="prescription" value="<?= htmlspecialchars($data['prescription']) ?>">

            <label>醫療建議：</label>
            <input class="form-input" name="medical_advice" value="<?= htmlspecialchars($data['medical_advice']) ?>">

            <label>治療計畫：</label>
            <input class="form-input" name="treatment_plan" value="<?= htmlspecialchars($data['treatment_plan']) ?>">

            <label>治療狀態：</label>
            <select name="diagnosis_status" class="form-select">
                <option value="ongoing" <?= $data['diagnosis_status'] == 'ongoing' ? 'selected' : '' ?>>治療中</option>
                <option value="completed" <?= $data['diagnosis_status'] == 'completed' ? 'selected' : '' ?>>治療完成</option>
                <option value="followup" <?= $data['diagnosis_status'] == 'followup' ? 'selected' : '' ?>>需回診</option>
            </select>



            <button class="submit-btn" type="submit">確認修改</button>
        </form>

        <button class="submit-btn" onclick="history.back()">返回</button>

    </div>


</body>

</html>