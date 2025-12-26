USE prefeitura_app;

ALTER TABLE service_requests
  ADD COLUMN chamado_pai_id BIGINT UNSIGNED DEFAULT NULL AFTER secretaria_id,
  ADD COLUMN contador_apoios INT UNSIGNED NOT NULL DEFAULT 0 AFTER chamado_pai_id,
  ADD INDEX idx_service_requests_pai (chamado_pai_id),
  ADD INDEX idx_service_requests_subtype_created (service_subtype_id, created_at),
  ADD CONSTRAINT fk_service_requests_pai FOREIGN KEY (chamado_pai_id) REFERENCES service_requests(id) ON DELETE SET NULL;
