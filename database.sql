CREATE TABLE comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_path VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    author VARCHAR(80) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX comments_post_status (post_path, status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Non si conservano gli IP: solo hash temporanei per limitare gli invii.
CREATE TABLE comment_rate_limits (
    client_hash CHAR(64) CHARACTER SET ascii PRIMARY KEY,
    last_submission BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;
