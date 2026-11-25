USE client_serveradin;

-- Preserve the existing BIGINT UNSIGNED foreign-key type while allowing solo sessions.
ALTER TABLE sesi_kuis
    MODIFY COLUMN kelompok_id BIGINT UNSIGNED NULL;

SET @migration_sql = IF(
    (
        SELECT COUNT(*)
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'posisi_pemain'
          AND index_name = 'idx_posisi_pemain_updated_at'
    ) = 0,
    'ALTER TABLE posisi_pemain ADD KEY idx_posisi_pemain_updated_at (updated_at)',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- jawaban_user has no kelompok_id column; its session foreign key already supports solo sessions.
