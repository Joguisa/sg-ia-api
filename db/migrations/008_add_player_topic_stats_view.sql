-- =========================================================
-- Migración 008: Crear vista v_player_topic_stats
-- Fecha: 2026-01-16
-- Descripción: Vista para estadísticas de jugador por categoría
-- =========================================================

USE sg_ia_db;

-- Eliminar vista si existe
DROP VIEW IF EXISTS v_player_topic_stats;

-- Vista de estadísticas de jugador por categoría/tema
CREATE VIEW v_player_topic_stats AS
SELECT
  p.id AS player_id,
  qc.id AS category_id,
  qc.name AS category_name,
  COUNT(pa.id) AS answers,
  COALESCE(ROUND(AVG(pa.is_correct) * 100, 2), 0) AS accuracy_percent,
  COALESCE(ROUND(AVG(pa.time_taken_seconds), 2), 0) AS avg_time_sec
FROM players p
INNER JOIN game_sessions gs ON gs.player_id = p.id
INNER JOIN player_answers pa ON pa.session_id = gs.id
INNER JOIN questions q ON q.id = pa.question_id
INNER JOIN question_categories qc ON qc.id = q.category_id
GROUP BY p.id, qc.id, qc.name;

-- Verificación
SELECT 'Migración 008 completada - Vista v_player_topic_stats creada' AS mensaje;
