<?php

$host = getenv("DB_HOST");
$username = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$database = getenv("DB_NAME");
$portValue = getenv("DB_PORT");

if ($portValue === false || $portValue === '') {
    $port = 3306;
} elseif (!ctype_digit($portValue) || (int) $portValue < 1 || (int) $portValue > 65535) {
    error_log("Konfigurasi DB_PORT tidak valid.");
    http_response_code(500);
    die("Konfigurasi port database tidak valid.");
} else {
    $port = (int) $portValue;
}

if (
    $host === false || $host === '' ||
    $username === false || $username === '' ||
    $password === false ||
    $database === false || $database === ''
) {
    error_log("Konfigurasi database belum lengkap. Atur DB_HOST, DB_USER, DB_PASSWORD, dan DB_NAME.");
    http_response_code(500);
    die("Konfigurasi database belum lengkap.");
}

try {
    $conn = mysqli_connect($host, $username, $password, $database, $port);
} catch (mysqli_sql_exception $exception) {
    error_log("Koneksi database gagal: " . $exception->getMessage());
    http_response_code(500);
    die("Koneksi database gagal.");
}

if (!$conn) {
    error_log("Koneksi database gagal: " . mysqli_connect_error());
    http_response_code(500);
    die("Koneksi database gagal.");
}

if (!mysqli_set_charset($conn, "utf8mb4")) {
    error_log("Gagal mengatur karakter database: " . mysqli_error($conn));
    http_response_code(500);
    die("Gagal mengatur karakter database.");
}

?>
