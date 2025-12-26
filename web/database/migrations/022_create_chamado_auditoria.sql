USE prefeitura_app;

CREATE TABLE IF NOT EXISTS chamado_auditoria (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chamado_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  acao VARCHAR(80) NOT NULL,
  detalhes JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chamado_auditoria_chamado (chamado_id),
  INDEX idx_chamado_auditoria_usuario (usuario_id),
  INDEX idx_chamado_auditoria_acao (acao),
  CONSTRAINT fk_chamado_auditoria_chamado FOREIGN KEY (chamado_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_chamado_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_chamado_auditoria_no_update;
DROP TRIGGER IF EXISTS trg_chamado_auditoria_no_delete;

CREATE TRIGGER trg_chamado_auditoria_no_update
BEFORE UPDATE ON chamado_auditoria
FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Auditoria de chamado nao pode ser alterada.';

CREATE TRIGGER trg_chamado_auditoria_no_delete
BEFORE DELETE ON chamado_auditoria
FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Auditoria de chamado nao pode ser removida.';
