/***********************************************************************************
Nombre           : 003_unified_schema.sql
Descripción      : Esquema unificado completo de la base de datos SG-IA
                   Incluye: Sistema de salas, multiidioma, roles, batches y logs completos
Proyecto         : Serious Game con IA — Alfabetización Sanitaria Cáncer de Colon
Autor            : Jonatán Guillén
Fecha            : 11/01/2026
Versión          : 3.3 Final
***********************************************************************************/

DROP DATABASE IF EXISTS sg_ia_db;
CREATE DATABASE sg_ia_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sg_ia_db;

SET SQL_MODE = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';
SET time_zone = '+00:00';

-- =========================================================
-- 1. TABLAS MAESTRAS Y CONFIGURACIÓN
-- =========================================================

CREATE TABLE question_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_sources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  citation VARCHAR(255) NOT NULL,
  url VARCHAR(512) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla Admins con roles y eliminado lógico
CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('superadmin', 'admin') NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=activo, 0=desactivado',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_admins_email (email),
  KEY ix_admins_role (role),
  KEY ix_admins_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla System Prompts con idioma por defecto
CREATE TABLE system_prompts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prompt_text TEXT NOT NULL,
  temperature DECIMAL(3,2) NOT NULL DEFAULT 0.7,
  preferred_ai_provider VARCHAR(50) NOT NULL DEFAULT 'auto',
  max_questions_per_game INT UNSIGNED NOT NULL DEFAULT 15 COMMENT 'Máximo de preguntas por juego (5-100)',
  default_language ENUM('es', 'en') NOT NULL DEFAULT 'es' COMMENT 'Idioma por defecto para generación de preguntas',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_system_prompts_active (is_active),
  CONSTRAINT ck_max_questions_range CHECK (max_questions_per_game BETWEEN 5 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2. TABLAS DE GESTIÓN DE CONTENIDO (Batches)
-- =========================================================

CREATE TABLE question_batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_name VARCHAR(100) NOT NULL,
  batch_type ENUM('ai_generated', 'csv_imported', 'manual_entry') NOT NULL,
  description VARCHAR(255) NULL,
  ai_provider_used VARCHAR(50) NULL,
  language ENUM('es', 'en') NOT NULL DEFAULT 'es' COMMENT 'Idioma de las preguntas del lote',
  total_questions INT UNSIGNED NOT NULL DEFAULT 0,
  verified_count INT UNSIGNED NOT NULL DEFAULT 0,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending', 'partial', 'complete') NOT NULL DEFAULT 'pending',
  KEY ix_qbatch_type_status (batch_type, status),
  KEY ix_qbatch_imported_at (imported_at),
  KEY ix_qbatch_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 3. TABLAS DE JUEGO (Usuarios y Salas)
-- =========================================================

CREATE TABLE players (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  age TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_players_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE game_rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_code VARCHAR(8) NOT NULL UNIQUE COMMENT 'Código único de sala',
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  admin_id INT UNSIGNED NOT NULL,
  filter_categories JSON NULL,
  filter_difficulties JSON NULL,
  max_players INT UNSIGNED NOT NULL DEFAULT 50,
  language ENUM('es', 'en') NOT NULL DEFAULT 'es' COMMENT 'Idioma de la interfaz del juego',
  status ENUM('active', 'paused', 'closed') NOT NULL DEFAULT 'active',
  started_at TIMESTAMP NULL,
  ended_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_rooms_admin FOREIGN KEY (admin_id) REFERENCES admins(id),
  UNIQUE KEY uk_room_code (room_code),
  KEY ix_rooms_status (status),
  KEY ix_rooms_admin (admin_id),
  KEY ix_rooms_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla Questions con idioma
CREATE TABLE questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  statement TEXT NOT NULL,
  difficulty TINYINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  source_id INT UNSIGNED NULL,
  batch_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_ai_generated BOOLEAN DEFAULT 0,
  admin_verified BOOLEAN DEFAULT 1,
  language ENUM('es', 'en') NOT NULL DEFAULT 'es' COMMENT 'Idioma de la pregunta',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT ck_questions_difficulty CHECK (difficulty BETWEEN 1 AND 5),
  CONSTRAINT fk_questions_category FOREIGN KEY (category_id) REFERENCES question_categories(id),
  CONSTRAINT fk_questions_source FOREIGN KEY (source_id) REFERENCES content_sources(id),
  CONSTRAINT fk_questions_batch FOREIGN KEY (batch_id) REFERENCES question_batches(id) ON DELETE SET NULL,
  KEY ix_q_cat_diff_active (category_id, difficulty, is_active),
  KEY ix_questions_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_explanations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  explanation_type ENUM('correct', 'incorrect') NOT NULL DEFAULT 'correct',
  text TEXT NOT NULL,
  source_ref VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qexp_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  UNIQUE KEY uk_qexp_type (question_id, explanation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  content VARCHAR(255) NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_qopt_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  UNIQUE KEY uk_qopt_text (question_id, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 4. TABLAS TRANSACCIONALES (Sesiones y Logs)
-- =========================================================

CREATE TABLE game_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  player_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NULL COMMENT 'Sala de juego asociada',
  current_difficulty DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  score INT UNSIGNED NOT NULL DEFAULT 0,
  lives TINYINT UNSIGNED NOT NULL DEFAULT 3,
  status ENUM('active', 'completed', 'game_over') NOT NULL DEFAULT 'active',
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_gs_player FOREIGN KEY (player_id) REFERENCES players(id),
  CONSTRAINT fk_gs_room FOREIGN KEY (room_id) REFERENCES game_rooms(id) ON DELETE SET NULL,
  KEY ix_gs_room_status (room_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  selected_option_id INT UNSIGNED NULL,
  is_correct TINYINT(1) NOT NULL,
  time_taken_seconds DECIMAL(5,2) NOT NULL,
  difficulty_at_answer DECIMAL(3,2) NOT NULL,
  answered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pa_session FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_pa_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_pa_option FOREIGN KEY (selected_option_id) REFERENCES question_options(id),
  UNIQUE KEY uk_pa_once (session_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de logs de errores del frontend
CREATE TABLE error_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message VARCHAR(255) NOT NULL,
  status INT UNSIGNED NOT NULL,
  status_text VARCHAR(100) NULL COMMENT 'Texto descriptivo del código HTTP',
  url VARCHAR(512) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_error_logs_status (status),
  KEY ix_error_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de logs de acciones administrativas (auditoría)
CREATE TABLE admin_system_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL COMMENT 'ID del admin que ejecutó la acción',
  action VARCHAR(100) NOT NULL COMMENT 'Tipo de acción (verify_bulk, delete_question, etc.)',
  entity_table VARCHAR(50) NULL COMMENT 'Tabla afectada',
  entity_id INT UNSIGNED NULL COMMENT 'ID del registro afectado',
  details JSON NULL COMMENT 'Detalles adicionales de la acción',
  logged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_asl_admin FOREIGN KEY (user_id) REFERENCES admins(id) ON DELETE SET NULL,
  KEY ix_asl_action_entity (action, entity_table, logged_at),
  KEY ix_asl_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5. LÓGICA (Triggers)
-- =========================================================

DELIMITER $$
CREATE TRIGGER trg_qopt_one_correct_ins BEFORE INSERT ON question_options FOR EACH ROW
BEGIN
  IF NEW.is_correct = 1 AND (SELECT COUNT(*) FROM question_options WHERE question_id = NEW.question_id AND is_correct = 1) >= 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo una opción correcta por pregunta';
  END IF;
END$$

CREATE TRIGGER trg_qopt_one_correct_upd BEFORE UPDATE ON question_options FOR EACH ROW
BEGIN
  IF NEW.is_correct = 1 AND OLD.is_correct <> 1 AND (SELECT COUNT(*) FROM question_options WHERE question_id = NEW.question_id AND is_correct = 1) >= 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo una opción correcta por pregunta';
  END IF;
END$$
DELIMITER ;

-- =========================================================
-- 6. ANALÍTICA (Vistas)
-- =========================================================

CREATE VIEW v_batch_statistics AS
SELECT
  qb.id,
  qb.batch_name,
  qb.batch_type,
  qb.language,
  qb.ai_provider_used,
  qb.total_questions,
  qb.verified_count,
  ROUND((qb.verified_count / NULLIF(qb.total_questions, 0) * 100), 2) AS verification_percent,
  qb.imported_at,
  qb.status
FROM question_batches qb;

CREATE VIEW v_room_statistics AS
SELECT
  gr.id AS room_id,
  gr.room_code,
  gr.name AS room_name,
  gr.language AS room_language,
  gr.status AS room_status,
  COUNT(DISTINCT gs.id) AS total_sessions,
  COUNT(DISTINCT gs.player_id) AS unique_players,
  COUNT(pa.id) AS total_answers,
  ROUND(AVG(pa.is_correct) * 100, 2) AS avg_accuracy,
  MAX(gs.score) AS highest_score
FROM game_rooms gr
LEFT JOIN game_sessions gs ON gs.room_id = gr.id
LEFT JOIN player_answers pa ON pa.session_id = gs.id
GROUP BY gr.id, gr.room_code, gr.name, gr.language, gr.status;

-- =========================================================
-- 7. DATOS SEMILLA
-- =========================================================

INSERT INTO question_categories (name, description) VALUES
('Epidemiología y Generalidades', 'Datos globales, regionales y supervivencia.'),
('Factores de Riesgo', 'Factores modificables y no modificables.'),
('Tamizaje y Detección', 'Métodos de diagnóstico y detección temprana.'),
('Prevención y Estilos de Vida', 'Hábitos saludables y prevención.');

-- Admin inicial como Superadmin
-- Password: Admin123!
INSERT INTO admins (email, password_hash, role, is_active)
VALUES ('admin@sg-ia.com', '$2y$12$te4rWeMY9jRnwrqK2wvqd.ZepWvkXunhOLQu5gRA5tfG/R4Ck3Weq', 'superadmin', 1);

-- Prompt del sistema inicial
INSERT INTO system_prompts (prompt_text, temperature, preferred_ai_provider, max_questions_per_game, default_language, is_active)
VALUES (
  'Eres un experto oncólogo y educador sanitario especializado en Cáncer de Colon.
Genera EXACTAMENTE 1 pregunta de opción múltiple sobre {topic} para nivel de dificultad {difficulty} ({difficulty_desc}).

Contexto educativo: Alfabetización sobre Cáncer de Colon en Ecuador
Estándares: Basado en Guías MSP Ecuador y OMS

INSTRUCCIONES CRÍTICAS:
1. Genera SOLO un JSON válido, sin markdown ni comentarios
2. Estructura EXACTA: { "statement": "...", "options": [{"text": "...", "is_correct": bool}], "explanation_correct": "...", "explanation_incorrect": "...", "source_ref": "..." }
3. Incluye exactamente 4 opciones
4. Una sola opción debe ser correcta (is_correct: true)
5. El enunciado debe ser claro y conciso (100-300 caracteres)
6. Opciones balanceadas, ninguna obviamente incorrecta
7. Explicaciones medidas (150-250 caracteres cada una)
8. explanation_correct: explicación para respuesta correcta
9. explanation_incorrect: explicación general para respuestas incorrectas
10. source_ref: referencia a "Guías MSP Ecuador", "OMS", o literatura médica

JSON VÁLIDO ESTRICTO (sin markdown):', 0.7, 'auto', 15, 'es',1
);

-- =========================================================
-- 8. VERIFICACIÓN
-- =========================================================

SELECT
  'Esquema 003 creado exitosamente' AS mensaje,
  COUNT(*) AS total_tablas
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'sg_ia_db';
