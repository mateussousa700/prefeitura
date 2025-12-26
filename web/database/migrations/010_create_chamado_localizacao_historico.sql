USE prefeitura_app;

CREATE TABLE IF NOT EXISTS chamado_localizacao_historico (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chamado_id BIGINT UNSIGNED NOT NULL,
  endereco_anterior TEXT NOT NULL,
  endereco_novo TEXT NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chamado_localizacao_chamado (chamado_id),
  INDEX idx_chamado_localizacao_usuario (usuario_id),
  CONSTRAINT fk_chamado_localizacao_chamado FOREIGN KEY (chamado_id) REFERENCES service_requests(id),
  CONSTRAINT fk_chamado_localizacao_usuario FOREIGN KEY (usuario_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_chamado_localizacao_no_update;
DROP TRIGGER IF EXISTS trg_chamado_localizacao_no_delete;

CREATE TRIGGER trg_chamado_localizacao_no_update
BEFORE UPDATE ON chamado_localizacao_historico
FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de localizacao nao pode ser alterado.';

CREATE TRIGGER trg_chamado_localizacao_no_delete
BEFORE DELETE ON chamado_localizacao_historico
FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de localizacao nao pode ser removido.';
