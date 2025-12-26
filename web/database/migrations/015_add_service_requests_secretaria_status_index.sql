USE prefeitura_app;

CREATE INDEX idx_service_requests_secretaria_status_created
  ON service_requests (secretaria_id, status, created_at);
