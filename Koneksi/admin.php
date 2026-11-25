<?php

session_start();

if (empty($_SESSION['akun_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $tujuan = ($_SESSION['role'] ?? '') === 'pembuat_soal'
        ? "../Dashboard/PembuatSoal.php"
        : (($_SESSION['role'] ?? '') === 'user' ? "../Dashboard/Beranda.php" : "../navbar.html?error=auth");
    header("Location: " . $tujuan);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Allow: POST");
    http_response_code(405);
    exit("Metode tidak diizinkan.");
}

require_once __DIR__ . "/koneksi.php";
require_once __DIR__ . "/akun.php";

function alihkanAdmin($status, $search)
{
    $query = array('notice' => $status);
    if ($search !== '') {
        $query['user_q'] = $search;
    }

    header("Location: ../Dashboard/Admin.php?" . http_build_query($query) . "#pengguna");
    exit();
}

$adminId = (int) $_SESSION['akun_id'];
$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
$token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
$search = isset($_POST['user_q']) && is_string($_POST['user_q']) ? trim($_POST['user_q']) : '';
$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    alihkanAdmin('csrf', $search);
}

if ($action !== 'change_role') {
    alihkanAdmin('invalid_action', $search);
}

$targetId = isset($_POST['user_id']) ? filter_var($_POST['user_id'], FILTER_VALIDATE_INT) : false;
$newRole = isset($_POST['role']) && is_string($_POST['role']) ? $_POST['role'] : '';
if ($targetId === false || $targetId <= 0) {
    alihkanAdmin('invalid_user', $search);
}
if (!in_array($newRole, array('user', 'pembuat_soal'), true)) {
    alihkanAdmin('invalid_role', $search);
}
if ((int) $targetId === $adminId) {
    alihkanAdmin('self_role', $search);
}

$transactionStarted = false;
try {
    mysqli_begin_transaction($conn);
    $transactionStarted = true;

    $stmtCurrent = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
    if (!$stmtCurrent) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmtCurrent, "i", $targetId);
    mysqli_stmt_execute($stmtCurrent);
    mysqli_stmt_bind_result($stmtCurrent, $currentRole);
    $userFound = mysqli_stmt_fetch($stmtCurrent);
    mysqli_stmt_close($stmtCurrent);

    if (!$userFound) {
        mysqli_rollback($conn);
        $transactionStarted = false;
        alihkanAdmin('user_not_found', $search);
    }
    if ($currentRole === 'admin') {
        mysqli_rollback($conn);
        $transactionStarted = false;
        alihkanAdmin('admin_role_locked', $search);
    }
    if ($currentRole === $newRole) {
        mysqli_rollback($conn);
        $transactionStarted = false;
        alihkanAdmin('role_unchanged', $search);
    }

    $stmtUpdate = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE id = ? AND role <> 'admin'");
    if (!$stmtUpdate) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmtUpdate, "si", $newRole, $targetId);
    mysqli_stmt_execute($stmtUpdate);
    $updatedRows = mysqli_stmt_affected_rows($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);

    if ($updatedRows !== 1) {
        throw new RuntimeException("Role akun tidak berhasil diperbarui.");
    }

    $detail = 'Mengubah role akun #' . (int) $targetId . ' dari ' . $currentRole . ' menjadi ' . $newRole . '.';
    if (!catatLogAktivitas($conn, $adminId, 'role_ubah', $detail)) {
        throw new RuntimeException("Log perubahan role tidak dapat disimpan.");
    }

    mysqli_commit($conn);
    $transactionStarted = false;
    alihkanAdmin('role_changed', $search);
} catch (mysqli_sql_exception $exception) {
    if ($transactionStarted) {
        mysqli_rollback($conn);
    }
    error_log("Gagal mengubah role akun dari dashboard admin: " . $exception->getMessage());
    alihkanAdmin('database', $search);
} catch (RuntimeException $exception) {
    if ($transactionStarted) {
        mysqli_rollback($conn);
    }
    error_log("Perubahan role akun gagal: " . $exception->getMessage());
    alihkanAdmin('database', $search);
}
