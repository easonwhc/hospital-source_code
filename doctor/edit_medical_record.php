<?php
// 1. 引入 MySQL 連線
require_once __DIR__ . '/../db/connect.php';

// 2. 引入 MongoDB 函式庫 (注意路徑：從 /doctor/ 到 /vendor/autoload.php)
require_once __DIR__ . '/../vendor/autoload.php';
use MongoDB\Client;
use MongoDB\BSON\ObjectId;

// --- 設定區塊 ---
const MONGODB_URI = 'mongodb://localhost:27017';
const DB_NAME = 'hospital'; // 🌟 請確認您的 MongoDB 資料庫名稱
// --- 結束設定區塊 ---

$recordId = $_GET['record_id'];

// 3. MySQL 查詢 (確保選取 exam_image_ids 欄位)
$sql = "SELECT treatment_result, exam_result, exam_image_ids FROM medical_record WHERE record_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recordId);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();


// 4. 處理 GridFS 圖片 ID
$existingImageIds = [];
if (!empty($data['exam_image_ids'])) {
    $existingImageIds = json_decode($data['exam_image_ids'], true) ?? [];
}

// 5. 建立 MongoDB 連線 (可選，這裡主要是確認連線並準備)
try {
    $mongoClient = new Client(MONGODB_URI);
    $database = $mongoClient->selectDatabase(DB_NAME);
} catch (\Exception $e) {
    error_log("MongoDB connection failed: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <title>修改病歷內容</title>

    <style>
        body {
            background: #f4f4f4;
            font-family: Arial;
        }

        .edit-container {
            width: 500px;
            margin: 40px auto;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .1);
        }

        label {
            font-weight: bold;
            margin-top: 12px;
            display: block;
        }

        textarea {
            width: 100%;
            height: 120px;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
            resize: vertical;
        }

        .full-btn {
            display: block;
            width: 100%;
            background: #4a90e2;
            color: white;
            padding: 12px 0;
            text-align: center;
            font-size: 16px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            margin: 10px 0;
        }

        .full-btn:hover {
            background: #357ABD;
        }

        /* 🌟 新增圖片預覽樣式 🌟 */
        .image-preview {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 10px;
        }

        .image-wrapper {
            display: inline-block;
            width: 150px;
            /* 圖片預覽大小 */
            margin: 0 10px 10px 0;
            text-align: center;
            vertical-align: top;
        }

        .image-wrapper p {
            font-size: 12px;
            margin: 5px 0 0;
            color: #666;
            word-break: break-all;
        }
    </style>
</head>

<body>

    <div class="edit-container">
        <h2>修改病歷內容</h2>

        <form method="POST" action="update_medical_record.php" enctype="multipart/form-data">
            <input type="hidden" name="record_id" value="<?= $recordId ?>">

            <label>診療結果：</label>
            <textarea name="treatment_result"><?= htmlspecialchars($data['treatment_result']) ?></textarea>

            <label>檢驗結果：</label>
            <textarea name="exam_result"><?= htmlspecialchars($data['exam_result']) ?></textarea>

            <?php if (!empty($existingImageIds)): ?>
                <label style="margin-top: 20px;">現有檢驗圖片：</label>
                <div style="border: 1px solid #eee; padding: 10px; margin-bottom: 20px;">
                    <?php foreach ($existingImageIds as $imageId): ?>
                        <?php
                        // 圖片來源指向 view_image.php，並傳入 GridFS ID
                        $imageSrc = "../image/view_image.php?id=" . urlencode($imageId);
                        ?>
                        <div class="image-wrapper">
                            <img src="<?= htmlspecialchars($imageSrc) ?>" alt="檢驗圖片" class="image-preview">
                            <p>ID: <?= substr(htmlspecialchars($imageId), 0, 8) . '...' ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <label>上傳新的檢驗圖片：</label>
            <input type="file" name="exam_images[]" accept="image/*" multiple>

            <button class="full-btn" type="submit">確認修改</button>
            <a href="javascript:history.back()" class="full-btn">返回</a>
        </form>
    </div>
</body>

</html>