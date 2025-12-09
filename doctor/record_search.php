<?php
require_once __DIR__ . '/../db/connect.php';

// 讀取查詢參數
$name = $_GET['name'] ?? '';
$fromDate = $_GET['fromDate'] ?? '';
$toDate = $_GET['toDate'] ?? '';

$records = [];

if ($_GET) {
    $sql = "
        SELECT 
            mr.record_id,
            mr.visit_time,
            p.patient_id,
            p.name
        FROM medical_record mr
        JOIN patient p ON mr.patient_id = p.patient_id
        WHERE 1 = 1
    ";

    $params = [];
    $types = "";

    // ✔ 病人姓名模糊查詢（保留）
    if ($name !== "") {
        $sql .= " AND p.name LIKE ? ";
        $params[] = "%" . $name . "%";
        $types .= "s";
    }

    // ✔ 開始日期
    if ($fromDate !== "") {
        $sql .= " AND mr.visit_time >= ? ";
        $params[] = $fromDate . " 00:00:00";
        $types .= "s";
    }

    // ✔ 結束日期
    if ($toDate !== "") {
        $sql .= " AND mr.visit_time <= ? ";
        $params[] = $toDate . " 23:59:59";
        $types .= "s";
    }

    $sql .= " ORDER BY mr.visit_time DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}
?>


<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>病歷查詢</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <h2>病歷查詢（依 SQL 結構調整）</h2>

    <form class="search-box" method="get" action="record_search.php">
        <label>病人姓名：</label>
        <input type="text" name="name" value="<?= htmlspecialchars($name) ?>">

        <label>開始日期：</label>
        <input type="date" name="fromDate" value="<?= htmlspecialchars($fromDate) ?>">

        <label>結束日期：</label>
        <input type="date" name="toDate" value="<?= htmlspecialchars($toDate) ?>">

        <button type="submit">查詢</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Record ID</th>
                <th>病人名稱</th>
                <th>就診時間</th>
                <th>查看</th>
            </tr>
        </thead>
        <tbody id="resultTable">
            <?php if (empty($records)): ?>
                <tr>
                    <td colspan="4">尚無查詢結果</td>
                </tr>
            <?php else: ?>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?= (int) $row['record_id'] ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['visit_time']) ?></td>
                        <td>
                            <button onclick="location.href='record_detail.php?record_id=<?= (int) $row['record_id'] ?>'">
                                查看
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <button onclick="history.back()">返回</button>

</body>

</html>