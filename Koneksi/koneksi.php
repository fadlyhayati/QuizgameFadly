<?php

$host = getenv("2l7bzx.h.filess.io");
$username = getenv("client_serveradin_slowlylong");
$password = getenv("Fadly11223");
$database = getenv("client_serveradin_slowlylong");
$port = gatenv("61031");

if ($host === false || $username === false || $password === false || $database === false) {
    error_log("Konfigurasi database belum lengkap. Atur DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT");
    http_response_code(500);
    die("Konfigurasi database belum lengkap.");
}

$conn = mysqli_connect($host, $username, $password, $database, $port);

if (!$conn) {
    die("Conection failed: " . mysqli_connect_error());
}

if (!mysqli_set_charset($conn, "utf8mb4")) {
    die("Gagal mengatur karakter database: " . mysqli_error($conn));
}

?>
