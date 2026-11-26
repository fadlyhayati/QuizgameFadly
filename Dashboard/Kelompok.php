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
    exit("Database kelompok dan permainan belum siap.");
}

$userId = (int) $_SESSION['akun_id'];
$csrf = escapeKuis(tokenCsrfKuis());
$profileNickname = null;
$profileAvatarJson = null;
$stmtProfile = mysqli_prepare(
    $conn,
    "SELECT nama_panggilan, avatar_config FROM users WHERE id = ? LIMIT 1"
);
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
$_SESSION['nama_panggilan'] = $profileNickname;
$group = null;
$members = array();
$activeSessions = array();

$stmtGroup = mysqli_prepare(
    $conn,
    "SELECT k.id, k.nama_kelompok, k.kode_gabung, u.nama_lengkap,
            (SELECT COUNT(*) FROM kelompok_anggota ka2 WHERE ka2.kelompok_id = k.id)
     FROM kelompok_anggota ka
     JOIN kelompok k ON k.id = ka.kelompok_id
     JOIN users u ON u.id = k.dibuat_oleh
     WHERE ka.user_id = ?
     LIMIT 1"
);
if (!$stmtGroup) {
    http_response_code(500);
    exit("Informasi kelompok tidak dapat dimuat.");
}
mysqli_stmt_bind_param($stmtGroup, "i", $userId);
mysqli_stmt_execute($stmtGroup);
mysqli_stmt_bind_result($stmtGroup, $groupId, $groupName, $groupCode, $groupCreator, $groupMemberCount);
if (mysqli_stmt_fetch($stmtGroup)) {
    $group = array(
        'id' => (int) $groupId,
        'name' => $groupName,
        'code' => $groupCode,
        'creator' => $groupCreator,
        'member_count' => (int) $groupMemberCount
    );
}
mysqli_stmt_close($stmtGroup);

if ($group !== null) {
    $stmtMembers = mysqli_prepare(
        $conn,
        "SELECT u.id, u.nama_lengkap, u.asal_daerah, ka.bergabung_pada,
                (u.id = k.dibuat_oleh) AS pembuat, u.nama_panggilan, u.avatar_config
         FROM kelompok_anggota ka
         JOIN users u ON u.id = ka.user_id
         JOIN kelompok k ON k.id = ka.kelompok_id
         WHERE ka.kelompok_id = ?
         ORDER BY pembuat DESC, u.nama_lengkap"
    );
    if (!$stmtMembers) {
        http_response_code(500);
        exit("Daftar anggota tidak dapat dimuat.");
    }
    mysqli_stmt_bind_param($stmtMembers, "i", $group['id']);
    mysqli_stmt_execute($stmtMembers);
    mysqli_stmt_bind_result(
        $stmtMembers,
        $memberId,
        $memberName,
        $memberRegion,
        $joinedAt,
        $isCreator,
        $memberNickname,
        $memberAvatarJson
    );
    while (mysqli_stmt_fetch($stmtMembers)) {
        $members[] = array(
            'name' => $memberNickname ?: $memberName,
            'avatar_config' => avatarNormalizeConfig($memberAvatarJson),
            'region' => $memberRegion,
            'joined' => $joinedAt,
            'creator' => (bool) $isCreator
        );
    }
    mysqli_stmt_close($stmtMembers);

    $stmtSessions = mysqli_prepare(
        $conn,
        "SELECT s.id, s.dimulai_pada, u.nama_lengkap,
                COUNT(DISTINCT p.user_id) AS jumlah_peserta,
                saya.status AS status_saya
         FROM sesi_kuis s
         JOIN users u ON u.id = s.dimulai_oleh
         LEFT JOIN sesi_kuis_peserta p ON p.sesi_kuis_id = s.id
         LEFT JOIN sesi_kuis_peserta saya
            ON saya.sesi_kuis_id = s.id AND saya.user_id = ?
         WHERE s.kelompok_id = ? AND s.status = 'berlangsung'
         GROUP BY s.id, s.dimulai_pada, u.nama_lengkap, saya.status
         ORDER BY s.dimulai_pada DESC
         LIMIT 5"
    );
    if (!$stmtSessions) {
        http_response_code(500);
        exit("Sesi kelompok tidak dapat dimuat.");
    }
    mysqli_stmt_bind_param($stmtSessions, "ii", $userId, $group['id']);
    mysqli_stmt_execute($stmtSessions);
    mysqli_stmt_bind_result($stmtSessions, $sessionId, $startedAt, $starterName, $participantCount, $myStatus);
    while (mysqli_stmt_fetch($stmtSessions)) {
        $activeSessions[] = array(
            'id' => (int) $sessionId,
            'started' => $startedAt,
            'starter' => $starterName,
            'participants' => (int) $participantCount,
            'my_status' => $myStatus
        );
    }
    mysqli_stmt_close($stmtSessions);
}

$groups = array();
if ($group === null) {
    $resultGroups = mysqli_query(
        $conn,
        "SELECT k.id, k.nama_kelompok, k.kode_gabung, u.nama_lengkap,
                COUNT(ka.user_id) AS jumlah_anggota
         FROM kelompok k
         JOIN users u ON u.id = k.dibuat_oleh
         LEFT JOIN kelompok_anggota ka ON ka.kelompok_id = k.id
         GROUP BY k.id, k.nama_kelompok, k.kode_gabung, u.nama_lengkap
         ORDER BY jumlah_anggota DESC, k.created_at DESC
         LIMIT 30"
    );
    if (!$resultGroups) {
        http_response_code(500);
        exit("Daftar kelompok tidak dapat dimuat.");
    }
    while ($row = mysqli_fetch_assoc($resultGroups)) {
        $groups[] = $row;
    }
    mysqli_free_result($resultGroups);
}

$notice = isset($_GET['notice']) && is_string($_GET['notice']) ? $_GET['notice'] : '';
$notices = array(
    'group_created' => array('success', 'Kelompok berhasil dibuat. Bagikan kode gabung kepada anggota.'),
    'group_joined' => array('success', 'Anda berhasil bergabung ke kelompok.'),
    'already_group' => array('error', 'Akun Anda sudah menjadi anggota sebuah kelompok.'),
    'invalid_group' => array('error', 'Nama kelompok wajib diisi dan maksimal 120 karakter.'),
    'group_not_found' => array('error', 'Kelompok atau kode gabung tidak ditemukan. Periksa kembali datanya.'),
    'not_in_group' => array('error', 'Anda harus menjadi anggota kelompok untuk memulai sesi.'),
    'session_unavailable' => array('error', 'Sesi tidak tersedia atau bukan sesi kelompok Anda.'),
    'no_questions' => array('error', 'Belum ada soal aktif. Minta Pembuat Soal menambahkan soal terlebih dahulu.'),
    'csrf' => array('error', 'Sesi formulir tidak valid. Muat ulang halaman, lalu coba kembali.'),
    'database' => array('error', 'Permintaan belum dapat diproses. Periksa koneksi database dan coba lagi.')
);
$message = isset($notices[$notice]) ? $notices[$notice] : null;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css?v=20261126-2">
    <title>Kelompok | Quiz Game</title>
</head>
<body class="player-page">
    <header class="player-header">
        <a class="player-brand" href="Beranda.php">Quiz<span>Game</span></a>
        <nav class="player-nav">
            <a href="Beranda.php">Beranda</a>
            <a class="is-current" href="Kelompok.php">Kelompok</a>
        </nav>
        <div class="player-header-actions">
            <div class="player-user">
                <span class="player-avatar player-avatar-render"><?= avatarSvgHtml($avatarConfig, 'player-avatar-art', 'Avatar ' . $namaPanggilan) ?></span>
                <span><strong><?= escapeKuis($namaPanggilan) ?></strong><small>Pemain</small></span>
            </div>
            <button class="player-profile-button" type="button" id="openAvatarModal">Avatar Saya</button>
            <form action="../Koneksi/auth.php?action=logout" method="post">
                <button class="player-logout" type="submit">Logout</button>
            </form>
        </div>
    </header>

    <main class="player-main">
        <section class="player-page-heading">
            <p class="player-eyebrow">Makin seru bersama</p>
            <h1>Kelompok <span>permainan</span></h1>
            <p>Buat tim sendiri atau bergabung dengan teman menggunakan kode kelompok.</p>
        </section>

        <?php if ($message): ?>
            <div class="player-notice notice-<?= escapeKuis($message[0]) ?>" role="status"><?= escapeKuis($message[1]) ?></div>
        <?php endif; ?>

        <?php if ($group !== null): ?>
            <section class="team-overview">
                <div class="team-overview-top">
                    <div class="team-emblem">♧</div>
                    <div class="team-overview-copy">
                        <p class="player-eyebrow">Kelompok aktif</p>
                        <h2><?= escapeKuis($group['name']) ?></h2>
                        <p><?= $group['member_count'] ?> anggota · dibuat oleh <?= escapeKuis($group['creator']) ?></p>
                    </div>
                    <div class="team-code-box"><small>KODE GABUNG</small><strong><?= escapeKuis($group['code']) ?></strong></div>
                </div>
                <form action="../Koneksi/kuis.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="start_group_quiz">
                    <input type="hidden" name="kelompok_id" value="<?= $group['id'] ?>">
                    <button class="player-primary-button" type="submit">▶ Mulai Sesi Kuis Baru</button>
                </form>
            </section>

            <div class="team-columns">
                <section class="player-panel">
                    <div class="player-section-heading">
                        <div><p class="player-eyebrow">Tim kamu</p><h2>Anggota Kelompok</h2></div>
                        <span class="team-count"><?= count($members) ?> orang</span>
                    </div>
                    <div class="team-member-list">
                        <?php foreach ($members as $index => $member): ?>
                            <div class="team-member">
                                <span class="member-avatar member-avatar-render"><?= avatarSvgHtml($member['avatar_config'], 'member-avatar-art', 'Avatar ' . $member['name']) ?></span>
                                <span class="member-details"><strong><?= escapeKuis($member['name']) ?></strong><small><?= escapeKuis($member['region'] ?: 'Daerah belum diisi') ?></small></span>
                                <?php if ($member['creator']): ?><span class="member-leader">Ketua</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="player-panel">
                    <div class="player-section-heading">
                        <div><p class="player-eyebrow">Ikut bermain</p><h2>Sesi Kelompok</h2></div>
                        <span class="team-count"><?= count($activeSessions) ?> berlangsung</span>
                    </div>
                    <?php if (empty($activeSessions)): ?>
                        <div class="team-session-empty"><span>✦</span><p>Belum ada sesi aktif. Mulai sesi baru dan undang anggota untuk bergabung.</p></div>
                    <?php else: ?>
                        <div class="team-session-list">
                            <?php foreach ($activeSessions as $session): ?>
                                <article class="team-session">
                                    <div><strong>Sesi #<?= $session['id'] ?></strong><small>Dimulai oleh <?= escapeKuis($session['starter']) ?> · <?= $session['participants'] ?> peserta</small></div>
                                    <?php if ($session['my_status'] === 'selesai'): ?>
                                        <a class="team-session-result" href="Hasil.php?sesi=<?= $session['id'] ?>">Lihat hasil</a>
                                    <?php else: ?>
                                        <form action="../Koneksi/kuis.php" method="post">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="action" value="join_session">
                                            <input type="hidden" name="sesi_kuis_id" value="<?= $session['id'] ?>">
                                            <button class="team-session-join" type="submit"><?= $session['my_status'] ? 'Lanjutkan' : 'Gabung' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        <?php else: ?>
            <div class="team-join-layout">
                <section class="player-panel team-create-panel">
                    <span class="team-panel-icon">✦</span>
                    <p class="player-eyebrow">Jadi kapten</p>
                    <h2>Buat Kelompok Baru</h2>
                    <p>Mulai tim kamu sendiri. Kode gabung unik akan dibuat otomatis dan bisa dibagikan ke teman.</p>
                    <form action="../Koneksi/kuis.php" method="post" class="team-create-form">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="create_group">
                        <label for="namaKelompok">Nama kelompok</label>
                        <input id="namaKelompok" name="nama_kelompok" type="text" maxlength="120" placeholder="Contoh: Tim Cerdas" required>
                        <button class="player-primary-button" type="submit">Buat Kelompok</button>
                    </form>
                </section>

                <section class="player-panel team-join-panel">
                    <span class="team-panel-icon team-icon-alt">♧</span>
                    <p class="player-eyebrow">Sudah punya tim?</p>
                    <h2>Gabung dengan Kode</h2>
                    <p>Masukkan kode yang dibagikan oleh ketua kelompok.</p>
                    <form action="../Koneksi/kuis.php" method="post" class="team-code-form">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="join_code">
                        <label class="sr-only" for="kodeGabung">Kode gabung</label>
                        <input id="kodeGabung" name="kode_gabung" type="text" maxlength="8" minlength="8" pattern="[A-HJ-NP-Z2-9]{8}" placeholder="CONTOH: QZ8K4MNP" required>
                        <button class="player-primary-button" type="submit">Gabung</button>
                    </form>
                </section>
            </div>

            <section class="player-panel public-groups-panel">
                <div class="player-section-heading">
                    <div><p class="player-eyebrow">Temukan teman bermain</p><h2>Daftar Kelompok</h2></div>
                    <span class="team-count"><?= count($groups) ?> kelompok</span>
                </div>
                <?php if (empty($groups)): ?>
                    <div class="team-session-empty"><span>✦</span><p>Belum ada kelompok. Jadilah yang pertama membuat tim!</p></div>
                <?php else: ?>
                    <div class="public-group-grid">
                        <?php foreach ($groups as $publicGroup): ?>
                            <article class="public-group-card">
                                <span class="public-group-icon">♧</span>
                                <h3><?= escapeKuis($publicGroup['nama_kelompok']) ?></h3>
                                <p><?= (int) $publicGroup['jumlah_anggota'] ?> anggota · ketua <?= escapeKuis($publicGroup['nama_lengkap']) ?></p>
                                <form action="../Koneksi/kuis.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="join_group">
                                    <input type="hidden" name="kelompok_id" value="<?= (int) $publicGroup['id'] ?>">
                                    <button class="public-group-join" type="submit">Gabung Kelompok</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php require __DIR__ . "/AvatarModal.php"; ?>
    </main>
    <script src="../avatar.js?v=20261126-2" defer></script>
</body>
</html>
