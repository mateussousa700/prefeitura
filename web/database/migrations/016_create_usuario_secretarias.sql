USE prefeitura_app;

CREATE TABLE IF NOT EXISTS usuario_secretarias (
  usuario_id BIGINT UNSIGNED NOT NULL,
  secretaria_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, secretaria_id),
  INDEX idx_usuario_secretarias_secretaria (secretaria_id),
  CONSTRAINT fk_usuario_secretarias_usuario FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_usuario_secretarias_secretaria FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
