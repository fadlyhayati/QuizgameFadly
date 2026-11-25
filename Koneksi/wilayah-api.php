<?php

function wilayahKirimRespons($status, $data)
{
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function wilayahAmbilJson($url)
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array('Accept: application/json'),
            CURLOPT_USERAGENT => 'QuizGameRegionLookup/1.0'
        ));

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException(
                'Wilayah API request failed (HTTP ' . $status . '): ' . $error
            );
        }
    } else {
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'timeout' => 12,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: QuizGameRegionLookup/1.0\r\n"
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true
            )
        ));

        set_error_handler(function ($severity, $message) {
            throw new RuntimeException($message);
        });

        try {
            $body = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        $status = 0;
        if (isset($http_response_header) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
            $status = (int) $match[1];
        }

        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Wilayah API request failed (HTTP ' . $status . ').');
        }
    }

    $result = json_decode($body, true);
    if (!is_array($result) || !isset($result['data']) || !is_array($result['data'])) {
        throw new RuntimeException('Wilayah API returned invalid data.');
    }

    return array('data' => $result['data']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header("Allow: GET");
    wilayahKirimRespons(405, array(
        'success' => false,
        'message' => 'Endpoint wilayah hanya menerima metode GET.'
    ));
}

$type = isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : '';
$code = isset($_GET['code']) && is_string($_GET['code']) ? $_GET['code'] : '';
$baseUrl = 'https://wilayah.id/api';

if ($type === 'provinces' && $code === '') {
    $path = '/provinces.json';
} elseif ($type === 'regencies' && preg_match('/^\d{2}$/', $code)) {
    $path = '/regencies/' . $code . '.json';
} elseif ($type === 'districts' && preg_match('/^\d{2}\.\d{2}$/', $code)) {
    $path = '/districts/' . $code . '.json';
} elseif ($type === 'villages' && preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $code)) {
    $path = '/villages/' . $code . '.json';
} else {
    wilayahKirimRespons(400, array(
        'success' => false,
        'message' => 'Jenis atau kode wilayah tidak valid.'
    ));
}

try {
    wilayahKirimRespons(200, wilayahAmbilJson($baseUrl . $path));
} catch (RuntimeException $exception) {
    error_log('Proxy wilayah gagal: ' . $exception->getMessage());
    wilayahKirimRespons(502, array(
        'success' => false,
        'message' => 'Data wilayah sedang tidak tersedia. Silakan isi alamat secara manual.'
    ));
}
