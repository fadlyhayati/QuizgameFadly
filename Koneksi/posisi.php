<?php

ini_set('display_errors', '0');
ob_start();
session_start();

function posisiJsonResponse($status, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo $json === false ? '{"error":"Respons posisi tidak dapat diproses."}' : $json;
    exit();
}

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
        error_log('Sinkronisasi posisi pemain mengalami kesalahan fatal: ' . $error['message']);
        posisiJsonResponse(500, array('error' => 'Posisi pemain belum dapat disinkronkan.'));
    }
});

$conn = null;
try {
if (empty($_SESSION['akun_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    posisiJsonResponse(403, array('error' => 'Sesi pemain tidak valid.'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    posisiJsonResponse(405, array('error' => 'Metode tidak diizinkan.'));
}

$userId = (int) $_SESSION['akun_id'];
$csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {
    posisiJsonResponse(403, array('error' => 'Token keamanan tidak valid.'));
}

$sessionId = isset($_POST['sesi_id']) ? filter_var($_POST['sesi_id'], FILTER_VALIDATE_INT) : false;
$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : 'sync';
if ($sessionId === false || $sessionId <= 0 || !in_array($action, array('sync', 'leave'), true)) {
    posisiJsonResponse(422, array('error' => 'Sesi atau aksi tidak valid.'));
}
$sessionId = (int) $sessionId;
$posX = isset($_POST['pos_x']) ? filter_var($_POST['pos_x'], FILTER_VALIDATE_INT) : false;
$posY = isset($_POST['pos_y']) ? filter_var($_POST['pos_y'], FILTER_VALIDATE_INT) : false;
$direction = isset($_POST['arah']) && is_string($_POST['arah']) ? $_POST['arah'] : '';
if (
    $action === 'sync' &&
    ($posX === false || $posX < 0 || $posX > 3600 || $posY === false || $posY < 0 || $posY > 2400 ||
        !in_array($direction, array('up', 'down', 'left', 'right'), true))
) {
    posisiJsonResponse(422, array('error' => 'Posisi pemain tidak valid.'));
}

session_write_close();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/avatar.php';

    $stmtAccess = mysqli_prepare(
        $conn,
        "SELECT s.kelompok_id, s.status,
                TIMESTAMPDIFF(SECOND, CURRENT_TIMESTAMP, s.batas_waktu)
         FROM sesi_kuis s
         JOIN sesi_kuis_peserta p ON p.sesi_kuis_id = s.id
         WHERE s.id = ? AND p.user_id = ?
         LIMIT 1"
    );
    if (!$stmtAccess) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmtAccess, 'ii', $sessionId, $userId);
    mysqli_stmt_execute($stmtAccess);
    mysqli_stmt_bind_result($stmtAccess, $groupId, $currentSessionStatus, $currentSessionRemaining);
    $sessionFound = mysqli_stmt_fetch($stmtAccess);
    mysqli_stmt_close($stmtAccess);
    if (!$sessionFound) {
        posisiJsonResponse(403, array('error' => 'Anda bukan peserta sesi ini.'));
    }

    if (!mysqli_query($conn, "DELETE FROM posisi_pemain WHERE updated_at < NOW() - INTERVAL 2 MINUTE")) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    if (
        $action === 'sync' &&
        ($currentSessionStatus !== 'berlangsung' ||
            ($currentSessionRemaining !== null && (int) $currentSessionRemaining <= 0))
    ) {
        $stmtExpire = mysqli_prepare(
            $conn,
            "UPDATE sesi_kuis SET status = 'selesai', selesai_pada = COALESCE(selesai_pada, CURRENT_TIMESTAMP)
             WHERE id = ? AND status = 'berlangsung'"
        );
        if (!$stmtExpire) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtExpire, 'i', $sessionId);
        mysqli_stmt_execute($stmtExpire);
        mysqli_stmt_close($stmtExpire);
        $stmtExpireParticipants = mysqli_prepare(
            $conn,
            "UPDATE sesi_kuis_peserta
             SET status = 'selesai', selesai_pada = COALESCE(selesai_pada, CURRENT_TIMESTAMP)
             WHERE sesi_kuis_id = ? AND status = 'berlangsung'"
        );
        if (!$stmtExpireParticipants) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtExpireParticipants, 'i', $sessionId);
        mysqli_stmt_execute($stmtExpireParticipants);
        mysqli_stmt_close($stmtExpireParticipants);
        $stmtExpirePositions = mysqli_prepare($conn, 'DELETE FROM posisi_pemain WHERE sesi_id = ?');
        if (!$stmtExpirePositions) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtExpirePositions, 'i', $sessionId);
        mysqli_stmt_execute($stmtExpirePositions);
        mysqli_stmt_close($stmtExpirePositions);
        posisiJsonResponse(200, array('success' => true, 'session_expired' => true, 'players' => array()));
    }

    if ($action === 'leave') {
        $stmtLeave = mysqli_prepare($conn, 'DELETE FROM posisi_pemain WHERE sesi_id = ? AND user_id = ?');
        if (!$stmtLeave) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtLeave, 'ii', $sessionId, $userId);
        mysqli_stmt_execute($stmtLeave);
        mysqli_stmt_close($stmtLeave);
        posisiJsonResponse(200, array('success' => true, 'players' => array()));
    }

    $stmtPosition = mysqli_prepare(
        $conn,
        "INSERT INTO posisi_pemain (sesi_id, user_id, pos_x, pos_y, arah, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            pos_x = VALUES(pos_x),
            pos_y = VALUES(pos_y),
            arah = VALUES(arah),
            updated_at = NOW()"
    );
    if (!$stmtPosition) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmtPosition, 'iiiis', $sessionId, $userId, $posX, $posY, $direction);
    mysqli_stmt_execute($stmtPosition);
    mysqli_stmt_close($stmtPosition);

    $stmtPlayers = mysqli_prepare(
        $conn,
        "SELECT pp.user_id, pp.sesi_id, COALESCE(NULLIF(u.nama_panggilan, ''), u.nama_lengkap),
                u.avatar_config, pp.pos_x, pp.pos_y, pp.arah, s.kelompok_id, k.nama_kelompok,
                (s.kelompok_id IS NOT NULL AND s.kelompok_id =
                    (SELECT kelompok_id FROM sesi_kuis WHERE id = ?)
                    OR pp.sesi_id = ?) AS is_same_group,
                (s.kelompok_id IS NULL) AS is_solo,
                (SELECT COUNT(DISTINCT j.soal_id) FROM jawaban_user j
                 WHERE j.sesi_kuis_id = pp.sesi_id
                   AND (s.kelompok_id IS NOT NULL OR j.user_id = pp.user_id)) AS answered,
                (SELECT COUNT(*) FROM sesi_kuis_soal sq WHERE sq.sesi_kuis_id = pp.sesi_id) AS total,
                (SELECT COALESCE(SUM(j.skor), 0) FROM jawaban_user j
                 WHERE j.sesi_kuis_id = pp.sesi_id
                   AND ((s.kelompok_id IS NULL AND j.user_id = pp.user_id)
                        OR (s.kelompok_id IS NOT NULL AND j.id = (
                            SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                            WHERE first_answer.sesi_kuis_id = j.sesi_kuis_id
                              AND first_answer.soal_id = j.soal_id
                        )))) AS score,
                (SELECT COALESCE(SUM(j.skor), 0) FROM jawaban_user j
                 WHERE j.sesi_kuis_id = pp.sesi_id AND j.user_id = pp.user_id) AS personal_score
         FROM posisi_pemain pp
         JOIN sesi_kuis s ON s.id = pp.sesi_id
         JOIN sesi_kuis_peserta p ON p.sesi_kuis_id = pp.sesi_id AND p.user_id = pp.user_id
         JOIN users u ON u.id = pp.user_id
         LEFT JOIN kelompok k ON k.id = s.kelompok_id
         WHERE pp.user_id <> ?
           AND pp.updated_at >= NOW() - INTERVAL 15 SECOND
         ORDER BY pp.updated_at DESC"
    );
    if (!$stmtPlayers) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmtPlayers, 'iii', $sessionId, $sessionId, $userId);
    mysqli_stmt_execute($stmtPlayers);
    mysqli_stmt_bind_result(
        $stmtPlayers,
        $playerId,
        $playerSessionId,
        $playerName,
        $playerAvatarJson,
        $playerX,
        $playerY,
        $playerDirection,
        $playerGroupId,
        $playerGroupName,
        $sameGroup,
        $isSolo,
        $playerAnswered,
        $playerTotal,
        $playerScore,
        $playerPersonalScore
    );
    $players = array();
    while (mysqli_stmt_fetch($stmtPlayers)) {
        $players[] = array(
            'id' => (int) $playerId,
            'session_id' => (int) $playerSessionId,
            'name' => $playerName,
            'avatar_config' => avatarNormalizeConfig($playerAvatarJson),
            'pos_x' => (int) $playerX,
            'pos_y' => (int) $playerY,
            'arah' => $playerDirection,
            'group_id' => $playerGroupId === null ? null : (int) $playerGroupId,
            'nama_kelompok' => $playerGroupName,
            'is_same_group' => (bool) $sameGroup,
            'is_solo' => (bool) $isSolo,
            'answered' => (int) $playerAnswered,
            'total' => (int) $playerTotal,
            'score' => (int) $playerScore,
            'personal_score' => (int) $playerPersonalScore
        );
    }
    mysqli_stmt_close($stmtPlayers);

    $answeredQuestions = array();
    $stmtAnsweredQuestions = mysqli_prepare(
        $conn,
        "SELECT j.soal_id, COALESCE(NULLIF(u.nama_panggilan, ''), u.nama_lengkap)
         FROM (
            SELECT MIN(id) AS id, soal_id FROM jawaban_user
            WHERE sesi_kuis_id = ? AND (? = 1 OR user_id = ?)
            GROUP BY soal_id
         ) first_answer
         JOIN jawaban_user j ON j.id = first_answer.id
         JOIN users u ON u.id = j.user_id"
    );
    if (!$stmtAnsweredQuestions) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    $sharedProgress = $groupId !== null ? 1 : 0;
    mysqli_stmt_bind_param($stmtAnsweredQuestions, 'iii', $sessionId, $sharedProgress, $userId);
    mysqli_stmt_execute($stmtAnsweredQuestions);
    mysqli_stmt_bind_result($stmtAnsweredQuestions, $answeredQuestionId, $answeredQuestionName);
    while (mysqli_stmt_fetch($stmtAnsweredQuestions)) {
        $answeredQuestions[] = array(
            'soal_id' => (int) $answeredQuestionId,
            'name' => $answeredQuestionName
        );
    }
    mysqli_stmt_close($stmtAnsweredQuestions);

    $stmtCurrentProgress = mysqli_prepare(
        $conn,
        "SELECT COUNT(DISTINCT soal_id), COALESCE(SUM(answer_row.skor), 0)
         FROM jawaban_user answer_row
         WHERE answer_row.sesi_kuis_id = ?
           AND ((? = 0 AND answer_row.user_id = ?)
                OR (? = 1 AND answer_row.id = (
                    SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                    WHERE first_answer.sesi_kuis_id = answer_row.sesi_kuis_id
                      AND first_answer.soal_id = answer_row.soal_id
                )))"
    );
    if (!$stmtCurrentProgress) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmtCurrentProgress, 'iiii', $sessionId, $sharedProgress, $userId, $sharedProgress);
    mysqli_stmt_execute($stmtCurrentProgress);
    mysqli_stmt_bind_result($stmtCurrentProgress, $currentAnswered, $currentScore);
    mysqli_stmt_fetch($stmtCurrentProgress);
    mysqli_stmt_close($stmtCurrentProgress);
    posisiJsonResponse(200, array(
        'success' => true,
        'players' => $players,
        'answered_questions' => $answeredQuestions,
        'answered' => (int) $currentAnswered,
        'shared_score' => (int) $currentScore
    ));
} catch (Throwable $exception) {
    error_log('Sinkronisasi posisi pemain gagal: ' . $exception->getMessage());
    posisiJsonResponse(500, array('error' => 'Posisi pemain belum dapat disinkronkan.'));
}
