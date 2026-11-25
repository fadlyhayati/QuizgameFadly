<?php

session_start();

if (empty($_SESSION['akun_id'])) {
    header("Location: ../navbar.html?error=auth");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'pembuat_soal') {
    $tujuan = ($_SESSION['role'] ?? '') === 'admin'
        ? "Admin.php"
        : "Beranda.php";
    header("Location: " . $tujuan);
    exit();
}

require_once __DIR__ . "/../Koneksi/koneksi.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$creatorId = (int) $_SESSION['akun_id'];
$namaAwal = $_SESSION['nama_lengkap'] ?? 'Pembuat Soal';
$namaLengkap = h($namaAwal);
$avatar = function_exists('mb_substr')
    ? mb_substr($namaAwal, 0, 1, 'UTF-8')
    : substr($namaAwal, 0, 1);
$csrfToken = h($_SESSION['csrf_token']);
$statusFilter = isset($_GET['status']) && is_string($_GET['status']) &&
    in_array($_GET['status'], array('semua', 'aktif', 'arsip'), true)
    ? $_GET['status']
    : 'semua';
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
if (function_exists('mb_substr')) {
    $search = mb_substr($search, 0, 100, 'UTF-8');
} else {
    $search = substr($search, 0, 100);
}
$notice = isset($_GET['notice']) && is_string($_GET['notice']) ? $_GET['notice'] : '';

$stmtStats = mysqli_prepare(
    $conn,
    "SELECT
        SUM(CASE WHEN aktif = 1 THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN aktif = 0 THEN 1 ELSE 0 END) AS arsip
     FROM soal
     WHERE pembuat_id = ?"
);
if (!$stmtStats) {
    http_response_code(500);
    exit("Statistik bank soal tidak dapat dimuat.");
}
mysqli_stmt_bind_param($stmtStats, "i", $creatorId);
mysqli_stmt_execute($stmtStats);
mysqli_stmt_bind_result($stmtStats, $totalAktif, $totalArsip);
mysqli_stmt_fetch($stmtStats);
mysqli_stmt_close($stmtStats);
$totalAktif = (int) $totalAktif;
$totalArsip = (int) $totalArsip;

$soalEdit = null;
$editId = isset($_GET['edit']) ? filter_var($_GET['edit'], FILTER_VALIDATE_INT) : false;
if ($editId !== false && $editId > 0) {
    $editId = (int) $editId;
    $stmtEdit = mysqli_prepare(
        $conn,
        "SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban,
                tipe_soal, jawaban_esai
         FROM soal
         WHERE id = ? AND pembuat_id = ?
         LIMIT 1"
    );
    if (!$stmtEdit) {
        http_response_code(500);
        exit("Data soal tidak dapat dimuat.");
    }

    mysqli_stmt_bind_param($stmtEdit, "ii", $editId, $creatorId);
    mysqli_stmt_execute($stmtEdit);
    mysqli_stmt_bind_result(
        $stmtEdit,
        $dataId,
        $dataPertanyaan,
        $dataOpsiA,
        $dataOpsiB,
        $dataOpsiC,
        $dataOpsiD,
        $dataKunci,
        $dataTipe,
        $dataJawabanEsai
    );
    if (mysqli_stmt_fetch($stmtEdit)) {
        $soalEdit = array(
            'id' => $dataId,
            'pertanyaan' => $dataPertanyaan,
            'opsi_a' => $dataOpsiA,
            'opsi_b' => $dataOpsiB,
            'opsi_c' => $dataOpsiC,
            'opsi_d' => $dataOpsiD,
            'kunci_jawaban' => $dataKunci,
            'tipe_soal' => $dataTipe,
            'jawaban_esai' => $dataJawabanEsai
        );
    }
    mysqli_stmt_close($stmtEdit);

    if ($soalEdit === null) {
        $notice = 'notfound';
    }
} else {
    $editId = 0;
}

$kataKunci = '%' . $search . '%';
$stmtSoal = mysqli_prepare(
    $conn,
    "SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban,
            tipe_soal, jawaban_esai, aktif, updated_at
     FROM soal
     WHERE pembuat_id = ?
       AND (? = 'semua' OR (? = 'aktif' AND aktif = 1) OR (? = 'arsip' AND aktif = 0))
       AND (? = '' OR pertanyaan LIKE ? OR opsi_a LIKE ? OR opsi_b LIKE ? OR opsi_c LIKE ? OR opsi_d LIKE ?)
     ORDER BY updated_at DESC, id DESC"
);
if (!$stmtSoal) {
    http_response_code(500);
    exit("Daftar soal tidak dapat dimuat.");
}
mysqli_stmt_bind_param(
    $stmtSoal,
    "isssssssss",
    $creatorId,
    $statusFilter,
    $statusFilter,
    $statusFilter,
    $search,
    $kataKunci,
    $kataKunci,
    $kataKunci,
    $kataKunci,
    $kataKunci
);
mysqli_stmt_execute($stmtSoal);
mysqli_stmt_bind_result(
    $stmtSoal,
    $rowId,
    $rowPertanyaan,
    $rowOpsiA,
    $rowOpsiB,
    $rowOpsiC,
    $rowOpsiD,
    $rowKunci,
    $rowTipe,
    $rowJawabanEsai,
    $rowAktif,
    $rowUpdated
);
$daftarSoal = array();
while (mysqli_stmt_fetch($stmtSoal)) {
    $daftarSoal[] = array(
        'id' => $rowId,
        'pertanyaan' => $rowPertanyaan,
        'opsi_a' => $rowOpsiA,
        'opsi_b' => $rowOpsiB,
        'opsi_c' => $rowOpsiC,
        'opsi_d' => $rowOpsiD,
        'kunci_jawaban' => $rowKunci,
        'tipe_soal' => $rowTipe,
        'jawaban_esai' => $rowJawabanEsai,
        'aktif' => (int) $rowAktif,
        'updated_at' => $rowUpdated
    );
}
mysqli_stmt_close($stmtSoal);

$pesan = array(
    'created' => array('success', 'Soal berhasil ditambahkan ke bank soal.'),
    'updated' => array('success', 'Perubahan soal berhasil disimpan.'),
    'archived' => array('success', 'Soal berhasil diarsipkan.'),
    'activated' => array('success', 'Soal berhasil diaktifkan kembali.'),
    'invalid' => array('error', 'Data belum valid. Lengkapi pertanyaan dan isian sesuai tipe soal.'),
    'notfound' => array('error', 'Soal tidak ditemukan atau bukan milik akun Anda.'),
    'csrf' => array('error', 'Sesi formulir tidak valid. Muat ulang halaman, lalu coba kembali.'),
    'database' => array('error', 'Perubahan tidak dapat disimpan. Periksa koneksi database dan coba lagi.')
);
$pesanAktif = isset($pesan[$notice]) ? $pesan[$notice] : null;
$nilaiForm = $soalEdit ?: array(
    'pertanyaan' => '',
    'opsi_a' => '',
    'opsi_b' => '',
    'opsi_c' => '',
    'opsi_d' => '',
    'kunci_jawaban' => 'A',
    'tipe_soal' => 'pilihan_ganda',
    'jawaban_esai' => ''
);
$labelFilter = array('semua' => 'Semua', 'aktif' => 'Aktif', 'arsip' => 'Arsip');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css?v=20261126-1">
    <title>Bank Soal | Quiz Game</title>
</head>
<body class="creator-page">
    <header class="creator-header">
        <a class="creator-brand" href="PembuatSoal.php">Quiz<span>Game</span></a>
        <div class="creator-header-actions">
            <div class="creator-user">
                <span class="creator-avatar"><?= h($avatar) ?></span>
                <span>
                    <strong><?= $namaLengkap ?></strong>
                    <small>Pembuat Soal</small>
                </span>
            </div>
            <form action="../Koneksi/auth.php?action=logout" method="post">
                <button class="creator-logout" type="submit">Logout</button>
            </form>
        </div>
    </header>

    <main class="creator-main">
        <section class="creator-welcome">
            <div>
                <p class="creator-eyebrow">Ruang kreator</p>
                <h1>Bank Soal</h1>
                <p>Kelola pertanyaan kuis, atur kunci jawaban, dan simpan soal untuk dimainkan.</p>
            </div>
            <a class="creator-primary-link" href="#form-soal"><?= $soalEdit ? 'Lanjut edit soal' : '+ Tambah soal' ?></a>
        </section>

        <section class="creator-stats" aria-label="Ringkasan bank soal">
            <article class="creator-stat-card">
                <span class="creator-stat-icon creator-icon-active">✓</span>
                <div><small>Soal aktif</small><strong><?= $totalAktif ?></strong></div>
            </article>
            <article class="creator-stat-card">
                <span class="creator-stat-icon creator-icon-archive">▤</span>
                <div><small>Soal diarsipkan</small><strong><?= $totalArsip ?></strong></div>
            </article>
        </section>

        <?php if ($pesanAktif): ?>
            <div class="creator-notice creator-notice-<?= h($pesanAktif[0]) ?>" role="status">
                <?= h($pesanAktif[1]) ?>
            </div>
        <?php endif; ?>

        <div class="creator-layout">
            <section class="creator-panel creator-form-panel" id="form-soal">
                <div class="creator-panel-heading">
                    <div>
                        <p class="creator-eyebrow"><?= $soalEdit ? 'Perbarui bank soal' : 'Mulai berkontribusi' ?></p>
                        <h2><?= $soalEdit ? 'Edit Soal' : 'Tambah Soal Baru' ?></h2>
                    </div>
                    <span class="creator-panel-mark"><?= $soalEdit ? '✎' : '+' ?></span>
                </div>
                <form action="../Koneksi/soal.php" method="post" class="creator-question-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="<?= $soalEdit ? 'update' : 'create' ?>">
                    <input type="hidden" name="soal_id" value="<?= $soalEdit ? (int) $soalEdit['id'] : '' ?>">
                    <input type="hidden" name="filter" value="<?= h($statusFilter) ?>">
                    <input type="hidden" name="q" value="<?= h($search) ?>">

                    <label class="creator-field">
                        <span>Pertanyaan</span>
                        <textarea name="pertanyaan" rows="4" maxlength="5000" placeholder="Tuliskan pertanyaan kuis..." required><?= h($nilaiForm['pertanyaan']) ?></textarea>
                    </label>

                    <label class="creator-field">
                        <span>Tipe Soal</span>
                        <select name="tipe_soal" id="tipeSoal" required>
                            <option value="pilihan_ganda" <?= $nilaiForm['tipe_soal'] === 'pilihan_ganda' ? 'selected' : '' ?>>Pilihan Ganda</option>
                            <option value="esai" <?= $nilaiForm['tipe_soal'] === 'esai' ? 'selected' : '' ?>>Esai (Ketik Jawaban)</option>
                        </select>
                    </label>

                    <div class="creator-options-grid" id="pilihanGandaFields">
                        <label class="creator-field">
                            <span><b class="option-letter">A</b> Opsi A</span>
                            <input type="text" name="opsi_a" maxlength="500" value="<?= h($nilaiForm['opsi_a']) ?>" placeholder="Jawaban pilihan A" required>
                        </label>
                        <label class="creator-field">
                            <span><b class="option-letter">B</b> Opsi B</span>
                            <input type="text" name="opsi_b" maxlength="500" value="<?= h($nilaiForm['opsi_b']) ?>" placeholder="Jawaban pilihan B" required>
                        </label>
                        <label class="creator-field">
                            <span><b class="option-letter">C</b> Opsi C</span>
                            <input type="text" name="opsi_c" maxlength="500" value="<?= h($nilaiForm['opsi_c']) ?>" placeholder="Jawaban pilihan C" required>
                        </label>
                        <label class="creator-field">
                            <span><b class="option-letter">D</b> Opsi D</span>
                            <input type="text" name="opsi_d" maxlength="500" value="<?= h($nilaiForm['opsi_d']) ?>" placeholder="Jawaban pilihan D" required>
                        </label>
                    </div>

                    <label class="creator-field" id="kunciPilihanField">
                        <span>Kunci jawaban benar</span>
                        <select name="kunci_jawaban" required>
                            <?php foreach (array('A', 'B', 'C', 'D') as $huruf): ?>
                                <option value="<?= $huruf ?>" <?= $nilaiForm['kunci_jawaban'] === $huruf ? 'selected' : '' ?>>
                                    <?= $huruf ?><?= $nilaiForm['opsi_' . strtolower($huruf)] !== '' ? ' — ' . h($nilaiForm['opsi_' . strtolower($huruf)]) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="creator-field" id="kunciEsaiField">
                        <span>Kunci Jawaban Esai</span>
                        <input type="text" name="jawaban_esai" maxlength="255" value="<?= h($nilaiForm['jawaban_esai']) ?>" placeholder="Contoh: SELECT, select">
                        <small class="creator-help-text">Pisahkan variasi jawaban yang benar dengan koma.</small>
                    </label>

                    <div class="creator-form-actions">
                        <button class="creator-submit" type="submit"><?= $soalEdit ? 'Simpan Perubahan' : 'Simpan Soal' ?></button>
                        <?php if ($soalEdit): ?>
                            <a class="creator-cancel" href="?status=<?= h($statusFilter) ?>&q=<?= urlencode($search) ?>#bank-soal">Batal edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="creator-panel creator-bank-panel" id="bank-soal">
                <div class="creator-panel-heading bank-heading">
                    <div>
                        <p class="creator-eyebrow">Koleksi pertanyaan</p>
                        <h2>Daftar Soal</h2>
                    </div>
                    <span class="creator-count"><?= count($daftarSoal) ?> ditampilkan</span>
                </div>

                <form class="creator-search" method="get" action="PembuatSoal.php">
                    <label class="sr-only" for="searchQuestion">Cari soal</label>
                    <input id="searchQuestion" type="search" name="q" value="<?= h($search) ?>" maxlength="100" placeholder="Cari pertanyaan atau opsi...">
                    <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
                    <button type="submit" aria-label="Cari soal">Cari</button>
                    <?php if ($search !== ''): ?>
                        <a class="creator-search-reset" href="?status=<?= h($statusFilter) ?>#bank-soal">Reset</a>
                    <?php endif; ?>
                </form>

                <nav class="creator-filters" aria-label="Filter status soal">
                    <?php foreach ($labelFilter as $key => $label): ?>
                        <a class="<?= $statusFilter === $key ? 'is-selected' : '' ?>"
                           href="?status=<?= $key ?>&q=<?= urlencode($search) ?>#bank-soal"><?= $label ?>
                            <?php if ($key === 'aktif'): ?><span><?= $totalAktif ?></span><?php endif; ?>
                            <?php if ($key === 'arsip'): ?><span><?= $totalArsip ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <?php if (empty($daftarSoal)): ?>
                    <div class="creator-empty">
                        <span>✦</span>
                        <h3>Belum ada soal di tampilan ini</h3>
                        <p>Tambahkan pertanyaan baru atau ubah filter pencarian Anda.</p>
                    </div>
                <?php else: ?>
                    <div class="creator-question-list">
                        <?php foreach ($daftarSoal as $soal): ?>
                            <article class="creator-question-card">
                                <div class="creator-question-top">
                                    <span class="creator-question-id">SOAL #<?= (int) $soal['id'] ?></span>
                                    <span class="creator-type-badge"><?= $soal['tipe_soal'] === 'esai' ? 'Esai' : 'Pilihan Ganda' ?></span>
                                    <span class="creator-status <?= $soal['aktif'] ? 'status-active' : 'status-archived' ?>">
                                        <?= $soal['aktif'] ? 'Aktif' : 'Diarsipkan' ?>
                                    </span>
                                </div>
                                <h3><?= nl2br(h($soal['pertanyaan'])) ?></h3>
                                <?php if ($soal['tipe_soal'] === 'esai'): ?>
                                    <div class="creator-essay-key"><strong>Kunci esai:</strong> <?= h($soal['jawaban_esai']) ?></div>
                                <?php else: ?>
                                    <div class="creator-answer-grid">
                                        <?php foreach (array('A' => 'opsi_a', 'B' => 'opsi_b', 'C' => 'opsi_c', 'D' => 'opsi_d') as $huruf => $kolom): ?>
                                            <div class="creator-answer <?= $soal['kunci_jawaban'] === $huruf ? 'answer-correct' : '' ?>">
                                                <b><?= $huruf ?></b>
                                                <span><?= h($soal[$kolom]) ?></span>
                                                <?php if ($soal['kunci_jawaban'] === $huruf): ?>
                                                    <em>Kunci benar</em>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="creator-question-footer">
                                    <small>Diperbarui <?= h(date('d M Y, H:i', strtotime($soal['updated_at']))) ?></small>
                                    <div class="creator-question-actions">
                                        <a class="creator-edit" href="?status=<?= h($statusFilter) ?>&q=<?= urlencode($search) ?>&edit=<?= (int) $soal['id'] ?>#form-soal">Edit</a>
                                        <form action="../Koneksi/soal.php" method="post" onsubmit="return confirm('<?= $soal['aktif'] ? 'Arsipkan' : 'Aktifkan kembali' ?> soal ini?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="soal_id" value="<?= (int) $soal['id'] ?>">
                                            <input type="hidden" name="filter" value="<?= h($statusFilter) ?>">
                                            <input type="hidden" name="q" value="<?= h($search) ?>">
                                            <input type="hidden" name="action" value="<?= $soal['aktif'] ? 'archive' : 'activate' ?>">
                                            <button class="<?= $soal['aktif'] ? 'creator-archive' : 'creator-activate' ?>" type="submit">
                                                <?= $soal['aktif'] ? 'Arsipkan' : 'Aktifkan' ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
<script>
    (function () {
        var typeSelect = document.getElementById('tipeSoal');
        var choices = document.getElementById('pilihanGandaFields');
        var choiceKey = document.getElementById('kunciPilihanField');
        var essayKey = document.getElementById('kunciEsaiField');
        var optionInputs = choices.querySelectorAll('input');
        var choiceKeySelect = choiceKey.querySelector('select');
        var essayInput = essayKey.querySelector('input');

        function updateQuestionType() {
            var isEssay = typeSelect.value === 'esai';
            choices.hidden = isEssay;
            choiceKey.hidden = isEssay;
            essayKey.hidden = !isEssay;
            optionInputs.forEach(function (input) { input.required = !isEssay; });
            choiceKeySelect.required = !isEssay;
            essayInput.required = isEssay;
        }

        typeSelect.addEventListener('change', updateQuestionType);
        updateQuestionType();
    }());
</script>
</body>
</html>
