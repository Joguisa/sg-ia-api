-- =========================================================
-- SG-IA DB: Script de Inicialización Unificado (Clean Install)
-- Fecha: 2025-11-23
-- =========================================================

DROP DATABASE IF EXISTS sg_ia_db;
CREATE DATABASE sg_ia_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sg_ia_db;

SET SQL_MODE = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';
SET time_zone = '+00:00';

-- =========================================================
-- 1. TABLAS MAESTRAS (Catálogos)
-- =========================================================

-- Categorías de preguntas
CREATE TABLE question_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fuentes bibliográficas
CREATE TABLE content_sources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  citation VARCHAR(255) NOT NULL,
  url VARCHAR(512) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Administradores (Acceso al Dashboard)
CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2. TABLAS DE JUEGO (Usuarios y Contenido)
-- =========================================================

-- Jugadores
CREATE TABLE players (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  age TINYINT UNSIGNED NOT NULL, -- Columna agregada en migración 003
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_players_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preguntas
CREATE TABLE questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  statement TEXT NOT NULL,
  difficulty TINYINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  source_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_ai_generated BOOLEAN DEFAULT 0,   -- Columna agregada en migración 003
  admin_verified BOOLEAN DEFAULT 1,    -- Columna agregada en migración 003
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT ck_questions_difficulty CHECK (difficulty BETWEEN 1 AND 5),
  CONSTRAINT fk_questions_category FOREIGN KEY (category_id) REFERENCES question_categories(id),
  CONSTRAINT fk_questions_source   FOREIGN KEY (source_id)   REFERENCES content_sources(id),
  KEY ix_q_cat_diff_active (category_id, difficulty, is_active),
  KEY ix_q_diff_active (difficulty, is_active),
  KEY ix_questions_ai_verified (is_ai_generated, admin_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Explicaciones (Feedback educativo)
CREATE TABLE question_explanations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  text TEXT NOT NULL,
  source_ref VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qexp_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  KEY ix_qexp_q (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opciones de respuesta
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
-- 3. TABLAS TRANSACCIONALES (Sesiones y Respuestas)
-- =========================================================

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
  CONSTRAINT fk_pa_session  FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_pa_question FOREIGN KEY (question_id) REFERENCES questions(id),
  CONSTRAINT fk_pa_option   FOREIGN KEY (selected_option_id) REFERENCES question_options(id),
  UNIQUE KEY uk_pa_once (session_id, question_id),
  KEY ix_pa_session_time  (session_id, answered_at),
  KEY ix_pa_session_perf  (session_id, is_correct, answered_at)
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

-- =========================================================
-- 4. TRIGGERS
-- =========================================================

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

-- =========================================================
-- 5. VISTAS DE MÉTRICAS
-- =========================================================

CREATE VIEW v_session_stats AS
SELECT
  pa.session_id,
  COUNT(*)                          AS questions_answered,
  AVG(pa.is_correct)                AS accuracy,
  AVG(pa.time_taken_seconds)        AS avg_time_sec
FROM player_answers pa
GROUP BY pa.session_id;

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

-- =========================================================
-- 6. DATOS SEMILLA (SEED DATA)
-- =========================================================

-- Insertar Categorías
INSERT INTO question_categories (name, description) VALUES
('Epidemiología y Generalidades', 'Datos globales, regionales y tasas de supervivencia.'),
('Factores de Riesgo', 'Factores modificables (dieta, tabaco) y no modificables.'),
('Tamizaje y Detección', 'Métodos de diagnóstico y edades recomendadas.'),
('Prevención y Estilos de Vida', 'Hábitos saludables y autocuidado.');

-- Insertar Fuentes
INSERT INTO content_sources (citation, url) VALUES
('Guías de Salud Pública y Literatura Científica (Resumen Propuesta)', NULL);

-- Insertar Super Admin
-- Email: admin@sg-ia.com
-- Password: admin123
INSERT INTO admins (email, password_hash) 
VALUES ('admin@sg-ia.com', '$2y$10$7n3Lj5mK9xK8pQrLxZvN3O8qQ9r8sK7jL4mN6oP7qR8sT9uV0wX1y');