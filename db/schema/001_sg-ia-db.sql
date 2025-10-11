CREATE DATABASE IF NOT EXISTS sg_ia_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
-- =========================================================
-- SG-IA-DB
-- Requisitos: MySQL 8.0+, InnoDB, utf8mb4. Tiempos en UTC.
-- =========================================================

USE sg_ia_db;
-- Sesión
SET SQL_MODE = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';
SET time_zone = '+00:00';

-- ======================
-- Drops seguros (orden)
-- ======================
DROP VIEW IF EXISTS v_sessions_activity;
DROP VIEW IF EXISTS v_player_topic_stats;
DROP VIEW IF EXISTS v_session_stats;
DROP TRIGGER IF EXISTS trg_qopt_one_correct_ins;
DROP TRIGGER IF EXISTS trg_qopt_one_correct_upd;
DROP TABLE IF EXISTS admin_system_logs;
DROP TABLE IF EXISTS player_answers;
DROP TABLE IF EXISTS game_sessions;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS question_explanations;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS content_sources;
DROP TABLE IF EXISTS question_categories;

-- =========================
-- 1) Catálogo de categorías
-- =========================
CREATE TABLE question_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Semilla mínima
INSERT INTO question_categories (name, description)
VALUES ('Cáncer de Colon', 'Banco base para piloto');

-- ==================================
-- 2) Fuentes (trazabilidad académica)
-- ==================================
CREATE TABLE content_sources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  citation VARCHAR(255) NOT NULL,
  url VARCHAR(512) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============
-- 3) Jugadores
-- ============
CREATE TABLE players (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_players_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================
-- 4) Preguntas del cuestionario
-- ==========================
CREATE TABLE questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  statement TEXT NOT NULL,
  difficulty TINYINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  source_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT ck_questions_difficulty CHECK (difficulty BETWEEN 1 AND 5),
  CONSTRAINT fk_questions_category FOREIGN KEY (category_id) REFERENCES question_categories(id),
  CONSTRAINT fk_questions_source   FOREIGN KEY (source_id)   REFERENCES content_sources(id),
  KEY ix_q_cat_diff_active (category_id, difficulty, is_active),
  KEY ix_q_diff_active (difficulty, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================
-- 5) Explicaciones por pregunta
-- ==============================
CREATE TABLE question_explanations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  text TEXT NOT NULL,
  source_ref VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qexp_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  KEY ix_qexp_q (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 6) Opciones de respuesta
-- =========================
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

-- =====================
-- 7) Sesiones de juego
-- =====================
CREATE TABLE game_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  player_id INT UNSIGNED NOT NULL,
  current_difficulty DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  score INT UNSIGNED NOT NULL DEFAULT 0,
  lives TINYINT UNSIGNED NOT NULL DEFAULT 3,
  status ENUM('active','completed','game_over') NOT NULL DEFAULT 'active',
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT ck_gs_diff  CHECK (current_difficulty >= 1.00 AND current_difficulty <= 5.00),
  CONSTRAINT ck_gs_lives CHECK (lives >= 0 AND lives <= 9),
  CONSTRAINT fk_gs_player FOREIGN KEY (player_id) REFERENCES players(id),
  KEY ix_gs_player_status (player_id, status, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- 8) Respuestas del jugador (log inmutable)
-- =========================================
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
  CONSTRAINT fk_pa_session  FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_pa_question FOREIGN KEY (question_id) REFERENCES questions(id),
  CONSTRAINT fk_pa_option   FOREIGN KEY (selected_option_id) REFERENCES question_options(id),
  UNIQUE KEY uk_pa_once (session_id, question_id),
  KEY ix_pa_session_time  (session_id, answered_at),
  KEY ix_pa_session_perf  (session_id, is_correct, answered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- 9) Auditoría administrativa
-- ===========================
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

-- =========================
-- 10) Triggers de integridad
--     (solo una opción correcta por pregunta)
-- =========================
DELIMITER $$

CREATE TRIGGER trg_qopt_one_correct_ins
BEFORE INSERT ON question_options
FOR EACH ROW
BEGIN
  IF NEW.is_correct = 1 THEN
    IF (SELECT COUNT(*) FROM question_options
        WHERE question_id = NEW.question_id AND is_correct = 1) >= 1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo una opción correcta por pregunta';
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_qopt_one_correct_upd
BEFORE UPDATE ON question_options
FOR EACH ROW
BEGIN
  IF NEW.is_correct = 1 AND OLD.is_correct <> 1 THEN
    IF (SELECT COUNT(*) FROM question_options
        WHERE question_id = NEW.question_id AND is_correct = 1) >= 1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo una opción correcta por pregunta';
    END IF;
  END IF;
END$$

DELIMITER ;

-- ======================
-- 11) Vistas de métricas
-- ======================

-- Por sesión
CREATE VIEW v_session_stats AS
SELECT
  pa.session_id,
  COUNT(*)                          AS questions_answered,
  AVG(pa.is_correct)                AS accuracy,
  AVG(pa.time_taken_seconds)        AS avg_time_sec
FROM player_answers pa
GROUP BY pa.session_id;

-- Por jugador y categoría
CREATE VIEW v_player_topic_stats AS
SELECT
  p.id                   AS player_id,
  qc.id                  AS category_id,
  qc.name                AS category_name,
  COUNT(pa.id)           AS answers,
  AVG(pa.is_correct)     AS accuracy,
  AVG(pa.time_taken_seconds) AS avg_time_sec
FROM players p
JOIN game_sessions gs ON gs.player_id = p.id
JOIN player_answers pa ON pa.session_id = gs.id
JOIN questions q ON q.id = pa.question_id
JOIN question_categories qc ON qc.id = q.category_id
GROUP BY p.id, qc.id, qc.name;

-- Actividad e inactividad
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