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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function playerEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$userId = (int) $_SESSION['akun_id'];
$namaLengkap = $_SESSION['nama_lengkap'] ?? 'Pemain';
$csrf = playerEscape($_SESSION['csrf_token']);
$lengkapiProfilGoogle = !empty($_SESSION['google_profile_incomplete']);

$groupId = 0;
$groupName = '';
$groupCode = '';
$groupMembers = 0;
$asalDaerah = 'Belum diisi';
$stmtLocation = mysqli_prepare(
    $conn,
    "SELECT asal_daerah, nama_panggilan, avatar_config FROM users WHERE id = ? LIMIT 1"
);
if (!$stmtLocation) {
    http_response_code(500);
    exit("Profil pemain tidak dapat dimuat.");
}
mysqli_stmt_bind_param($stmtLocation, "i", $userId);
mysqli_stmt_execute($stmtLocation);
mysqli_stmt_bind_result($stmtLocation, $asalDaerahResult, $nicknameResult, $avatarConfigResult);
mysqli_stmt_fetch($stmtLocation);
if ($asalDaerahResult !== '') {
    $asalDaerah = $asalDaerahResult;
}
mysqli_stmt_close($stmtLocation);
$namaPanggilan = $nicknameResult ?: $namaLengkap;
$nama = playerEscape($namaPanggilan);
$avatarConfig = avatarNormalizeConfig($avatarConfigResult);
$_SESSION['nama_panggilan'] = $nicknameResult;

$stmtGroup = mysqli_prepare(
    $conn,
    "SELECT k.id, k.nama_kelompok, k.kode_gabung,
            (SELECT COUNT(*) FROM kelompok_anggota ka2 WHERE ka2.kelompok_id = k.id) AS jumlah_anggota
     FROM kelompok_anggota ka
     JOIN kelompok k ON k.id = ka.kelompok_id
     WHERE ka.user_id = ?
     LIMIT 1"
);
if (!$stmtGroup) {
    http_response_code(500);
    exit("Status kelompok tidak dapat dimuat.");
}
mysqli_stmt_bind_param($stmtGroup, "i", $userId);
mysqli_stmt_execute($stmtGroup);
mysqli_stmt_bind_result($stmtGroup, $groupIdResult, $groupNameResult, $groupCodeResult, $groupMembersResult);
if (mysqli_stmt_fetch($stmtGroup)) {
    $groupId = (int) $groupIdResult;
    $groupName = $groupNameResult;
    $groupCode = $groupCodeResult;
    $groupMembers = (int) $groupMembersResult;
}
mysqli_stmt_close($stmtGroup);

$stmtStats = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_sesi,
            COALESCE(MAX(skor), 0) AS skor_terbaik,
            COALESCE(AVG(skor), 0) AS skor_rata
     FROM (
        SELECT p.sesi_kuis_id, COALESCE(SUM(j.skor), 0) AS skor
        FROM sesi_kuis_peserta p
        LEFT JOIN jawaban_user j
            ON j.sesi_kuis_id = p.sesi_kuis_id AND j.user_id = p.user_id
        WHERE p.user_id = ? AND p.status = 'selesai'
        GROUP BY p.sesi_kuis_id
     ) hasil"
);
if (!$stmtStats) {
    http_response_code(500);
    exit("Statistik bermain tidak dapat dimuat.");
}
mysqli_stmt_bind_param($stmtStats, "i", $userId);
mysqli_stmt_execute($stmtStats);
mysqli_stmt_bind_result($stmtStats, $totalSesi, $skorTerbaik, $skorRata);
mysqli_stmt_fetch($stmtStats);
mysqli_stmt_close($stmtStats);

$hasilTerbaru = array();
$stmtRecent = mysqli_prepare(
    $conn,
    "SELECT p.sesi_kuis_id, COALESCE(k.nama_kelompok, 'Individu (Solo)'), p.selesai_pada,
            COALESCE(SUM(j.skor), 0) AS skor,
            COALESCE(SUM(CASE WHEN j.benar = 1 THEN 1 ELSE 0 END), 0) AS benar
     FROM sesi_kuis_peserta p
     JOIN sesi_kuis s ON s.id = p.sesi_kuis_id
     LEFT JOIN kelompok k ON k.id = s.kelompok_id
     LEFT JOIN jawaban_user j
        ON j.sesi_kuis_id = p.sesi_kuis_id AND j.user_id = p.user_id
     WHERE p.user_id = ? AND p.status = 'selesai'
     GROUP BY p.sesi_kuis_id, k.nama_kelompok, p.selesai_pada
     ORDER BY p.selesai_pada DESC
     LIMIT 3"
);
if (!$stmtRecent) {
    http_response_code(500);
    exit("Riwayat permainan tidak dapat dimuat.");
}
mysqli_stmt_bind_param($stmtRecent, "i", $userId);
mysqli_stmt_execute($stmtRecent);
mysqli_stmt_bind_result($stmtRecent, $recentSession, $recentGroup, $recentFinished, $recentScore, $recentCorrect);
while (mysqli_stmt_fetch($stmtRecent)) {
    $hasilTerbaru[] = array(
        'session_id' => (int) $recentSession,
        'group' => $recentGroup,
        'finished' => $recentFinished,
        'score' => (int) $recentScore,
        'correct' => (int) $recentCorrect
    );
}
mysqli_stmt_close($stmtRecent);

$notice = isset($_GET['notice']) && is_string($_GET['notice']) ? $_GET['notice'] : '';
$notices = array(
    'group_created' => 'Kelompok berhasil dibuat. Bagikan kode gabung kepada anggota.',
    'group_joined' => 'Anda berhasil bergabung ke kelompok.',
    'already_group' => 'Akun Anda sudah menjadi anggota sebuah kelompok.',
    'no_questions' => 'Belum ada soal aktif. Minta Pembuat Soal menambahkan soal terlebih dahulu.',
    'database' => 'Permintaan belum dapat diproses. Periksa koneksi database dan coba lagi.',
    'csrf' => 'Sesi formulir tidak valid. Muat ulang halaman, lalu coba kembali.'
);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css?v=20261126-2">
    <title>Beranda Pemain | Quiz Game</title>
</head>
<body class="player-page">
    <header class="player-header">
        <a class="player-brand" href="Beranda.php">Quiz<span>Game</span></a>
        <div class="player-header-actions">
            <div class="player-user">
                <span class="player-avatar player-avatar-render"><?= avatarSvgHtml($avatarConfig, 'player-avatar-art', 'Avatar ' . $namaPanggilan) ?></span>
                <span><strong><?= $nama ?></strong><small><?= playerEscape($_SESSION['email'] ?? '') ?></small></span>
            </div>
            <button class="player-profile-button" type="button" id="openAvatarModal">Avatar Saya</button>
            <form action="../Koneksi/auth.php?action=logout" method="post">
                <button class="player-logout" type="submit">Logout</button>
            </form>
        </div>
    </header>

    <main class="player-main">
        <?php if (isset($notices[$notice])): ?>
            <div class="player-notice" role="status"><?= playerEscape($notices[$notice]) ?></div>
        <?php endif; ?>

        <section class="player-welcome">
            <div>
                <p class="player-eyebrow">Area pemain</p>
                <h1>Hai, <?= $nama ?> <span class="wave">✦</span></h1>
                <p>Asal daerah: <strong><?= playerEscape($asalDaerah) ?></strong>. Siap menguji wawasan hari ini?</p>
            </div>
            <div class="welcome-orbit" aria-hidden="true"><span>Q</span><i>✦</i></div>
        </section>

        <section class="player-stats" aria-label="Statistik bermain">
            <article class="player-stat">
                <span class="player-stat-symbol">◷</span>
                <div><small>Total sesi selesai</small><strong><?= (int) $totalSesi ?></strong></div>
            </article>
            <article class="player-stat">
                <span class="player-stat-symbol symbol-gold">★</span>
                <div><small>Skor terbaik</small><strong><?= (int) $skorTerbaik ?><em> poin</em></strong></div>
            </article>
            <article class="player-stat">
                <span class="player-stat-symbol symbol-blue">↗</span>
                <div><small>Rata-rata skor</small><strong><?= (int) round((float) $skorRata) ?><em> poin</em></strong></div>
            </article>
        </section>

        <section class="player-group-card">
            <div class="player-group-info">
                <span class="player-group-icon">♧</span>
                <div>
                    <p class="player-eyebrow">Kelompok kamu</p>
                    <?php if ($groupId > 0): ?>
                        <h2><?= playerEscape($groupName) ?></h2>
                        <p><?= $groupMembers ?> anggota <span class="group-code">Kode: <?= playerEscape($groupCode) ?></span></p>
                    <?php else: ?>
                        <h2>Belum bergabung kelompok</h2>
                        <p>Buat kelompok baru atau gabung dengan kode temanmu untuk bermain bersama.</p>
                    <?php endif; ?>
                </div>
            </div>
            <a class="player-outline-button" href="Kelompok.php"><?= $groupId > 0 ? 'Lihat Kelompok' : 'Pilih Kelompok' ?></a>
        </section>

        <section class="player-quick-links">
            <a class="player-quick-card quick-group" href="Kelompok.php">
                <span class="quick-card-icon">♧</span>
                <span class="quick-card-copy"><strong>Kelompok</strong><small>Kelola anggota dan sesi bersama</small></span>
                <span class="quick-arrow">↗</span>
            </a>
            <?php if ($groupId > 0): ?>
                <form class="player-quick-form" action="../Koneksi/kuis.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="start_group_quiz">
                    <input type="hidden" name="kelompok_id" value="<?= $groupId ?>">
                    <button class="player-quick-card quick-play" type="submit">
                        <span class="quick-card-icon">▶</span>
                        <span class="quick-card-copy"><strong>Mulai Game Kuis</strong><small>Jelajahi soal Pilihan Ganda &amp; Esai</small></span>
                        <span class="quick-arrow">↗</span>
                    </button>
                </form>
            <?php else: ?>
                <form class="player-quick-form" action="../Koneksi/kuis.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="start_solo_quiz">
                    <button class="player-quick-card quick-play" type="submit">
                        <span class="quick-card-icon">▶</span>
                        <span class="quick-card-copy"><strong>Mulai Game Kuis</strong><small>Bermain sendiri di peta kampus</small></span>
                        <span class="quick-arrow">↗</span>
                    </button>
                </form>
            <?php endif; ?>
            <a class="player-quick-card quick-history" href="Hasil.php?riwayat=1">
                <span class="quick-card-icon">▤</span>
                <span class="quick-card-copy"><strong>Riwayat Hasil</strong><small>Lihat skor dan review permainan</small></span>
                <span class="quick-arrow">↗</span>
            </a>
        </section>

        <section class="player-recent">
            <div class="player-section-heading">
                <div><p class="player-eyebrow">Perjalananmu</p><h2>Hasil Terbaru</h2></div>
                <a href="Hasil.php?riwayat=1">Lihat semua</a>
            </div>
            <?php if (empty($hasilTerbaru)): ?>
                <div class="player-empty">
                    <span>✦</span>
                    <p>Belum ada hasil kuis. Bergabunglah ke kelompok dan mulai permainan pertamamu!</p>
                </div>
            <?php else: ?>
                <div class="player-recent-list">
                    <?php foreach ($hasilTerbaru as $hasil): ?>
                        <a class="player-recent-row" href="Hasil.php?sesi=<?= $hasil['session_id'] ?>">
                            <span class="recent-score"><?= $hasil['score'] ?><small>poin</small></span>
                            <span class="recent-info"><strong><?= playerEscape($hasil['group']) ?></strong><small><?= $hasil['correct'] ?> jawaban benar · <?= playerEscape(date('d M Y, H:i', strtotime($hasil['finished']))) ?></small></span>
                            <span class="quick-arrow">↗</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php require __DIR__ . "/AvatarModal.php"; ?>
    </main>
    <script src="../avatar.js?v=20261126-2" defer></script>
    <?php if ($lengkapiProfilGoogle): ?>
        <div class="login-modal" id="googleProfileModal" role="dialog" aria-modal="true" aria-labelledby="googleProfileTitle">
            <section class="login-box register-box">
                <h2 id="googleProfileTitle">Lengkapi Profil</h2>
                <p class="address-api-message">Lengkapi data jenis kelamin dan asal daerah agar informasi sebaran pemain tercatat dengan benar.</p>
                <form class="register-form" id="googleProfileForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="form-group">
                        <label for="googleProfileGender">Jenis Kelamin</label>
                        <select id="googleProfileGender" name="jenis_kelamin" required>
                            <option value="">Pilih jenis kelamin</option>
                            <option value="Laki-laki">Laki-laki</option>
                            <option value="Perempuan">Perempuan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="googleProfileCountry">Negara</label>
                        <select id="googleProfileCountry" name="negara" required>
                            <option value="Indonesia" selected>Indonesia</option>
                            <option value="Malaysia">Malaysia</option>
                            <option value="Singapura">Singapura</option>
                            <option value="Brunei Darussalam">Brunei Darussalam</option>
                            <option value="Timor-Leste">Timor-Leste</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <p class="address-api-message" id="googleProfileRegionMessage" role="status" hidden></p>
                    <div class="form-group">
                        <label for="googleProfileProvince">Provinsi</label>
                        <select id="googleProfileProvince" name="provinsi" required disabled>
                            <option value="">Memuat provinsi...</option>
                        </select>
                        <input type="text" id="googleProfileProvinceManual" maxlength="100" hidden disabled>
                    </div>
                    <div class="form-group">
                        <label for="googleProfileCity">Kabupaten / Kota</label>
                        <select id="googleProfileCity" name="kabupaten_kota" required disabled>
                            <option value="">Pilih provinsi terlebih dahulu</option>
                        </select>
                        <input type="text" id="googleProfileCityManual" maxlength="100" hidden disabled>
                    </div>
                    <div class="form-group">
                        <label for="googleProfileDistrict">Kecamatan</label>
                        <select id="googleProfileDistrict" name="kecamatan" required disabled>
                            <option value="">Pilih kabupaten / kota terlebih dahulu</option>
                        </select>
                        <input type="text" id="googleProfileDistrictManual" maxlength="100" hidden disabled>
                    </div>
                    <div class="form-group">
                        <label for="googleProfileVillage">Kelurahan / Desa</label>
                        <select id="googleProfileVillage" name="kelurahan_desa" required disabled>
                            <option value="">Pilih kecamatan terlebih dahulu</option>
                        </select>
                        <input type="text" id="googleProfileVillageManual" maxlength="100" hidden disabled>
                    </div>
                    <button class="text-button manual-address-button" id="googleProfileManualButton" type="button">Isi alamat secara manual</button>
                    <button class="submit-button" type="submit">Simpan Profil</button>
                    <p class="login-message" id="googleProfileMessage" role="alert" aria-live="polite" hidden></p>
                </form>
            </section>
        </div>
        <script src="../profile-completion.js?v=20261126-1" defer></script>
    <?php endif; ?>
</body>
</html>
