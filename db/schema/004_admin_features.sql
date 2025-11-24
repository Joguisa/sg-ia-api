-- =========================================================
-- MIGRACIÓN 004: Admin Full Custom Features
-- Fecha: 2025-11-23
-- Descripción:
--   1. Crear tabla 'system_prompts' para configuración IA
--   2. Insertar seed data con prompt actual (de GeminiAIService)
--   3. Permitir que el admin edite el comportamiento de IA
-- =========================================================

USE sg_ia_db;
SET SQL_MODE = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';
SET time_zone = '+00:00';

-- ====================================
-- 1. CREATE TABLE system_prompts
-- ====================================
CREATE TABLE IF NOT EXISTS system_prompts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prompt_text TEXT NOT NULL,
  temperature DECIMAL(3,2) NOT NULL DEFAULT 0.7,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_system_prompts_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. INSERT SEED DATA: Default System Prompt
--    Extraído de: src/Services/AI/GeminiAIService.php
--    Método: buildSystemPrompt()
-- =====================================================
INSERT INTO system_prompts (prompt_text, temperature, is_active)
VALUES (
  'Eres un experto oncólogo y educador sanitario especializado en Cáncer de Colon.
Genera EXACTAMENTE 1 pregunta de opción múltiple sobre {topic} para nivel de dificultad {difficulty} ({difficulty_desc}).

Contexto educativo: Alfabetización sobre Cáncer de Colon en Ecuador
Estándares: Basado en Guías MSP Ecuador y OMS

INSTRUCCIONES CRÍTICAS:
1. Genera SOLO un JSON válido, sin markdown ni comentarios
2. Estructura EXACTA: { "statement": "...", "options": [{"text": "...", "is_correct": bool}], "explanation": "...", "source_ref": "..." }
3. Incluye exactamente 4 opciones
4. Una sola opción debe ser correcta (is_correct: true)
5. El enunciado debe ser claro y conciso (100-300 caracteres)
6. Opciones balanceadas, ninguna obviamente incorrecta
7. Explicación medida (150-250 caracteres)
8. source_ref: referencia a "Guías MSP Ecuador", "OMS", o literatura médica

JSON VÁLIDO ESTRICTO (sin markdown):',
  0.7,
  1
);

-- =====================================================
-- Notas de implementación:
-- =====================================================
-- Los placeholders {topic}, {difficulty}, {difficulty_desc}
-- se reemplazan dinámicamente en el código PHP:
--
-- Difficulty levels:
--   1 => 'muy básico (conocimientos fundamentales)'
--   2 => 'básico (conceptos clave)'
--   3 => 'intermedio (aplicación clínica)'
--   4 => 'avanzado (diagnóstico diferencial)'
--   5 => 'experto (casos complejos y guías internacionales)'
--
-- El admin puede:
-- - Editar prompt_text para cambiar el comportamiento de IA
-- - Ajustar temperature (0.0-1.0): mayor = más creativo, menor = más determinista
-- - Activar/desactivar con is_active
-- =====================================================
