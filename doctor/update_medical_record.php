<?php
// 1. 引入 MySQL 連線
require_once __DIR__ . '/../db/connect.php';

// 2. 引入 MongoDB 函式庫
require_once __DIR__ . '/../vendor/autoload.php';
use MongoDB\Client;
use MongoDB\GridFS\Bucket;
// 移除 ObjectId，因為在本檔案中未使用

// --- MongoDB 設定區塊 ---
const MONGODB_URI = 'mongodb://localhost:27017';
const DB_NAME = 'hospital'; // 🌟 請確認您的 MongoDB 資料庫名稱
// --- 結束設定區塊 ---

// 接收表單數據
$record_id = $_POST['record_id'];
$treatment_result = $_POST['treatment_result'];
$exam_result = $_POST['exam_result'];

// --- A. 連接 MongoDB 並讀取舊圖片 ID ---

// 1. 連接 MongoDB 並初始化 GridFS
try {
        // 🌟 修正點 1: 移除 MongoDB\Client 的命名參數 'uri:'
        $mongoClient = new Client(MONGODB_URI);

        // 獲取 Database 物件
        $database = $mongoClient->selectDatabase(DB_NAME);

        // 🌟 修正點 2: 解決 TypeError！使用 Manager 物件 ($mongoClient->getManager()) 和 Database 名稱 (DB_NAME) 兩個參數
        // 修正點 3: 將 'hospital' 替換為常數 DB_NAME
        $bucket = new Bucket($mongoClient->getManager(), DB_NAME);

} catch (\Exception $e) {
        error_log("FATAL: MongoDB connection failed: " . $e->getMessage());
        die("伺服器錯誤：無法連接圖片資料庫。");
}


// 2. 從 MySQL 讀取現有的圖片 ID JSON 字串
$sql_select = "SELECT exam_image_ids FROM medical_record WHERE record_id=?";
$stmt_select = $conn->prepare($sql_select);
$stmt_select->bind_param("i", $record_id);
$stmt_select->execute();
$old_data = $stmt_select->get_result()->fetch_assoc();
$stmt_select->close();

// 將舊的 JSON 字串轉換為 PHP 陣列
$existingImageIds = [];
if (!empty($old_data['exam_image_ids'])) {
        $existingImageIds = json_decode($old_data['exam_image_ids'], true) ?? [];
}

// --- B. 處理新上傳的圖片並儲存到 GridFS ---

$uploadedFileIds = [];

// 檢查是否有圖片上傳
if (!empty($_FILES['exam_images']['name'][0])) {
        $fileCount = count($_FILES['exam_images']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
                $fileTmpPath = $_FILES['exam_images']['tmp_name'][$i];
                $fileName = $_FILES['exam_images']['name'][$i];
                $fileError = $_FILES['exam_images']['error'][$i];
                $fileType = $_FILES['exam_images']['type'][$i];

                if ($fileError === UPLOAD_ERR_OK) {
                        try {
                                $fileStream = fopen($fileTmpPath, 'r');

                                // 儲存到 GridFS
                                // uploadFromStream 成功時返回一個 ObjectId
                                $fileId = $bucket->uploadFromStream($fileName, $fileStream, [
                                        'metadata' => [
                                                'original_name' => $fileName,
                                                'record_id' => $record_id,
                                                'content_type' => $fileType
                                        ]
                                ]);

                                fclose($fileStream);
                                // 收集新圖片的 ID，將 ObjectId 轉為字串儲存
                                $uploadedFileIds[] = (string) $fileId;

                        } catch (\Exception $e) {
                                error_log("GridFS upload failed for file {$fileName}: " . $e->getMessage());
                                // 建議這裡不要 die()，而是跳過這個失敗的檔案
                        }
                }
        }
}

// --- C. 更新 MySQL：合併 ID 並儲存 JSON ---

// 1. 合併新舊圖片 ID
$allImageIds = array_merge($existingImageIds, $uploadedFileIds);

// 2. 將合併後的 ID 陣列轉為 JSON 字串
$jsonIds = json_encode($allImageIds);

// 3. 執行 MySQL UPDATE
$sql_update = "UPDATE medical_record
               SET treatment_result=?, exam_result=?, exam_image_ids=?
               WHERE record_id=?";

$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param("sssi", $treatment_result, $exam_result, $jsonIds, $record_id);
$stmt_update->execute();
$stmt_update->close();

// 回到病歷詳細頁
header("Location: record_detail.php?record_id=" . $record_id);
exit;