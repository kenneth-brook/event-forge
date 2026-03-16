CREATE TABLE event_admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'staff',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_admin_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_event_id INT UNSIGNED DEFAULT NULL,

  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) DEFAULT NULL,

  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME DEFAULT NULL,
  all_day TINYINT(1) NOT NULL DEFAULT 0,

  location VARCHAR(255) DEFAULT NULL,
  summary TEXT DEFAULT NULL,
  description MEDIUMTEXT DEFAULT NULL,

  image_path VARCHAR(255) DEFAULT NULL,
  pdf_path VARCHAR(255) DEFAULT NULL,
  external_url VARCHAR(255) DEFAULT NULL,

  is_published TINYINT(1) NOT NULL DEFAULT 1,
  is_canceled TINYINT(1) NOT NULL DEFAULT 0,

  is_recurring_parent TINYINT(1) NOT NULL DEFAULT 0,
  is_independent_child TINYINT(1) NOT NULL DEFAULT 0,

  recurrence_type VARCHAR(50) DEFAULT NULL,
  recurrence_interval INT UNSIGNED DEFAULT NULL,
  recurrence_days VARCHAR(50) DEFAULT NULL,
  recurrence_week_of_month VARCHAR(20) DEFAULT NULL,
  recurrence_day_of_week VARCHAR(20) DEFAULT NULL,
  recurrence_end_date DATE DEFAULT NULL,
  recurrence_count INT UNSIGNED DEFAULT NULL,
  recurrence_instance_date DATE DEFAULT NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),

  KEY idx_events_parent_event_id (parent_event_id),
  KEY idx_events_start_datetime (start_datetime),
  KEY idx_events_is_published (is_published),
  KEY idx_events_is_canceled (is_canceled),
  KEY idx_events_is_recurring_parent (is_recurring_parent),
  KEY idx_events_is_independent_child (is_independent_child),
  KEY idx_events_recurrence_instance_date (recurrence_instance_date),
  KEY idx_events_slug (slug),

  KEY idx_events_parent_instance (parent_event_id, recurrence_instance_date),
  KEY idx_events_calendar_filter (is_published, is_recurring_parent, start_datetime),

  CONSTRAINT fk_events_parent_event
    FOREIGN KEY (parent_event_id)
    REFERENCES events(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE eventforge_system (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  system_key VARCHAR(100) NOT NULL,
  system_value TEXT DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_eventforge_system_key (system_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO eventforge_system (system_key, system_value) VALUES
  ('schema_version', '1'),
  ('app_version', '0.1.0');