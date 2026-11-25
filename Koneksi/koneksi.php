<?php

$host = getenv("DB_HOST");
$username = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$database = getenv("DB_NAME");

if ($host === false || $username === false || $password === false || $database === false) {
    error_log("Konfigurasi database belum lengkap. Atur DB_HOST, DB_USER, DB_PASSWORD, dan DB_NAME.");
    http_response_code(500);
    die("Konfigurasi database belum lengkap.");
}

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Conection failed: " . mysqli_connect_error());
}

if (!mysqli_set_charset($conn, "utf8mb4")) {
    die("Gagal mengatur karakter database: " . mysqli_error($conn));
}

?>