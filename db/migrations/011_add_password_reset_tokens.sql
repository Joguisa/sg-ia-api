CREATE TABLE password_reset_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE COMMENT '6-digit code',
    expires_at TIMESTAMP NOT NULL COMMENT '15 minutes expiration',
    used_at TIMESTAMP NULL COMMENT 'Timestamp when used',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prt_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    KEY ix_prt_token (token),
    KEY ix_prt_expires (expires_at),
    KEY ix_prt_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
