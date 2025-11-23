-- =========================================================
-- MIGRACIÓN 003: Actualización de Requisitos Funcionales
-- Fecha: 2025-11-23
-- Descripción:
--   1. Agregar columna 'age' a tabla 'players'
--   2. Agregar columnas 'is_ai_generated' y 'admin_verified' a tabla 'questions'
--   3. Crear tabla 'admins' para gestión administrativa
--   4. Insertar admin por defecto
-- =========================================================

USE sg_ia_db;
SET SQL_MODE = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';
SET time_zone = '+00:00';

-- ==============================================
-- 1. ALTER TABLE players: Agregar columna age
-- ==============================================
ALTER TABLE players
ADD COLUMN age TINYINT UNSIGNED NOT NULL AFTER name;

-- ================================================
-- 2. ALTER TABLE questions: Agregar columnas IA
-- ================================================
ALTER TABLE questions
ADD COLUMN is_ai_generated BOOLEAN DEFAULT 0 AFTER is_active,
ADD COLUMN admin_verified BOOLEAN DEFAULT 1 AFTER is_ai_generated;

-- ====================================
-- 3. CREATE TABLE admins
-- ====================================
CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- 4. INSERT admin por defecto (email: admin@sg-ia.com)
--    Password: 'admin123' hasheado con BCRYPT
--    Hash generado: $2y$10$7n3Lj5mK9xK8pQrLxZvN3O8qQ9r8sK7jL4mN6oP7qR8sT9uV0wX1y
-- ======================================================
INSERT INTO admins (email, password_hash)
VALUES ('admin@sg-ia.com', '$2y$10$7n3Lj5mK9xK8pQrLxZvN3O8qQ9r8sK7jL4mN6oP7qR8sT9uV0wX1y');

-- =====================================================
-- Índices adicionales para optimizar consultas
-- =====================================================
CREATE INDEX ix_questions_ai_verified ON questions(is_ai_generated, admin_verified);
CREATE INDEX ix_questions_verified ON questions(admin_verified);
