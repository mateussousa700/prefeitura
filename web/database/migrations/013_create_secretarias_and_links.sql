USE prefeitura_app;

CREATE TABLE IF NOT EXISTS secretarias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  ativa TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_secretarias_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE service_subtypes
  ADD COLUMN secretaria_id BIGINT UNSIGNED DEFAULT NULL AFTER sla_hours,
  ADD INDEX idx_service_subtypes_secretaria (secretaria_id),
  ADD CONSTRAINT fk_service_subtypes_secretaria FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE RESTRICT;

ALTER TABLE service_requests
  ADD COLUMN secretaria_id BIGINT UNSIGNED DEFAULT NULL AFTER service_subtype_id,
  ADD INDEX idx_service_requests_secretaria (secretaria_id),
  ADD CONSTRAINT fk_service_requests_secretaria FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE RESTRICT;

UPDATE service_requests sr
LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
SET sr.secretaria_id = ss.secretaria_id
WHERE sr.secretaria_id IS NULL;
