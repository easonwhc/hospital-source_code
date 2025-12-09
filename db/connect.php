<?php
// db/connect.php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "hospital";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "error" => "DB connection failed",
        "detail" => $conn->connect_error
    ]));
}

$conn->set_charset("utf8mb4");
?>