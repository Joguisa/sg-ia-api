CREATE TABLE user_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin', 'player') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    ui_language ENUM('es', 'en') NOT NULL DEFAULT 'es',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_pref (user_type, user_id),
    KEY ix_user_pref_type (user_type),
    KEY ix_user_pref_lang (ui_language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
