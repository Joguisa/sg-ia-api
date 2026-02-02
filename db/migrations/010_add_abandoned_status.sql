ALTER TABLE game_sessions
MODIFY COLUMN status ENUM('active', 'completed', 'game_over', 'abandoned') NOT NULL DEFAULT 'active';
