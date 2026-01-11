-- =========================================================
-- Migración 006: Agregar tabla admin_system_logs
-- Fecha: 11/01/2026
-- Descripción: Tabla para auditoría de acciones administrativas
-- =========================================================

USE sg_ia_db;

-- Verificar si la tabla ya existe
SET @table_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = 'sg_ia_db'
  AND TABLE_NAME = 'admin_system_logs'
);

-- Solo crear si no existe
SET @sql = IF(@table_exists = 0,
  'CREATE TABLE admin_system_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL COMMENT "ID del admin que ejecutó la acción",
    action VARCHAR(100) NOT NULL COMMENT "Tipo de acción (verify_bulk, delete_question, etc.)",
    entity_table VARCHAR(50) NULL COMMENT "Tabla afectada",
    entity_id INT UNSIGNED NULL COMMENT "ID del registro afectado",
    details JSON NULL COMMENT "Detalles adicionales de la acción",
    logged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_asl_admin FOREIGN KEY (user_id) REFERENCES admins(id) ON DELETE SET NULL,
    KEY ix_asl_action_entity (action, entity_table, logged_at),
    KEY ix_asl_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
  'SELECT "Tabla admin_system_logs ya existe, omitiendo creación" AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificación final
SELECT
  CASE
    WHEN @table_exists = 0 THEN 'Tabla admin_system_logs creada exitosamente'
    ELSE 'Tabla admin_system_logs ya existía'
  END AS mensaje;
