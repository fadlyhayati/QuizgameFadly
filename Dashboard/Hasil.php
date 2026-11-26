<?php

session_start();

if (empty($_SESSION['akun_id'])) {
    header("Location: ../navbar.html?error=auth");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'user') {
    $tujuan = ($_SESSION['role'] ?? '') === 'admin'
        ? "Admin.php"
        : "PembuatSoal.php";
    header("Location: " . $tujuan);
    exit();
}

require_once __DIR__ . "/../Koneksi/koneksi.php";
require_once __DIR__ . "/../Koneksi/kuis-common.php";

if (!siapkanTabelPesertaKuis($conn)) {
    http_response_code(500);
    exit("Database hasil kuis belum siap.");
}

$userId = (int) $_SESSION['akun_id'];
$historyMode = isset($_GET['riwayat']) && $_GET['riwayat'] === '1';

if ($historyMode) {
    $history = array();
    $stmtHistory = mysqli_prepare(
        $conn,
        "SELECT p.sesi_kuis_id, COALESCE(k.nama_kelompok, 'Individu (Solo)'), p.selesai_pada,
                COUNT(DISTINCT sq.soal_id) AS jumlah_soal,
                CASE WHEN s.kelompok_id IS NOT NULL
                    THEN (SELECT COALESCE(SUM(shared_answer.skor), 0)
                          FROM jawaban_user shared_answer
                          WHERE shared_answer.sesi_kuis_id = p.sesi_kuis_id
                            AND shared_answer.id = (
                                SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                                WHERE first_answer.sesi_kuis_id = shared_answer.sesi_kuis_id
                                  AND first_answer.soal_id = shared_answer.soal_id
                            ))
                    ELSE COALESCE(SUM(j.skor), 0) END AS skor,
                CASE WHEN s.kelompok_id IS NOT NULL
                    THEN (SELECT COALESCE(SUM(shared_answer.benar), 0)
                          FROM jawaban_user shared_answer
                          WHERE shared_answer.sesi_kuis_id = p.sesi_kuis_id
                            AND shared_answer.id = (
                                SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                                WHERE first_answer.sesi_kuis_id = shared_answer.sesi_kuis_id
                                  AND first_answer.soal_id = shared_answer.soal_id
                            ))
                    ELSE COALESCE(SUM(CASE WHEN j.benar = 1 THEN 1 ELSE 0 END), 0) END AS benar
         FROM sesi_kuis_peserta p
         JOIN sesi_kuis s ON s.id = p.sesi_kuis_id
         LEFT JOIN kelompok k ON k.id = s.kelompok_id
         LEFT JOIN sesi_kuis_soal sq ON sq.sesi_kuis_id = p.sesi_kuis_id
         LEFT JOIN jawaban_user j
            ON j.sesi_kuis_id = p.sesi_kuis_id
            AND j.soal_id = sq.soal_id
            AND j.user_id = p.user_id
         WHERE p.user_id = ? AND p.status = 'selesai'
         GROUP BY p.sesi_kuis_id, k.nama_kelompok, p.selesai_pada
         ORDER BY p.selesai_pada DESC"
    );
    if (!$stmtHistory) {
        http_response_code(500);
        exit("Riwayat hasil tidak dapat dimuat.");
    }
    mysqli_stmt_bind_param($stmtHistory, "i", $userId);
    mysqli_stmt_execute($stmtHistory);
    mysqli_stmt_bind_result($stmtHistory, $historySession, $historyGroup, $historyFinished, $historyTotal, $historyScore, $historyCorrect);
    while (mysqli_stmt_fetch($stmtHistory)) {
        $history[] = array(
            'session_id' => (int) $historySession,
            'group' => $historyGroup,
            'finished' => $historyFinished,
            'total' => (int) $historyTotal,
            'score' => (int) $historyScore,
            'correct' => (int) $historyCorrect
        );
    }
    mysqli_stmt_close($stmtHistory);

    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../style.css?v=20261126-2">
        <title>Riwayat Hasil | Quiz Game</title>
    </head>
    <body class="player-page">
        <header class="player-header">
            <a class="player-brand" href="Beranda.php">Quiz<span>Game</span></a>
            <nav class="player-nav"><a href="Beranda.php">Beranda</a><a href="Kelompok.php">Kelompok</a></nav>
            <div class="player-header-actions">
                <div class="player-user"><span class="player-avatar"><?= escapeKuis(function_exists('mb_substr') ? mb_substr($_SESSION['nama_lengkap'] ?? 'P', 0, 1, 'UTF-8') : substr($_SESSION['nama_lengkap'] ?? 'P', 0, 1)) ?></span><span><strong><?= escapeKuis($_SESSION['nama_lengkap'] ?? 'Pemain') ?></strong><small>Pemain</small></span></div>
                <form action="../Koneksi/auth.php?action=logout" method="post"><button class="player-logout" type="submit">Logout</button></form>
            </div>
        </header>
        <main class="player-main">
            <section class="player-page-heading">
                <p class="player-eyebrow">Catatan pencapaian</p>
                <h1>Riwayat <span>hasil kuis</span></h1>
                <p>Semua sesi yang telah kamu selesaikan tersimpan di sini.</p>
            </section>
            <section class="player-panel history-panel">
                <?php if (empty($history)): ?>
                    <div class="player-empty"><span>✦</span><p>Belum ada sesi selesai. Hasil permainanmu akan muncul di sini.</p></div>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($history as $item): ?>
                            <?php $accuracy = $item['total'] > 0 ? round(($item['correct'] / $item['total']) * 100) : 0; ?>
                            <a class="history-row" href="Hasil.php?sesi=<?= $item['session_id'] ?>">
                                <span class="history-score"><?= $item['score'] ?><small>poin</small></span>
                                <span class="history-copy"><strong><?= escapeKuis($item['group']) ?></strong><small><?= escapeKuis(date('d M Y, H:i', strtotime($item['finished']))) ?> · <?= $item['correct'] ?>/<?= $item['total'] ?> benar · <?= $accuracy ?>% akurasi</small></span>
                                <span class="quick-arrow">↗</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a class="player-outline-button history-back" href="Beranda.php">Kembali ke Beranda</a>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit();
}

$sessionId = isset($_GET['sesi']) ? filter_var($_GET['sesi'], FILTER_VALIDATE_INT) : false;
if ($sessionId === false || $sessionId <= 0) {
    header("Location: Hasil.php?riwayat=1");
    exit();
}
$sessionId = (int) $sessionId;

$stmtSummary = mysqli_prepare(
    $conn,
    "SELECT s.kelompok_id, COALESCE(k.nama_kelompok, 'Individu (Solo)'), s.dimulai_pada,
            p.bergabung_pada, COALESCE(p.selesai_pada, s.selesai_pada), s.status,
            (SELECT COUNT(*) FROM sesi_kuis_soal sq WHERE sq.sesi_kuis_id = s.id),
            (SELECT COUNT(DISTINCT j.soal_id) FROM jawaban_user j
             WHERE j.sesi_kuis_id = s.id AND (s.kelompok_id IS NOT NULL OR j.user_id = p.user_id)),
            (SELECT COALESCE(SUM(j.skor), 0) FROM jawaban_user j
             WHERE j.sesi_kuis_id = s.id
               AND (s.kelompok_id IS NULL AND j.user_id = p.user_id
                    OR s.kelompok_id IS NOT NULL AND j.id = (
                        SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                        WHERE first_answer.sesi_kuis_id = j.sesi_kuis_id
                          AND first_answer.soal_id = j.soal_id
                    ))),
            (SELECT COALESCE(SUM(CASE WHEN j.benar = 1 THEN 1 ELSE 0 END), 0) FROM jawaban_user j
             WHERE j.sesi_kuis_id = s.id
               AND (s.kelompok_id IS NULL AND j.user_id = p.user_id
                    OR s.kelompok_id IS NOT NULL AND j.id = (
                        SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                        WHERE first_answer.sesi_kuis_id = j.sesi_kuis_id
                          AND first_answer.soal_id = j.soal_id
                    ))),
            (SELECT COALESCE(SUM(CASE WHEN j.benar = 0 AND COALESCE(j.jawaban_teks, '') <> '[WAKTU HABIS]' THEN 1 ELSE 0 END), 0)
             FROM jawaban_user j WHERE j.sesi_kuis_id = s.id
               AND (s.kelompok_id IS NULL AND j.user_id = p.user_id
                    OR s.kelompok_id IS NOT NULL AND j.id = (
                        SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                        WHERE first_answer.sesi_kuis_id = j.sesi_kuis_id
                          AND first_answer.soal_id = j.soal_id
                    ))),
            (SELECT COALESCE(SUM(CASE WHEN j.jawaban_teks = '[WAKTU HABIS]' THEN 1 ELSE 0 END), 0)
             FROM jawaban_user j WHERE j.sesi_kuis_id = s.id
               AND (s.kelompok_id IS NULL AND j.user_id = p.user_id
                    OR s.kelompok_id IS NOT NULL AND j.id = (
                        SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                        WHERE first_answer.sesi_kuis_id = j.sesi_kuis_id
                          AND first_answer.soal_id = j.soal_id
                    )))
     FROM sesi_kuis_peserta p
     JOIN sesi_kuis s ON s.id = p.sesi_kuis_id
     LEFT JOIN kelompok k ON k.id = s.kelompok_id
     WHERE p.sesi_kuis_id = ? AND p.user_id = ? AND p.status = 'selesai'
     LIMIT 1"
);
if (!$stmtSummary) {
    http_response_code(500);
    exit("Ringkasan hasil tidak dapat dimuat.");
}
mysqli_stmt_bind_param($stmtSummary, "ii", $sessionId, $userId);
mysqli_stmt_execute($stmtSummary);
mysqli_stmt_bind_result(
    $stmtSummary,
    $groupId,
    $groupName,
    $sessionStarted,
    $joinedAt,
    $finishedAt,
    $sessionStatus,
    $totalQuestions,
    $totalAnswered,
    $score,
    $correctCount,
    $wrongCount,
    $timeoutCount
);
$resultFound = mysqli_stmt_fetch($stmtSummary);
mysqli_stmt_close($stmtSummary);

if (!$resultFound) {
    header("Location: Hasil.php?riwayat=1&notice=notfound");
    exit();
}

$totalQuestions = (int) $totalQuestions;
$totalAnswered = (int) $totalAnswered;
$score = (int) $score;
$correctCount = (int) $correctCount;
$wrongCount = (int) $wrongCount;
$timeoutCount = (int) $timeoutCount;
$accuracy = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100) : 0;
$startedTime = strtotime($sessionStarted);
$finishedTime = strtotime($finishedAt);
$duration = $startedTime !== false && $finishedTime !== false ? max(0, $finishedTime - $startedTime) : 0;
$durationText = $duration >= 60
    ? floor($duration / 60) . 'm ' . ($duration % 60) . ' dtk'
    : $duration . ' dtk';

$review = array();
$stmtReview = mysqli_prepare(
    $conn,
    "SELECT sq.urutan, sq.pertanyaan_snapshot,
            sq.opsi_a_snapshot, sq.opsi_b_snapshot, sq.opsi_c_snapshot, sq.opsi_d_snapshot,
            sq.kunci_snapshot, j.jawaban_dipilih, j.jawaban_teks, j.benar,
            sq.tipe_soal_snapshot, sq.jawaban_esai_snapshot
     FROM sesi_kuis_soal sq
     LEFT JOIN (
        SELECT MIN(id) AS id, sesi_kuis_id, soal_id
        FROM jawaban_user
        WHERE sesi_kuis_id = ? AND (? = 1 OR user_id = ?)
        GROUP BY sesi_kuis_id, soal_id
     ) first_answer
        ON first_answer.sesi_kuis_id = sq.sesi_kuis_id AND first_answer.soal_id = sq.soal_id
     LEFT JOIN jawaban_user j ON j.id = first_answer.id
     WHERE sq.sesi_kuis_id = ?
     ORDER BY sq.urutan"
);
if (!$stmtReview) {
    http_response_code(500);
    exit("Review jawaban tidak dapat dimuat.");
}
$sharedProgress = $groupId !== null ? 1 : 0;
mysqli_stmt_bind_param($stmtReview, "iiii", $sessionId, $sharedProgress, $userId, $sessionId);
mysqli_stmt_execute($stmtReview);
mysqli_stmt_bind_result(
    $stmtReview,
    $reviewOrder,
    $reviewQuestion,
    $reviewA,
    $reviewB,
    $reviewC,
    $reviewD,
    $reviewKey,
    $reviewAnswer,
    $reviewTextAnswer,
    $reviewCorrect,
    $reviewType,
    $reviewEssayKey
);
while (mysqli_stmt_fetch($stmtReview)) {
    $review[] = array(
        'order' => (int) $reviewOrder,
        'question' => $reviewQuestion,
        'options' => array('A' => $reviewA, 'B' => $reviewB, 'C' => $reviewC, 'D' => $reviewD),
        'key' => $reviewKey,
        'answer' => $reviewType === 'esai' ? $reviewTextAnswer : $reviewAnswer,
        'type' => $reviewType,
        'essay_key' => $reviewEssayKey,
        'correct' => (int) $reviewCorrect === 1,
        'timed_out' => $reviewTextAnswer === '[WAKTU HABIS]'
    );
}
mysqli_stmt_close($stmtReview);

$bestMembers = array();
if ($groupId === null) {
    $bestMembers[$userId] = array(
        'user_id' => $userId,
        'name' => $_SESSION['nama_panggilan'] ?? $_SESSION['nama_lengkap'] ?? 'Pemain',
        'score' => $score,
        'session_id' => $sessionId
    );
} else {
    $stmtRanking = mysqli_prepare(
        $conn,
        "SELECT p.user_id, u.nama_lengkap, p.sesi_kuis_id,
                COALESCE(SUM(j.skor), 0) AS skor
         FROM sesi_kuis_peserta p
         JOIN sesi_kuis s ON s.id = p.sesi_kuis_id
         JOIN users u ON u.id = p.user_id
         LEFT JOIN jawaban_user j
            ON j.sesi_kuis_id = p.sesi_kuis_id AND j.user_id = p.user_id
         WHERE s.kelompok_id = ? AND p.status = 'selesai'
         GROUP BY p.user_id, u.nama_lengkap, p.sesi_kuis_id
         ORDER BY skor DESC, p.selesai_pada ASC"
    );
    if (!$stmtRanking) {
        http_response_code(500);
        exit("Peringkat kelompok tidak dapat dimuat.");
    }
    mysqli_stmt_bind_param($stmtRanking, "i", $groupId);
    mysqli_stmt_execute($stmtRanking);
    mysqli_stmt_bind_result($stmtRanking, $rankUserId, $rankName, $rankSession, $rankScore);
    while (mysqli_stmt_fetch($stmtRanking)) {
        $rankUserId = (int) $rankUserId;
        if (!isset($bestMembers[$rankUserId])) {
            $bestMembers[$rankUserId] = array(
                'user_id' => $rankUserId,
                'name' => $rankName,
                'score' => (int) $rankScore,
                'session_id' => (int) $rankSession
            );
        }
    }
    mysqli_stmt_close($stmtRanking);
}
uasort($bestMembers, function ($left, $right) {
    return $right['score'] <=> $left['score'];
});
$playerRank = 0;
$position = 1;
foreach ($bestMembers as $memberId => $member) {
    if ((int) $memberId === $userId) {
        $playerRank = $position;
        break;
    }
    $position++;
}

$contributions = array();
if ($groupId !== null) {
    $stmtContributions = mysqli_prepare(
        $conn,
        "SELECT p.user_id, COALESCE(NULLIF(u.nama_panggilan, ''), u.nama_lengkap),
                COUNT(DISTINCT j.soal_id),
                COALESCE(SUM(CASE WHEN j.benar = 1 THEN 1 ELSE 0 END), 0),
                COALESCE(SUM(CASE WHEN j.jawaban_teks = '[WAKTU HABIS]' THEN 1 ELSE 0 END), 0),
                COALESCE(SUM(j.skor), 0)
         FROM sesi_kuis_peserta p
         JOIN users u ON u.id = p.user_id
         LEFT JOIN jawaban_user j ON j.sesi_kuis_id = p.sesi_kuis_id AND j.user_id = p.user_id
         WHERE p.sesi_kuis_id = ?
         GROUP BY p.user_id, u.nama_panggilan, u.nama_lengkap
         ORDER BY SUM(j.skor) DESC, COUNT(j.id) DESC"
    );
    if (!$stmtContributions) {
        http_response_code(500);
        exit("Kontribusi anggota tidak dapat dimuat.");
    }
    mysqli_stmt_bind_param($stmtContributions, "i", $sessionId);
    mysqli_stmt_execute($stmtContributions);
    mysqli_stmt_bind_result(
        $stmtContributions,
        $contributorId,
        $contributorName,
        $contributorAnswered,
        $contributorCorrect,
        $contributorTimeout,
        $contributorScore
    );
    while (mysqli_stmt_fetch($stmtContributions)) {
        $contributions[] = array(
            'user_id' => (int) $contributorId,
            'name' => $contributorName,
            'answered' => (int) $contributorAnswered,
            'correct' => (int) $contributorCorrect,
            'timeouts' => (int) $contributorTimeout,
            'score' => (int) $contributorScore
        );
    }
    mysqli_stmt_close($stmtContributions);
}

$groupLeaderboard = array();
$stmtGroupLeaderboard = mysqli_prepare(
    $conn,
    "SELECT s.kelompok_id, k.nama_kelompok, SUM(CASE
                WHEN j.id IS NULL THEN 0
                WHEN j.id = (SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                             WHERE first_answer.sesi_kuis_id = j.sesi_kuis_id
                               AND first_answer.soal_id = j.soal_id) THEN j.skor
                ELSE 0 END), s.selesai_pada
     FROM sesi_kuis s
     JOIN kelompok k ON k.id = s.kelompok_id
     LEFT JOIN jawaban_user j ON j.sesi_kuis_id = s.id
     WHERE s.status = 'selesai' AND s.kelompok_id IS NOT NULL
     GROUP BY s.id, s.kelompok_id, k.nama_kelompok, s.selesai_pada
     ORDER BY 3 DESC, s.selesai_pada ASC"
);
if (!$stmtGroupLeaderboard) {
    http_response_code(500);
    exit("Peringkat kelompok tidak dapat dimuat.");
}
mysqli_stmt_execute($stmtGroupLeaderboard);
mysqli_stmt_bind_result($stmtGroupLeaderboard, $leaderGroupId, $leaderGroupName, $leaderGroupScore, $leaderGroupFinished);
$seenLeaderboardGroups = array();
while (mysqli_stmt_fetch($stmtGroupLeaderboard)) {
    $leaderGroupId = (int) $leaderGroupId;
    if (!isset($seenLeaderboardGroups[$leaderGroupId])) {
        $seenLeaderboardGroups[$leaderGroupId] = true;
        $groupLeaderboard[] = array(
            'id' => $leaderGroupId,
            'name' => $leaderGroupName,
            'score' => (int) $leaderGroupScore,
            'finished' => $leaderGroupFinished
        );
    }
}
mysqli_stmt_close($stmtGroupLeaderboard);

$individualLeaderboard = array();
$stmtIndividualLeaderboard = mysqli_prepare(
    $conn,
    "SELECT p.user_id, COALESCE(NULLIF(u.nama_panggilan, ''), u.nama_lengkap),
            s.kelompok_id, COALESCE(k.nama_kelompok, 'Solo'), SUM(j.skor), s.selesai_pada
     FROM sesi_kuis_peserta p
     JOIN users u ON u.id = p.user_id
     JOIN sesi_kuis s ON s.id = p.sesi_kuis_id
     LEFT JOIN kelompok k ON k.id = s.kelompok_id
     LEFT JOIN jawaban_user j ON j.sesi_kuis_id = p.sesi_kuis_id AND j.user_id = p.user_id
     WHERE p.status = 'selesai'
     GROUP BY p.user_id, u.nama_panggilan, u.nama_lengkap, s.id, s.kelompok_id, k.nama_kelompok, s.selesai_pada
     ORDER BY SUM(j.skor) DESC, s.selesai_pada ASC"
);
if (!$stmtIndividualLeaderboard) {
    http_response_code(500);
    exit("Peringkat individu tidak dapat dimuat.");
}
mysqli_stmt_execute($stmtIndividualLeaderboard);
mysqli_stmt_bind_result(
    $stmtIndividualLeaderboard,
    $leaderUserId,
    $leaderUserName,
    $leaderUserGroupId,
    $leaderUserGroupName,
    $leaderUserScore,
    $leaderUserFinished
);
$seenLeaderboardUsers = array();
while (mysqli_stmt_fetch($stmtIndividualLeaderboard)) {
    $leaderUserId = (int) $leaderUserId;
    if (!isset($seenLeaderboardUsers[$leaderUserId])) {
        $seenLeaderboardUsers[$leaderUserId] = true;
        $individualLeaderboard[] = array(
            'id' => $leaderUserId,
            'name' => $leaderUserName,
            'group_id' => $leaderUserGroupId === null ? null : (int) $leaderUserGroupId,
            'group' => $leaderUserGroupId === null ? 'Solo' : $leaderUserGroupName,
            'score' => (int) $leaderUserScore,
            'finished' => $leaderUserFinished
        );
    }
}
mysqli_stmt_close($stmtIndividualLeaderboard);

$leaderGroupRank = 0;
foreach ($groupLeaderboard as $index => $leaderGroup) {
    if ((int) $leaderGroup['id'] === (int) $groupId) {
        $leaderGroupRank = $index + 1;
        break;
    }
}
if ($groupId !== null && $leaderGroupRank > 0) {
    $playerRank = $leaderGroupRank;
}

$resultNotice = isset($_GET['notice']) && is_string($_GET['notice']) ? $_GET['notice'] : '';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css?v=20261126-2">
    <title>Hasil Kuis | Quiz Game</title>
</head>
<body class="player-page">
    <header class="player-header">
        <a class="player-brand" href="Beranda.php">Quiz<span>Game</span></a>
        <nav class="player-nav"><a href="Beranda.php">Beranda</a><a href="Kelompok.php">Kelompok</a></nav>
        <div class="player-header-actions">
            <div class="player-user"><span class="player-avatar"><?= escapeKuis(function_exists('mb_substr') ? mb_substr($_SESSION['nama_lengkap'] ?? 'P', 0, 1, 'UTF-8') : substr($_SESSION['nama_lengkap'] ?? 'P', 0, 1)) ?></span><span><strong><?= escapeKuis($_SESSION['nama_lengkap'] ?? 'Pemain') ?></strong><small>Pemain</small></span></div>
            <form action="../Koneksi/auth.php?action=logout" method="post"><button class="player-logout" type="submit">Logout</button></form>
        </div>
    </header>
    <main class="player-main result-main">
        <?php if ($resultNotice === 'notfound'): ?><div class="player-notice notice-error">Hasil tidak ditemukan atau bukan milik akun Anda.</div><?php endif; ?>
        <section class="result-hero">
            <div class="result-glow" aria-hidden="true"></div>
            <p class="player-eyebrow">Sesi kuis selesai</p>
            <h1>Kerja bagus, <?= escapeKuis($_SESSION['nama_lengkap'] ?? 'Pemain') ?>!</h1>
            <p class="result-group-name">Hasil permainan <strong><?= escapeKuis($groupName) ?></strong></p>
            <div class="result-score-orbit"><span><?= $groupId === null ? 'SKOR PRIBADI' : 'SKOR BERSAMA KELOMPOK' ?></span><strong><?= $score ?></strong><small>poin</small></div>
            <div class="result-summary-grid">
                <div><strong><?= $correctCount ?></strong><span>Benar</span></div>
                <div><strong><?= $wrongCount ?></strong><span>Salah</span></div>
                <div><strong><?= $timeoutCount ?></strong><span>Waktu Habis</span></div>
                <div><strong><?= $accuracy ?>%</strong><span>Akurasi</span></div>
                <div><strong><?= escapeKuis($durationText) ?></strong><span>Waktu</span></div>
            </div>
            <div class="result-rank-line">
                <span><?= $groupId === null ? 'Mode Individu (Solo)' : 'Kelompok ' . escapeKuis($groupName) ?></span>
                <strong><?= $playerRank > 0 ? ($groupId === null ? 'Skor pribadi tersimpan' : 'Peringkat #' . $playerRank) : 'Skor tersimpan' ?></strong>
            </div>
        </section>

        <div class="result-columns">
            <section class="player-panel result-review-panel">
                <div class="player-section-heading">
                    <div><p class="player-eyebrow">Cek kembali jawabanmu</p><h2>Review Jawaban</h2></div>
                    <span class="team-count"><?= $totalAnswered ?>/<?= $totalQuestions ?> dijawab</span>
                </div>
                <div class="result-review-list">
                    <?php foreach ($review as $item): ?>
                        <?php
                        $category = '';
                        $reviewQuestionText = $item['question'];
                        if (preg_match('/^\[([^\]]{1,60})\]\s*/u', $reviewQuestionText, $reviewCategoryMatch)) {
                            $category = trim($reviewCategoryMatch[1]);
                            $reviewQuestionText = preg_replace('/^\[[^\]]{1,60}\]\s*/u', '', $reviewQuestionText, 1);
                        }
                        ?>
                        <article class="result-review-card <?= $item['timed_out'] ? 'review-timed-out' : ($item['correct'] ? 'review-correct' : 'review-wrong') ?>">
                            <div class="result-review-heading">
                                <span>SOAL <?= sprintf('%02d', $item['order']) ?><?= $category !== '' ? ' · ' . escapeKuis($category) : '' ?></span>
                                <strong><?= $item['timed_out'] ? '◷ Waktu Habis' : ($item['correct'] ? '✓ Benar' : ($item['answer'] ? '× Salah' : 'Belum dijawab')) ?></strong>
                            </div>
                            <h3><?= nl2br(escapeKuis($reviewQuestionText)) ?></h3>
                            <div class="result-review-answer">
                                <span><?= $item['timed_out'] ? 'Status' : 'Jawabanmu' ?></span>
                                <strong><?= $item['timed_out'] ? 'Waktu soal habis · 0 poin' : ($item['answer'] ? escapeKuis($item['type'] === 'esai' ? $item['answer'] : $item['answer'] . '. ' . ($item['options'][$item['answer']] ?? '')) : 'Tidak dijawab') ?></strong>
                            </div>
                            <?php if (!$item['correct']): ?>
                                <div class="result-review-answer answer-key-line">
                                    <span>Kunci jawaban</span>
                                    <strong><?= escapeKuis($item['type'] === 'esai' ? $item['essay_key'] : $item['key'] . '. ' . $item['options'][$item['key']]) ?></strong>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <aside class="player-panel result-team-panel">
                <div class="player-section-heading">
                    <div><p class="player-eyebrow"><?= $groupId === null ? 'Hasil individu' : 'Kontribusi tim' ?></p><h2><?= $groupId === null ? 'Skor Pribadi' : 'Anggota Kelompok' ?></h2></div>
                    <span class="team-count"><?= $groupId === null ? 'Solo' : count($contributions) . ' pemain' ?></span>
                </div>
                <?php if ($groupId === null): ?>
                    <div class="result-personal-score"><strong><?= $score ?></strong><span>poin pribadi</span><p><?= $correctCount ?> soal benar dari <?= $totalQuestions ?> soal.</p></div>
                <?php elseif (empty($contributions)): ?>
                    <div class="player-empty"><span>✦</span><p>Belum ada kontribusi anggota di sesi ini.</p></div>
                <?php else: ?>
                    <div class="result-table-wrap">
                        <table class="result-data-table">
                            <thead><tr><th>Anggota</th><th>Dijawab</th><th>Benar</th><th>Habis</th><th>Skor</th></tr></thead>
                            <tbody>
                            <?php foreach ($contributions as $member): ?>
                                <tr class="<?= $member['user_id'] === $userId ? 'is-player' : '' ?>">
                                    <td><?= escapeKuis($member['name']) ?><?= $member['user_id'] === $userId ? ' (Kamu)' : '' ?></td>
                                    <td><?= $member['answered'] ?></td><td><?= $member['correct'] ?></td><td><?= $member['timeouts'] ?></td><td><strong><?= $member['score'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <a class="player-primary-button result-play-again" href="Kelompok.php">Kembali ke Kelompok</a>
            </aside>
        </div>

        <section class="player-panel class-leaderboard">
            <div class="player-section-heading">
                <div><p class="player-eyebrow">Pencapaian kelas</p><h2>Papan Peringkat Kompetitif</h2></div>
                <span class="team-count">Skor tertinggi · selesai tercepat</span>
            </div>
            <div class="leaderboard-tabs" role="tablist" aria-label="Pilih klasemen">
                <button type="button" class="is-selected" role="tab" aria-selected="true" aria-controls="groupLeaderboard" data-leaderboard-tab="groupLeaderboard">Antar Kelompok</button>
                <button type="button" role="tab" aria-selected="false" aria-controls="individualLeaderboard" data-leaderboard-tab="individualLeaderboard">Individu &amp; Solo</button>
            </div>
            <div class="leaderboard-panel" id="groupLeaderboard" role="tabpanel">
                <?php if (empty($groupLeaderboard)): ?>
                    <div class="player-empty"><span>✦</span><p>Belum ada hasil kelompok yang selesai.</p></div>
                <?php else: ?>
                    <ol class="result-ranking">
                        <?php foreach ($groupLeaderboard as $index => $leader): ?>
                            <li class="<?= (int) $leader['id'] === (int) $groupId ? 'is-player' : '' ?>">
                                <span class="ranking-position"><?= $index + 1 ?></span>
                                <span class="ranking-name"><strong><?= escapeKuis($leader['name']) ?></strong><small><?= escapeKuis(date('d M Y, H:i', strtotime($leader['finished']))) ?></small></span>
                                <b><?= $leader['score'] ?> poin</b>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
            <div class="leaderboard-panel" id="individualLeaderboard" role="tabpanel" hidden>
                <?php if (empty($individualLeaderboard)): ?>
                    <div class="player-empty"><span>✦</span><p>Belum ada hasil individu yang selesai.</p></div>
                <?php else: ?>
                    <ol class="result-ranking">
                        <?php foreach ($individualLeaderboard as $index => $leader): ?>
                            <li class="<?= $leader['id'] === $userId ? 'is-player' : '' ?>">
                                <span class="ranking-position"><?= $index + 1 ?></span>
                                <span class="ranking-name"><strong><?= escapeKuis($leader['name']) ?><?= $leader['id'] === $userId ? ' (Kamu)' : '' ?></strong><small><?= $leader['group_id'] === null ? 'Solo' : 'Kelompok ' . escapeKuis($leader['group']) ?></small></span>
                                <b><?= $leader['score'] ?> poin</b>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-leaderboard-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                document.querySelectorAll('[data-leaderboard-tab]').forEach(function (otherTab) {
                    var selected = otherTab === tab;
                    otherTab.classList.toggle('is-selected', selected);
                    otherTab.setAttribute('aria-selected', selected ? 'true' : 'false');
                    document.getElementById(otherTab.dataset.leaderboardTab).hidden = !selected;
                });
            });
        });
    </script>
</body>
</html>
