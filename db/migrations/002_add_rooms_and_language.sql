-- =========================================================
-- Migración v3.0: Sistema de Salas y Multi-idioma
-- Fecha: 06/01/2026
-- Autor: JGUILLEN
--
-- IMPORTANTE: Ejecutar este script en bases de datos existentes
-- para agregar las nuevas funcionalidades sin perder datos.
-- =========================================================

USE sg_ia_db;

-- =========================================================
-- 1. MODIFICAR system_prompts: Agregar columna de idioma
-- =========================================================

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'sg_ia_db'
  AND TABLE_NAME = 'system_prompts'
  AND COLUMN_NAME = 'default_language'
);

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE system_prompts ADD COLUMN default_language ENUM("es","en") NOT NULL DEFAULT "es" COMMENT "Idioma por defecto para generación de preguntas" AFTER max_questions_per_game',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 2. MODIFICAR question_batches: Agregar columna de idioma
-- =========================================================

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'sg_ia_db'
  AND TABLE_NAME = 'question_batches'
  AND COLUMN_NAME = 'language'
);

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE question_batches ADD COLUMN language ENUM("es","en") NOT NULL DEFAULT "es" COMMENT "Idioma de las preguntas del lote" AFTER ai_provider_used',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar índice a question_batches.language
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'sg_ia_db'
  AND TABLE_NAME = 'question_batches'
  AND INDEX_NAME = 'ix_qbatch_language'
);

SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE question_batches ADD KEY ix_qbatch_language (language)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 3. MODIFICAR questions: Agregar columna de idioma
-- =========================================================

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'sg_ia_db'
  AND TABLE_NAME = 'questions'
  AND COLUMN_NAME = 'language'
);

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE questions ADD COLUMN language ENUM("es","en") NOT NULL DEFAULT "es" COMMENT "Idioma de la pregunta" AFTER admin_verified',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar índice a questions.language
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'sg_ia_db'
  AND TABLE_NAME = 'questions'
  AND INDEX_NAME = 'ix_questions_language'
);

SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE questions ADD KEY ix_questions_language (language)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 4. CREAR tabla game_rooms
-- =========================================================

CREATE TABLE IF NOT EXISTS game_rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_code VARCHAR(8) NOT NULL UNIQUE COMMENT 'Código único de sala (ej: ABC123)',
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  admin_id INT UNSIGNED NOT NULL,
  filter_categories JSON NULL COMMENT 'IDs de categorías permitidas, null = todas',
  filter_difficulties JSON NULL COMMENT 'Niveles permitidos [1,2,3,4,5], null = todos',
  max_players INT UNSIGNED NOT NULL DEFAULT 50,
  status ENUM('active', 'paused', 'closed') NOT NULL DEFAULT 'active',
  started_at TIMESTAMP NULL,
  ended_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_rooms_admin FOREIGN KEY (admin_id) REFERENCES admins(id),
  UNIQUE KEY uk_room_code (room_code),
  KEY ix_rooms_status (status),
  KEY ix_rooms_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5. MODIFICAR game_sessions: Agregar room_id
-- =========================================================

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'sg_ia_db'
  AND TABLE_NAME = 'game_sessions'
  AND COLUMN_NAME = 'room_id'
);

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE game_sessions ADD COLUMN room_id INT UNSIGNED NULL COMMENT "Sala de juego asociada (NULL = juego libre)" AFTER player_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar FK solo si no existe
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = 'sg_ia_db'
  AND TABLE_NAME = 'game_sessions'
  AND CONSTRAINT_NAME = 'fk_gs_room'
);

SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE game_sessions ADD CONSTRAINT fk_gs_room FOREIGN KEY (room_id) REFERENCES game_rooms(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar índice compuesto si no existe
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'sg_ia_db'
  AND TABLE_NAME = 'game_sessions'
  AND INDEX_NAME = 'ix_gs_room_status'
);

SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE game_sessions ADD KEY ix_gs_room_status (room_id, status)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 6. ACTUALIZAR vista v_batch_statistics (agregar language)
-- =========================================================

DROP VIEW IF EXISTS v_batch_statistics;

CREATE VIEW v_batch_statistics AS
SELECT
  qb.id,
  qb.batch_name,
  qb.batch_type,
  qb.ai_provider_used,
  qb.language,
  qb.total_questions,
  qb.verified_count,
  (qb.total_questions - qb.verified_count) AS pending_count,
  ROUND((qb.verified_count / NULLIF(qb.total_questions, 0) * 100), 2) AS verification_percent,
  qb.imported_at,
  qb.status
FROM question_batches qb
ORDER BY qb.imported_at DESC;

-- =========================================================
-- 7. CREAR vistas de estadísticas de sala
-- =========================================================

DROP VIEW IF EXISTS v_room_statistics;
CREATE VIEW v_room_statistics AS
SELECT
  gr.id AS room_id,
  gr.room_code,
  gr.name AS room_name,
  gr.status AS room_status,
  COUNT(DISTINCT gs.id) AS total_sessions,
  COUNT(DISTINCT gs.player_id) AS unique_players,
  COUNT(pa.id) AS total_answers,
  ROUND(AVG(pa.is_correct) * 100, 2) AS avg_accuracy,
  ROUND(AVG(pa.time_taken_seconds), 2) AS avg_time_sec,
  MAX(gs.score) AS highest_score,
  ROUND(AVG(gs.score), 2) AS avg_score,
  gr.created_at AS room_created_at
FROM game_rooms gr
LEFT JOIN game_sessions gs ON gs.room_id = gr.id
LEFT JOIN player_answers pa ON pa.session_id = gs.id
GROUP BY gr.id, gr.room_code, gr.name, gr.status, gr.created_at;

DROP VIEW IF EXISTS v_room_player_stats;
CREATE VIEW v_room_player_stats AS
SELECT
  gr.id AS room_id,
  gr.room_code,
  p.id AS player_id,
  p.name AS player_name,
  p.age AS player_age,
  COUNT(DISTINCT gs.id) AS sessions_played,
  MAX(gs.score) AS high_score,
  ROUND(AVG(gs.score), 2) AS avg_score,
  COUNT(pa.id) AS total_answers,
  ROUND(AVG(pa.is_correct) * 100, 2) AS accuracy_percent,
  ROUND(AVG(pa.time_taken_seconds), 2) AS avg_time_sec
FROM game_rooms gr
JOIN game_sessions gs ON gs.room_id = gr.id
JOIN players p ON p.id = gs.player_id
LEFT JOIN player_answers pa ON pa.session_id = gs.id
GROUP BY gr.id, gr.room_code, p.id, p.name, p.age;

DROP VIEW IF EXISTS v_room_question_stats;
CREATE VIEW v_room_question_stats AS
SELECT
  gr.id AS room_id,
  gr.room_code,
  q.id AS question_id,
  q.statement,
  qc.name AS category_name,
  q.difficulty,
  COUNT(pa.id) AS times_answered,
  SUM(CASE WHEN pa.is_correct = 1 THEN 1 ELSE 0 END) AS correct_count,
  SUM(CASE WHEN pa.is_correct = 0 THEN 1 ELSE 0 END) AS error_count,
  ROUND((SUM(CASE WHEN pa.is_correct = 0 THEN 1 ELSE 0 END) / NULLIF(COUNT(pa.id), 0)) * 100, 2) AS error_rate,
  ROUND(AVG(pa.time_taken_seconds), 2) AS avg_time_sec
FROM game_rooms gr
JOIN game_sessions gs ON gs.room_id = gr.id
JOIN player_answers pa ON pa.session_id = gs.id
JOIN questions q ON q.id = pa.question_id
JOIN question_categories qc ON qc.id = q.category_id
GROUP BY gr.id, gr.room_code, q.id, q.statement, qc.name, q.difficulty;

DROP VIEW IF EXISTS v_room_category_stats;
CREATE VIEW v_room_category_stats AS
SELECT
  gr.id AS room_id,
  gr.room_code,
  qc.id AS category_id,
  qc.name AS category_name,
  COUNT(pa.id) AS total_answers,
  ROUND(AVG(pa.is_correct) * 100, 2) AS accuracy_percent,
  ROUND(AVG(pa.time_taken_seconds), 2) AS avg_time_sec
FROM game_rooms gr
JOIN game_sessions gs ON gs.room_id = gr.id
JOIN player_answers pa ON pa.session_id = gs.id
JOIN questions q ON q.id = pa.question_id
JOIN question_categories qc ON qc.id = q.category_id
GROUP BY gr.id, gr.room_code, qc.id, qc.name;

-- =========================================================
-- FIN DE MIGRACIÓN
-- =========================================================
SELECT 'Migración v3.0 completada exitosamente' AS resultado;
