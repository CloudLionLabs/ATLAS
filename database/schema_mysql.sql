SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    email VARCHAR(190) NULL,
    display_name VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'member',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_active (is_active, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS countries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    iso2 CHAR(2) NOT NULL,
    iso3 CHAR(3) NULL,
    name_en VARCHAR(255) NOT NULL,
    name_local VARCHAR(255) NULL,
    name_zh VARCHAR(255) NULL,
    default_language VARCHAR(12) NOT NULL DEFAULT 'en',
    phone_code VARCHAR(20) NULL,
    center_lat DOUBLE NULL,
    center_lng DOUBLE NULL,
    default_zoom TINYINT UNSIGNED NOT NULL DEFAULT 5,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_countries_iso2 (iso2),
    KEY idx_countries_active (is_active, name_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS regions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    country_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    region_type VARCHAR(30) NOT NULL DEFAULT 'city',
    name VARCHAR(255) NOT NULL,
    name_local VARCHAR(255) NULL,
    code VARCHAR(100) NULL,
    latitude DOUBLE NULL,
    longitude DOUBLE NULL,
    default_zoom TINYINT UNSIGNED NOT NULL DEFAULT 11,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_regions_country (country_id, region_type),
    KEY idx_regions_name (name),
    CONSTRAINT fk_regions_country FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
    CONSTRAINT fk_regions_parent FOREIGN KEY (parent_id) REFERENCES regions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS industries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED NULL,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_industries_code (code),
    KEY idx_industries_parent (parent_id),
    CONSTRAINT fk_industries_parent FOREIGN KEY (parent_id) REFERENCES industries(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS industry_translations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    industry_id BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(12) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_industry_translation (industry_id, language_code),
    CONSTRAINT fk_industry_translations_industry FOREIGN KEY (industry_id) REFERENCES industries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS keywords (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    industry_id BIGINT UNSIGNED NULL,
    country_id BIGINT UNSIGNED NULL,
    language_code VARCHAR(12) NOT NULL DEFAULT 'en',
    keyword VARCHAR(300) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_keywords_filters (country_id, industry_id, language_code, is_active),
    KEY idx_keywords_user (user_id, is_active, created_at),
    KEY idx_keywords_picker (user_id, is_active, country_id, industry_id, language_code),
    CONSTRAINT fk_keywords_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_keywords_industry FOREIGN KEY (industry_id) REFERENCES industries(id) ON DELETE SET NULL,
    CONSTRAINT fk_keywords_country FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scrape_batches (
    id VARCHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    queue_mode TINYINT(1) NOT NULL DEFAULT 0,
    queue_paused TINYINT(1) NOT NULL DEFAULT 0,
    total_jobs INT UNSIGNED NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_batches_user (user_id, created_at),
    KEY idx_batches_queue (user_id, queue_mode, created_at),
    CONSTRAINT fk_batches_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scrape_jobs (
    id VARCHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    batch_id VARCHAR(64) NULL,
    country_id BIGINT UNSIGNED NULL,
    region_id BIGINT UNSIGNED NULL,
    industry_id BIGINT UNSIGNED NULL,
    keyword_id BIGINT UNSIGNED NULL,
    task_name VARCHAR(500) NOT NULL,
    keyword_text VARCHAR(500) NOT NULL,
    language_code VARCHAR(12) NOT NULL DEFAULT 'en',
    latitude DOUBLE NULL,
    longitude DOUBLE NULL,
    zoom TINYINT UNSIGNED NOT NULL DEFAULT 15,
    radius INT UNSIGNED NOT NULL DEFAULT 10000,
    depth SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    fastmode TINYINT(1) NOT NULL DEFAULT 0,
    extract_email TINYINT(1) NOT NULL DEFAULT 0,
    queue_order INT UNSIGNED NOT NULL DEFAULT 0,
    max_time VARCHAR(20) NOT NULL DEFAULT '10m',
    scraper_job_id VARCHAR(64) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    result_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    target_address VARCHAR(1000) NULL,
    external_status VARCHAR(64) NULL,
    raw_csv_path VARCHAR(1000) NULL,
    last_synced_at DATETIME NULL,
    submitted_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    imported_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_jobs_user (user_id, status, created_at),
    KEY idx_jobs_user_created (user_id, created_at),
    KEY idx_jobs_batch_queue (batch_id, queue_order),
    KEY idx_jobs_status (status, created_at),
    KEY idx_jobs_scraper (scraper_job_id),
    KEY idx_jobs_user_external (user_id, scraper_job_id),
    KEY idx_jobs_country (country_id),
    CONSTRAINT fk_jobs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_jobs_batch FOREIGN KEY (batch_id) REFERENCES scrape_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_jobs_country FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL,
    CONSTRAINT fk_jobs_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL,
    CONSTRAINT fk_jobs_industry FOREIGN KEY (industry_id) REFERENCES industries(id) ON DELETE SET NULL,
    CONSTRAINT fk_jobs_keyword FOREIGN KEY (keyword_id) REFERENCES keywords(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS businesses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    business_name VARCHAR(500) NOT NULL,
    normalized_name VARCHAR(500) NULL,
    industry_id BIGINT UNSIGNED NULL,
    country_id BIGINT UNSIGNED NULL,
    region_id BIGINT UNSIGNED NULL,
    country_code CHAR(2) NULL,
    city VARCHAR(255) NULL,
    state_region VARCHAR(255) NULL,
    postal_code VARCHAR(40) NULL,
    address VARCHAR(1000) NULL,
    phone VARCHAR(80) NULL,
    normalized_phone VARCHAR(60) NULL,
    website VARCHAR(1000) NULL,
    website_domain VARCHAR(255) NULL,
    description TEXT NULL,
    email_status VARCHAR(30) NOT NULL DEFAULT 'not_checked',
    google_data_id VARCHAR(191) NULL,
    google_cid VARCHAR(191) NULL,
    google_place_id VARCHAR(191) NULL,
    plus_code VARCHAR(100) NULL,
    latitude DOUBLE NULL,
    longitude DOUBLE NULL,
    rating DECIMAL(3,2) NULL,
    review_count INT UNSIGNED NULL,
    business_status VARCHAR(100) NULL,
    timezone VARCHAR(100) NULL,
    source_first_seen DATETIME NULL,
    completeness_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_businesses_user (user_id, created_at),
    KEY idx_businesses_user_country (user_id, country_id),
    KEY idx_businesses_user_industry (user_id, industry_id),
    KEY idx_businesses_user_phone (user_id, normalized_phone),
    KEY idx_businesses_user_name (user_id, normalized_name(191)),
    KEY idx_businesses_data_id (user_id, google_data_id),
    KEY idx_businesses_place_id (user_id, google_place_id),
    KEY idx_businesses_domain (user_id, website_domain),
    KEY idx_businesses_completeness (user_id, completeness_score),
    KEY idx_businesses_user_updated (user_id, updated_at),
    KEY idx_businesses_user_email (user_id, email_status),
    FULLTEXT KEY ft_business_search (business_name, address, website, phone),
    CONSTRAINT fk_businesses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_businesses_industry FOREIGN KEY (industry_id) REFERENCES industries(id) ON DELETE SET NULL,
    CONSTRAINT fk_businesses_country FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL,
    CONSTRAINT fk_businesses_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    contact_type VARCHAR(30) NOT NULL,
    contact_value VARCHAR(500) NOT NULL,
    normalized_value VARCHAR(191) NULL,
    source_url VARCHAR(1000) NULL,
    source_type VARCHAR(50) NOT NULL DEFAULT 'scraper',
    verification_status VARCHAR(30) NOT NULL DEFAULT 'unverified',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_business_contact (business_id, contact_type, normalized_value),
    KEY idx_contacts_business (business_id),
    CONSTRAINT fk_contacts_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS raw_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id VARCHAR(64) NOT NULL,
    row_no INT UNSIGNED NOT NULL,
    external_data_id VARCHAR(255) NULL,
    raw_json LONGTEXT NOT NULL,
    content_hash CHAR(64) NOT NULL,
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_raw_job_row (job_id, row_no),
    UNIQUE KEY uq_raw_job_hash (job_id, content_hash),
    KEY idx_raw_job (job_id),
    CONSTRAINT fk_raw_job FOREIGN KEY (job_id) REFERENCES scrape_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    raw_record_id BIGINT UNSIGNED NOT NULL,
    job_id VARCHAR(64) NOT NULL,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_business_raw (business_id, raw_record_id),
    KEY idx_sources_job (job_id),
    KEY idx_sources_business (business_id),
    CONSTRAINT fk_sources_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_sources_raw FOREIGN KEY (raw_record_id) REFERENCES raw_records(id) ON DELETE CASCADE,
    CONSTRAINT fk_sources_job FOREIGN KEY (job_id) REFERENCES scrape_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id VARCHAR(64) NULL,
    status VARCHAR(30) NOT NULL,
    message TEXT NULL,
    rows_total INT UNSIGNED NOT NULL DEFAULT 0,
    rows_imported INT UNSIGNED NOT NULL DEFAULT 0,
    rows_skipped INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_import_job (job_id, created_at),
    CONSTRAINT fk_import_job FOREIGN KEY (job_id) REFERENCES scrape_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id VARCHAR(100) NULL,
    context_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id, created_at),
    KEY idx_audit_action (action, created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS export_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    format VARCHAR(10) NOT NULL,
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    filters_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_exports_user (user_id, created_at),
    CONSTRAINT fk_exports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
    `key` VARCHAR(100) NOT NULL,
    value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_settings (
    user_id BIGINT UNSIGNED NOT NULL,
    `key` VARCHAR(100) NOT NULL,
    value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, `key`),
    CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
