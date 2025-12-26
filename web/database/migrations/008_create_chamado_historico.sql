USE prefeitura_app;

CREATE TABLE IF NOT EXISTS chamado_historico (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chamado_id BIGINT UNSIGNED NOT NULL,
  status_anterior VARCHAR(20) NOT NULL,
  status_novo VARCHAR(20) NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  observacao TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chamado_historico_chamado (chamado_id),
  INDEX idx_chamado_historico_usuario (usuario_id),
  CONSTRAINT fk_chamado_historico_chamado FOREIGN KEY (chamado_id) REFERENCES service_requests(id),
  CONSTRAINT fk_chamado_historico_status_anterior FOREIGN KEY (status_anterior) REFERENCES status_chamado(code),
  CONSTRAINT fk_chamado_historico_status_novo FOREIGN KEY (status_novo) REFERENCES status_chamado(code),
  CONSTRAINT fk_chamado_historico_usuario FOREIGN KEY (usuario_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_chamado_historico_no_update;
DROP TRIGGER IF EXISTS trg_chamado_historico_no_delete;

CREATE TRIGGER trg_chamado_historico_no_update
BEFORE UPDATE ON chamado_historico
FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico nao pode ser alterado.';

CREATE TRIGGER trg_chamado_historico_no_delete
BEFORE DELETE ON chamado_historico
FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico nao pode ser removido.';
