CREATE DATABASE IF NOT EXISTS auth_demo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE auth_demo;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(50)  NOT NULL,
  username      VARCHAR(15)  NULL UNIQUE,
  student_id    CHAR(8)      NOT NULL UNIQUE,
  phone         CHAR(11)     NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS books (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(120) NOT NULL,
  label        VARCHAR(60)  NOT NULL DEFAULT '',
  author       VARCHAR(80)  NOT NULL DEFAULT '',
  category     VARCHAR(20)  NOT NULL,
  section      VARCHAR(60)  NOT NULL DEFAULT 'General',
  description  TEXT         NOT NULL,
  total_pages  SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  color        CHAR(7)      NOT NULL DEFAULT '#1f5f7a',
  cover_image  VARCHAR(40)  NULL,
  created_by   INT UNSIGNED NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_books_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS library_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  book_id      INT UNSIGNED NOT NULL,
  status       ENUM('saved','current','archived') NOT NULL DEFAULT 'saved',
  current_page SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  added_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_book (user_id, book_id),
  CONSTRAINT fk_li_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_li_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reading_history (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  book_id      INT UNSIGNED NOT NULL,
  chapter      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  page         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  progress     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status       ENUM('in_progress','finished') NOT NULL DEFAULT 'in_progress',
  read_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_time (user_id, read_at),
  CONSTRAINT fk_rh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rh_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB;
