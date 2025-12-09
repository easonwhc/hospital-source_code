<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\GridFS\Bucket;

const URI = "mongodb://localhost:27017";
const DB = "hospital";

$imageId = $_GET["id"] ?? null;

if (!$imageId) {
    http_response_code(400);
    die("Missing id.");
}

try {
    $client = new Client(URI);
    $db = $client->selectDatabase(DB);

    // ★ 必須建立 bucket
    $bucket = new Bucket(
        $client->getManager(),
        DB
    );

    $objectId = new ObjectId($imageId);

    // ★ 先抓 metadata
    $file = $db->selectCollection("fs.files")->findOne([
        "_id" => $objectId
    ]);

    if (!$file) {
        http_response_code(404);
        die("Image not found.");
    }

    // ★ 設定 Content-Type
    $contentType = $file->metadata['content_type'] ?? "image/jpeg";

    header("Content-Type: $contentType");
    header("Content-Length: " . $file->length);

    // ★ 讀取 chunk → 這才是 GridFS 正確讀取方式
    $stream = $bucket->openDownloadStream($objectId);

    fpassthru($stream);
    fclose($stream);

} catch (Exception $e) {
    error_log("GridFS error: " . $e->getMessage());
    http_response_code(500);
    die("Server error.");
}
