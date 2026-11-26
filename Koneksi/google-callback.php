<?php

require_once __DIR__ . '/google-oauth-common.php';

function googleCallbackRedirectError($error, $mode)
{
    header('Location: ../navbar.html?' . http_build_query(array(
        'google_error' => $error,
        'mode' => in_array($mode, array('login', 'register'), true) ? $mode : 'login'
    )));
    exit();
}

function googleCallbackFindUser($conn, $column, $value)
{
    $sql = "SELECT id, nama_lengkap, email, role, google_id FROM users WHERE " . $column . " = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new mysqli_sql_exception(mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, 's', $value);
    if (!mysqli_stmt_execute($stmt)) {
        throw new mysqli_sql_exception(mysqli_stmt_error($stmt));
    }
    mysqli_stmt_bind_result($stmt, $id, $name, $email, $role, $googleId);
    $found = mysqli_stmt_fetch($stmt);
    $user = $found
        ? array(
            'id' => (int) $id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'google_id' => $googleId
        )
        : null;
    mysqli_stmt_close($stmt);

    return $user;
}

try {
    quizStartSession();
} catch (Throwable $exception) {
    error_log('Sesi callback Google gagal: ' . $exception->getMessage());
    googleCallbackRedirectError('failed', 'login');
}

$mode = isset($_SESSION['google_oauth_mode']) && is_string($_SESSION['google_oauth_mode'])
    ? $_SESSION['google_oauth_mode']
    : 'login';
$expectedState = isset($_SESSION['google_oauth_state']) && is_string($_SESSION['google_oauth_state'])
    ? $_SESSION['google_oauth_state']
    : '';
$startedAt = isset($_SESSION['google_oauth_started_at']) ? (int) $_SESSION['google_oauth_started_at'] : 0;
unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_mode'], $_SESSION['google_oauth_started_at']);

$receivedState = isset($_GET['state']) && is_string($_GET['state']) ? $_GET['state'] : '';
if (
    $expectedState === '' ||
    $receivedState === '' ||
    !hash_equals($expectedState, $receivedState) ||
    $startedAt < time() - 600
) {
    googleCallbackRedirectError('state', $mode);
}

if (isset($_GET['error'])) {
    googleCallbackRedirectError($_GET['error'] === 'access_denied' ? 'cancelled' : 'failed', $mode);
}

$code = isset($_GET['code']) && is_string($_GET['code']) ? $_GET['code'] : '';
$config = googleAuthConfiguration();
if ($code === '' || $config['client_id'] === '' || $config['client_secret'] === '') {
    googleCallbackRedirectError('failed', $mode);
}

try {
    $redirectUri = googleAuthRedirectUri();
    $token = googleAuthRequestJson(
        'https://oauth2.googleapis.com/token',
        'POST',
        array('Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'),
        http_build_query(array(
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ), '', '&', PHP_QUERY_RFC3986)
    );

    if (!isset($token['access_token']) || !is_string($token['access_token']) || $token['access_token'] === '') {
        throw new RuntimeException('Google tidak mengembalikan access token yang valid.');
    }

    $profile = googleAuthRequestJson(
        'https://openidconnect.googleapis.com/v1/userinfo',
        'GET',
        array('Authorization: Bearer ' . $token['access_token'], 'Accept: application/json'),
        null
    );
    $googleId = isset($profile['sub']) && is_string($profile['sub']) ? trim($profile['sub']) : '';
    $email = isset($profile['email']) && is_string($profile['email'])
        ? strtolower(trim($profile['email']))
        : '';
    $emailVerified = isset($profile['email_verified']) &&
        ($profile['email_verified'] === true || $profile['email_verified'] === 'true');
    $name = isset($profile['name']) && is_string($profile['name']) ? trim($profile['name']) : '';

    if (
        $googleId === '' ||
        !$emailVerified ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $name === ''
    ) {
        throw new RuntimeException('Profil Google belum memiliki email terverifikasi yang valid.');
    }
    if (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') > 120 : strlen($name) > 120) {
        $name = function_exists('mb_substr') ? mb_substr($name, 0, 120, 'UTF-8') : substr($name, 0, 120);
    }

    require_once __DIR__ . '/koneksi.php';
    require_once __DIR__ . '/akun.php';
    if (!siapkanTabelAkun($conn)) {
        throw new RuntimeException('Database akun belum siap.');
    }

    $user = googleCallbackFindUser($conn, 'google_id', $googleId);
    if ($user === null) {
        $user = googleCallbackFindUser($conn, 'email', $email);
        if ($user !== null) {
            if (!empty($user['google_id']) && !hash_equals((string) $user['google_id'], $googleId)) {
                googleCallbackRedirectError('email_conflict', $mode);
            }

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE users SET google_id = ?, auth_provider = 'google'
                 WHERE id = ? AND (google_id IS NULL OR google_id = ?)"
            );
            if (!$stmt) {
                throw new mysqli_sql_exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, 'sis', $googleId, $user['id'], $googleId);
            if (!mysqli_stmt_execute($stmt)) {
                throw new mysqli_sql_exception(mysqli_stmt_error($stmt), mysqli_stmt_errno($stmt));
            }
            $linked = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            if ($linked === 0) {
                $linkedUser = googleCallbackFindUser($conn, 'google_id', $googleId);
                if ($linkedUser === null || $linkedUser['id'] !== $user['id']) {
                    googleCallbackRedirectError('email_conflict', $mode);
                }
            }
            $user['google_id'] = $googleId;
        }
    }

    $isNewGoogleAccount = false;
    if ($user === null) {
        if ($mode !== 'register') {
            googleCallbackRedirectError('no_account', $mode);
        }

        $randomPassword = bin2hex(random_bytes(32));
        $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Kredensial akun Google tidak dapat disiapkan.');
        }

        $initialLocation = 'Belum diisi';
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO users
             (nama_lengkap, email, password_hash, role, asal_daerah, google_id, auth_provider)
             VALUES (?, ?, ?, 'user', ?, ?, 'google')"
        );
        if (!$stmt) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $passwordHash, $initialLocation, $googleId);
        if (!mysqli_stmt_execute($stmt)) {
            $errorCode = mysqli_stmt_errno($stmt);
            $errorMessage = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            if ($errorCode === 1062) {
                googleCallbackRedirectError('email_conflict', $mode);
            }
            throw new mysqli_sql_exception($errorMessage, $errorCode);
        }
        $user = array(
            'id' => (int) mysqli_insert_id($conn),
            'name' => $name,
            'email' => $email,
            'role' => 'user'
        );
        mysqli_stmt_close($stmt);
        $isNewGoogleAccount = true;
    }

    session_regenerate_id(true);
    $_SESSION['akun_id'] = $user['id'];
    $_SESSION['nama_lengkap'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];

    $aktivitas = $isNewGoogleAccount ? 'register_google' : 'login_google';
    if (!catatLogAktivitas($conn, $user['id'], $aktivitas, 'Autentikasi Google berhasil.')) {
        error_log('Gagal mencatat aktivitas OAuth Google untuk akun ' . $user['id'] . '.');
    }

    if ($isNewGoogleAccount) {
        $_SESSION['google_profile_incomplete'] = true;
        header('Location: ../Dashboard/Beranda.php?lengkapi_profil=1');
    } else {
        unset($_SESSION['google_profile_incomplete']);
        if ($user['role'] === 'admin') {
            header('Location: ../Dashboard/Admin.php');
        } elseif ($user['role'] === 'pembuat_soal') {
            header('Location: ../Dashboard/PembuatSoal.php');
        } else {
            header('Location: ../Dashboard/Beranda.php');
        }
    }
    exit();
} catch (Throwable $exception) {
    error_log('Callback OAuth Google gagal: ' . $exception->getMessage());
    googleCallbackRedirectError('failed', $mode);
}

?>
