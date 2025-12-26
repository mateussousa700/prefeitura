USE prefeitura_app;

CREATE TABLE IF NOT EXISTS notificacoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('STATUS','SLA_ALERTA','ENCERRAMENTO') NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  mensagem TEXT NOT NULL,
  canal ENUM('email') NOT NULL DEFAULT 'email',
  status_envio ENUM('pendente','enviado','erro') NOT NULL DEFAULT 'pendente',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notificacoes_usuario (usuario_id),
  INDEX idx_notificacoes_status (status_envio),
  INDEX idx_notificacoes_tipo (tipo),
  CONSTRAINT fk_notificacoes_usuario FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
