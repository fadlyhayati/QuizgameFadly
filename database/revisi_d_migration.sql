USE client_serveradin;

-- Safe, additive migration: existing accounts, groups, questions, sessions and answers remain intact.
SET @migration_sql = IF(
    (
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'sesi_kuis'
          AND column_name = 'durasi_detik'
    ) = 0,
    'ALTER TABLE sesi_kuis ADD COLUMN durasi_detik INT UNSIGNED NOT NULL DEFAULT 600',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'sesi_kuis'
          AND column_name = 'batas_waktu'
    ) = 0,
    'ALTER TABLE sesi_kuis ADD COLUMN batas_waktu DATETIME NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

UPDATE sesi_kuis
SET batas_waktu = DATE_ADD(dimulai_pada, INTERVAL durasi_detik SECOND)
WHERE batas_waktu IS NULL AND status = 'berlangsung';

CREATE TABLE IF NOT EXISTS timer_soal (
    sesi_kuis_id BIGINT UNSIGNED NOT NULL,
    soal_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    dibuka_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_kuis_id, soal_id, user_id),
    CONSTRAINT fk_timer_soal_sesi
        FOREIGN KEY (sesi_kuis_id, soal_id)
        REFERENCES sesi_kuis_soal (sesi_kuis_id, soal_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_timer_soal_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
