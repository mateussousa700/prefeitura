USE prefeitura_app;

CREATE TABLE IF NOT EXISTS status_chamado (
  code VARCHAR(20) NOT NULL PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO status_chamado (code) VALUES
  ('RECEBIDO'),
  ('EM_ANALISE'),
  ('ENCAMINHADO'),
  ('EM_EXECUCAO'),
  ('RESOLVIDO'),
  ('ENCERRADO')
ON DUPLICATE KEY UPDATE code = VALUES(code);

ALTER TABLE service_requests
  MODIFY status VARCHAR(20) NOT NULL DEFAULT 'RECEBIDO';

UPDATE service_requests
SET status = CASE status
  WHEN 'aberta' THEN 'RECEBIDO'
  WHEN 'em_andamento' THEN 'EM_EXECUCAO'
  WHEN 'concluida' THEN 'RESOLVIDO'
  WHEN 'concluido' THEN 'RESOLVIDO'
  WHEN 'concluído' THEN 'RESOLVIDO'
  WHEN 'resolvido' THEN 'RESOLVIDO'
  WHEN 'cancelada' THEN 'ENCERRADO'
  ELSE status
END;

UPDATE service_requests
SET status = 'RECEBIDO'
WHERE status NOT IN ('RECEBIDO','EM_ANALISE','ENCAMINHADO','EM_EXECUCAO','RESOLVIDO','ENCERRADO');

ALTER TABLE service_requests
  ADD CONSTRAINT fk_service_requests_status FOREIGN KEY (status) REFERENCES status_chamado(code);
