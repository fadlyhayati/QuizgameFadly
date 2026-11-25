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
require_once __DIR__ . "/../Koneksi/avatar.php";

if (!siapkanTabelPesertaKuis($conn)) {
    http_response_code(500);
    exit("Database permainan belum siap.");
}

$userId = (int) $_SESSION['akun_id'];
$sessionId = isset($_GET['sesi']) ? filter_var($_GET['sesi'], FILTER_VALIDATE_INT) : false;
if ($sessionId === false || $sessionId <= 0) {
    header("Location: Kelompok.php?notice=session_unavailable");
    exit();
}
$sessionId = (int) $sessionId;

$stmtSession = mysqli_prepare(
    $conn,
    "SELECT s.kelompok_id, s.status, COALESCE(k.nama_kelompok, 'Individu (Solo)'), p.status,
            TIMESTAMPDIFF(SECOND, p.bergabung_pada, CURRENT_TIMESTAMP), s.batas_waktu,
            TIMESTAMPDIFF(SECOND, CURRENT_TIMESTAMP, s.batas_waktu)
     FROM sesi_kuis s
     LEFT JOIN kelompok k ON k.id = s.kelompok_id
     JOIN sesi_kuis_peserta p ON p.sesi_kuis_id = s.id
     WHERE s.id = ? AND p.user_id = ?
     LIMIT 1"
);
if (!$stmtSession) {
    http_response_code(500);
    exit("Sesi kuis tidak dapat dimuat.");
}
mysqli_stmt_bind_param($stmtSession, "ii", $sessionId, $userId);
mysqli_stmt_execute($stmtSession);
mysqli_stmt_bind_result($stmtSession, $groupId, $sessionStatus, $groupName, $participantStatus, $timerElapsed, $sessionDeadline, $sessionRemaining);
$sessionFound = mysqli_stmt_fetch($stmtSession);
mysqli_stmt_close($stmtSession);

if (!$sessionFound) {
    header("Location: Kelompok.php?notice=session_unavailable");
    exit();
}
if ($participantStatus === 'selesai') {
    header("Location: Hasil.php?sesi=" . $sessionId);
    exit();
}

$questions = array();
$sharedSession = $groupId !== null;
$stmtQuestions = mysqli_prepare(
    $conn,
    "SELECT sq.soal_id, sq.urutan, sq.pertanyaan_snapshot,
            sq.opsi_a_snapshot, sq.opsi_b_snapshot, sq.opsi_c_snapshot,
            sq.opsi_d_snapshot, sq.tipe_soal_snapshot,
            CASE WHEN j.id IS NULL THEN 0 ELSE 1 END AS sudah_dijawab,
            j.user_id, COALESCE(NULLIF(u.nama_panggilan, ''), u.nama_lengkap)
     FROM sesi_kuis_soal sq
     LEFT JOIN (
        SELECT MIN(id) AS id, sesi_kuis_id, soal_id
        FROM jawaban_user
        GROUP BY sesi_kuis_id, soal_id
     ) first_answer
        ON first_answer.sesi_kuis_id = sq.sesi_kuis_id AND first_answer.soal_id = sq.soal_id
     LEFT JOIN jawaban_user j
        ON j.id = first_answer.id AND (? = 1 OR j.user_id = ?)
     LEFT JOIN users u ON u.id = j.user_id
     WHERE sq.sesi_kuis_id = ?
     ORDER BY sq.urutan"
);
if (!$stmtQuestions) {
    http_response_code(500);
    exit("Daftar soal sesi tidak dapat dimuat.");
}
mysqli_stmt_bind_param($stmtQuestions, "iii", $sharedSession, $userId, $sessionId);
mysqli_stmt_execute($stmtQuestions);
mysqli_stmt_bind_result(
    $stmtQuestions,
    $questionId,
    $questionOrder,
    $questionText,
    $optionA,
    $optionB,
    $optionC,
    $optionD,
    $questionType,
    $alreadyAnswered,
    $questionAnsweredBy,
    $questionAnswererName
);
while (mysqli_stmt_fetch($stmtQuestions)) {
    $questions[] = array(
        'id' => (int) $questionId,
        'order' => (int) $questionOrder,
        'question' => $questionText,
        'options' => array(
            'A' => $optionA,
            'B' => $optionB,
            'C' => $optionC,
            'D' => $optionD
        ),
        'type' => $questionType,
        'answered' => (bool) $alreadyAnswered,
        'answeredBy' => $questionAnsweredBy ? (int) $questionAnsweredBy : null,
        'answeredByName' => $questionAnswererName
    );
}
mysqli_stmt_close($stmtQuestions);
$totalQuestions = count($questions);
$answeredQuestions = count(array_filter($questions, function ($question) {
    return $question['answered'];
}));

$players = array();
$stmtPlayers = mysqli_prepare(
    $conn,
    "SELECT u.id, COALESCE(NULLIF(u.nama_panggilan, ''), u.nama_lengkap),
            p.status,
            CASE WHEN s.kelompok_id IS NOT NULL
                THEN (SELECT COALESCE(SUM(shared_answer.skor), 0)
                      FROM jawaban_user shared_answer
                      WHERE shared_answer.sesi_kuis_id = p.sesi_kuis_id
                        AND shared_answer.id = (
                            SELECT MIN(first_answer.id) FROM jawaban_user first_answer
                            WHERE first_answer.sesi_kuis_id = shared_answer.sesi_kuis_id
                              AND first_answer.soal_id = shared_answer.soal_id
                        ))
                ELSE COALESCE(SUM(j.skor), 0) END,
            CASE WHEN s.kelompok_id IS NOT NULL
                THEN (SELECT COUNT(DISTINCT shared_answer.soal_id)
                      FROM jawaban_user shared_answer WHERE shared_answer.sesi_kuis_id = p.sesi_kuis_id)
                ELSE COUNT(DISTINCT j.soal_id) END,
            COALESCE(SUM(j.skor), 0)
     FROM sesi_kuis_peserta p
     JOIN users u ON u.id = p.user_id
     JOIN sesi_kuis s ON s.id = p.sesi_kuis_id
     LEFT JOIN jawaban_user j
        ON j.sesi_kuis_id = p.sesi_kuis_id AND j.user_id = p.user_id
     WHERE p.sesi_kuis_id = ?
     GROUP BY u.id, u.nama_panggilan, u.nama_lengkap, p.status, s.kelompok_id, p.sesi_kuis_id
     ORDER BY SUM(j.skor) DESC, u.nama_panggilan ASC, u.nama_lengkap ASC"
);
if (!$stmtPlayers) {
    http_response_code(500);
    exit("Daftar pemain sesi tidak dapat dimuat.");
}
mysqli_stmt_bind_param($stmtPlayers, "i", $sessionId);
mysqli_stmt_execute($stmtPlayers);
mysqli_stmt_bind_result($stmtPlayers, $playerId, $playerName, $playerStatus, $playerScore, $playerAnswered, $playerPersonalScore);
while (mysqli_stmt_fetch($stmtPlayers)) {
    $players[] = array(
        'id' => (int) $playerId,
        'name' => $playerName,
        'status' => $playerStatus,
        'score' => (int) $playerScore,
        'personal_score' => (int) $playerPersonalScore,
        'group_id' => $groupId === null ? null : (int) $groupId,
        'group_name' => $groupName,
        'answered' => (int) $playerAnswered,
        'total' => $totalQuestions
    );
}
mysqli_stmt_close($stmtPlayers);

$profileNickname = null;
$profileAvatarJson = null;
$stmtProfile = mysqli_prepare($conn, "SELECT nama_panggilan, avatar_config FROM users WHERE id = ? LIMIT 1");
if (!$stmtProfile) {
    http_response_code(500);
    exit("Profil pemain tidak dapat dimuat.");
}
mysqli_stmt_bind_param($stmtProfile, "i", $userId);
mysqli_stmt_execute($stmtProfile);
mysqli_stmt_bind_result($stmtProfile, $profileNickname, $profileAvatarJson);
mysqli_stmt_fetch($stmtProfile);
mysqli_stmt_close($stmtProfile);
$namaPanggilan = $profileNickname ?: ($_SESSION['nama_lengkap'] ?? 'Pemain');
$avatarConfig = avatarNormalizeConfig($profileAvatarJson);
$csrf = escapeKuis(tokenCsrfKuis());

$timerElapsed = max(0, (int) $timerElapsed);
$worldConfig = array(
    'sessionId' => $sessionId,
    'playerId' => $userId,
    'groupId' => $groupId === null ? null : (int) $groupId,
    'groupName' => $groupName,
    'playerName' => $namaPanggilan,
    'avatar' => $avatarConfig,
    'questions' => $questions,
    'players' => $players,
    'answered' => $answeredQuestions,
    'total' => $totalQuestions,
    'timerElapsed' => $timerElapsed,
    'sessionDeadline' => $sessionDeadline ? strtotime($sessionDeadline) * 1000 : null,
    'sessionRemaining' => max(0, (int) $sessionRemaining + ((int) $sessionRemaining > 0 ? 1 : 0)),
    'sharedProgress' => $sharedSession,
    'csrf' => $_SESSION['csrf_token'],
    'answerEndpoint' => '../Koneksi/kuis.php',
    'positionEndpoint' => '../Koneksi/posisi.php',
    'resultUrl' => 'Hasil.php?sesi=' . $sessionId,
    'exitUrl' => 'Kelompok.php'
);
$worldJson = json_encode(
    $worldConfig,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
if ($worldJson === false) {
    http_response_code(500);
    exit("Konfigurasi arena tidak dapat dimuat.");
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#10142b">
    <link rel="stylesheet" href="../style.css?v=20261126-1">
    <title>Arena Kuis | Quiz Game</title>
</head>
<body class="world-page">
    <main class="world-shell" id="quizWorld" data-world="<?= escapeKuis($worldJson) ?>">
        <canvas id="worldCanvas" class="world-canvas" aria-label="Peta interaktif Quiz Game"></canvas>
        <div class="world-vignette" aria-hidden="true"></div>

        <section class="world-hud world-hud-left" aria-label="Progres permainan">
            <a class="world-brand" href="Beranda.php">Quiz<span>Game</span></a>
            <div class="world-hud-card">
                <span class="world-hud-label">PROGRES SOAL</span>
                <strong><span id="worldProgress"><?= $answeredQuestions ?></span><i>/</i><?= $totalQuestions ?></strong>
                <div class="world-progress-track"><span id="worldProgressBar" style="width: <?= $totalQuestions > 0 ? round($answeredQuestions / $totalQuestions * 100) : 0 ?>%"></span></div>
                <div class="world-timer"><span class="world-live-dot"></span><span>WAKTU</span><strong id="worldTimer">00:00</strong></div>
            </div>
            <button type="button" class="world-sound-button" id="worldSound" aria-pressed="true"><span>♫</span> Suara ON</button>
        </section>

        <section class="world-hud world-hud-right" aria-label="Menu permainan">
            <div class="world-session-chip"><span class="world-live-dot"></span><?= escapeKuis($groupName ?: 'Individu (Solo)') ?> · #<?= $sessionId ?></div>
            <div class="world-menu-buttons">
                <button class="world-menu-button" type="button" id="openPlayers"><span>♙</span><b>Pemain</b></button>
                <button class="world-menu-button" type="button" id="openRanking"><span>★</span><b>Peringkat</b></button>
                <a class="world-menu-button world-exit-button" href="Kelompok.php"><span>↩</span><b>Keluar</b></a>
            </div>
        </section>

        <div class="world-location" id="worldLocation">Halaman Kampus</div>
        <div class="world-interaction-hint" id="worldHint">Dekati Pos Soal lalu tekan <kbd>Spasi</kbd></div>
        <aside class="world-minimap" id="worldMinimap" aria-label="Minimap kampus">
            <button type="button" id="toggleMinimap" aria-expanded="true">Minimap <span>−</span></button>
            <canvas id="minimapCanvas" width="240" height="160" aria-label="Peta Gedung Kampus tujuh ruangan, koridor, pemain, dan Pos Soal"></canvas>
        </aside>

        <section class="world-controls" aria-label="Kontrol karakter">
            <div class="world-joystick" id="worldJoystick" aria-label="Analog virtual">
                <span class="joystick-arrow joystick-up">▲</span>
                <span class="joystick-arrow joystick-down">▼</span>
                <span class="joystick-arrow joystick-left">◀</span>
                <span class="joystick-arrow joystick-right">▶</span>
                <span class="joystick-knob" id="joystickKnob"></span>
            </div>
            <button type="button" class="world-action-button" id="worldAction" disabled>
                <span>✦</span><strong>Aksi</strong>
            </button>
        </section>

        <dialog class="world-question-dialog" id="worldQuestionDialog" aria-labelledby="worldQuestionTitle">
            <div class="world-dialog-card">
                <button type="button" class="world-dialog-close" data-close-dialog aria-label="Tutup">×</button>
                <div class="world-question-meta">
                    <span class="world-question-category" id="worldQuestionCategory">SOAL</span>
                    <span class="world-question-type" id="worldQuestionType">PILIHAN GANDA</span>
                    <span class="world-question-order" id="worldQuestionOrder"></span>
                </div>
                <div class="question-countdown" id="questionCountdown" aria-live="polite">
                    <span><i id="questionCountdownBar"></i></span><strong id="questionCountdownText">00:30</strong>
                </div>
                <h2 id="worldQuestionTitle"></h2>
                <div class="world-answer-options" id="worldAnswerOptions"></div>
                <form class="world-essay-form" id="worldEssayForm" hidden>
                    <label class="sr-only" for="worldEssayAnswer">Jawaban esai</label>
                    <textarea id="worldEssayAnswer" name="jawaban_teks" rows="3" maxlength="255" placeholder="Ketik jawabanmu..." required></textarea>
                    <button class="world-submit-answer" type="submit">Kirim Jawaban <span>↗</span></button>
                </form>
                <p class="world-answer-feedback" id="worldAnswerFeedback" role="status" aria-live="polite"></p>
            </div>
        </dialog>

        <dialog class="world-list-dialog" id="worldPlayersDialog" aria-labelledby="worldPlayersTitle">
            <div class="world-dialog-card">
                <button type="button" class="world-dialog-close" data-close-dialog aria-label="Tutup">×</button>
                <p class="world-dialog-eyebrow">SESI #<?= $sessionId ?></p>
                <h2 id="worldPlayersTitle">Pemain di Sesi</h2>
                <div class="world-list-content" id="worldPlayersList"></div>
            </div>
        </dialog>

        <dialog class="world-list-dialog" id="worldRankingDialog" aria-labelledby="worldRankingTitle">
            <div class="world-dialog-card">
                <button type="button" class="world-dialog-close" data-close-dialog aria-label="Tutup">×</button>
                <p class="world-dialog-eyebrow">SKOR SEMENTARA</p>
                <h2 id="worldRankingTitle">Peringkat Kelompok</h2>
                <div class="world-ranking-tabs" role="tablist" aria-label="Klasemen live">
                    <button type="button" class="is-selected" data-ranking-mode="groups" aria-selected="true">Kelompok</button>
                    <button type="button" data-ranking-mode="individual" aria-selected="false">Individu &amp; Solo</button>
                </div>
                <div class="world-list-content" id="worldRankingList"></div>
            </div>
        </dialog>

        <dialog class="world-complete-dialog" id="worldCompleteDialog" aria-labelledby="worldCompleteTitle">
            <div class="world-dialog-card world-complete-card">
                <span class="world-complete-mark">✓</span>
                <p class="world-dialog-eyebrow">MISI SELESAI</p>
                <h2 id="worldCompleteTitle">Semua soal terjawab!</h2>
                <p id="worldCompleteMessage">Jawabanmu sudah tersimpan. Lihat ringkasan hasil dan pembahasan.</p>
                <a class="world-submit-answer" id="worldResultLink" href="Hasil.php?sesi=<?= $sessionId ?>">Lihat Hasil <span>↗</span></a>
            </div>
        </dialog>

    </main>
    <script src="../avatar.js?v=20261126-2" defer></script>
    <script src="../quiz-world.js?v=20261126-1" defer></script>
</body>
</html>
