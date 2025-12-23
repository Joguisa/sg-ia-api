/***********************************************************************************
Nombre           : sg_ia_db_init_unified.sql
Descripción      : Script para la BDD del Serious Game de Alfabetización Sanitaria. 
                   Incluye tablas maestras, gestión de contenido IA, analítica de juego y configuración.
Proyecto         : Serious Game con IA — Alfabetización Sanitaria Cáncer de Colon
Autor            : Jonatán Guillén
Fecha de creación: 10/12/2024

Ej de ejecución  : 
 -- Desde terminal:
 mysql -u root -p < sg_ia_db_init_unified.sql
 
 -- Desde cliente SQL (Workbench/DBeaver):
 Abrir archivo -> Seleccionar todo -> Ejecutar Script

-------------------  ------------------------------------------------------------------------------     -------------------- 
 FECHA MODIFICACION                                    MOTIVO                                                 USUARIO    
-------------------  ------------------------------------------------------------------------------     --------------------  
10/12/2024           Creación inicial de estructura core: tablas de jugadores, sesiones,                  JGUILLEN
                     preguntas básicas y configuración de admins.
                     
15/01/2025           v2.2: Refactorización de Feedback. Se divide 'explanations' en tipos                 JGUILLEN
                     'correct'/'incorrect'. Se añade tabla 'question_batches' para trazabilidad
                     de preguntas generadas por IA vs. manuales.

22/01/2025           v2.3: Unificación de migraciones. Se integra columna 'max_questions_per_game'        JGUILLEN
                     directamente en tabla 'system_prompts' para limitar longitud de partida
                     vía configuración (Range: 5-100).
******************************************************************************************************************************/

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

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_prompts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prompt_text TEXT NOT NULL,
  temperature DECIMAL(3,2) NOT NULL DEFAULT 0.7,
  preferred_ai_provider VARCHAR(50) NOT NULL DEFAULT 'auto',
  max_questions_per_game INT UNSIGNED NOT NULL DEFAULT 15 COMMENT 'Máximo de preguntas por juego (5-100)',
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
  total_questions INT UNSIGNED NOT NULL DEFAULT 0,
  verified_count INT UNSIGNED NOT NULL DEFAULT 0,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending', 'partial', 'complete') NOT NULL DEFAULT 'pending',
  KEY ix_qbatch_type_status (batch_type, status),
  KEY ix_qbatch_imported_at (imported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 3. TABLAS DE JUEGO (Usuarios y Contenido)
-- =========================================================

CREATE TABLE players (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  age TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_players_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT ck_questions_difficulty CHECK (difficulty BETWEEN 1 AND 5),
  CONSTRAINT fk_questions_category FOREIGN KEY (category_id) REFERENCES question_categories(id),
  CONSTRAINT fk_questions_source FOREIGN KEY (source_id) REFERENCES content_sources(id),
  CONSTRAINT fk_questions_batch FOREIGN KEY (batch_id) REFERENCES question_batches(id) ON DELETE SET NULL,
  KEY ix_q_cat_diff_active (category_id, difficulty, is_active),
  KEY ix_q_diff_active (difficulty, is_active),
  KEY ix_questions_ai_verified (is_ai_generated, admin_verified),
  KEY ix_questions_batch_verified (batch_id, admin_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 4. EXPLICACIONES (Diferenciadas por tipo de respuesta)
-- =========================================================

CREATE TABLE question_explanations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  explanation_type ENUM('correct', 'incorrect') NOT NULL DEFAULT 'correct',
  text TEXT NOT NULL,
  source_ref VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qexp_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  UNIQUE KEY uk_qexp_type (question_id, explanation_type),
  KEY ix_qexp_q (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  content VARCHAR(255) NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_qopt_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  UNIQUE KEY uk_qopt_text (question_id, content),
  KEY ix_qopt_correct (question_id, is_correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5. TABLAS TRANSACCIONALES (Sesiones y Logs)
-- =========================================================

CREATE TABLE game_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  player_id INT UNSIGNED NOT NULL,
  current_difficulty DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  score INT UNSIGNED NOT NULL DEFAULT 0,
  lives TINYINT UNSIGNED NOT NULL DEFAULT 3,
  status ENUM('active', 'completed', 'game_over') NOT NULL DEFAULT 'active',
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT ck_gs_diff CHECK (current_difficulty >= 1.00 AND current_difficulty <= 5.00),
  CONSTRAINT ck_gs_lives CHECK (lives >= 0 AND lives <= 9),
  CONSTRAINT fk_gs_player FOREIGN KEY (player_id) REFERENCES players(id),
  KEY ix_gs_player_status (player_id, status, started_at),
  KEY idx_player_current_difficulty (player_id, current_difficulty)
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
  CONSTRAINT ck_pa_time CHECK (time_taken_seconds >= 0.10 AND time_taken_seconds <= 120.00),
  CONSTRAINT ck_pa_diff CHECK (difficulty_at_answer >= 1.00 AND difficulty_at_answer <= 5.00),
  CONSTRAINT fk_pa_session FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_pa_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_pa_option FOREIGN KEY (selected_option_id) REFERENCES question_options(id),
  UNIQUE KEY uk_pa_once (session_id, question_id),
  KEY ix_pa_session_time (session_id, answered_at),
  KEY ix_pa_session_perf (session_id, is_correct, answered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_system_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_table VARCHAR(50) NULL,
  entity_id INT UNSIGNED NULL,
  details JSON NULL,
  logged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_asl_action_entity (action, entity_table, logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE error_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message VARCHAR(255) NOT NULL,
  status INT UNSIGNED NOT NULL,
  status_text VARCHAR(100) NULL,
  url VARCHAR(512) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_error_logs_status_created (status, created_at),
  KEY ix_error_logs_url_created (url, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 6. TRIGGERS
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
-- 7. VISTAS
-- =========================================================

CREATE VIEW v_session_stats AS
SELECT 
  pa.session_id, 
  COUNT(*) AS questions_answered, 
  SUM(pa.is_correct) AS correct_answers,
  ROUND(AVG(pa.is_correct) * 100, 2) AS accuracy_percent,
  ROUND(AVG(pa.time_taken_seconds), 2) AS avg_time_sec,
  MAX(pa.difficulty_at_answer) AS max_difficulty_reached
FROM player_answers pa 
GROUP BY pa.session_id;

CREATE VIEW v_player_topic_stats AS
SELECT 
  p.id AS player_id, 
  qc.id AS category_id, 
  qc.name AS category_name, 
  COUNT(pa.id) AS answers, 
  ROUND(AVG(pa.is_correct) * 100, 2) AS accuracy_percent, 
  ROUND(AVG(pa.time_taken_seconds), 2) AS avg_time_sec
FROM players p
JOIN game_sessions gs ON gs.player_id = p.id
JOIN player_answers pa ON pa.session_id = gs.id
JOIN questions q ON q.id = pa.question_id
JOIN question_categories qc ON qc.id = q.category_id
GROUP BY p.id, qc.id, qc.name;

CREATE VIEW v_sessions_activity AS
SELECT 
  gs.id AS session_id, 
  gs.player_id, 
  gs.status, 
  gs.started_at, 
  gs.ended_at,
  COALESCE(MAX(pa.answered_at), gs.started_at) AS last_activity_at,
  TIMESTAMPDIFF(MINUTE, COALESCE(MAX(pa.answered_at), gs.started_at), UTC_TIMESTAMP()) AS minutes_inactive
FROM game_sessions gs 
LEFT JOIN player_answers pa ON pa.session_id = gs.id
GROUP BY gs.id, gs.player_id, gs.status, gs.started_at, gs.ended_at;

CREATE VIEW v_unverified_questions AS
SELECT 
  q.id,
  q.statement,
  qc.name AS category,
  q.difficulty,
  qb.batch_name,
  qb.batch_type,
  q.is_ai_generated,
  (SELECT COUNT(*) FROM question_explanations WHERE question_id = q.id AND explanation_type = 'correct') AS has_correct_explanation,
  (SELECT COUNT(*) FROM question_explanations WHERE question_id = q.id AND explanation_type = 'incorrect') AS has_incorrect_explanation,
  (SELECT COUNT(*) FROM question_options WHERE question_id = q.id) AS option_count,
  q.created_at
FROM questions q
LEFT JOIN question_categories qc ON qc.id = q.category_id
LEFT JOIN question_batches qb ON qb.id = q.batch_id
WHERE q.admin_verified = 0
ORDER BY qb.imported_at DESC, q.created_at DESC;

CREATE VIEW v_batch_statistics AS
SELECT
  qb.id,
  qb.batch_name,
  qb.batch_type,
  qb.ai_provider_used,
  qb.total_questions,
  qb.verified_count,
  (qb.total_questions - qb.verified_count) AS pending_count,
  ROUND((qb.verified_count / NULLIF(qb.total_questions, 0) * 100), 2) AS verification_percent,
  qb.imported_at,
  qb.status
FROM question_batches qb
ORDER BY qb.imported_at DESC;

-- =========================================================
-- 8. DATOS SEMILLA
-- =========================================================

INSERT INTO question_categories (name, description) VALUES
('Epidemiología y Generalidades', 'Datos globales, regionales y tasas de supervivencia.'),
('Factores de Riesgo', 'Factores modificables (dieta, tabaco) y no modificables.'),
('Tamizaje y Detección', 'Métodos de diagnóstico y edades recomendadas.'),
('Prevención y Estilos de Vida', 'Hábitos saludables y autocuidado.');

INSERT INTO content_sources (citation, url) VALUES
('Guías de Salud Pública y Literatura Científica (Resumen Propuesta)', NULL);

INSERT INTO admins (email, password_hash) 
VALUES ('admin@sg-ia.com', '$2y$12$te4rWeMY9jRnwrqK2wvqd.ZepWvkXunhOLQu5gRA5tfG/R4Ck3Weq');

INSERT INTO system_prompts (prompt_text, temperature, preferred_ai_provider, max_questions_per_game, is_active)
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

JSON VÁLIDO ESTRICTO (sin markdown):', 0.7, 'auto', 15, 1
);
