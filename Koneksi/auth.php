<?php

require_once __DIR__ . "/google-oauth-common.php";

$aksi = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';

if ($aksi === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Allow: POST");
        header("Location: ../navbar.html");
        exit();
    }

    require_once __DIR__ . "/koneksi.php";
    require_once __DIR__ . "/akun.php";

    $emailInput = $_POST['email'] ?? '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!siapkanTabelAkun($conn)) {
        error_log("Gagal menyiapkan tabel users: " . mysqli_error($conn));
        header("Location: ../navbar.html?error=setup");
        exit();
    }

    try {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, nama_lengkap, email, password_hash, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if (!$stmt) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $userId, $namaLengkap, $emailUser, $passwordHash, $role);
        $ditemukan = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    } catch (mysqli_sql_exception $exception) {
        error_log("Gagal memeriksa login: " . $exception->getMessage());
        header("Location: ../navbar.html?error=setup");
        exit();
    }

    if (!$ditemukan || !password_verify($password, $passwordHash)) {
        header("Location: ../navbar.html?error=invalid");
        exit();
    }

    quizStartSession();
    session_regenerate_id(true);
    $_SESSION['akun_id'] = (int) $userId;
    $_SESSION['nama_lengkap'] = $namaLengkap;
    $_SESSION['email'] = $emailUser;
    $_SESSION['role'] = $role;

    catatLogAktivitas($conn, (int) $userId, 'login', 'Berhasil masuk ke website.');

    if ($role === 'admin') {
        $tujuan = "../Dashboard/Admin.php";
    } elseif ($role === 'pembuat_soal') {
        $tujuan = "../Dashboard/PembuatSoal.php";
    } else {
        $tujuan = "../Dashboard/Beranda.php";
    }

    header("Location: " . $tujuan);
    exit();
}

if ($aksi === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Allow: POST");
        http_response_code(405);
        exit("Metode tidak diizinkan.");
    }

    quizStartSession();
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $parameterCookie = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parameterCookie['path'],
            $parameterCookie['domain'],
            $parameterCookie['secure'],
            $parameterCookie['httponly']
        );
    }

    session_destroy();
    header("Location: ../navbar.html");
    exit();
}

http_response_code(404);
echo "Aksi autentikasi tidak dikenal.";
