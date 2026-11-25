<?php

session_start();

if (empty($_SESSION['akun_id'])) {
    header("Location: ../navbar.html?error=auth");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'pembuat_soal') {
    $tujuan = ($_SESSION['role'] ?? '') === 'admin'
        ? "../Dashboard/Admin.php"
        : "../Dashboard/Beranda.php";
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

function kembaliKeBankSoal($status, $filter, $search, $editId = 0)
{
    $parameter = array(
        'status' => $filter,
        'q' => $search,
        'notice' => $status
    );

    if ($editId > 0) {
        $parameter['edit'] = $editId;
    }

    header("Location: ../Dashboard/PembuatSoal.php?" . http_build_query($parameter) . "#bank-soal");
    exit();
}

function panjangTeksSoal($nilai)
{
    return function_exists('mb_strlen') ? mb_strlen($nilai, 'UTF-8') : strlen($nilai);
}

$creatorId = (int) $_SESSION['akun_id'];
$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
$filter = isset($_POST['filter']) && in_array($_POST['filter'], array('semua', 'aktif', 'arsip'), true)
    ? $_POST['filter']
    : 'semua';
$search = isset($_POST['q']) && is_string($_POST['q']) ? trim($_POST['q']) : '';
$token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    kembaliKeBankSoal('csrf', $filter, $search);
}

$soalId = isset($_POST['soal_id']) ? filter_var($_POST['soal_id'], FILTER_VALIDATE_INT) : false;
$soalId = $soalId !== false && $soalId > 0 ? (int) $soalId : 0;

if (!in_array($action, array('create', 'update', 'archive', 'activate'), true)) {
    kembaliKeBankSoal('invalid', $filter, $search);
}

if ($action !== 'create' && $soalId === 0) {
    kembaliKeBankSoal('invalid', $filter, $search);
}

$editId = $action === 'update' ? $soalId : 0;

if ($action === 'create' || $action === 'update') {
    $pertanyaan = isset($_POST['pertanyaan']) && is_string($_POST['pertanyaan'])
        ? trim($_POST['pertanyaan'])
        : '';
    $tipeSoal = isset($_POST['tipe_soal']) && is_string($_POST['tipe_soal'])
        ? $_POST['tipe_soal']
        : 'pilihan_ganda';
    $opsiA = isset($_POST['opsi_a']) && is_string($_POST['opsi_a']) ? trim($_POST['opsi_a']) : '';
    $opsiB = isset($_POST['opsi_b']) && is_string($_POST['opsi_b']) ? trim($_POST['opsi_b']) : '';
    $opsiC = isset($_POST['opsi_c']) && is_string($_POST['opsi_c']) ? trim($_POST['opsi_c']) : '';
    $opsiD = isset($_POST['opsi_d']) && is_string($_POST['opsi_d']) ? trim($_POST['opsi_d']) : '';
    $jawabanEsai = isset($_POST['jawaban_esai']) && is_string($_POST['jawaban_esai'])
        ? trim($_POST['jawaban_esai'])
        : '';
    $kunci = isset($_POST['kunci_jawaban']) && is_string($_POST['kunci_jawaban'])
        ? strtoupper(trim($_POST['kunci_jawaban']))
        : '';
    $isPilihanGanda = $tipeSoal === 'pilihan_ganda';

    if (
        $pertanyaan === '' ||
        panjangTeksSoal($pertanyaan) > 5000 ||
        !in_array($tipeSoal, array('pilihan_ganda', 'esai'), true) ||
        ($isPilihanGanda && (
            $opsiA === '' || $opsiB === '' || $opsiC === '' || $opsiD === '' ||
            panjangTeksSoal($opsiA) > 500 || panjangTeksSoal($opsiB) > 500 ||
            panjangTeksSoal($opsiC) > 500 || panjangTeksSoal($opsiD) > 500 ||
            !in_array($kunci, array('A', 'B', 'C', 'D'), true)
        )) ||
        (!$isPilihanGanda && ($jawabanEsai === '' || panjangTeksSoal($jawabanEsai) > 255))
    ) {
        kembaliKeBankSoal('invalid', $filter, $search, $editId);
    }

    if (!$isPilihanGanda) {
        $opsiA = '';
        $opsiB = '';
        $opsiC = '';
        $opsiD = '';
        $kunci = 'A';
    } else {
        $jawabanEsai = null;
    }
}

try {
    mysqli_begin_transaction($conn);

    if ($action === 'create') {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO soal
             (pembuat_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban,
              tipe_soal, jawaban_esai)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "issssssss",
            $creatorId,
            $pertanyaan,
            $opsiA,
            $opsiB,
            $opsiC,
            $opsiD,
            $kunci,
            $tipeSoal,
            $jawabanEsai
        );
        mysqli_stmt_execute($stmt);
        $soalIdBaru = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $aktivitas = 'soal_tambah';
        $detail = 'Menambahkan soal #' . $soalIdBaru . '.';
        $editId = 0;
        $statusSukses = 'created';
    } elseif ($action === 'update') {
        if ($isPilihanGanda) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE soal
                 SET pertanyaan = ?, opsi_a = ?, opsi_b = ?, opsi_c = ?, opsi_d = ?,
                     kunci_jawaban = ?, tipe_soal = ?, jawaban_esai = ?
                 WHERE id = ? AND pembuat_id = ?"
            );
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE soal
                 SET pertanyaan = ?, tipe_soal = ?, jawaban_esai = ?
                 WHERE id = ? AND pembuat_id = ?"
            );
        }
        if (!$stmt) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }

        if ($isPilihanGanda) {
            mysqli_stmt_bind_param(
                $stmt,
                "ssssssssii",
                $pertanyaan,
                $opsiA,
                $opsiB,
                $opsiC,
                $opsiD,
                $kunci,
                $tipeSoal,
                $jawabanEsai,
                $soalId,
                $creatorId
            );
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                "sssii",
                $pertanyaan,
                $tipeSoal,
                $jawabanEsai,
                $soalId,
                $creatorId
            );
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmtCek = mysqli_prepare($conn, "SELECT id FROM soal WHERE id = ? AND pembuat_id = ? LIMIT 1");
        if (!$stmtCek) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtCek, "ii", $soalId, $creatorId);
        mysqli_stmt_execute($stmtCek);
        mysqli_stmt_store_result($stmtCek);
        $ditemukan = mysqli_stmt_num_rows($stmtCek) > 0;
        mysqli_stmt_close($stmtCek);

        if (!$ditemukan) {
            mysqli_rollback($conn);
            kembaliKeBankSoal('notfound', $filter, $search);
        }

        $aktivitas = 'soal_edit';
        $detail = 'Mengubah soal #' . $soalId . '.';
        $statusSukses = 'updated';
    } else {
        $aktifBaru = $action === 'activate' ? 1 : 0;
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE soal SET aktif = ? WHERE id = ? AND pembuat_id = ?"
        );
        if (!$stmt) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, "iii", $aktifBaru, $soalId, $creatorId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmtCek = mysqli_prepare($conn, "SELECT id FROM soal WHERE id = ? AND pembuat_id = ? LIMIT 1");
        if (!$stmtCek) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtCek, "ii", $soalId, $creatorId);
        mysqli_stmt_execute($stmtCek);
        mysqli_stmt_store_result($stmtCek);
        $ditemukan = mysqli_stmt_num_rows($stmtCek) > 0;
        mysqli_stmt_close($stmtCek);

        if (!$ditemukan) {
            mysqli_rollback($conn);
            kembaliKeBankSoal('notfound', $filter, $search);
        }

        $aktivitas = $action === 'activate' ? 'soal_aktifkan' : 'soal_arsipkan';
        $detail = ($action === 'activate' ? 'Mengaktifkan kembali soal #' : 'Mengarsipkan soal #') . $soalId . '.';
        $statusSukses = $action === 'activate' ? 'activated' : 'archived';
    }

    if (!catatLogAktivitas($conn, $creatorId, $aktivitas, $detail)) {
        throw new RuntimeException('Log aktivitas soal tidak dapat disimpan.');
    }

    mysqli_commit($conn);
    kembaliKeBankSoal($statusSukses, $filter, $search, $editId);
} catch (mysqli_sql_exception $exception) {
    mysqli_rollback($conn);
    error_log("Gagal menyimpan perubahan soal: " . $exception->getMessage());
    kembaliKeBankSoal('database', $filter, $search, $editId);
} catch (RuntimeException $exception) {
    mysqli_rollback($conn);
    error_log("Gagal mencatat aktivitas pengelolaan soal: " . $exception->getMessage());
    kembaliKeBankSoal('database', $filter, $search, $editId);
}
