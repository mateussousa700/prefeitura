USE prefeitura_app;

CREATE TABLE IF NOT EXISTS ativos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('poste','via','arvore','lixeira') NOT NULL,
  identificador_publico VARCHAR(120) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'ATIVO',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ativos_identificador (identificador_publico),
  INDEX idx_ativos_tipo (tipo),
  INDEX idx_ativos_status (status),
  INDEX idx_ativos_lat_lng (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE service_requests
  ADD COLUMN ativo_id BIGINT UNSIGNED DEFAULT NULL AFTER secretaria_id,
  ADD INDEX idx_service_requests_ativo (ativo_id),
  ADD CONSTRAINT fk_service_requests_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS ativo_chamado_historico (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ativo_id BIGINT UNSIGNED NOT NULL,
  chamado_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ativo_chamado_ativo (ativo_id),
  INDEX idx_ativo_chamado_chamado (chamado_id),
  INDEX idx_ativo_chamado_usuario (usuario_id),
  CONSTRAINT fk_ativo_chamado_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ativo_chamado_chamado FOREIGN KEY (chamado_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ativo_chamado_usuario FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_ativo_chamado_no_update;
DROP TRIGGER IF EXISTS trg_ativo_chamado_no_delete;

DELIMITER $$
CREATE TRIGGER trg_ativo_chamado_no_update
BEFORE UPDATE ON ativo_chamado_historico
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de ativo nao pode ser alterado.';
END$$
CREATE TRIGGER trg_ativo_chamado_no_delete
BEFORE DELETE ON ativo_chamado_historico
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de ativo nao pode ser removido.';
END$$
DELIMITER ;
