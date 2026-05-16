CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS store_books (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    author      VARCHAR(255) NOT NULL,
    cover_url   VARCHAR(500),
    description TEXT,
    genre       VARCHAR(100),
    file_path   VARCHAR(500) NOT NULL,
    file_type   ENUM('epub','pdf') NOT NULL,
    redeem_code VARCHAR(8)   NOT NULL UNIQUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_books (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT          NOT NULL,
    title         VARCHAR(255) NOT NULL,
    author        VARCHAR(255) NOT NULL,
    cover_url     VARCHAR(500),
    genre         VARCHAR(100),
    file_path     VARCHAR(500) NOT NULL,
    file_type     ENUM('epub','pdf') NOT NULL,
    source        ENUM('upload','store') NOT NULL DEFAULT 'upload',
    store_book_id INT NULL,
    added_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (store_book_id) REFERENCES store_books(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reading_progress (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_book_id  INT          NOT NULL,
    user_id       INT          NOT NULL,
    cfi_position  TEXT,
    page_number   INT          NOT NULL DEFAULT 0,
    percentage    DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    last_read     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_book_id) REFERENCES user_books(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
    UNIQUE KEY uq_progress (user_book_id, user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS redeemed_codes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    store_book_id INT NOT NULL,
    redeemed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (store_book_id) REFERENCES store_books(id) ON DELETE CASCADE,
    UNIQUE KEY uq_redeem (user_id, store_book_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bundle_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(12)  NOT NULL UNIQUE,
    name       VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bundle_books (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    bundle_id     INT NOT NULL,
    store_book_id INT NOT NULL,
    FOREIGN KEY (bundle_id)     REFERENCES bundle_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (store_book_id) REFERENCES store_books(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS redeemed_bundles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    bundle_id   INT NOT NULL,
    redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)   REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (bundle_id) REFERENCES bundle_codes(id) ON DELETE CASCADE,
    UNIQUE KEY uq_bundle_redeem (user_id, bundle_id)
) ENGINE=InnoDB;

-- Default admin: email=admin@thgbook.com  password=admin123
-- Hash generated with password_hash('admin123', PASSWORD_BCRYPT)
-- If login fails run setup/install.php to regenerate the hash
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@thgbook.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'admin')
ON DUPLICATE KEY UPDATE id=id;
