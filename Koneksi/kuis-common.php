<?php

function siapkanTabelPesertaKuis($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS sesi_kuis_peserta (
        sesi_kuis_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        status ENUM('berlangsung', 'selesai') NOT NULL DEFAULT 'berlangsung',
        bergabung_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        selesai_pada TIMESTAMP NULL,
        PRIMARY KEY (sesi_kuis_id, user_id),
        KEY idx_peserta_user_status (user_id, status),
        CONSTRAINT fk_peserta_sesi
            FOREIGN KEY (sesi_kuis_id) REFERENCES sesi_kuis (id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
        CONSTRAINT fk_peserta_user
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        return mysqli_query($conn, $sql) === true;
    } catch (Throwable $exception) {
        error_log("Gagal menyiapkan tabel peserta kuis: " . $exception->getMessage());
        return false;
    }
}

function tokenCsrfKuis()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function escapeKuis($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
