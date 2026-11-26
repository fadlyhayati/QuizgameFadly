<?php

session_start();

if (empty($_SESSION['akun_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../navbar.html?error=auth");
    exit();
}

require_once __DIR__ . "/../Koneksi/koneksi.php";
require_once __DIR__ . "/../Koneksi/kuis-common.php";

function adminEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminScalar($conn, $sql)
{
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }

    $row = mysqli_fetch_row($result);
    mysqli_free_result($result);
    return $row ? $row[0] : 0;
}

function adminPageNumber($name)
{
    $page = isset($_GET[$name]) ? filter_var($_GET[$name], FILTER_VALIDATE_INT) : 1;
    return $page !== false && $page > 0 ? (int) $page : 1;
}

function adminPageUrl($name, $page, $anchor)
{
    $parameters = $_GET;
    $parameters[$name] = $page;
    return 'Admin.php?' . http_build_query($parameters) . '#' . $anchor;
}

if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = adminEscape($_SESSION['csrf_token']);
$pageSize = 25;

if (!siapkanTabelPesertaKuis($conn)) {
    http_response_code(500);
    exit("Tabel peserta kuis belum siap. Periksa skema database Quiz Game.");
}

$answerSearch = isset($_GET['jawaban_q']) && is_string($_GET['jawaban_q'])
    ? trim($_GET['jawaban_q'])
    : '';
$answerSearch = function_exists('mb_substr')
    ? mb_substr($answerSearch, 0, 100, 'UTF-8')
    : substr($answerSearch, 0, 100);
$answerStatus = isset($_GET['status']) && in_array($_GET['status'], array('semua', 'benar', 'salah', 'waktu_habis'), true)
    ? $_GET['status']
    : 'semua';
$answerLike = '%' . $answerSearch . '%';
$answerPage = adminPageNumber('jawaban_page');

$logSearch = isset($_GET['log_q']) && is_string($_GET['log_q']) ? trim($_GET['log_q']) : '';
$logSearch = function_exists('mb_substr') ? mb_substr($logSearch, 0, 100, 'UTF-8') : substr($logSearch, 0, 100);
$activityFilter = isset($_GET['aktivitas']) && is_string($_GET['aktivitas']) ? $_GET['aktivitas'] : '';
$logLike = '%' . $logSearch . '%';
$logPage = adminPageNumber('log_page');

$userSearch = isset($_GET['user_q']) && is_string($_GET['user_q']) ? trim($_GET['user_q']) : '';
$userSearch = function_exists('mb_substr') ? mb_substr($userSearch, 0, 100, 'UTF-8') : substr($userSearch, 0, 100);
$userLike = '%' . $userSearch . '%';
$noticeCode = isset($_GET['notice']) && is_string($_GET['notice']) ? $_GET['notice'] : '';
$noticeMap = array(
    'role_changed' => array('success', 'Role akun berhasil diperbarui dan aktivitasnya sudah dicatat.'),
    'role_unchanged' => array('info', 'Akun tersebut sudah memiliki role yang dipilih.'),
    'self_role' => array('error', 'Role akun admin yang sedang digunakan tidak dapat diubah.'),
    'admin_role_locked' => array('error', 'Role admin akun lain tidak dapat diubah dari dashboard ini.'),
    'user_not_found' => array('error', 'Akun yang dipilih tidak ditemukan.'),
    'invalid_user' => array('error', 'Data akun tidak valid.'),
    'invalid_role' => array('error', 'Role yang dipilih tidak diizinkan.'),
    'invalid_action' => array('error', 'Permintaan admin tidak dikenali.'),
    'csrf' => array('error', 'Token formulir tidak valid. Muat ulang halaman, lalu coba lagi.'),
    'database' => array('error', 'Perubahan belum dapat disimpan. Periksa koneksi database dan coba lagi.')
);
$notice = isset($noticeMap[$noticeCode]) ? $noticeMap[$noticeCode] : null;

try {
    $totalAccounts = (int) adminScalar($conn, "SELECT COUNT(*) FROM users");
    $totalAdminAccounts = (int) adminScalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $totalGroups = (int) adminScalar($conn, "SELECT COUNT(*) FROM kelompok");
    $activeQuestions = (int) adminScalar($conn, "SELECT COUNT(*) FROM soal WHERE aktif = 1");
    $completedSessions = (int) adminScalar($conn, "SELECT COUNT(*) FROM sesi_kuis_peserta WHERE status = 'selesai'");
    $averageAccuracy = (float) adminScalar(
        $conn,
        "SELECT COALESCE(ROUND(AVG(benar) * 100, 1), 0) FROM jawaban_user"
    );

    $regions = array();
    $resultRegions = mysqli_query(
        $conn,
        "SELECT COALESCE(NULLIF(TRIM(asal_daerah), ''), 'Belum diisi') AS daerah, COUNT(*) AS jumlah
         FROM users
         WHERE role <> 'admin'
         GROUP BY COALESCE(NULLIF(TRIM(asal_daerah), ''), 'Belum diisi')
         ORDER BY jumlah DESC, daerah ASC"
    );
    if (!$resultRegions) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    while ($row = mysqli_fetch_assoc($resultRegions)) {
        $regions[] = array('name' => $row['daerah'], 'count' => (int) $row['jumlah']);
    }
    mysqli_free_result($resultRegions);

    $activityTypes = array();
    $resultActivityTypes = mysqli_query($conn, "SELECT DISTINCT aktivitas FROM log_aktivitas ORDER BY aktivitas");
    if (!$resultActivityTypes) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    while ($row = mysqli_fetch_assoc($resultActivityTypes)) {
        $activityTypes[] = $row['aktivitas'];
    }
    mysqli_free_result($resultActivityTypes);
    if ($activityFilter !== '' && !in_array($activityFilter, $activityTypes, true)) {
        $activityFilter = '';
    }

    $answerWhere = "WHERE (? = '' OR u.nama_lengkap LIKE ? OR COALESCE(k.nama_kelompok, 'Individu (Solo)') LIKE ?)
                    AND (? = 'semua'
                         OR (? = 'benar' AND j.benar = 1)
                         OR (? = 'salah' AND j.benar = 0 AND COALESCE(j.jawaban_teks, '') <> '[WAKTU HABIS]')
                         OR (? = 'waktu_habis' AND j.jawaban_teks = '[WAKTU HABIS]'))";
    $stmtAnswerCount = mysqli_prepare(
        $conn,
        "SELECT COUNT(*)
         FROM jawaban_user j
         JOIN users u ON u.id = j.user_id
         JOIN sesi_kuis s ON s.id = j.sesi_kuis_id
         LEFT JOIN kelompok k ON k.id = s.kelompok_id
         $answerWhere"
    );
    if (!$stmtAnswerCount) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param(
        $stmtAnswerCount,
        "sssssss",
        $answerSearch,
        $answerLike,
        $answerLike,
        $answerStatus,
        $answerStatus,
        $answerStatus,
        $answerStatus
    );
    mysqli_stmt_execute($stmtAnswerCount);
    mysqli_stmt_bind_result($stmtAnswerCount, $answerTotal);
    mysqli_stmt_fetch($stmtAnswerCount);
    mysqli_stmt_close($stmtAnswerCount);
    $answerTotal = (int) $answerTotal;
    $answerPages = max(1, (int) ceil($answerTotal / $pageSize));
    $answerPage = min($answerPage, $answerPages);
    $answerOffset = ($answerPage - 1) * $pageSize;

    $answerRecaps = array();
    $stmtRecaps = mysqli_prepare(
        $conn,
        "SELECT j.sesi_kuis_id, u.nama_lengkap, COALESCE(k.nama_kelompok, 'Individu (Solo)'),
                COUNT(*) AS dijawab,
                SUM(CASE WHEN j.benar = 1 THEN 1 ELSE 0 END) AS benar,
                SUM(CASE WHEN j.benar = 0 AND COALESCE(j.jawaban_teks, '') <> '[WAKTU HABIS]' THEN 1 ELSE 0 END) AS salah,
                SUM(CASE WHEN j.jawaban_teks = '[WAKTU HABIS]' THEN 1 ELSE 0 END) AS waktu_habis,
                SUM(j.skor) AS skor
         FROM jawaban_user j
         JOIN users u ON u.id = j.user_id
         JOIN sesi_kuis s ON s.id = j.sesi_kuis_id
         LEFT JOIN kelompok k ON k.id = s.kelompok_id
         $answerWhere
         GROUP BY j.sesi_kuis_id, u.id, u.nama_lengkap, k.id, k.nama_kelompok
         ORDER BY MAX(j.dijawab_pada) DESC"
    );
    if (!$stmtRecaps) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param(
        $stmtRecaps,
        "sssssss",
        $answerSearch,
        $answerLike,
        $answerLike,
        $answerStatus,
        $answerStatus,
        $answerStatus,
        $answerStatus
    );
    mysqli_stmt_execute($stmtRecaps);
    mysqli_stmt_bind_result($stmtRecaps, $recapSessionId, $recapUser, $recapGroup, $recapAnswered, $recapCorrect, $recapWrong, $recapTimeout, $recapScore);
    while (mysqli_stmt_fetch($stmtRecaps)) {
        $answerRecaps[] = array(
            'session_id' => (int) $recapSessionId,
            'user' => $recapUser,
            'group' => $recapGroup,
            'answered' => (int) $recapAnswered,
            'correct' => (int) $recapCorrect,
            'wrong' => (int) $recapWrong,
            'timeout' => (int) $recapTimeout,
            'score' => (int) $recapScore
        );
    }
    mysqli_stmt_close($stmtRecaps);

    $answers = array();
    $stmtAnswers = mysqli_prepare(
        $conn,
        "SELECT j.id, j.sesi_kuis_id, j.dijawab_pada, u.nama_lengkap, u.email,
                COALESCE(k.nama_kelompok, 'Individu (Solo)'), sq.urutan, sq.pertanyaan_snapshot,
                j.jawaban_dipilih,
                CASE j.jawaban_dipilih
                    WHEN 'A' THEN sq.opsi_a_snapshot
                    WHEN 'B' THEN sq.opsi_b_snapshot
                    WHEN 'C' THEN sq.opsi_c_snapshot
                    ELSE sq.opsi_d_snapshot
                END AS opsi_dipilih,
                sq.kunci_snapshot,
                CASE sq.kunci_snapshot
                    WHEN 'A' THEN sq.opsi_a_snapshot
                    WHEN 'B' THEN sq.opsi_b_snapshot
                    WHEN 'C' THEN sq.opsi_c_snapshot
                    ELSE sq.opsi_d_snapshot
                END AS opsi_kunci,
                j.benar, j.skor, j.jawaban_teks,
                sq.tipe_soal_snapshot, sq.jawaban_esai_snapshot
         FROM jawaban_user j
         JOIN users u ON u.id = j.user_id
         JOIN sesi_kuis s ON s.id = j.sesi_kuis_id
         LEFT JOIN kelompok k ON k.id = s.kelompok_id
         JOIN sesi_kuis_soal sq
            ON sq.sesi_kuis_id = j.sesi_kuis_id AND sq.soal_id = j.soal_id
         $answerWhere
         ORDER BY j.dijawab_pada DESC, j.id DESC
         LIMIT ? OFFSET ?"
    );
    if (!$stmtAnswers) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param(
        $stmtAnswers,
        "sssssssii",
        $answerSearch,
        $answerLike,
        $answerLike,
        $answerStatus,
        $answerStatus,
        $answerStatus,
        $answerStatus,
        $pageSize,
        $answerOffset
    );
    mysqli_stmt_execute($stmtAnswers);
    mysqli_stmt_bind_result(
        $stmtAnswers,
        $answerId,
        $sessionId,
        $answeredAt,
        $answerUserName,
        $answerEmail,
        $answerGroup,
        $questionOrder,
        $questionText,
        $selectedLetter,
        $selectedText,
        $correctLetter,
        $correctText,
        $isCorrect,
        $answerScore,
        $textAnswer,
        $questionType,
        $essayAnswerKey
    );
    while (mysqli_stmt_fetch($stmtAnswers)) {
        $answers[] = array(
            'id' => (int) $answerId,
            'session_id' => (int) $sessionId,
            'answered_at' => $answeredAt,
            'user' => $answerUserName,
            'email' => $answerEmail,
            'group' => $answerGroup,
            'order' => (int) $questionOrder,
            'question' => $questionText,
            'selected_letter' => $selectedLetter,
            'selected_text' => $selectedText,
            'correct_letter' => $correctLetter,
            'correct_text' => $correctText,
            'text_answer' => $textAnswer,
            'question_type' => $questionType,
            'essay_key' => $essayAnswerKey,
            'correct' => (int) $isCorrect === 1,
            'timed_out' => $textAnswer === '[WAKTU HABIS]',
            'score' => (int) $answerScore
        );
    }
    mysqli_stmt_close($stmtAnswers);

    $logWhere = "WHERE (? = '' OR u.nama_lengkap LIKE ? OR l.detail LIKE ?)
                 AND (? = '' OR l.aktivitas = ?)";
    $stmtLogCount = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) FROM log_aktivitas l
         LEFT JOIN users u ON u.id = l.user_id
         $logWhere"
    );
    if (!$stmtLogCount) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param(
        $stmtLogCount,
        "sssss",
        $logSearch,
        $logLike,
        $logLike,
        $activityFilter,
        $activityFilter
    );
    mysqli_stmt_execute($stmtLogCount);
    mysqli_stmt_bind_result($stmtLogCount, $logTotal);
    mysqli_stmt_fetch($stmtLogCount);
    mysqli_stmt_close($stmtLogCount);
    $logTotal = (int) $logTotal;
    $logPages = max(1, (int) ceil($logTotal / $pageSize));
    $logPage = min($logPage, $logPages);
    $logOffset = ($logPage - 1) * $pageSize;

    $logs = array();
    $stmtLogs = mysqli_prepare(
        $conn,
        "SELECT l.id, l.aktivitas, l.detail, l.dibuat_pada,
                COALESCE(u.nama_lengkap, 'Sistem') AS nama_lengkap,
                COALESCE(u.email, '-') AS email
         FROM log_aktivitas l
         LEFT JOIN users u ON u.id = l.user_id
         $logWhere
         ORDER BY l.dibuat_pada DESC, l.id DESC
         LIMIT ? OFFSET ?"
    );
    if (!$stmtLogs) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param(
        $stmtLogs,
        "sssssii",
        $logSearch,
        $logLike,
        $logLike,
        $activityFilter,
        $activityFilter,
        $pageSize,
        $logOffset
    );
    mysqli_stmt_execute($stmtLogs);
    mysqli_stmt_bind_result($stmtLogs, $logId, $activity, $logDetail, $logDate, $logUser, $logEmail);
    while (mysqli_stmt_fetch($stmtLogs)) {
        $logs[] = array(
            'id' => (int) $logId,
            'activity' => $activity,
            'detail' => $logDetail,
            'date' => $logDate,
            'user' => $logUser,
            'email' => $logEmail
        );
    }
    mysqli_stmt_close($stmtLogs);

    $users = array();
    $stmtUsers = mysqli_prepare(
        $conn,
        "SELECT u.id, u.nama_lengkap, u.email, u.role, u.jenis_kelamin,
                u.negara, u.provinsi, u.kabupaten_kota, u.kecamatan, u.kelurahan_desa,
                u.asal_daerah, u.created_at, k.nama_kelompok
         FROM users u
         LEFT JOIN kelompok_anggota ka ON ka.user_id = u.id
         LEFT JOIN kelompok k ON k.id = ka.kelompok_id
         WHERE (? = '' OR u.nama_lengkap LIKE ? OR u.email LIKE ? OR u.asal_daerah LIKE ?)
         ORDER BY u.created_at DESC, u.id DESC"
    );
    if (!$stmtUsers) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmtUsers, "ssss", $userSearch, $userLike, $userLike, $userLike);
    mysqli_stmt_execute($stmtUsers);
    mysqli_stmt_bind_result(
        $stmtUsers,
        $accountId,
        $accountName,
        $accountEmail,
        $accountRole,
        $accountGender,
        $accountCountry,
        $accountProvince,
        $accountCity,
        $accountDistrict,
        $accountVillage,
        $accountRegion,
        $accountCreated,
        $accountGroup
    );
    while (mysqli_stmt_fetch($stmtUsers)) {
        $users[] = array(
            'id' => (int) $accountId,
            'name' => $accountName,
            'email' => $accountEmail,
            'role' => $accountRole,
            'gender' => $accountGender,
            'country' => $accountCountry,
            'province' => $accountProvince,
            'city' => $accountCity,
            'district' => $accountDistrict,
            'kelurahan_desa' => $accountVillage,
            'region' => $accountRegion,
            'created' => $accountCreated,
            'group' => $accountGroup
        );
    }
    mysqli_stmt_close($stmtUsers);
} catch (mysqli_sql_exception $exception) {
    error_log("Gagal memuat monitoring dashboard admin: " . $exception->getMessage());
    http_response_code(500);
    exit("Data monitoring admin tidak dapat dimuat. Periksa skema database Quiz Game.");
}

$namaAdmin = adminEscape($_SESSION['nama_lengkap'] ?? 'Administrator');
$totalNonAdminUsers = max(1, $totalAccounts - $totalAdminAccounts);
$answerPrevious = max(1, $answerPage - 1);
$answerNext = min($answerPages, $answerPage + 1);
$logPrevious = max(1, $logPage - 1);
$logNext = min($logPages, $logPage + 1);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css?v=20261126-2">
    <title>Dashboard Admin | Quiz Game</title>
</head>
<body class="admin-page">
    <header class="admin-header">
        <a class="admin-brand" href="Admin.php"><span>Q</span> Quiz<span>Game</span> <small>ADMIN</small></a>
        <nav class="admin-nav" aria-label="Navigasi dashboard">
            <a href="#overview">Ringkasan</a>
            <a href="#jawaban">Hasil kuis</a>
            <a href="#aktivitas">Aktivitas</a>
            <a href="#pengguna">Pengguna</a>
        </nav>
        <div class="admin-header-user">
            <span class="admin-user-avatar"><?= adminEscape(function_exists('mb_substr') ? mb_substr($_SESSION['nama_lengkap'] ?? 'A', 0, 1, 'UTF-8') : substr($_SESSION['nama_lengkap'] ?? 'A', 0, 1)) ?></span>
            <span><strong><?= $namaAdmin ?></strong><small>Administrator</small></span>
            <form action="../Koneksi/auth.php?action=logout" method="post">
                <button class="admin-logout" type="submit">Logout</button>
            </form>
        </div>
    </header>

    <main class="admin-main">
        <section class="admin-welcome" id="overview">
            <div>
                <p class="admin-eyebrow">Pusat monitoring</p>
                <h1>Selamat datang, <?= $namaAdmin ?> <span>✦</span></h1>
                <p>Pantau aktivitas, hasil kuis, dan perkembangan komunitas Quiz Game dari satu tempat.</p>
            </div>
            <div class="admin-welcome-mark" aria-hidden="true"><span>Q</span><i>✦</i></div>
        </section>

        <?php if ($notice): ?>
            <div class="admin-notice admin-notice-<?= adminEscape($notice[0]) ?>" role="status"><?= adminEscape($notice[1]) ?></div>
        <?php endif; ?>

        <section class="admin-stats" aria-label="Statistik utama">
            <article class="admin-stat-card"><span class="admin-stat-icon">♙</span><div><small>Total akun terdaftar</small><strong><?= $totalAccounts ?></strong></div><i>Semua role</i></article>
            <article class="admin-stat-card"><span class="admin-stat-icon stat-violet">♧</span><div><small>Total kelompok</small><strong><?= $totalGroups ?></strong></div><i>Tim bermain</i></article>
            <article class="admin-stat-card"><span class="admin-stat-icon stat-cyan">▤</span><div><small>Soal aktif</small><strong><?= $activeQuestions ?></strong></div><i>Bank soal</i></article>
            <article class="admin-stat-card"><span class="admin-stat-icon stat-gold">▶</span><div><small>Sesi diselesaikan</small><strong><?= $completedSessions ?></strong></div><i>Per peserta</i></article>
            <article class="admin-stat-card"><span class="admin-stat-icon stat-pink">◎</span><div><small>Rata-rata akurasi</small><strong><?= number_format($averageAccuracy, 1, ',', '.') ?><em>%</em></strong></div><i>Semua jawaban</i></article>
        </section>

        <section class="admin-panel admin-regions-panel">
            <div class="admin-section-heading">
                <div><p class="admin-eyebrow">Komunitas Quiz Game</p><h2>Sebaran Asal Daerah</h2></div>
                <span class="admin-count-pill"><?= count($regions) ?> daerah</span>
            </div>
            <?php if (empty($regions)): ?>
                <div class="admin-empty-state">Belum ada data daerah pengguna.</div>
            <?php else: ?>
                <div class="admin-region-list">
                    <?php foreach ($regions as $region): ?>
                        <?php $regionWidth = $totalNonAdminUsers > 0 ? min(100, round(($region['count'] / $totalNonAdminUsers) * 100)) : 0; ?>
                        <div class="admin-region-row">
                            <span><?= adminEscape($region['name']) ?></span>
                            <div class="admin-region-track"><i style="width: <?= $regionWidth ?>%"></i></div>
                            <strong><?= $region['count'] ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="admin-panel admin-answer-panel" id="jawaban">
            <div class="admin-section-heading">
                <div><p class="admin-eyebrow">Monitoring permainan</p><h2>Hasil Jawaban User</h2><p>Rekap peserta per sesi dan detail setiap jawaban.</p></div>
                <span class="admin-count-pill"><?= $answerTotal ?> jawaban</span>
            </div>
            <form class="admin-filter-form" method="get" action="Admin.php#jawaban">
                <label class="admin-search-field"><span class="sr-only">Cari nama user atau kelompok</span><input type="search" name="jawaban_q" value="<?= adminEscape($answerSearch) ?>" placeholder="Cari nama user atau kelompok..."></label>
                <label><span class="sr-only">Status jawaban</span>
                    <select name="status">
                        <option value="semua" <?= $answerStatus === 'semua' ? 'selected' : '' ?>>Semua status</option>
                        <option value="benar" <?= $answerStatus === 'benar' ? 'selected' : '' ?>>Benar</option>
                        <option value="salah" <?= $answerStatus === 'salah' ? 'selected' : '' ?>>Salah</option>
                        <option value="waktu_habis" <?= $answerStatus === 'waktu_habis' ? 'selected' : '' ?>>Waktu habis</option>
                    </select>
                </label>
                <button class="admin-button" type="submit">Terapkan</button>
                <?php if ($answerSearch !== '' || $answerStatus !== 'semua'): ?><a class="admin-reset-link" href="Admin.php#jawaban">Reset</a><?php endif; ?>
            </form>

            <div class="admin-subsection-heading"><h3>Rekap per peserta dan sesi</h3><span><?= count($answerRecaps) ?> rekap</span></div>
            <div class="admin-table-wrap">
                <table class="admin-table admin-summary-table">
                    <thead><tr><th>Sesi</th><th>Kelompok</th><th>Nama User</th><th>Dijawab</th><th>Benar</th><th>Salah</th><th>Waktu habis</th><th>Skor</th></tr></thead>
                    <tbody>
                        <?php if (empty($answerRecaps)): ?>
                            <tr><td colspan="7" class="admin-empty-state">Belum ada jawaban yang sesuai filter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($answerRecaps as $recap): ?>
                                <tr><td>#<?= $recap['session_id'] ?></td><td><?= adminEscape($recap['group']) ?></td><td><?= adminEscape($recap['user']) ?></td><td><?= $recap['answered'] ?></td><td><span class="admin-answer-count is-correct"><?= $recap['correct'] ?></span></td><td><span class="admin-answer-count is-wrong"><?= $recap['wrong'] ?></span></td><td><?= $recap['timeout'] ?></td><td><strong><?= $recap['score'] ?></strong></td></tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-subsection-heading admin-detail-heading"><h3>Detail jawaban per soal</h3><span>Halaman <?= $answerPage ?> dari <?= $answerPages ?></span></div>
            <div class="admin-table-wrap">
                <table class="admin-table admin-answers-table">
                    <thead><tr><th>Waktu</th><th>User</th><th>Kelompok / sesi</th><th>Pertanyaan</th><th>Jawaban dipilih</th><th>Kunci</th><th>Status</th><th>Skor</th></tr></thead>
                    <tbody>
                        <?php if (empty($answers)): ?>
                            <tr><td colspan="8" class="admin-empty-state">Tidak ada detail jawaban pada filter ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($answers as $answer): ?>
                                <?php
                                $answerCategory = '';
                                $answerQuestionText = $answer['question'];
                                if (preg_match('/^\[([^\]]{1,60})\]\s*/u', $answerQuestionText, $categoryMatch)) {
                                    $answerCategory = trim($categoryMatch[1]);
                                    $answerQuestionText = preg_replace('/^\[[^\]]{1,60}\]\s*/u', '', $answerQuestionText, 1);
                                }
                                ?>
                                <tr>
                                    <td><?= adminEscape(date('d M Y, H:i', strtotime($answer['answered_at']))) ?></td>
                                    <td><strong><?= adminEscape($answer['user']) ?></strong><small><?= adminEscape($answer['email']) ?></small></td>
                                    <td><?= adminEscape($answer['group']) ?><small>Sesi #<?= $answer['session_id'] ?></small></td>
                                    <td><strong>Soal <?= sprintf('%02d', $answer['order']) ?><?= $answerCategory !== '' ? ' · ' . adminEscape($answerCategory) : '' ?></strong><small><?= nl2br(adminEscape($answerQuestionText)) ?></small></td>
                                    <td><?= $answer['timed_out'] ? 'WAKTU HABIS' : adminEscape($answer['question_type'] === 'esai' ? $answer['text_answer'] : $answer['selected_letter'] . '. ' . $answer['selected_text']) ?></td>
                                    <td><?= adminEscape($answer['question_type'] === 'esai' ? $answer['essay_key'] : $answer['correct_letter'] . '. ' . $answer['correct_text']) ?></td>
                                    <td><span class="admin-status-badge <?= $answer['timed_out'] ? 'status-wrong' : ($answer['correct'] ? 'status-correct' : 'status-wrong') ?>"><?= $answer['timed_out'] ? 'Waktu habis' : ($answer['correct'] ? 'Benar' : 'Salah') ?></span></td>
                                    <td><?= $answer['score'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">
                <?php if ($answerPage > 1): ?><a href="<?= adminEscape(adminPageUrl('jawaban_page', $answerPrevious, 'jawaban')) ?>">← Sebelumnya</a><?php else: ?><span>← Sebelumnya</span><?php endif; ?>
                <small><?= $answerTotal ?> jawaban · <?= $answerPage ?>/<?= $answerPages ?></small>
                <?php if ($answerPage < $answerPages): ?><a href="<?= adminEscape(adminPageUrl('jawaban_page', $answerNext, 'jawaban')) ?>">Berikutnya →</a><?php else: ?><span>Berikutnya →</span><?php endif; ?>
            </div>
        </section>

        <section class="admin-panel admin-activity-panel" id="aktivitas">
            <div class="admin-section-heading">
                <div><p class="admin-eyebrow">Jejak sistem</p><h2>Log Aktivitas User</h2><p>Riwayat daftar, login, soal, kelompok, dan sesi kuis.</p></div>
                <span class="admin-count-pill"><?= $logTotal ?> aktivitas</span>
            </div>
            <form class="admin-filter-form" method="get" action="Admin.php#aktivitas">
                <label class="admin-search-field"><span class="sr-only">Cari user atau detail aktivitas</span><input type="search" name="log_q" value="<?= adminEscape($logSearch) ?>" placeholder="Cari nama user atau detail..."></label>
                <label><span class="sr-only">Jenis aktivitas</span>
                    <select name="aktivitas">
                        <option value="">Semua aktivitas</option>
                        <?php foreach ($activityTypes as $type): ?><option value="<?= adminEscape($type) ?>" <?= $activityFilter === $type ? 'selected' : '' ?>><?= adminEscape($type) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button class="admin-button" type="submit">Terapkan</button>
                <?php if ($logSearch !== '' || $activityFilter !== ''): ?><a class="admin-reset-link" href="Admin.php#aktivitas">Reset</a><?php endif; ?>
            </form>
            <div class="admin-table-wrap">
                <table class="admin-table admin-log-table">
                    <thead><tr><th>Waktu</th><th>User</th><th>Jenis Aktivitas</th><th>Detail</th></tr></thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="4" class="admin-empty-state">Belum ada aktivitas yang sesuai filter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr><td><?= adminEscape(date('d M Y, H:i:s', strtotime($log['date']))) ?></td><td><strong><?= adminEscape($log['user']) ?></strong><small><?= adminEscape($log['email']) ?></small></td><td><span class="admin-activity-tag"><?= adminEscape($log['activity']) ?></span></td><td><?= adminEscape($log['detail'] ?: '-') ?></td></tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">
                <?php if ($logPage > 1): ?><a href="<?= adminEscape(adminPageUrl('log_page', $logPrevious, 'aktivitas')) ?>">← Sebelumnya</a><?php else: ?><span>← Sebelumnya</span><?php endif; ?>
                <small><?= $logTotal ?> aktivitas · <?= $logPage ?>/<?= $logPages ?></small>
                <?php if ($logPage < $logPages): ?><a href="<?= adminEscape(adminPageUrl('log_page', $logNext, 'aktivitas')) ?>">Berikutnya →</a><?php else: ?><span>Berikutnya →</span><?php endif; ?>
            </div>
        </section>

        <section class="admin-panel admin-users-panel" id="pengguna">
            <div class="admin-section-heading">
                <div><p class="admin-eyebrow">Manajemen akun</p><h2>Data Pendaftaran User</h2><p>Profil, alamat lengkap, kelompok, dan pengaturan role akun.</p></div>
                <span class="admin-count-pill"><?= count($users) ?> akun tampil</span>
            </div>
            <form class="admin-filter-form admin-user-search" method="get" action="Admin.php#pengguna">
                <label class="admin-search-field"><span class="sr-only">Cari nama, email, atau daerah</span><input type="search" name="user_q" value="<?= adminEscape($userSearch) ?>" placeholder="Cari nama, email, atau asal daerah..."></label>
                <button class="admin-button" type="submit">Cari User</button>
                <?php if ($userSearch !== ''): ?><a class="admin-reset-link" href="Admin.php#pengguna">Reset</a><?php endif; ?>
            </form>
            <div class="admin-table-wrap">
                <table class="admin-table admin-users-table">
                    <thead><tr><th>Nama / Email</th><th>Role</th><th>Jenis Kelamin</th><th>Alamat Lengkap</th><th>Kelompok</th><th>Tanggal Daftar</th><th>Kelola Role</th></tr></thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7" class="admin-empty-state">Tidak ada akun yang sesuai pencarian.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $account): ?>
                                <?php
                                $addressParts = array_filter(
                                    array(
                                        $account['kelurahan_desa'] ?? '',
                                        $account['district'] ?? '',
                                        $account['city'] ?? '',
                                        $account['province'] ?? '',
                                        $account['country'] ?? ''
                                    ),
                                    function ($part) { return trim((string) $part) !== ''; }
                                );
                                $addressText = !empty($addressParts) ? implode(', ', $addressParts) : ($account['region'] ?: 'Alamat belum diisi');
                                ?>
                                <tr>
                                    <td><strong><?= adminEscape($account['name']) ?></strong><small><?= adminEscape($account['email']) ?></small></td>
                                    <td><span class="admin-role-badge role-<?= adminEscape($account['role']) ?>"><?= adminEscape($account['role']) ?></span></td>
                                    <td><?= adminEscape($account['gender'] ?: '-') ?></td>
                                    <td><strong><?= adminEscape($account['region'] ?: 'Belum diisi') ?></strong><small><?= adminEscape($addressText) ?></small></td>
                                    <td><?= adminEscape($account['group'] ?: 'Belum bergabung') ?></td>
                                    <td><?= adminEscape(date('d M Y, H:i', strtotime($account['created']))) ?></td>
                                    <td>
                                        <?php if ($account['id'] === (int) $_SESSION['akun_id']): ?>
                                            <span class="admin-self-role">Role akun aktif</span>
                                        <?php elseif ($account['role'] === 'admin'): ?>
                                            <span class="admin-self-role">Role admin terkunci</span>
                                        <?php else: ?>
                                            <form class="admin-role-form" action="../Koneksi/admin.php" method="post">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="action" value="change_role">
                                                <input type="hidden" name="user_id" value="<?= $account['id'] ?>">
                                                <input type="hidden" name="user_q" value="<?= adminEscape($userSearch) ?>">
                                                <label class="sr-only" for="role-<?= $account['id'] ?>">Role <?= adminEscape($account['name']) ?></label>
                                                <select id="role-<?= $account['id'] ?>" name="role">
                                                    <option value="user" <?= $account['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                    <option value="pembuat_soal" <?= $account['role'] === 'pembuat_soal' ? 'selected' : '' ?>>Pembuat Soal</option>
                                                </select>
                                                <button class="admin-role-save" type="submit">Simpan</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="admin-footer">Quiz Game · Panel monitoring administrator</footer>
    </main>
</body>
</html>
