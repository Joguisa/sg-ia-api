-- ============================================================
-- SEED DATA: Banco de Preguntas - Cáncer de Colon (Piloto)
-- ============================================================

USE sg_ia_db;

-- 1. LIMPIEZA PREVIA (Para evitar duplicados si se corre varias veces)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE player_answers;
TRUNCATE TABLE game_sessions;
TRUNCATE TABLE question_options;
TRUNCATE TABLE question_explanations;
TRUNCATE TABLE questions;
TRUNCATE TABLE content_sources;
TRUNCATE TABLE question_categories;
SET FOREIGN_KEY_CHECKS = 1;

-- 2. CATEGORÍAS
-- Definidas según el Alcance del Proyecto 
INSERT INTO question_categories (id, name, description) VALUES
(1, 'Epidemiología y Generalidades', 'Datos globales, regionales y tasas de supervivencia.'),
(2, 'Factores de Riesgo', 'Factores modificables (dieta, tabaco) y no modificables.'),
(3, 'Tamizaje y Detección', 'Métodos de diagnóstico y edades recomendadas.'),
(4, 'Prevención y Estilos de Vida', 'Hábitos saludables y autocuidado.');

-- 3. FUENTES
-- Referencias académicas genéricas basadas en la propuesta
INSERT INTO content_sources (id, citation, url) VALUES
(1, 'Guías de Salud Pública y Literatura Científica (Resumen Propuesta)', NULL),
(2, 'Estadísticas Globales de Cáncer Colorrectal', NULL);

-- ============================================================
-- 4. BANCO DE PREGUNTAS (Distribución por Dificultad 1-5)
-- ============================================================

-- ------------------------------------------------------------
-- NIVEL 1: CONCEPTOS BÁSICOS (Binarias: Sí/No, V/F)
-- ------------------------------------------------------------

-- P1 (Prevención)
INSERT INTO questions (id, statement, difficulty, category_id, source_id) 
VALUES (1, '¿El cáncer de colon es una enfermedad que se puede prevenir con hábitos saludables?', 1, 4, 1);

INSERT INTO question_options (question_id, content, is_correct) VALUES 
(1, 'Sí', 1),
(1, 'No', 0);

INSERT INTO question_explanations (question_id, text) 
VALUES (1, 'Correcto. El cáncer de colon es altamente prevenible mediante la adopción de estilos de vida saludables y chequeos regulares.');

-- P2 (Riesgo - Sedentarismo)
INSERT INTO questions (id, statement, difficulty, category_id, source_id) 
VALUES (2, '¿La inactividad física aumenta el riesgo de desarrollar cáncer de colon?', 1, 2, 1);

INSERT INTO question_options (question_id, content, is_correct) VALUES 
(2, 'Verdadero', 1),
(2, 'Falso', 0);

INSERT INTO question_explanations (question_id, text) 
VALUES (2, 'Correcto. La inactividad física es un factor de riesgo modificable clave identificado en la literatura científica.');

-- ------------------------------------------------------------
-- NIVEL 2: CONOCIMIENTO GENERAL (Selección Múltiple Simple)
-- ------------------------------------------------------------

-- P3 (Tamizaje - Edad) 
INSERT INTO questions (id, statement, difficulty, category_id, source_id) 
VALUES (3, 'Según las guías de salud pública actuales, ¿a qué edad se recomienda generalmente iniciar el tamizaje para cáncer de colon?', 2, 3, 1);

INSERT INTO question_options (question_id, content, is_correct) VALUES 
(3, 'A los 20 años', 0),
(3, 'A los 45 años', 1),
(3, 'A los 65 años', 0),
(3, 'Cuando aparezcan síntomas graves', 0);

INSERT INTO question_explanations (question_id, text) 
VALUES (3, 'Correcto. Las recomendaciones actuales sugieren iniciar el tamizaje a los 45 años para una detección temprana efectiva.');

-- P4 (Riesgo - Alimentación) 
INSERT INTO questions (id, statement, difficulty, category_id, source_id) 
VALUES (4, '¿Cuál de los siguientes alimentos se asocia con un mayor riesgo de cáncer de colon si se consume en exceso?', 2, 2, 1);

INSERT INTO question_options (question_id, content, is_correct) VALUES 
(4, 'Frutas y verduras', 0),
(4, 'Pescado azul', 0),
(4, 'Carnes rojas y procesadas', 1),
(4, 'Granos integrales', 0);

INSERT INTO question_explanations (question_id, text) 
VALUES (4, 'Correcto. El consumo excesivo de carne roja y procesada es un factor de riesgo modificable reconocido.');

-- ------------------------------------------------------------
-- NIVEL 3: CONOCIMIENTO INTERMEDIO (Asociaciones)
-- ------------------------------------------------------------

-- P5 (Detección - Métodos) 
INSERT INTO questions (id, statement, difficulty, category_id, source_id) 
VALUES (5, 'Además de la colonoscopía, ¿qué otra prueba se utiliza comúnmente para el tamizaje inicial?', 3, 3, 1);

INSERT INTO question_options (question_id, content, is_correct) VALUES 
(5, 'Radiografía de tórax', 0),
(5, 'Prueba inmunoquímica fecal', 1),
(5, 'Examen de orina', 0),
(5, 'Electrocardiograma', 0);

INSERT INTO question_explanations (question_id, text) 
VALUES (5, 'Correcto. La prueba inmunoquímica fecal es un método de tamizaje validado y menos invasivo para la detección inicial.');

-- P6 (Epidemiología - Detección temprana) 
INSERT INTO questions (id, statement, difficulty, category_id, source_id) 
VALUES (6, '¿Por qué es crítica la detección temprana del cáncer de colon?', 3, 1, 1);

INSERT INTO question_options (question_id, content, is_correct) VALUES 
(6, 'Porque no existe tratamiento en etapas avanzadas', 0),
(6, 'Porque la supervivencia disminuye drásticamente entre la Etapa I y la IV', 1),
(6, 'Porque afecta solo a personas jóvenes', 0);

INSERT INTO question_explanations (question_id, text) 
VALUES (6, 'Correcto. Existe una gran diferencia en la supervivencia dependiendo de la etapa en que se detecte la enfermedad.');

-- ------------------------------------------------------------
-- NIVEL 4: DATOS AVANZADOS (Porcentajes y Estadísticas)
-- ------------------------------------------------------------

-- P7 (Supervivencia - Etapa I) 
INSERT INTO questions (id, statement, difficulty, category_id, source_id) 
VALUES (7, 'Según la evidencia científica, ¿cuál es la tasa aproximada de supervivencia si el cáncer se detecta en la Etapa I?', 4, 1, 1);

INSERT INTO question_options (question_id, content, is_correct) VALUES 
(7, 'Alrededor del 50%', 0),
(7, 'Aproximadamente el 91%', 1),
(7, 'Cerca del 14%', 0),
(7, 'El 100% siempre', 0);

INSERT INTO question_explanations (question_id, text) 
VALUES (7, 'Correcto. La detección en Etapa I ofrece una tasa de supervivencia muy alta, cercana al 91%.');

-- P8 (Supervivencia - Etapa IV) 
INSERT INTO questions (id, statement, difficulty, category_id, source_id) 
VALUES (8, 'En contraste, ¿a cuánto desciende la tasa de supervivencia si el cáncer se detecta tardíamente en la Etapa IV?', 4, 1, 1);

INSERT INTO question_options (question_id, content, is_correct) VALUES 
(8, 'Baja al 60%', 0),
(8, 'Baja drásticamente al 14%', 1),
(8, 'Se mantiene en el 80%', 0),
(8, 'Es del 5%', 0);

INSERT INTO question_explanations (question_id, text) 
VALUES (8, 'Correcto. La supervivencia en Etapa IV cae significativamente al 14%, lo que resalta la importancia del tamizaje.');

-- ------------------------------------------------------------
-- NIVEL 5: EXPERTO (Odds Ratios y Datos Técnicos)
-- ------------------------------------------------------------

-- P9 (Riesgo - Historia Familiar OR) 
INSERT INTO questions (id, statement, difficulty, category_id, source_id) 
VALUES (9, 'En términos estadísticos de riesgo (Odds Ratio - OR), ¿cuál es el impacto de tener historia familiar de cáncer de colon?', 5, 2, 1);

INSERT INTO question_options (question_id, content, is_correct) VALUES 
(9, 'OR de 1.57 (Riesgo moderado)', 0),
(9, 'OR de 5.90 (Riesgo muy alto)', 1),
(9, 'OR de 1.44 (Riesgo leve)', 0),
(9, 'No tiene impacto estadístico', 0);

INSERT INTO question_explanations (question_id, text) 
VALUES (9, 'Correcto. La historia familiar es un factor de riesgo no modificable crítico, con un Odds Ratio elevado de 5.90.');

-- P10 (Riesgo - Tabaquismo OR) 
INSERT INTO questions (id, statement, difficulty, category_id, source_id) 
VALUES (10, 'Según la literatura validada, ¿cuál es el Odds Ratio (OR) asociado al tabaquismo como factor de riesgo?', 5, 2, 1);

INSERT INTO question_options (question_id, content, is_correct) VALUES 
(10, 'OR 1.44', 1),
(10, 'OR 5.90', 0),
(10, 'OR 0.35', 0),
(10, 'OR 2.50', 0);

INSERT INTO question_explanations (question_id, text) 
VALUES (10, 'Correcto. El tabaquismo presenta un OR de 1.44, lo que indica una asociación significativa con el aumento del riesgo.');

-- P11 (Riesgo - Obesidad OR) 
INSERT INTO questions (id, statement, difficulty, category_id, source_id) 
VALUES (11, '¿Qué valor de Odds Ratio (OR) se atribuye a la obesidad como factor de riesgo para cáncer de colon?', 5, 2, 1);

INSERT INTO question_options (question_id, content, is_correct) VALUES 
(11, 'OR 1.57', 1),
(11, 'OR 3.00', 0),
(11, 'OR 0.90', 0),
(11, 'OR 10.5', 0);

INSERT INTO question_explanations (question_id, text) 
VALUES (11, 'Correcto. La obesidad es un factor de riesgo modificable con un OR de 1.57 según los estudios referenciados.');