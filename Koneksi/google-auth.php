<?php

require_once __DIR__ . '/google-oauth-common.php';

$mode = isset($_GET['mode']) && is_string($_GET['mode']) ? $_GET['mode'] : '';
if (!in_array($mode, array('login', 'register'), true)) {
    header('Location: ../navbar.html');
    exit();
}

$config = googleAuthConfiguration();
if ($config['client_id'] === '' || $config['client_secret'] === '') {
    header('Location: ../navbar.html?' . http_build_query(array(
        'google_error' => 'config',
        'mode' => $mode
    )));
    exit();
}

try {
    $redirectUri = googleAuthRedirectUri();
    quizStartSession();
    session_regenerate_id(true);
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $state;
    $_SESSION['google_oauth_mode'] = $mode;
    $_SESSION['google_oauth_started_at'] = time();

    $query = http_build_query(array(
        'client_id' => $config['client_id'],
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'select_account'
    ), '', '&', PHP_QUERY_RFC3986);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    exit();
} catch (Throwable $exception) {
    error_log('Gagal memulai OAuth Google: ' . $exception->getMessage());
    header('Location: ../navbar.html?' . http_build_query(array(
        'google_error' => 'failed',
        'mode' => $mode
    )));
    exit();
}

?>
