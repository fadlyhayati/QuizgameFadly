USE client_serveradin;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users'
          AND column_name = 'nama_panggilan'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN nama_panggilan VARCHAR(30) NULL'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users'
          AND column_name = 'avatar_config'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN avatar_config TEXT NULL'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'soal'
          AND column_name = 'tipe_soal'
    ),
    'SELECT 1',
    'ALTER TABLE soal ADD COLUMN tipe_soal ENUM(''pilihan_ganda'', ''esai'') NOT NULL DEFAULT ''pilihan_ganda'''
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'soal'
          AND column_name = 'jawaban_esai'
    ),
    'SELECT 1',
    'ALTER TABLE soal ADD COLUMN jawaban_esai VARCHAR(255) NULL'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'jawaban_user'
          AND column_name = 'jawaban_teks'
    ),
    'SELECT 1',
    'ALTER TABLE jawaban_user ADD COLUMN jawaban_teks VARCHAR(255) NULL'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'sesi_kuis_soal'
          AND column_name = 'tipe_soal_snapshot'
    ),
    'SELECT 1',
    'ALTER TABLE sesi_kuis_soal ADD COLUMN tipe_soal_snapshot ENUM(''pilihan_ganda'', ''esai'') NOT NULL DEFAULT ''pilihan_ganda'''
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'sesi_kuis_soal'
          AND column_name = 'jawaban_esai_snapshot'
    ),
    'SELECT 1',
    'ALTER TABLE sesi_kuis_soal ADD COLUMN jawaban_esai_snapshot VARCHAR(255) NULL'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

CREATE TABLE IF NOT EXISTS posisi_pemain (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sesi_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    pos_x INT NOT NULL DEFAULT 200,
    pos_y INT NOT NULL DEFAULT 200,
    arah VARCHAR(10) NOT NULL DEFAULT 'down',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_posisi_pemain_sesi_user (sesi_id, user_id),
    KEY idx_posisi_pemain_user (user_id),
    CONSTRAINT fk_posisi_pemain_sesi
        FOREIGN KEY (sesi_id) REFERENCES sesi_kuis (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_posisi_pemain_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
