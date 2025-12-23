-- Tabela de solicitações de serviços municipais

USE prefeitura_app;

CREATE TABLE IF NOT EXISTS service_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  service_name VARCHAR(120) NOT NULL,
  problem_type VARCHAR(200) NOT NULL,
  address TEXT NOT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  duration ENUM('hoje','ultima_semana','ultimo_mes','mais_tempo') NOT NULL,
  evidence_files JSON DEFAULT NULL, -- lista de arquivos/imagens armazenados
  status ENUM('aberta','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'aberta',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_service_name (service_name),
  INDEX idx_status (status),
  CONSTRAINT fk_service_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
