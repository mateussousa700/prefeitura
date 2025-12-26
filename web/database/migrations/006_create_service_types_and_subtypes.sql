USE prefeitura_app;

CREATE TABLE IF NOT EXISTS service_types (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_service_types_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_subtypes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  service_type_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_service_subtypes_name (service_type_id, name),
  INDEX idx_service_subtypes_type (service_type_id),
  CONSTRAINT fk_service_subtypes_type FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE service_requests
  ADD COLUMN service_type_id BIGINT UNSIGNED DEFAULT NULL AFTER user_id,
  ADD COLUMN service_subtype_id BIGINT UNSIGNED DEFAULT NULL AFTER service_type_id,
  ADD INDEX idx_service_requests_type (service_type_id),
  ADD INDEX idx_service_requests_subtype (service_subtype_id),
  ADD CONSTRAINT fk_service_requests_type FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_service_requests_subtype FOREIGN KEY (service_subtype_id) REFERENCES service_subtypes(id) ON DELETE SET NULL;
