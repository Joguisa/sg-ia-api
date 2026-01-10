-- Migration: Add language field to game_rooms table
-- Purpose: Allow rooms to have a specific language for the game interface
-- Date: 2026-01-09

-- Add language column to game_rooms
ALTER TABLE game_rooms
ADD COLUMN language ENUM('es', 'en') NOT NULL DEFAULT 'es' AFTER max_players;

-- Add comment for documentation
ALTER TABLE game_rooms
MODIFY COLUMN language ENUM('es', 'en') NOT NULL DEFAULT 'es'
COMMENT 'Language for the game interface in this room (es=Spanish, en=English)';
