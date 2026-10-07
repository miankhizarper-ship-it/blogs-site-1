-- =====================================================================
-- BLOG PLATFORM SCHEMA  (MySQL 5.7+/8.0, MariaDB 10.3+)
-- Charset utf8mb4 / InnoDB / FKs / indexes
-- Import:  mysql -h HOST -u USER -p DBNAME < schema.sql
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------ users
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100)     NOT NULL,
    email           VARCHAR(190)     NOT NULL,
    password        VARCHAR(255)     NOT NULL,             -- bcrypt via password_hash()
    role            ENUM('admin','editor','author') NOT NULL DEFAULT 'author',
    bio             TEXT             NULL,
    avatar          VARCHAR(255)     NULL,
    slug            VARCHAR(190)     NOT NULL,             -- for /author/{slug}
    must_change_pw  TINYINT(1)       NOT NULL DEFAULT 0,   -- force change on first login
    reset_token     VARCHAR(64)      NULL,                 -- forgot-password token (hashed at rest)
    reset_expires   DATETIME         NULL,
    remember_token  VARCHAR(64)      NULL,                 -- optional remember-me
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    last_login_at   DATETIME         NULL,
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_slug  (slug),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ categories
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL,
    description TEXT         NULL,
    image       VARCHAR(255) NULL,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ blogs
CREATE TABLE IF NOT EXISTS blogs (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title            VARCHAR(255) NOT NULL,
    slug             VARCHAR(255) NOT NULL,
    excerpt          VARCHAR(500) NULL,
    content          LONGTEXT     NOT NULL,               -- sanitized HTML from WYSIWYG
    featured_image   VARCHAR(255) NULL,
    status           ENUM('draft','published','scheduled','trashed') NOT NULL DEFAULT 'draft',
    is_featured      TINYINT(1)   NOT NULL DEFAULT 0,     -- homepage slider
    featured_order   SMALLINT     NOT NULL DEFAULT 0,
    rank             INT          NOT NULL DEFAULT 0,     -- trending/top ordering (drag & drop)
    category_id      INT UNSIGNED NULL,
    author_id        INT UNSIGNED NOT NULL,
    views            INT UNSIGNED NOT NULL DEFAULT 0,
    likes            INT UNSIGNED NOT NULL DEFAULT 0,
    reading_time     SMALLINT UNSIGNED NOT NULL DEFAULT 1, -- cached minutes
    published_at     DATETIME     NULL,                   -- set when published / scheduled time
    -- SEO fields
    meta_title       VARCHAR(190) NULL,
    meta_description VARCHAR(320) NULL,
    focus_keyword    VARCHAR(190) NULL,
    canonical_url    VARCHAR(500) NULL,
    og_title         VARCHAR(190) NULL,
    og_description   VARCHAR(320) NULL,
    og_image         VARCHAR(255) NULL,
    robots_meta      VARCHAR(20)  NOT NULL DEFAULT 'index,follow',
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME     NULL,                   -- soft delete / trash
    PRIMARY KEY (id),
    UNIQUE KEY uq_blogs_slug (slug),
    KEY idx_blogs_status_pub   (status, published_at),
    KEY idx_blogs_category     (category_id),
    KEY idx_blogs_author       (author_id),
    KEY idx_blogs_featured     (is_featured, featured_order),
    KEY idx_blogs_rank         (rank),
    KEY idx_blogs_deleted      (deleted_at),
    FULLTEXT KEY ft_blogs_search (title, excerpt, content),
    CONSTRAINT fk_blogs_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_blogs_author   FOREIGN KEY (author_id)  REFERENCES users(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ tags + pivot
CREATE TABLE IF NOT EXISTS tags (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(80)  NOT NULL,
    slug       VARCHAR(120) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_tags (
    blog_id INT UNSIGNED NOT NULL,
    tag_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (blog_id, tag_id),
    KEY idx_bt_tag (tag_id),
    CONSTRAINT fk_bt_blog FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    CONSTRAINT fk_bt_tag  FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ comments (nested via parent_id)
CREATE TABLE IF NOT EXISTS comments (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    blog_id         INT UNSIGNED NOT NULL,
    parent_id       INT UNSIGNED NULL,               -- nested replies
    user_id         INT UNSIGNED NULL,               -- set when admin reply
    author_name     VARCHAR(100)  NOT NULL,
    author_email    VARCHAR(190)  NULL,
    content         TEXT          NOT NULL,
    status          ENUM('pending','approved','spam','trash') NOT NULL DEFAULT 'pending',
    is_admin_reply  TINYINT(1)    NOT NULL DEFAULT 0,
    ip_address      VARCHAR(45)   NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comments_blog_status (blog_id, status),
    KEY idx_comments_parent      (parent_id),
    CONSTRAINT fk_comments_blog   FOREIGN KEY (blog_id)   REFERENCES blogs(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user   FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ contact messages
CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(190) NOT NULL,
    subject    VARCHAR(190) NULL,
    message    TEXT         NOT NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    replied_at DATETIME     NULL,
    ip_address VARCHAR(45)  NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_read (is_read),
    KEY idx_contact_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ newsletter subscribers
CREATE TABLE IF NOT EXISTS subscribers (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45)  NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscribers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ static pages (About, legal, custom)
CREATE TABLE IF NOT EXISTS pages (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title            VARCHAR(190) NOT NULL,
    slug             VARCHAR(190) NOT NULL,
    content          LONGTEXT     NULL,
    meta_title       VARCHAR(190) NULL,
    meta_description VARCHAR(320) NULL,
    is_published     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ media library
CREATE TABLE IF NOT EXISTS media (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_name     VARCHAR(255) NOT NULL,             -- stored (renamed) filename
    original_name VARCHAR(255) NOT NULL,
    file_path     VARCHAR(500) NOT NULL,             -- relative: uploads/...
    mime_type     VARCHAR(100) NOT NULL,
    file_size     INT UNSIGNED NOT NULL,             -- bytes
    width         SMALLINT UNSIGNED NULL,
    height        SMALLINT UNSIGNED NULL,
    alt_text      VARCHAR(255) NULL,
    uploaded_by   INT UNSIGNED NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_media_created (created_at),
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ settings (key/value)
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT         NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ per-hit view log (for charts)
CREATE TABLE IF NOT EXISTS blog_views (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    blog_id    INT UNSIGNED    NOT NULL,
    viewed_on  DATE            NOT NULL,             -- daily bucket
    ip_hash    CHAR(32)        NULL,                 -- hashed IP for dedupe
    user_agent VARCHAR(255)    NULL,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_views_blog_date (blog_id, viewed_on),
    KEY idx_views_date      (viewed_on),
    CONSTRAINT fk_views_blog FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ admin activity log
CREATE TABLE IF NOT EXISTS activity_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NULL,
    action      VARCHAR(100)    NOT NULL,             -- e.g. blog.created
    description VARCHAR(500)    NULL,
    model_type  VARCHAR(50)     NULL,
    model_id    INT UNSIGNED    NULL,
    ip_address  VARCHAR(45)     NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_user (user_id),
    KEY idx_log_created (created_at),
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ login throttling
CREATE TABLE IF NOT EXISTS login_attempts (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email       VARCHAR(190) NOT NULL,
    ip_address  VARCHAR(45)  NOT NULL,
    success     TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempt_lookup (ip_address, email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
