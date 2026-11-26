<?php

function googleAuthEnvironmentValue($key)
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    static $localEnvironment = null;
    if ($localEnvironment === null) {
        $localEnvironment = array();
        $environmentPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (is_file($environmentPath) && is_readable($environmentPath)) {
            $lines = file($environmentPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    if (preg_match('/^\s*([A-Z_][A-Z0-9_]*)\s*=\s*(.*?)\s*$/', $line, $match)) {
                        $entry = trim($match[2]);
                        if (
                            strlen($entry) >= 2 &&
                            (($entry[0] === '"' && substr($entry, -1) === '"') ||
                                ($entry[0] === "'" && substr($entry, -1) === "'"))
                        ) {
                            $entry = substr($entry, 1, -1);
                        }
                        $localEnvironment[$match[1]] = $entry;
                    }
                }
            }
        }
    }

    return isset($localEnvironment[$key]) ? $localEnvironment[$key] : '';
}

function googleAuthConfiguration()
{
    return array(
        'client_id' => googleAuthEnvironmentValue('GOOGLE_CLIENT_ID'),
        'client_secret' => googleAuthEnvironmentValue('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => googleAuthEnvironmentValue('GOOGLE_REDIRECT_URI')
    );
}

function googleAuthRedirectUri()
{
    $configured = googleAuthEnvironmentValue('GOOGLE_REDIRECT_URI');
    if ($configured !== '') {
        return $configured;
    }

    $host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])
        ? $_SERVER['HTTP_HOST']
        : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
    if (!is_string($host) || !preg_match('/\A(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:]+\])(?::[0-9]{1,5})?\z/', $host)) {
        throw new RuntimeException('Host aplikasi tidak valid untuk redirect OAuth.');
    }

    $forwardedProto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        is_string($_SERVER['HTTP_X_FORWARDED_PROTO'])
        ? $_SERVER['HTTP_X_FORWARDED_PROTO']
        : '';
    $isHttps = $forwardedProto === 'https' ||
        (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    $scheme = $isHttps ? 'https' : 'http';

    $scriptName = isset($_SERVER['SCRIPT_NAME']) && is_string($_SERVER['SCRIPT_NAME'])
        ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME'])
        : '/Koneksi/google-auth.php';
    $applicationPath = rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/');
    if ($applicationPath === '' || $applicationPath === '\\') {
        $applicationPath = '';
    } else {
        $segments = explode('/', rawurldecode($applicationPath));
        $applicationPath = implode('/', array_map('rawurlencode', $segments));
    }

    return $scheme . '://' . $host . $applicationPath . '/Koneksi/google-callback.php';
}

function quizStartSession()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $forwardedProto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        is_string($_SERVER['HTTP_X_FORWARDED_PROTO'])
        ? $_SERVER['HTTP_X_FORWARDED_PROTO']
        : '';
    $secure = $forwardedProto === 'https' ||
        (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');

    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ));
    if (!session_start()) {
        throw new RuntimeException('Sesi tidak dapat dimulai.');
    }
}

function googleAuthRequestJson($url, $method, $headers, $body)
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL PHP belum aktif di server.');
    }

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Permintaan autentikasi Google tidak dapat dimulai.');
    }

    $options = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'QuizGameGoogleOAuth/1.0'
    );
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false || $status < 200 || $status >= 300) {
        error_log('Permintaan OAuth Google gagal (HTTP ' . $status . '): ' . $error);
        throw new RuntimeException('Google tidak dapat menyelesaikan autentikasi. Silakan coba lagi.');
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Respons autentikasi Google tidak valid.');
    }

    return $data;
}

?>
