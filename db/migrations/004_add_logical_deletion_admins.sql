-- =========================================================
-- Migración v3.1: Agregar eliminado lógico a tabla admins
-- Fecha: 08/01/2026
-- Autor: JGUILLEN
--
-- Permite activar/desactivar administradores sin eliminarlos
-- físicamente de la base de datos.
-- =========================================================

USE sg_ia_db;

-- =========================================================
-- 1. AGREGAR columna is_active a tabla admins
-- =========================================================

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'sg_ia_db'
  AND TABLE_NAME = 'admins'
  AND COLUMN_NAME = 'is_active'
);

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE admins ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT "1=activo, 0=desactivado" AFTER role',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 2. AGREGAR índice a is_active para filtrado eficiente
-- =========================================================

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'sg_ia_db'
  AND TABLE_NAME = 'admins'
  AND INDEX_NAME = 'ix_admins_active'
);

SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE admins ADD KEY ix_admins_active (is_active)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 3. ASEGURAR que todos los admins existentes estén activos
-- =========================================================

UPDATE admins SET is_active = 1 WHERE is_active IS NULL;

-- =========================================================
-- FIN DE MIGRACIÓN
-- =========================================================
SELECT 'Migración v3.1 completada: Eliminado lógico de admins habilitado' AS resultado;
