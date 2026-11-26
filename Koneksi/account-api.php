<?php

function apiKirimRespons($status, $data)
{
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function apiAmbilTeks($data, $kunci)
{
    $nilai = $data[$kunci] ?? '';
    return is_string($nilai) ? trim($nilai) : '';
}

function apiAmbilJsonEksternal($url)
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array('Accept: application/json'),
            CURLOPT_USERAGENT => 'QuizGameRegistration/1.0'
        ));

        $isi = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($isi === false || $status < 200 || $status >= 300) {
            throw new RuntimeException("External API request failed (HTTP " . $status . "): " . $error);
        }
    } else {
        $konteks = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: QuizGameRegistration/1.0\r\n"
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
            $isi = file_get_contents($url, false, $konteks);
        } finally {
            restore_error_handler();
        }

        $status = 0;
        if (isset($http_response_header) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $cocok)) {
            $status = (int) $cocok[1];
        }

        if ($isi === false || $status < 200 || $status >= 300) {
            throw new RuntimeException("External API request failed (HTTP " . $status . ").");
        }
    }

    $hasil = json_decode($isi, true);
    if (!is_array($hasil)) {
        throw new RuntimeException("External API returned invalid JSON.");
    }

    return $hasil;
}

function apiWilayah()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header("Allow: GET");
        apiKirimRespons(405, array(
            "success" => false,
            "message" => "Endpoint wilayah hanya menerima metode GET."
        ));
    }

    $tipe = isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : '';
    $kode = isset($_GET['code']) && is_string($_GET['code']) ? $_GET['code'] : '';
    $urlApiWilayah = "https://wilayah.id/api";

    if ($tipe === 'countries') {
        $url = "https://countriesnow.space/api/v0.1/countries/positions";
    } elseif ($tipe === 'provinces') {
        $url = $urlApiWilayah . "/provinces.json";
    } elseif ($tipe === 'regencies' && preg_match('/^\d{2}$/', $kode)) {
        $url = $urlApiWilayah . "/regencies/" . $kode . ".json";
    } elseif ($tipe === 'districts' && preg_match('/^\d{2}\.\d{2}$/', $kode)) {
        $url = $urlApiWilayah . "/districts/" . $kode . ".json";
    } elseif ($tipe === 'villages' && preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $kode)) {
        $url = $urlApiWilayah . "/villages/" . $kode . ".json";
    } else {
        apiKirimRespons(400, array(
            "success" => false,
            "message" => "Jenis atau kode wilayah tidak valid."
        ));
    }

    try {
        $hasil = apiAmbilJsonEksternal($url);

        if ($tipe === 'countries') {
            if (!empty($hasil['error']) || !isset($hasil['data']) || !is_array($hasil['data'])) {
                throw new RuntimeException("The country API returned invalid data.");
            }

            $negara = array();
            foreach ($hasil['data'] as $item) {
                if (isset($item['name']) && is_string($item['name']) && trim($item['name']) !== '') {
                    $negara[] = array("name" => trim($item['name']));
                }
            }

            apiKirimRespons(200, array("data" => $negara));
        }

        if (!isset($hasil['data']) || !is_array($hasil['data'])) {
            throw new RuntimeException("The region API returned invalid data.");
        }

        apiKirimRespons(200, $hasil);
    } catch (RuntimeException $exception) {
        error_log("API wilayah gagal: " . $exception->getMessage());
        apiKirimRespons(502, array(
            "success" => false,
            "message" => "Server tidak dapat mengambil data dari API wilayah. Pastikan koneksi internet dan konfigurasi SSL PHP XAMPP aktif."
        ));
    }
}

function apiDaftarAkun()
{
    require_once __DIR__ . "/koneksi.php";
    require_once __DIR__ . "/akun.php";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Allow: POST");
        apiKirimRespons(405, array(
            "success" => false,
            "message" => "Endpoint pendaftaran hanya menerima metode POST."
        ));
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) {
        apiKirimRespons(400, array(
            "success" => false,
            "message" => "Format data tidak valid."
        ));
    }

    $namaLengkap = apiAmbilTeks($data, 'nama_lengkap');
    $email = strtolower(apiAmbilTeks($data, 'email'));
    $jenisKelamin = apiAmbilTeks($data, 'jenis_kelamin');
    $negara = apiAmbilTeks($data, 'negara');
    $password = is_string($data['password'] ?? null) ? $data['password'] : '';
    $konfirmasiPassword = is_string($data['konfirmasi_password'] ?? null) ? $data['konfirmasi_password'] : '';
    $provinsi = apiAmbilTeks($data, 'provinsi');
    $kabupatenKota = apiAmbilTeks($data, 'kabupaten_kota');
    $kecamatan = apiAmbilTeks($data, 'kecamatan');
    $kelurahanDesa = apiAmbilTeks($data, 'kelurahan_desa');
    $asalDaerah = $kabupatenKota !== '' ? $kabupatenKota : ($provinsi !== '' ? $provinsi : $negara);

    if (
        $namaLengkap === '' || $email === '' || $jenisKelamin === '' || $password === '' ||
        $konfirmasiPassword === '' || $negara === '' || $provinsi === '' ||
        $kabupatenKota === '' || $kecamatan === '' || $kelurahanDesa === ''
    ) {
        apiKirimRespons(422, array(
            "success" => false,
            "message" => "Semua kolom wajib diisi."
        ));
    }

    if (!in_array($jenisKelamin, array('Laki-laki', 'Perempuan'), true)) {
        apiKirimRespons(422, array(
            "success" => false,
            "message" => "Pilih jenis kelamin yang valid."
        ));
    }

    $batasPanjang = array(
        "Nama lengkap" => array($namaLengkap, 120),
        "Email" => array($email, 191),
        "Negara" => array($negara, 100),
        "Provinsi" => array($provinsi, 100),
        "Kabupaten / Kota" => array($kabupatenKota, 100),
        "Kecamatan" => array($kecamatan, 100),
        "Kelurahan / Desa" => array($kelurahanDesa, 100),
        "Asal Daerah" => array($asalDaerah, 150)
    );

    foreach ($batasPanjang as $label => $nilaiDanBatas) {
        $panjang = function_exists('mb_strlen')
            ? mb_strlen($nilaiDanBatas[0], 'UTF-8')
            : strlen($nilaiDanBatas[0]);

        if ($panjang > $nilaiDanBatas[1]) {
            apiKirimRespons(422, array(
                "success" => false,
                "message" => $label . " terlalu panjang."
            ));
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiKirimRespons(422, array(
            "success" => false,
            "message" => "Format email tidak valid."
        ));
    }

    if (strlen($password) < 8 || strlen($password) > 72) {
        apiKirimRespons(422, array(
            "success" => false,
            "message" => "Password harus memiliki minimal 8 dan maksimal 72 byte."
        ));
    }

    if ($password !== $konfirmasiPassword) {
        apiKirimRespons(422, array(
            "success" => false,
            "message" => "Konfirmasi password tidak sama."
        ));
    }

    if (!siapkanTabelAkun($conn)) {
        error_log("Gagal menyiapkan tabel akun: " . mysqli_error($conn));
        apiKirimRespons(500, array(
            "success" => false,
            "message" => "Database akun belum siap. Periksa konfigurasi database XAMPP."
        ));
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        error_log("Gagal membuat hash password akun.");
        apiKirimRespons(500, array(
            "success" => false,
            "message" => "Akun belum dapat dibuat. Silakan coba lagi."
        ));
    }

    try {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO users
             (nama_lengkap, jenis_kelamin, email, negara, password_hash, role,
              asal_daerah, provinsi, kabupaten_kota, kecamatan, kelurahan_desa)
             VALUES (?, ?, ?, ?, ?, 'user', ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }

        if (!mysqli_stmt_bind_param(
            $stmt,
            "ssssssssss",
            $namaLengkap,
            $jenisKelamin,
            $email,
            $negara,
            $passwordHash,
            $asalDaerah,
            $provinsi,
            $kabupatenKota,
            $kecamatan,
            $kelurahanDesa
        ) || !mysqli_stmt_execute($stmt)) {
            throw new mysqli_sql_exception(mysqli_stmt_error($stmt), mysqli_stmt_errno($stmt));
        }

        $akunId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    } catch (mysqli_sql_exception $exception) {
        if ((int) $exception->getCode() === 1062) {
            apiKirimRespons(409, array(
                "success" => false,
                "message" => "Email tersebut sudah terdaftar."
            ));
        }

        error_log("Gagal menyimpan akun: " . $exception->getMessage());
        apiKirimRespons(500, array(
            "success" => false,
            "message" => "Akun belum dapat disimpan. Periksa database XAMPP."
        ));
    }

    require_once __DIR__ . '/google-oauth-common.php';
    try {
        quizStartSession();
    } catch (Throwable $exception) {
        error_log('Sesi pendaftaran tidak dapat dimulai: ' . $exception->getMessage());
        apiKirimRespons(500, array(
            "success" => false,
            "message" => "Sesi pendaftaran belum dapat dimulai. Silakan coba lagi."
        ));
    }
    session_regenerate_id(true);
    $_SESSION['akun_id'] = (int) $akunId;
    $_SESSION['nama_lengkap'] = $namaLengkap;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = 'user';

    catatLogAktivitas($conn, (int) $akunId, 'register', 'Berhasil mendaftar akun.');

    apiKirimRespons(201, array(
        "success" => true,
        "message" => "Pendaftaran berhasil.",
        "redirect" => "Dashboard/Beranda.php"
    ));
}

function apiLengkapiProfilGoogle()
{
    require_once __DIR__ . '/google-oauth-common.php';
    try {
        quizStartSession();
    } catch (Throwable $exception) {
        error_log('Sesi pelengkapan profil tidak dapat dimulai: ' . $exception->getMessage());
        apiKirimRespons(500, array("success" => false, "message" => "Sesi tidak dapat dimulai. Muat ulang halaman."));
    }

    if (
        empty($_SESSION['akun_id']) ||
        ($_SESSION['role'] ?? '') !== 'user' ||
        empty($_SESSION['google_profile_incomplete'])
    ) {
        apiKirimRespons(403, array("success" => false, "message" => "Sesi pelengkapan profil tidak valid."));
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        apiKirimRespons(405, array("success" => false, "message" => "Endpoint hanya menerima metode POST."));
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        apiKirimRespons(400, array("success" => false, "message" => "Format data tidak valid."));
    }

    $csrf = apiAmbilTeks($data, 'csrf_token');
    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrf)
    ) {
        apiKirimRespons(403, array("success" => false, "message" => "Token keamanan tidak valid. Muat ulang halaman."));
    }

    $jenisKelamin = apiAmbilTeks($data, 'jenis_kelamin');
    $negara = apiAmbilTeks($data, 'negara');
    $provinsi = apiAmbilTeks($data, 'provinsi');
    $kabupatenKota = apiAmbilTeks($data, 'kabupaten_kota');
    $kecamatan = apiAmbilTeks($data, 'kecamatan');
    $kelurahanDesa = apiAmbilTeks($data, 'kelurahan_desa');
    $asalDaerah = $kabupatenKota !== '' ? $kabupatenKota : $provinsi;

    if (!in_array($jenisKelamin, array('Laki-laki', 'Perempuan'), true)) {
        apiKirimRespons(422, array("success" => false, "message" => "Pilih jenis kelamin yang valid."));
    }

    $batasPanjang = array(
        'Negara' => array($negara, 100),
        'Provinsi' => array($provinsi, 100),
        'Kabupaten / Kota' => array($kabupatenKota, 100),
        'Kecamatan' => array($kecamatan, 100),
        'Kelurahan / Desa' => array($kelurahanDesa, 100),
        'Asal Daerah' => array($asalDaerah, 150)
    );
    foreach ($batasPanjang as $label => $nilaiDanBatas) {
        $panjang = function_exists('mb_strlen')
            ? mb_strlen($nilaiDanBatas[0], 'UTF-8')
            : strlen($nilaiDanBatas[0]);
        if ($nilaiDanBatas[0] === '' || $panjang > $nilaiDanBatas[1]) {
            apiKirimRespons(422, array("success" => false, "message" => $label . " wajib diisi dan harus valid."));
        }
    }

    require_once __DIR__ . '/koneksi.php';
    require_once __DIR__ . '/akun.php';
    if (!siapkanTabelAkun($conn)) {
        error_log('Gagal menyiapkan tabel saat pelengkapan profil Google: ' . mysqli_error($conn));
        apiKirimRespons(500, array("success" => false, "message" => "Database profil belum siap."));
    }

    try {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE users
             SET jenis_kelamin = ?, negara = ?, provinsi = ?, kabupaten_kota = ?,
                 kecamatan = ?, kelurahan_desa = ?, asal_daerah = ?
             WHERE id = ? AND role = \'user\''
        );
        if (!$stmt) {
            throw new mysqli_sql_exception(mysqli_error($conn));
        }
        $userId = (int) $_SESSION['akun_id'];
        mysqli_stmt_bind_param(
            $stmt,
            'sssssssi',
            $jenisKelamin,
            $negara,
            $provinsi,
            $kabupatenKota,
            $kecamatan,
            $kelurahanDesa,
            $asalDaerah,
            $userId
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new mysqli_sql_exception(mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } catch (mysqli_sql_exception $exception) {
        error_log('Gagal menyimpan profil Google: ' . $exception->getMessage());
        apiKirimRespons(500, array("success" => false, "message" => "Profil belum dapat disimpan. Coba lagi."));
    }

    unset($_SESSION['google_profile_incomplete']);
    apiKirimRespons(200, array(
        "success" => true,
        "message" => "Profil berhasil dilengkapi.",
        "redirect" => "Dashboard/Beranda.php"
    ));
}

$aksi = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';

if ($aksi === 'wilayah') {
    apiWilayah();
}

if ($aksi === 'daftar') {
    apiDaftarAkun();
}

if ($aksi === 'lengkapi-profil-google') {
    apiLengkapiProfilGoogle();
}

apiKirimRespons(404, array(
    "success" => false,
    "message" => "Aksi API tidak dikenal."
));

?>
