<?php
// ----------------------
//  後端：處理 POST JSON
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header("Content-Type: application/json; charset=UTF-8");
    require_once "../db/connect.php";

    // 讀 JSON
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        echo json_encode(["error" => "無效的 JSON"]);
        exit;
    }

    // 取欄位（全部從 JSON）
    $record_id = $data["record_id"];
    $doctor_id = $data["doctor_id"];
    $diagnosis = $data["diagnosis"];
    $prescription = $data["prescription"];
    $medical_advice = $data["medical_advice"];
    $treatment_plan = $data["treatment_plan"];
    $hospitalized = $data["hospitalized"];  // ★ 正確方式

    // 檢查
    if (!$diagnosis) {
        echo json_encode(["error" => "診斷為必填"]);
        exit;
    }

    // SQL — 新增全部欄位
    $sql = "INSERT INTO diagnosis_result 
            (record_id, doctor_id, diagnosis, prescription, medical_advice, treatment_plan, hospitalized)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iissssi",
        $record_id,
        $doctor_id,
        $diagnosis,
        $prescription,
        $medical_advice,
        $treatment_plan,
        $hospitalized
    );

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "diagnosis_result_id" => $stmt->insert_id
        ]);
    } else {
        echo json_encode([
            "error" => "資料寫入失敗",
            "sql_error" => $stmt->error
        ]);
    }

    exit;
}
?>


<!-- ----------------------
     前端 HTML 顯示介面
----------------------- -->
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>新增診斷結果</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <h2>新增診斷結果（diagnosis_result）</h2>

    <div class="section">
        <h3>病歷基本資訊</h3>
        <p>Record ID：<span id="recordIdDisplay">（尚未取得）</span></p>
        <p>Doctor ID：<span id="doctorIdDisplay">1</span></p>
    </div>

    <div class="section">
        <h3>診斷內容</h3>

        <input type="hidden" id="record_id">
        <input type="hidden" id="doctor_id" value="1">

        <label>診斷（diagnosis）<span style="color:red;">＊必填</span></label><br>
        <textarea id="diagnosis" rows="3" cols="60"></textarea><br><br>

        <label>醫囑（prescription）</label><br>
        <textarea id="prescription" rows="3" cols="60"></textarea><br><br>

        <label>醫療建議（medical_advice）</label><br>
        <textarea id="medical_advice" rows="3" cols="60"></textarea><br><br>

        <label>治療計畫（treatment_plan）</label><br>
        <textarea id="treatment_plan" rows="3" cols="60"></textarea><br><br>

        <label>是否需要住院：</label>
        <select name="hospitalized">
            <option value="0">否</option>
            <option value="1">是</option>
        </select>

        <button type="button" onclick="submitDiagnosis()">送出診斷</button>
    </div>

    <button onclick="history.back()">返回</button>

    <script>
        // 載入 URL 的 record_id
        window.onload = function () {
            const recordId = new URLSearchParams(window.location.search).get("record_id");
            document.getElementById("record_id").value = recordId;
            document.getElementById("recordIdDisplay").innerText = recordId;
        };

        function submitDiagnosis() {

            const data = {
                record_id: document.getElementById('record_id').value,
                doctor_id: document.getElementById('doctor_id').value,
                diagnosis: document.getElementById('diagnosis').value.trim(),
                prescription: document.getElementById('prescription').value.trim(),
                medical_advice: document.getElementById('medical_advice').value.trim(),
                treatment_plan: document.getElementById('treatment_plan').value.trim(),
                hospitalized: document.querySelector("select[name='hospitalized']").value
            };

            if (!data.diagnosis) {
                alert("診斷為必填！");
                return;
            }

            // 呼叫自己（同一檔案）
            fetch("", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(data)
            })
                .then(res => res.json())
                .then(result => {
                    if (result.error) {
                        alert("寫入失敗：" + result.error);
                        console.log(result.sql_error);
                        return;
                    }

                    alert("新增成功！診斷結果 ID = " + result.diagnosis_result_id);
                    location.href = "record_detail.php?record_id=" + data.record_id;
                })
                .catch(err => alert("錯誤：" + err));
        }
    </script>

</body>

</html>