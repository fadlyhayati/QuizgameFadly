<?php

function avatarDefaultConfig()
{
    return array(
        'skin' => '#f2c6a0',
        'hair_style' => 'short',
        'hair_color' => '#38234f',
        'shirt_style' => 'tshirt',
        'shirt_color' => '#a78bfa',
        'pants_color' => '#5266b8',
        'accessory' => 'none'
    );
}

function avatarNormalizeConfig($value)
{
    $defaults = avatarDefaultConfig();
    $decoded = is_string($value) ? json_decode($value, true) : $value;
    if (!is_array($decoded)) {
        return $defaults;
    }

    $allowed = array(
        'skin' => array('#f2c6a0', '#e6ae82', '#bd805e', '#85543f'),
        'hair_style' => array('short', 'side', 'spiky', 'bob', 'long', 'pony', 'curly', 'hijab'),
        'hair_color' => array('#38234f', '#171a2f', '#9c4f45', '#d6a64f', '#5b3b2e', '#e7d4a3', '#d66c91', '#536b9c'),
        'shirt_style' => array('tshirt', 'shirt', 'hoodie', 'jacket'),
        'shirt_color' => array('#a78bfa', '#f08caf', '#70c8d6', '#f0b45d', '#72bd91', '#e76f51', '#5266b8', '#e6e8f0', '#34364f', '#b34f68'),
        'pants_color' => array('#5266b8', '#536276', '#684d87', '#387f78', '#222b3c', '#9b6b46'),
        'accessory' => array('none', 'glasses', 'hat', 'headphones', 'bow', 'crown', 'bandana')
    );

    foreach ($allowed as $key => $values) {
        if (isset($decoded[$key]) && is_string($decoded[$key]) && in_array($decoded[$key], $values, true)) {
            $defaults[$key] = $decoded[$key];
        }
    }

    return $defaults;
}

function avatarSvgHtml($config, $className, $label)
{
    $config = avatarNormalizeConfig($config);
    $configJson = json_encode(
        $config,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    if ($configJson === false) {
        throw new RuntimeException('Pengaturan avatar tidak dapat dirender.');
    }

    return '<canvas class="' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8')
        . ' avatar-art" data-avatar-config="' . htmlspecialchars($configJson, ENT_QUOTES, 'UTF-8')
        . '" width="100" height="110" role="img" aria-label="'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"></canvas>';
}

function avatarJsonResponse($status, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    session_start();
    ini_set('display_errors', '0');
    ob_start();

    $acceptHeader = isset($_SERVER['HTTP_ACCEPT']) && is_string($_SERVER['HTTP_ACCEPT'])
        ? $_SERVER['HTTP_ACCEPT']
        : '';
    $isAjax = (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || strpos($acceptHeader, 'application/json') !== false
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $returnUrl = '../Dashboard/Beranda.php';
    if (isset($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST'])) {
        $referer = parse_url($_SERVER['HTTP_REFERER']);
        $applicationPath = rawurldecode(rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/');
        $refererPath = isset($referer['path']) ? rawurldecode($referer['path']) : '';
        $refererHost = isset($referer['host']) ? $referer['host'] : '';
        if (
            strcasecmp($refererHost, $_SERVER['HTTP_HOST']) === 0 &&
            strpos($refererPath, $applicationPath . 'Dashboard/') === 0 &&
            in_array(basename($refererPath), array('Beranda.php', 'Kelompok.php', 'Kuis.php'), true)
        ) {
            $returnUrl = $referer['path'] . (isset($referer['query']) ? '?' . $referer['query'] : '');
        }
    }

    $respond = function ($status, $payload) use ($isAjax, $returnUrl) {
        if ($isAjax) {
            avatarJsonResponse($status, $payload);
        }

        $separator = strpos($returnUrl, '?') === false ? '?' : '&';
        http_response_code(303);
        header('Location: ' . $returnUrl . $separator . 'notice=avatar_error');
        exit();
    };

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        $respond(405, array('error' => 'Metode tidak diizinkan.'));
    }
    if (empty($_SESSION['akun_id']) || ($_SESSION['role'] ?? '') !== 'user') {
        $respond(403, array('error' => 'Sesi pemain tidak valid.'));
    }

    $csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrf)
    ) {
        $respond(403, array('error' => 'Token keamanan tidak valid. Muat ulang halaman.'));
    }

    $nickname = isset($_POST['nama_panggilan']) && is_string($_POST['nama_panggilan'])
        ? trim($_POST['nama_panggilan'])
        : '';
    $nicknameLength = function_exists('mb_strlen') ? mb_strlen($nickname, 'UTF-8') : strlen($nickname);
    $configJson = isset($_POST['avatar_config']) && is_string($_POST['avatar_config'])
        ? $_POST['avatar_config']
        : '';
    $decodedConfig = json_decode($configJson, true);
    if (
        $nickname === '' ||
        $nicknameLength > 30 ||
        !is_array($decodedConfig) ||
        count($decodedConfig) !== count(avatarDefaultConfig())
    ) {
        $respond(422, array('error' => 'Nama panggilan dan pengaturan avatar belum valid.'));
    }

    $allowedConfig = avatarDefaultConfig();
    $configChoices = array(
        'skin' => array('#f2c6a0', '#e6ae82', '#bd805e', '#85543f'),
        'hair_style' => array('short', 'side', 'spiky', 'bob', 'long', 'pony', 'curly', 'hijab'),
        'hair_color' => array('#38234f', '#171a2f', '#9c4f45', '#d6a64f', '#5b3b2e', '#e7d4a3', '#d66c91', '#536b9c'),
        'shirt_style' => array('tshirt', 'shirt', 'hoodie', 'jacket'),
        'shirt_color' => array('#a78bfa', '#f08caf', '#70c8d6', '#f0b45d', '#72bd91', '#e76f51', '#5266b8', '#e6e8f0', '#34364f', '#b34f68'),
        'pants_color' => array('#5266b8', '#536276', '#684d87', '#387f78', '#222b3c', '#9b6b46'),
        'accessory' => array('none', 'glasses', 'hat', 'headphones', 'bow', 'crown', 'bandana')
    );
    foreach ($configChoices as $key => $choices) {
        if (
            !isset($decodedConfig[$key]) ||
            !is_string($decodedConfig[$key]) ||
            !in_array($decodedConfig[$key], $choices, true)
        ) {
            $respond(422, array('error' => 'Pilihan avatar tidak valid.'));
        }
        $allowedConfig[$key] = $decodedConfig[$key];
    }
    $storedConfig = json_encode($allowedConfig, JSON_UNESCAPED_UNICODE);
    if ($storedConfig === false) {
        $respond(500, array('error' => 'Pengaturan avatar tidak dapat diproses.'));
    }

    require_once __DIR__ . '/koneksi.php';
    $userId = (int) $_SESSION['akun_id'];
    try {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE users SET nama_panggilan = ?, avatar_config = ? WHERE id = ? AND role = \'user\''
        );
        if (!$stmt) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, 'ssi', $nickname, $storedConfig, $userId);
        if (!mysqli_stmt_execute($stmt)) {
            throw new mysqli_sql_exception(mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } catch (mysqli_sql_exception $exception) {
        error_log('Gagal menyimpan avatar pemain: ' . $exception->getMessage());
        $respond(500, array('error' => 'Profil belum dapat disimpan.'));
    }

    $_SESSION['nama_panggilan'] = $nickname;
    if ($isAjax) {
        avatarJsonResponse(200, array(
            'success' => true,
            'nama_panggilan' => $nickname,
            'avatar_config' => $allowedConfig
        ));
    }

    http_response_code(303);
    header('Location: ' . $returnUrl);
    exit();
}
