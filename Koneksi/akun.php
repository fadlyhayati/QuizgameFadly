<?php

function siapkanTabelAkun($conn)
{
    $sqlUsers = "CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nama_lengkap VARCHAR(120) NOT NULL,
        email VARCHAR(191) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin', 'pembuat_soal', 'user') NOT NULL DEFAULT 'user',
        asal_daerah VARCHAR(150) NOT NULL,
        jenis_kelamin VARCHAR(20) NULL,
        negara VARCHAR(100) NOT NULL DEFAULT 'Indonesia',
        provinsi VARCHAR(100) NULL,
        kabupaten_kota VARCHAR(100) NULL,
        kecamatan VARCHAR(100) NULL,
        kelurahan_desa VARCHAR(100) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_users_email (email),
        KEY idx_users_role (role),
        KEY idx_users_asal_daerah (asal_daerah)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sqlLog = "CREATE TABLE IF NOT EXISTS log_aktivitas (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        aktivitas VARCHAR(100) NOT NULL,
        detail TEXT NULL,
        dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_log_user_waktu (user_id, dibuat_pada),
        KEY idx_log_aktivitas_waktu (aktivitas, dibuat_pada),
        CONSTRAINT fk_log_user
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON UPDATE CASCADE ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        if (mysqli_query($conn, $sqlUsers) !== true) {
            return false;
        }

        if (mysqli_query($conn, $sqlLog) !== true) {
            return false;
        }

        $akunSeedConfig = array(
            array(getenv('QUIZ_ADMIN_EMAIL'), getenv('QUIZ_ADMIN_PASSWORD'), 'Administrator', 'admin'),
            array(getenv('QUIZ_CREATOR_EMAIL'), getenv('QUIZ_CREATOR_PASSWORD'), 'Pembuat Soal', 'pembuat_soal')
        );

        foreach ($akunSeedConfig as $seedConfig) {
            list($email, $seedPassword, $nama, $role) = $seedConfig;
            if ($email === false && $seedPassword === false) {
                continue;
            }

            if (
                !is_string($email) ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                !is_string($seedPassword) ||
                strlen($seedPassword) < 12
            ) {
                error_log("Konfigurasi akun awal tidak valid. Periksa QUIZ_*_EMAIL dan QUIZ_*_PASSWORD.");
                return false;
            }

            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $jumlah = mysqli_stmt_num_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($jumlah === 0) {
                $hash = password_hash($seedPassword, PASSWORD_DEFAULT);
                if ($hash === false) {
                    error_log("Gagal membuat hash untuk akun awal.");
                    return false;
                }

                $stmtSeed = mysqli_prepare(
                    $conn,
                    "INSERT INTO users
                     (nama_lengkap, email, password_hash, role, asal_daerah)
                     VALUES (?, ?, ?, ?, 'Indonesia')"
                );

                if (!$stmtSeed) {
                    return false;
                }

                mysqli_stmt_bind_param($stmtSeed, "ssss", $nama, $email, $hash, $role);
                mysqli_stmt_execute($stmtSeed);
                mysqli_stmt_close($stmtSeed);
            }
        }

        return true;
    } catch (mysqli_sql_exception $exception) {
        error_log("Gagal menyiapkan tabel kuis: " . $exception->getMessage());
        return false;
    }
}

function catatLogAktivitas($conn, $userId, $aktivitas, $detail = null)
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO log_aktivitas (user_id, aktivitas, detail) VALUES (?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    $userIdNullable = $userId ? (int) $userId : null;
    mysqli_stmt_bind_param($stmt, "iss", $userIdNullable, $aktivitas, $detail);
    $berhasil = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $berhasil;
}
