USE prefeitura_app;

ALTER TABLE service_requests
  ADD COLUMN atribuido_em DATETIME DEFAULT NULL AFTER secretaria_id;

CREATE TABLE IF NOT EXISTS chamado_secretaria_historico (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chamado_id BIGINT UNSIGNED NOT NULL,
  secretaria_anterior_id BIGINT UNSIGNED DEFAULT NULL,
  secretaria_nova_id BIGINT UNSIGNED NOT NULL,
  motivo TEXT NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chamado_secretaria_chamado (chamado_id),
  INDEX idx_chamado_secretaria_usuario (usuario_id),
  CONSTRAINT fk_chamado_secretaria_chamado FOREIGN KEY (chamado_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_chamado_secretaria_anterior FOREIGN KEY (secretaria_anterior_id) REFERENCES secretarias(id) ON DELETE RESTRICT,
  CONSTRAINT fk_chamado_secretaria_nova FOREIGN KEY (secretaria_nova_id) REFERENCES secretarias(id) ON DELETE RESTRICT,
  CONSTRAINT fk_chamado_secretaria_usuario FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_chamado_secretaria_no_update;
DROP TRIGGER IF EXISTS trg_chamado_secretaria_no_delete;

DELIMITER $$
CREATE TRIGGER trg_chamado_secretaria_no_update
BEFORE UPDATE ON chamado_secretaria_historico
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de secretaria nao pode ser alterado.';
END$$
CREATE TRIGGER trg_chamado_secretaria_no_delete
BEFORE DELETE ON chamado_secretaria_historico
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de secretaria nao pode ser removido.';
END$$
DELIMITER ;
