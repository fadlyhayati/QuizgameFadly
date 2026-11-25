<?php

ini_set('display_errors', '0');
ob_start();
session_start();

$acceptHeader = isset($_SERVER['HTTP_ACCEPT']) && is_string($_SERVER['HTTP_ACCEPT'])
    ? $_SERVER['HTTP_ACCEPT']
    : '';
$isAjax = (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || strpos($acceptHeader, 'application/json') !== false;

function kuisJsonResponse($status, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store");
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo $json === false ? '{"error":"Respons server tidak dapat diproses."}' : $json;
    exit();
}

function kuisAlihkan($halaman, $notice, $id = 0)
{
    $parameter = array('notice' => $notice);
    if ($id > 0) {
        $parameter['sesi'] = $id;
    }

    header("Location: ../Dashboard/" . $halaman . "?" . http_build_query($parameter));
    exit();
}

function kuisAmbilIdPositif($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false && $id > 0 ? (int) $id : 0;
}

function kuisPenggunaSudahBerkelompok($conn, $userId)
{
    $stmt = mysqli_prepare($conn, "SELECT kelompok_id FROM kelompok_anggota WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $sudahAda = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $sudahAda;
}

if ($isAjax) {
    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    register_shutdown_function(function () {
        $error = error_get_last();
        if (
            $error &&
            in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)
        ) {
            error_log('Operasi AJAX kuis mengalami kesalahan fatal: ' . $error['message']);
            kuisJsonResponse(500, array('error' => 'Permintaan kuis belum dapat diproses. Muat ulang halaman.'));
        }
    });
}

$userId = (int) ($_SESSION['akun_id'] ?? 0);
$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
$sessionId = 0;
$groupTransactionStarted = false;
$conn = null;

try {
if (empty($_SESSION['akun_id'])) {
    if ($isAjax) {
        kuisJsonResponse(403, array('error' => 'Sesi pemain tidak valid. Silakan masuk kembali.'));
    }
    header("Location: ../navbar.html?error=auth");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'user') {
    if ($isAjax) {
        kuisJsonResponse(403, array('error' => 'Akun ini tidak memiliki akses ke sesi kuis.'));
    }
    $tujuan = ($_SESSION['role'] ?? '') === 'admin'
        ? "../Dashboard/Admin.php"
        : "../Dashboard/PembuatSoal.php";
    header("Location: " . $tujuan);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Allow: POST");
    if ($isAjax) {
        kuisJsonResponse(405, array('error' => 'Metode tidak diizinkan.'));
    }
    http_response_code(405);
    exit("Metode tidak diizinkan.");
}

require_once __DIR__ . "/koneksi.php";
require_once __DIR__ . "/akun.php";
require_once __DIR__ . "/kuis-common.php";

if (!siapkanTabelPesertaKuis($conn)) {
    if ($isAjax) {
        kuisJsonResponse(500, array('error' => 'Tabel peserta kuis belum siap. Periksa database Quiz Game.'));
    }
    http_response_code(500);
    exit("Tabel peserta kuis belum siap. Periksa database Quiz Game.");
}

$csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {
    if ($isAjax) {
        kuisJsonResponse(403, array('error' => 'Token keamanan tidak valid. Muat ulang halaman.'));
    }
    kuisAlihkan('Kelompok.php', 'csrf');
}

    if ($action === 'create_group') {
        $namaKelompok = isset($_POST['nama_kelompok']) && is_string($_POST['nama_kelompok'])
            ? trim($_POST['nama_kelompok'])
            : '';
        $panjangNama = function_exists('mb_strlen')
            ? mb_strlen($namaKelompok, 'UTF-8')
            : strlen($namaKelompok);

        if ($namaKelompok === '' || $panjangNama > 120) {
            kuisAlihkan('Kelompok.php', 'invalid_group');
        }

        if (kuisPenggunaSudahBerkelompok($conn, $userId)) {
            kuisAlihkan('Kelompok.php', 'already_group');
        }

        mysqli_begin_transaction($conn);
        $kodeGabung = '';
        $karakterKode = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($percobaan = 0; $percobaan < 8; $percobaan++) {
            $kodeGabung = '';
            for ($i = 0; $i < 8; $i++) {
                $kodeGabung .= $karakterKode[random_int(0, strlen($karakterKode) - 1)];
            }

            $stmtCekKode = mysqli_prepare($conn, "SELECT id FROM kelompok WHERE kode_gabung = ? LIMIT 1");
            if (!$stmtCekKode) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtCekKode, "s", $kodeGabung);
            mysqli_stmt_execute($stmtCekKode);
            mysqli_stmt_store_result($stmtCekKode);
            $kodeDipakai = mysqli_stmt_num_rows($stmtCekKode) > 0;
            mysqli_stmt_close($stmtCekKode);

            if (!$kodeDipakai) {
                break;
            }
            $kodeGabung = '';
        }

        if ($kodeGabung === '') {
            throw new RuntimeException("Kode kelompok unik tidak dapat dibuat.");
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO kelompok (nama_kelompok, kode_gabung, dibuat_oleh) VALUES (?, ?, ?)"
        );
        if (!$stmt) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "ssi", $namaKelompok, $kodeGabung, $userId);
        mysqli_stmt_execute($stmt);
        $groupId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $stmtAnggota = mysqli_prepare(
            $conn,
            "INSERT INTO kelompok_anggota (kelompok_id, user_id) VALUES (?, ?)"
        );
        if (!$stmtAnggota) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtAnggota, "ii", $groupId, $userId);
        mysqli_stmt_execute($stmtAnggota);
        mysqli_stmt_close($stmtAnggota);

        if (!catatLogAktivitas($conn, $userId, 'kelompok_buat', 'Membuat kelompok ' . $namaKelompok . '.')) {
            throw new RuntimeException("Log pembuatan kelompok tidak dapat disimpan.");
        }

        mysqli_commit($conn);
        kuisAlihkan('Kelompok.php', 'group_created');
    }

    if ($action === 'join_group' || $action === 'join_code') {
        if (kuisPenggunaSudahBerkelompok($conn, $userId)) {
            kuisAlihkan('Kelompok.php', 'already_group');
        }

        $groupId = 0;
        if ($action === 'join_group') {
            $groupId = kuisAmbilIdPositif($_POST['kelompok_id'] ?? null);
            if ($groupId === 0) {
                kuisAlihkan('Kelompok.php', 'group_not_found');
            }
            $stmtGroup = mysqli_prepare($conn, "SELECT nama_kelompok FROM kelompok WHERE id = ? LIMIT 1");
            if (!$stmtGroup) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtGroup, "i", $groupId);
        } else {
            $kodeGabung = isset($_POST['kode_gabung']) && is_string($_POST['kode_gabung'])
                ? strtoupper(trim($_POST['kode_gabung']))
                : '';
            if (!preg_match('/^[A-HJ-NP-Z2-9]{8}$/', $kodeGabung)) {
                kuisAlihkan('Kelompok.php', 'group_not_found');
            }
            $stmtGroup = mysqli_prepare($conn, "SELECT id, nama_kelompok FROM kelompok WHERE kode_gabung = ? LIMIT 1");
            if (!$stmtGroup) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtGroup, "s", $kodeGabung);
        }

        mysqli_stmt_execute($stmtGroup);
        if ($action === 'join_group') {
            mysqli_stmt_bind_result($stmtGroup, $namaKelompok);
            $adaKelompok = mysqli_stmt_fetch($stmtGroup);
        } else {
            mysqli_stmt_bind_result($stmtGroup, $groupId, $namaKelompok);
            $adaKelompok = mysqli_stmt_fetch($stmtGroup);
            $groupId = $adaKelompok ? (int) $groupId : 0;
        }
        mysqli_stmt_close($stmtGroup);

        if (!$adaKelompok) {
            kuisAlihkan('Kelompok.php', 'group_not_found');
        }

        mysqli_begin_transaction($conn);
        $stmtJoin = mysqli_prepare(
            $conn,
            "INSERT INTO kelompok_anggota (kelompok_id, user_id) VALUES (?, ?)"
        );
        if (!$stmtJoin) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtJoin, "ii", $groupId, $userId);
        mysqli_stmt_execute($stmtJoin);
        mysqli_stmt_close($stmtJoin);

        if (!catatLogAktivitas($conn, $userId, 'kelompok_gabung', 'Bergabung ke kelompok ' . $namaKelompok . '.')) {
            throw new RuntimeException("Log bergabung kelompok tidak dapat disimpan.");
        }

        mysqli_commit($conn);
        kuisAlihkan('Kelompok.php', 'group_joined');
    }

    if ($action === 'start_group_quiz' || $action === 'start_solo_quiz') {
        $groupId = null;
        if ($action === 'start_group_quiz') {
            $groupId = kuisAmbilIdPositif($_POST['kelompok_id'] ?? null);
            if ($groupId === 0) {
                kuisAlihkan('Kelompok.php', 'not_in_group');
            }

            $stmtMilik = mysqli_prepare(
                $conn,
                "SELECT kelompok_id FROM kelompok_anggota WHERE kelompok_id = ? AND user_id = ? LIMIT 1"
            );
            if (!$stmtMilik) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtMilik, "ii", $groupId, $userId);
            mysqli_stmt_execute($stmtMilik);
            mysqli_stmt_store_result($stmtMilik);
            $anggotaKelompok = mysqli_stmt_num_rows($stmtMilik) > 0;
            mysqli_stmt_close($stmtMilik);
            if (!$anggotaKelompok) {
                kuisAlihkan('Kelompok.php', 'not_in_group');
            }

            mysqli_begin_transaction($conn);
            $groupTransactionStarted = true;
            $stmtGroupLock = mysqli_prepare($conn, "SELECT id FROM kelompok WHERE id = ? FOR UPDATE");
            if (!$stmtGroupLock) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtGroupLock, "i", $groupId);
            mysqli_stmt_execute($stmtGroupLock);
            mysqli_stmt_store_result($stmtGroupLock);
            $groupExists = mysqli_stmt_num_rows($stmtGroupLock) > 0;
            mysqli_stmt_close($stmtGroupLock);
            if (!$groupExists) {
                throw new RuntimeException("Kelompok tidak ditemukan.");
            }

            $stmtActiveSession = mysqli_prepare(
                $conn,
                "SELECT id FROM sesi_kuis
                 WHERE kelompok_id = ? AND status = 'berlangsung'
                   AND (batas_waktu IS NULL OR batas_waktu > CURRENT_TIMESTAMP)
                 ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );
            if (!$stmtActiveSession) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtActiveSession, "i", $groupId);
            mysqli_stmt_execute($stmtActiveSession);
            mysqli_stmt_bind_result($stmtActiveSession, $activeSessionId);
            $hasActiveSession = mysqli_stmt_fetch($stmtActiveSession);
            mysqli_stmt_close($stmtActiveSession);
            if ($hasActiveSession) {
                $sessionId = (int) $activeSessionId;
                $stmtExistingParticipant = mysqli_prepare(
                    $conn,
                    "SELECT status FROM sesi_kuis_peserta WHERE sesi_kuis_id = ? AND user_id = ? LIMIT 1"
                );
                if (!$stmtExistingParticipant) {
                    throw new mysqli_sql_exception(mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmtExistingParticipant, "ii", $sessionId, $userId);
                mysqli_stmt_execute($stmtExistingParticipant);
                mysqli_stmt_bind_result($stmtExistingParticipant, $existingParticipantStatus);
                $participantAlreadyPresent = mysqli_stmt_fetch($stmtExistingParticipant);
                mysqli_stmt_close($stmtExistingParticipant);
                if ($participantAlreadyPresent && $existingParticipantStatus === 'selesai') {
                    mysqli_commit($conn);
                    kuisAlihkan('Hasil.php', 'session_done', $sessionId);
                }
                if (!$participantAlreadyPresent) {
                    $stmtAddParticipant = mysqli_prepare(
                        $conn,
                        "INSERT INTO sesi_kuis_peserta (sesi_kuis_id, user_id) VALUES (?, ?)"
                    );
                    if (!$stmtAddParticipant) {
                        throw new mysqli_sql_exception(mysqli_error($conn));
                    }
                    mysqli_stmt_bind_param($stmtAddParticipant, "ii", $sessionId, $userId);
                    mysqli_stmt_execute($stmtAddParticipant);
                    mysqli_stmt_close($stmtAddParticipant);
                }
                if (!catatLogAktivitas($conn, $userId, 'kuis_mulai', 'Bergabung otomatis ke sesi kelompok #' . $groupId . '.')) {
                    throw new RuntimeException("Log bergabung ke sesi tidak dapat disimpan.");
                }
                mysqli_commit($conn);
                kuisAlihkan('Kuis.php', 'joined', $sessionId);
            }
        }

        $soalTerpilih = array();
        $hasilSoal = mysqli_query(
            $conn,
            "SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban,
                    tipe_soal, jawaban_esai
             FROM soal
             WHERE aktif = 1
             ORDER BY RAND()
             LIMIT 25"
        );
        if (!$hasilSoal) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        while ($barisSoal = mysqli_fetch_assoc($hasilSoal)) {
            $soalTerpilih[] = $barisSoal;
        }
        mysqli_free_result($hasilSoal);

        if (empty($soalTerpilih)) {
            if ($groupTransactionStarted) {
                mysqli_rollback($conn);
            }
            kuisAlihkan('Kelompok.php', 'no_questions');
        }

        if (!$groupTransactionStarted) {
            mysqli_begin_transaction($conn);
        }
        $stmtSesi = mysqli_prepare(
            $conn,
            $groupId === null
                ? "INSERT INTO sesi_kuis (kelompok_id, dimulai_oleh, status, durasi_detik, batas_waktu)
                   VALUES (NULL, ?, 'berlangsung', 600, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 600 SECOND))"
                : "INSERT INTO sesi_kuis (kelompok_id, dimulai_oleh, status, durasi_detik, batas_waktu)
                   VALUES (?, ?, 'berlangsung', 600, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 600 SECOND))"
        );
        if (!$stmtSesi) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        if ($groupId === null) {
            mysqli_stmt_bind_param($stmtSesi, "i", $userId);
        } else {
            mysqli_stmt_bind_param($stmtSesi, "ii", $groupId, $userId);
        }
        mysqli_stmt_execute($stmtSesi);
        $sessionId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmtSesi);

        $stmtSnapshot = mysqli_prepare(
            $conn,
            "INSERT INTO sesi_kuis_soal
             (sesi_kuis_id, soal_id, urutan, pertanyaan_snapshot,
              opsi_a_snapshot, opsi_b_snapshot, opsi_c_snapshot, opsi_d_snapshot, kunci_snapshot,
              tipe_soal_snapshot, jawaban_esai_snapshot)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmtSnapshot) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        foreach ($soalTerpilih as $index => $soal) {
            $questionId = (int) $soal['id'];
            $order = $index + 1;
            mysqli_stmt_bind_param(
                $stmtSnapshot,
                "iiissssssss",
                $sessionId,
                $questionId,
                $order,
                $soal['pertanyaan'],
                $soal['opsi_a'],
                $soal['opsi_b'],
                $soal['opsi_c'],
                $soal['opsi_d'],
                $soal['kunci_jawaban'],
                $soal['tipe_soal'],
                $soal['jawaban_esai']
            );
            mysqli_stmt_execute($stmtSnapshot);
        }
        mysqli_stmt_close($stmtSnapshot);

        $stmtPeserta = mysqli_prepare(
            $conn,
            "INSERT INTO sesi_kuis_peserta (sesi_kuis_id, user_id) VALUES (?, ?)"
        );
        if (!$stmtPeserta) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtPeserta, "ii", $sessionId, $userId);
        mysqli_stmt_execute($stmtPeserta);
        mysqli_stmt_close($stmtPeserta);

        $sessionDescription = $groupId === null
            ? 'Memulai sesi kuis Individu (Solo) #' . $sessionId . '.'
            : 'Memulai sesi kuis kelompok #' . $groupId . '.';
        if (!catatLogAktivitas($conn, $userId, 'kuis_mulai', $sessionDescription)) {
            throw new RuntimeException("Log mulai kuis tidak dapat disimpan.");
        }

        mysqli_commit($conn);
        kuisAlihkan('Kuis.php', 'started', $sessionId);
    }

    if ($action === 'join_session') {
        $sessionId = kuisAmbilIdPositif($_POST['sesi_kuis_id'] ?? null);
        if ($sessionId === 0) {
            kuisAlihkan('Kelompok.php', 'session_unavailable');
        }

        mysqli_begin_transaction($conn);
        $stmtSession = mysqli_prepare(
            $conn,
            "SELECT s.kelompok_id, s.status
             FROM sesi_kuis s
             JOIN kelompok_anggota ka ON ka.kelompok_id = s.kelompok_id
             WHERE s.id = ? AND ka.user_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        if (!$stmtSession) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtSession, "ii", $sessionId, $userId);
        mysqli_stmt_execute($stmtSession);
        mysqli_stmt_bind_result($stmtSession, $groupId, $sessionStatus);
        $sessionFound = mysqli_stmt_fetch($stmtSession);
        mysqli_stmt_close($stmtSession);

        if (!$sessionFound) {
            mysqli_rollback($conn);
            kuisAlihkan('Kelompok.php', 'session_unavailable');
        }

        $stmtParticipant = mysqli_prepare(
            $conn,
            "SELECT status FROM sesi_kuis_peserta WHERE sesi_kuis_id = ? AND user_id = ? LIMIT 1"
        );
        if (!$stmtParticipant) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtParticipant, "ii", $sessionId, $userId);
        mysqli_stmt_execute($stmtParticipant);
        mysqli_stmt_bind_result($stmtParticipant, $participantStatus);
        $alreadyJoined = mysqli_stmt_fetch($stmtParticipant);
        mysqli_stmt_close($stmtParticipant);

        if ($sessionStatus !== 'berlangsung') {
            mysqli_rollback($conn);
            if ($alreadyJoined && $participantStatus === 'selesai') {
                kuisAlihkan('Hasil.php', 'session_done', $sessionId);
            }
            kuisAlihkan('Kelompok.php', 'session_unavailable');
        }

        if (!$alreadyJoined) {
            $stmtJoinSession = mysqli_prepare(
                $conn,
                "INSERT INTO sesi_kuis_peserta (sesi_kuis_id, user_id) VALUES (?, ?)"
            );
            if (!$stmtJoinSession) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtJoinSession, "ii", $sessionId, $userId);
            mysqli_stmt_execute($stmtJoinSession);
            mysqli_stmt_close($stmtJoinSession);

            if (!catatLogAktivitas($conn, $userId, 'kuis_mulai', 'Bergabung ke sesi kuis kelompok #' . $groupId . '.')) {
                throw new RuntimeException("Log bergabung kuis tidak dapat disimpan.");
            }
        }

        mysqli_commit($conn);
        if ($alreadyJoined && $participantStatus === 'selesai') {
            kuisAlihkan('Hasil.php', 'session_done', $sessionId);
        }
        kuisAlihkan('Kuis.php', 'joined', $sessionId);
    }

    if ($action === 'question_timer') {
        $sessionId = kuisAmbilIdPositif($_POST['sesi_kuis_id'] ?? null);
        $questionId = kuisAmbilIdPositif($_POST['soal_id'] ?? null);
        if ($sessionId === 0 || $questionId === 0) {
            kuisJsonResponse(422, array('error' => 'Soal atau sesi tidak valid.'));
        }

        $stmtTimerQuestion = mysqli_prepare(
            $conn,
            "SELECT s.kelompok_id, s.status,
                    TIMESTAMPDIFF(SECOND, CURRENT_TIMESTAMP, s.batas_waktu),
                    sq.tipe_soal_snapshot
             FROM sesi_kuis s
             JOIN sesi_kuis_peserta p ON p.sesi_kuis_id = s.id AND p.user_id = ?
             JOIN sesi_kuis_soal sq ON sq.sesi_kuis_id = s.id AND sq.soal_id = ?
             WHERE s.id = ? LIMIT 1"
        );
        if (!$stmtTimerQuestion) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtTimerQuestion, "iii", $userId, $questionId, $sessionId);
        mysqli_stmt_execute($stmtTimerQuestion);
        mysqli_stmt_bind_result($stmtTimerQuestion, $timerGroupId, $timerSessionStatus, $timerSessionRemaining, $timerQuestionType);
        $timerQuestionFound = mysqli_stmt_fetch($stmtTimerQuestion);
        mysqli_stmt_close($stmtTimerQuestion);
        if (!$timerQuestionFound || $timerSessionStatus !== 'berlangsung') {
            kuisJsonResponse(409, array('error' => 'Sesi atau soal sudah tidak aktif.'));
        }
        if ($timerSessionRemaining !== null && (int) $timerSessionRemaining <= 0) {
            kuisJsonResponse(409, array('error' => 'Waktu sesi sudah habis.', 'completed' => true));
        }

        $stmtAnswered = mysqli_prepare(
            $conn,
            "SELECT j.user_id, COALESCE(NULLIF(u.nama_panggilan, ''), u.nama_lengkap)
             FROM jawaban_user j
             JOIN users u ON u.id = j.user_id
             WHERE j.sesi_kuis_id = ? AND j.soal_id = ?
               AND (? = 1 OR j.user_id = ?)
             ORDER BY j.id ASC LIMIT 1"
        );
        if (!$stmtAnswered) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        $isSharedSession = $timerGroupId !== null ? 1 : 0;
        mysqli_stmt_bind_param($stmtAnswered, "iiii", $sessionId, $questionId, $isSharedSession, $userId);
        mysqli_stmt_execute($stmtAnswered);
        mysqli_stmt_bind_result($stmtAnswered, $answeredBy, $answeredByName);
        $timerAlreadyAnswered = mysqli_stmt_fetch($stmtAnswered);
        mysqli_stmt_close($stmtAnswered);
        if ($timerAlreadyAnswered) {
            kuisJsonResponse(200, array(
                'success' => true,
                'answered' => true,
                'answered_by_name' => $answeredByName
            ));
        }

        $duration = $timerQuestionType === 'esai' ? 45 : 30;
        $stmtStartTimer = mysqli_prepare(
            $conn,
            "INSERT IGNORE INTO timer_soal (sesi_kuis_id, soal_id, user_id, dibuka_pada)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP)"
        );
        if (!$stmtStartTimer) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtStartTimer, "iii", $sessionId, $questionId, $userId);
        mysqli_stmt_execute($stmtStartTimer);
        mysqli_stmt_close($stmtStartTimer);

        $stmtRemaining = mysqli_prepare(
            $conn,
            "SELECT GREATEST(0, ? - TIMESTAMPDIFF(SECOND, dibuka_pada, CURRENT_TIMESTAMP))
             FROM timer_soal WHERE sesi_kuis_id = ? AND soal_id = ? AND user_id = ? LIMIT 1"
        );
        if (!$stmtRemaining) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtRemaining, "iiii", $duration, $sessionId, $questionId, $userId);
        mysqli_stmt_execute($stmtRemaining);
        mysqli_stmt_bind_result($stmtRemaining, $remaining);
        mysqli_stmt_fetch($stmtRemaining);
        mysqli_stmt_close($stmtRemaining);
        kuisJsonResponse(200, array(
            'success' => true,
            'answered' => false,
            'duration' => $duration,
            'remaining' => (int) $remaining
        ));
    }

    if ($action === 'finish_session') {
        $sessionId = kuisAmbilIdPositif($_POST['sesi_kuis_id'] ?? null);
        if ($sessionId === 0) {
            kuisJsonResponse(422, array('error' => 'Sesi tidak valid.'));
        }
        mysqli_begin_transaction($conn);
        $stmtFinishSessionState = mysqli_prepare(
            $conn,
            "SELECT status, TIMESTAMPDIFF(SECOND, CURRENT_TIMESTAMP, batas_waktu), kelompok_id
             FROM sesi_kuis WHERE id = ? LIMIT 1 FOR UPDATE"
        );
        if (!$stmtFinishSessionState) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtFinishSessionState, "i", $sessionId);
        mysqli_stmt_execute($stmtFinishSessionState);
        mysqli_stmt_bind_result($stmtFinishSessionState, $finishSessionStatus, $finishRemaining, $finishGroupId);
        $finishSessionExists = mysqli_stmt_fetch($stmtFinishSessionState);
        mysqli_stmt_close($stmtFinishSessionState);
        if (!$finishSessionExists) {
            mysqli_rollback($conn);
            kuisJsonResponse(404, array('error' => 'Sesi tidak ditemukan.'));
        }
        $stmtFinishProgress = mysqli_prepare(
            $conn,
            "SELECT (SELECT COUNT(*) FROM sesi_kuis_soal WHERE sesi_kuis_id = ?),
                    (SELECT COUNT(DISTINCT soal_id) FROM jawaban_user
                     WHERE sesi_kuis_id = ? AND (? = 1 OR user_id = ?))"
        );
        if (!$stmtFinishProgress) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        $finishSharedFlag = $finishGroupId !== null ? 1 : 0;
        mysqli_stmt_bind_param($stmtFinishProgress, "iiii", $sessionId, $sessionId, $finishSharedFlag, $userId);
        mysqli_stmt_execute($stmtFinishProgress);
        mysqli_stmt_bind_result($stmtFinishProgress, $finishQuestionTotal, $finishAnsweredTotal);
        mysqli_stmt_fetch($stmtFinishProgress);
        mysqli_stmt_close($stmtFinishProgress);
        if (
            $finishSessionStatus === 'berlangsung' &&
            ($finishRemaining === null || (int) $finishRemaining > 0) &&
            ((int) $finishQuestionTotal === 0 || (int) $finishAnsweredTotal < (int) $finishQuestionTotal)
        ) {
            mysqli_rollback($conn);
            kuisJsonResponse(422, array('error' => 'Sesi belum mencapai batas waktu atau seluruh soal belum selesai.'));
        }
        $stmtFinishAccess = mysqli_prepare(
            $conn,
            "SELECT status FROM sesi_kuis_peserta
             WHERE sesi_kuis_id = ? AND user_id = ? LIMIT 1 FOR UPDATE"
        );
        if (!$stmtFinishAccess) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtFinishAccess, "ii", $sessionId, $userId);
        mysqli_stmt_execute($stmtFinishAccess);
        mysqli_stmt_store_result($stmtFinishAccess);
        $canFinishSession = mysqli_stmt_num_rows($stmtFinishAccess) > 0;
        mysqli_stmt_close($stmtFinishAccess);
        if (!$canFinishSession) {
            mysqli_rollback($conn);
            kuisJsonResponse(403, array('error' => 'Anda bukan peserta sesi ini.'));
        }
        $stmtFinishSession = mysqli_prepare(
            $conn,
            "UPDATE sesi_kuis
             SET status = 'selesai', selesai_pada = COALESCE(selesai_pada, CURRENT_TIMESTAMP)
             WHERE id = ? AND status = 'berlangsung'"
        );
        if (!$stmtFinishSession) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtFinishSession, "i", $sessionId);
        mysqli_stmt_execute($stmtFinishSession);
        mysqli_stmt_close($stmtFinishSession);
        $stmtFinishParticipants = mysqli_prepare(
            $conn,
            "UPDATE sesi_kuis_peserta
             SET status = 'selesai', selesai_pada = COALESCE(selesai_pada, CURRENT_TIMESTAMP)
             WHERE sesi_kuis_id = ? AND status = 'berlangsung'"
        );
        if (!$stmtFinishParticipants) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtFinishParticipants, "i", $sessionId);
        mysqli_stmt_execute($stmtFinishParticipants);
        mysqli_stmt_close($stmtFinishParticipants);
        $stmtDeletePositions = mysqli_prepare($conn, "DELETE FROM posisi_pemain WHERE sesi_id = ?");
        if (!$stmtDeletePositions) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtDeletePositions, "i", $sessionId);
        mysqli_stmt_execute($stmtDeletePositions);
        mysqli_stmt_close($stmtDeletePositions);
        mysqli_commit($conn);
        kuisJsonResponse(200, array('success' => true, 'completed' => true));
    }

    if ($action === 'answer') {
        $sessionId = kuisAmbilIdPositif($_POST['sesi_kuis_id'] ?? null);
        $questionId = kuisAmbilIdPositif($_POST['soal_id'] ?? null);
        $answer = isset($_POST['jawaban']) && is_string($_POST['jawaban'])
            ? strtoupper(trim($_POST['jawaban']))
            : '';
        $textAnswer = isset($_POST['jawaban_teks']) && is_string($_POST['jawaban_teks'])
            ? trim($_POST['jawaban_teks'])
            : '';
        $isTimeoutRequest = isset($_POST['timeout']) && $_POST['timeout'] === '1';
        if ($sessionId === 0 || $questionId === 0) {
            if ($isAjax) {
                kuisJsonResponse(422, array('error' => 'Soal atau sesi tidak valid.'));
            }
            kuisAlihkan('Kuis.php', 'invalid_answer', $sessionId);
        }

        mysqli_begin_transaction($conn);
        $stmtSessionLock = mysqli_prepare(
            $conn,
            "SELECT kelompok_id, status,
                    TIMESTAMPDIFF(SECOND, CURRENT_TIMESTAMP, batas_waktu)
             FROM sesi_kuis
             WHERE id = ? LIMIT 1 FOR UPDATE"
        );
        if (!$stmtSessionLock) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtSessionLock, "i", $sessionId);
        mysqli_stmt_execute($stmtSessionLock);
        mysqli_stmt_bind_result($stmtSessionLock, $answerGroupId, $answerSessionStatus, $answerSessionRemaining);
        $answerSessionFound = mysqli_stmt_fetch($stmtSessionLock);
        mysqli_stmt_close($stmtSessionLock);
        if (!$answerSessionFound || $answerSessionStatus !== 'berlangsung') {
            mysqli_rollback($conn);
            kuisJsonResponse(409, array('error' => 'Sesi sudah selesai.', 'completed' => true));
        }
        if ($answerSessionRemaining !== null && (int) $answerSessionRemaining <= 0) {
            mysqli_rollback($conn);
            kuisJsonResponse(409, array('error' => 'Waktu sesi sudah habis.', 'completed' => true));
        }
        $sharedProgress = $answerGroupId !== null;

        $stmtParticipant = mysqli_prepare(
            $conn,
            "SELECT status FROM sesi_kuis_peserta
             WHERE sesi_kuis_id = ? AND user_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        if (!$stmtParticipant) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtParticipant, "ii", $sessionId, $userId);
        mysqli_stmt_execute($stmtParticipant);
        mysqli_stmt_bind_result($stmtParticipant, $participantStatus);
        $participantFound = mysqli_stmt_fetch($stmtParticipant);
        mysqli_stmt_close($stmtParticipant);

        if (!$participantFound) {
            mysqli_rollback($conn);
            if ($isAjax) {
                kuisJsonResponse(403, array('error' => 'Anda bukan peserta sesi ini.'));
            }
            kuisAlihkan('Kelompok.php', 'session_unavailable');
        }
        if ($participantStatus === 'selesai') {
            mysqli_rollback($conn);
            if ($isAjax) {
                kuisJsonResponse(409, array('error' => 'Sesi Anda sudah selesai.', 'completed' => true));
            }
            kuisAlihkan('Hasil.php', 'session_done', $sessionId);
        }

        $stmtExisting = mysqli_prepare(
            $conn,
            "SELECT j.user_id, COALESCE(NULLIF(u.nama_panggilan, ''), u.nama_lengkap)
             FROM jawaban_user j
             JOIN users u ON u.id = j.user_id
             WHERE j.sesi_kuis_id = ? AND j.soal_id = ?
               AND (? = 1 OR j.user_id = ?)
             ORDER BY j.id ASC LIMIT 1"
        );
        if (!$stmtExisting) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        $sharedFlag = $sharedProgress ? 1 : 0;
        mysqli_stmt_bind_param($stmtExisting, "iiii", $sessionId, $questionId, $sharedFlag, $userId);
        mysqli_stmt_execute($stmtExisting);
        mysqli_stmt_bind_result($stmtExisting, $existingAnswerUserId, $existingAnswerUserName);
        $alreadyAnswered = mysqli_stmt_fetch($stmtExisting);
        mysqli_stmt_close($stmtExisting);
        if ($alreadyAnswered) {
            $stmtDuplicateCounts = mysqli_prepare(
                $conn,
                "SELECT COUNT(DISTINCT soal_id), COALESCE(SUM(answer_row.skor), 0),
                        (SELECT COUNT(*) FROM sesi_kuis_soal WHERE sesi_kuis_id = ?)
                 FROM jawaban_user answer_row
                 WHERE answer_row.sesi_kuis_id = ?
                   AND answer_row.id = (
                       SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                       WHERE first_answer.sesi_kuis_id = answer_row.sesi_kuis_id
                         AND first_answer.soal_id = answer_row.soal_id
                   )"
            );
            if (!$stmtDuplicateCounts) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtDuplicateCounts, "ii", $sessionId, $sessionId);
            mysqli_stmt_execute($stmtDuplicateCounts);
            mysqli_stmt_bind_result($stmtDuplicateCounts, $duplicateAnswered, $duplicateScore, $duplicateTotal);
            mysqli_stmt_fetch($stmtDuplicateCounts);
            mysqli_stmt_close($stmtDuplicateCounts);
            mysqli_commit($conn);
            kuisJsonResponse(200, array(
                'success' => true,
                'duplicate' => true,
                'answered_by_name' => $existingAnswerUserName,
                'answered' => (int) $duplicateAnswered,
                'total' => (int) $duplicateTotal,
                'shared_score' => (int) $duplicateScore,
                'completed' => (int) $duplicateTotal > 0 && (int) $duplicateAnswered >= (int) $duplicateTotal
            ));
        }

        $stmtKey = mysqli_prepare(
            $conn,
            "SELECT kunci_snapshot, tipe_soal_snapshot, jawaban_esai_snapshot
             FROM sesi_kuis_soal
             WHERE sesi_kuis_id = ? AND soal_id = ?
             LIMIT 1"
        );
        if (!$stmtKey) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtKey, "ii", $sessionId, $questionId);
        mysqli_stmt_execute($stmtKey);
        mysqli_stmt_bind_result($stmtKey, $answerKey, $questionType, $essayKey);
        $questionFound = mysqli_stmt_fetch($stmtKey);
        mysqli_stmt_close($stmtKey);
        if (!$questionFound) {
            mysqli_rollback($conn);
            if ($isAjax) {
                kuisJsonResponse(404, array('error' => 'Soal tidak ditemukan pada sesi ini.'));
            }
            kuisAlihkan('Kuis.php', 'invalid_answer', $sessionId);
        }

        $questionDuration = $questionType === 'esai' ? 45 : 30;
        $stmtQuestionTimer = mysqli_prepare(
            $conn,
            "SELECT TIMESTAMPDIFF(SECOND, dibuka_pada, CURRENT_TIMESTAMP)
             FROM timer_soal
             WHERE sesi_kuis_id = ? AND soal_id = ? AND user_id = ?
             LIMIT 1"
        );
        if (!$stmtQuestionTimer) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtQuestionTimer, "iii", $sessionId, $questionId, $userId);
        mysqli_stmt_execute($stmtQuestionTimer);
        mysqli_stmt_bind_result($stmtQuestionTimer, $questionElapsed);
        $questionTimerFound = mysqli_stmt_fetch($stmtQuestionTimer);
        mysqli_stmt_close($stmtQuestionTimer);
        if (!$questionTimerFound) {
            mysqli_rollback($conn);
            kuisJsonResponse(409, array('error' => 'Timer soal belum dimulai. Buka kembali soal untuk melanjutkan.'));
        }
        $timedOut = (int) $questionElapsed >= $questionDuration;
        if ($isTimeoutRequest && !$timedOut) {
            mysqli_rollback($conn);
            kuisJsonResponse(422, array('error' => 'Waktu soal belum habis.'));
        }

        if ($timedOut) {
            $answer = 'A';
            $textAnswer = '[WAKTU HABIS]';
            $isCorrect = 0;
        } elseif ($questionType === 'esai') {
            $answerLength = function_exists('mb_strlen')
                ? mb_strlen($textAnswer, 'UTF-8')
                : strlen($textAnswer);
            if ($textAnswer === '' || $answerLength > 255) {
                mysqli_rollback($conn);
                if ($isAjax) {
                    kuisJsonResponse(422, array('error' => 'Jawaban esai wajib diisi dan maksimal 255 karakter.'));
                }
                kuisAlihkan('Kuis.php', 'invalid_answer', $sessionId);
            }

            $acceptedAnswers = array_map('trim', explode(',', (string) $essayKey));
            $normalizedAnswer = function_exists('mb_strtolower')
                ? mb_strtolower($textAnswer, 'UTF-8')
                : strtolower($textAnswer);
            $isCorrect = 0;
            foreach ($acceptedAnswers as $acceptedAnswer) {
                $normalizedAccepted = function_exists('mb_strtolower')
                    ? mb_strtolower($acceptedAnswer, 'UTF-8')
                    : strtolower($acceptedAnswer);
                if ($normalizedAnswer === $normalizedAccepted) {
                    $isCorrect = 1;
                    break;
                }
            }
            $answer = 'A';
        } else {
            if (!in_array($answer, array('A', 'B', 'C', 'D'), true)) {
                mysqli_rollback($conn);
                if ($isAjax) {
                    kuisJsonResponse(422, array('error' => 'Pilih salah satu opsi jawaban.'));
                }
                kuisAlihkan('Kuis.php', 'invalid_answer', $sessionId);
            }
            $textAnswer = null;
            $isCorrect = $answer === $answerKey ? 1 : 0;
        }
        $score = !$timedOut && $isCorrect ? 100 : 0;
        $stmtAnswer = mysqli_prepare(
            $conn,
            "INSERT INTO jawaban_user
             (sesi_kuis_id, soal_id, user_id, jawaban_dipilih, jawaban_teks, benar, skor)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmtAnswer) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param(
            $stmtAnswer,
            "iiissii",
            $sessionId,
            $questionId,
            $userId,
            $answer,
            $textAnswer,
            $isCorrect,
            $score
        );
        mysqli_stmt_execute($stmtAnswer);
        mysqli_stmt_close($stmtAnswer);

        $stmtCount = mysqli_prepare(
            $conn,
            "SELECT
                (SELECT COUNT(*) FROM sesi_kuis_soal WHERE sesi_kuis_id = ?),
                (SELECT COUNT(DISTINCT soal_id) FROM jawaban_user
                 WHERE sesi_kuis_id = ? AND (? = 1 OR user_id = ?)),
                (SELECT COALESCE(SUM(answer_row.skor), 0) FROM jawaban_user answer_row
                 WHERE answer_row.sesi_kuis_id = ?
                   AND (? = 0 AND answer_row.user_id = ? OR ? = 1 AND answer_row.id = (
                       SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                       WHERE first_answer.sesi_kuis_id = answer_row.sesi_kuis_id
                         AND first_answer.soal_id = answer_row.soal_id
                   )))"
        );
        if (!$stmtCount) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        $sharedFlag = $sharedProgress ? 1 : 0;
        mysqli_stmt_bind_param(
            $stmtCount,
            "iiiiiiii",
            $sessionId,
            $sessionId,
            $sharedFlag,
            $userId,
            $sessionId,
            $sharedFlag,
            $userId,
            $sharedFlag
        );
        mysqli_stmt_execute($stmtCount);
        mysqli_stmt_bind_result($stmtCount, $totalQuestions, $totalAnswers, $sharedScore);
        mysqli_stmt_fetch($stmtCount);
        mysqli_stmt_close($stmtCount);

        $completed = (int) $totalQuestions > 0 && (int) $totalAnswers >= (int) $totalQuestions;
        if ($completed) {
            $stmtFinishParticipants = mysqli_prepare(
                $conn,
                "UPDATE sesi_kuis_peserta
                 SET status = 'selesai', selesai_pada = COALESCE(selesai_pada, CURRENT_TIMESTAMP)
                 WHERE sesi_kuis_id = ? AND status = 'berlangsung'"
            );
            if (!$stmtFinishParticipants) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtFinishParticipants, "i", $sessionId);
            mysqli_stmt_execute($stmtFinishParticipants);
            mysqli_stmt_close($stmtFinishParticipants);

            $stmtFinishSession = mysqli_prepare(
                $conn,
                "UPDATE sesi_kuis SET status = 'selesai', selesai_pada = CURRENT_TIMESTAMP
                 WHERE id = ? AND status = 'berlangsung'"
            );
            if (!$stmtFinishSession) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtFinishSession, "i", $sessionId);
            mysqli_stmt_execute($stmtFinishSession);
            mysqli_stmt_close($stmtFinishSession);
            $stmtDeletePositions = mysqli_prepare($conn, "DELETE FROM posisi_pemain WHERE sesi_id = ?");
            if (!$stmtDeletePositions) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtDeletePositions, "i", $sessionId);
            mysqli_stmt_execute($stmtDeletePositions);
            mysqli_stmt_close($stmtDeletePositions);
        }

        mysqli_commit($conn);
        if ($isAjax) {
            kuisJsonResponse(200, array(
                'success' => true,
                'correct' => (bool) $isCorrect,
                'timed_out' => $timedOut,
                'score' => $score,
                'shared_score' => (int) $sharedScore,
                'answered' => (int) $totalAnswers,
                'total' => (int) $totalQuestions,
                'completed' => $completed,
                'result_url' => '../Dashboard/Hasil.php?sesi=' . $sessionId
            ));
        }
        if ($completed) {
            kuisAlihkan('Hasil.php', 'completed', $sessionId);
        }
        kuisAlihkan('Kuis.php', 'answered', $sessionId);
    }

    if ($isAjax) {
        kuisJsonResponse(422, array('error' => 'Permintaan kuis tidak dikenali.'));
    }
    kuisAlihkan('Kelompok.php', 'invalid_action');
} catch (Throwable $exception) {
    if ($conn instanceof mysqli && ($conn->server_status & MYSQLI_SERVER_STATUS_IN_TRANS)) {
        mysqli_rollback($conn);
    }
    error_log("Operasi kuis gagal: " . $exception->getMessage());
    if ($isAjax) {
        $message = $action === 'answer'
            ? 'Jawaban belum dapat disimpan. Coba lagi.'
            : 'Permintaan kuis belum dapat diproses. Muat ulang halaman.';
        kuisJsonResponse(500, array('error' => $message));
    }
    if ($action === 'answer' && $sessionId > 0) {
        kuisAlihkan('Kuis.php', 'database', $sessionId);
    }
    kuisAlihkan('Kelompok.php', 'database');
}
