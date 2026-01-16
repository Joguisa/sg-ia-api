-- =========================================================
-- Migración 007: Actualizar y crear vistas de estadísticas de salas
-- Fecha: 2026-01-16
-- =========================================================

USE sg_ia_db;

-- Eliminar vista existente para recrearla con campos adicionales
DROP VIEW IF EXISTS v_room_statistics;

-- Vista de estadísticas generales de sala (actualizada con avg_time_sec y avg_score)
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
  COALESCE(ROUND(AVG(pa.is_correct) * 100, 2), 0) AS avg_accuracy,
  COALESCE(ROUND(AVG(pa.time_taken_seconds), 2), 0) AS avg_time_sec,
  COALESCE(MAX(gs.score), 0) AS highest_score,
  COALESCE(ROUND(AVG(gs.score), 2), 0) AS avg_score
FROM game_rooms gr
LEFT JOIN game_sessions gs ON gs.room_id = gr.id
LEFT JOIN player_answers pa ON pa.session_id = gs.id
GROUP BY gr.id, gr.room_code, gr.name, gr.language, gr.status;

-- Vista de estadísticas de jugadores por sala
DROP VIEW IF EXISTS v_room_player_stats;

CREATE VIEW v_room_player_stats AS
SELECT
  gs.room_id,
  p.id AS player_id,
  p.name AS player_name,
  p.age AS player_age,
  COUNT(DISTINCT gs.id) AS total_sessions,
  COALESCE(MAX(gs.score), 0) AS high_score,
  COALESCE(ROUND(AVG(gs.score), 2), 0) AS avg_score,
  COUNT(pa.id) AS total_answers,
  COALESCE(ROUND(AVG(pa.is_correct) * 100, 2), 0) AS accuracy,
  COALESCE(ROUND(AVG(pa.time_taken_seconds), 2), 0) AS avg_time_sec,
  MAX(gs.started_at) AS last_played
FROM players p
INNER JOIN game_sessions gs ON gs.player_id = p.id
LEFT JOIN player_answers pa ON pa.session_id = gs.id
WHERE gs.room_id IS NOT NULL
GROUP BY gs.room_id, p.id, p.name, p.age;

-- Vista de estadísticas de preguntas por sala
DROP VIEW IF EXISTS v_room_question_stats;

CREATE VIEW v_room_question_stats AS
SELECT
  gs.room_id,
  q.id AS question_id,
  q.statement,
  q.difficulty,
  qc.name AS category_name,
  COUNT(pa.id) AS times_answered,
  SUM(CASE WHEN pa.is_correct = 1 THEN 1 ELSE 0 END) AS correct_count,
  SUM(CASE WHEN pa.is_correct = 0 THEN 1 ELSE 0 END) AS incorrect_count,
  COALESCE(ROUND((SUM(CASE WHEN pa.is_correct = 0 THEN 1 ELSE 0 END) / NULLIF(COUNT(pa.id), 0)) * 100, 2), 0) AS error_rate,
  COALESCE(ROUND(AVG(pa.time_taken_seconds), 2), 0) AS avg_time_sec
FROM questions q
INNER JOIN player_answers pa ON pa.question_id = q.id
INNER JOIN game_sessions gs ON gs.id = pa.session_id
INNER JOIN question_categories qc ON qc.id = q.category_id
WHERE gs.room_id IS NOT NULL
GROUP BY gs.room_id, q.id, q.statement, q.difficulty, qc.name;

-- Vista de estadísticas por categoría por sala
DROP VIEW IF EXISTS v_room_category_stats;

CREATE VIEW v_room_category_stats AS
SELECT
  gs.room_id,
  qc.id AS category_id,
  qc.name AS category_name,
  COUNT(pa.id) AS total_answers,
  SUM(CASE WHEN pa.is_correct = 1 THEN 1 ELSE 0 END) AS correct_count,
  COALESCE(ROUND(AVG(pa.is_correct) * 100, 2), 0) AS accuracy,
  COALESCE(ROUND(AVG(pa.time_taken_seconds), 2), 0) AS avg_time_sec
FROM question_categories qc
INNER JOIN questions q ON q.category_id = qc.id
INNER JOIN player_answers pa ON pa.question_id = q.id
INNER JOIN game_sessions gs ON gs.id = pa.session_id
WHERE gs.room_id IS NOT NULL
GROUP BY gs.room_id, qc.id, qc.name;

-- Verificación
SELECT 'Migración 007 completada - Vistas de sala actualizadas' AS mensaje;
