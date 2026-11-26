SET @database_name = DATABASE();

SET @google_id_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'google_id'
);
SET @migration_sql = IF(
    @google_id_exists = 0,
    'ALTER TABLE users ADD COLUMN google_id VARCHAR(191) NULL',
    'SELECT 1'
);
PREPARE google_auth_stmt FROM @migration_sql;
EXECUTE google_auth_stmt;
DEALLOCATE PREPARE google_auth_stmt;

SET @auth_provider_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'auth_provider'
);
SET @migration_sql = IF(
    @auth_provider_exists = 0,
    'ALTER TABLE users ADD COLUMN auth_provider VARCHAR(30) NOT NULL DEFAULT ''local''',
    'SELECT 1'
);
PREPARE google_auth_stmt FROM @migration_sql;
EXECUTE google_auth_stmt;
DEALLOCATE PREPARE google_auth_stmt;

SET @google_id_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'google_id'
      AND NON_UNIQUE = 0
);
SET @migration_sql = IF(
    @google_id_index_exists = 0,
    'ALTER TABLE users ADD UNIQUE KEY uq_users_google_id (google_id)',
    'SELECT 1'
);
PREPARE google_auth_stmt FROM @migration_sql;
EXECUTE google_auth_stmt;
DEALLOCATE PREPARE google_auth_stmt;
