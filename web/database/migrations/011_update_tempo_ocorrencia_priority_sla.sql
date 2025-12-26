USE prefeitura_app;

ALTER TABLE service_requests
  ADD COLUMN tempo_ocorrencia ENUM('MENOS_24H','ENTRE_1_E_3_DIAS','MAIS_3_DIAS','RECORRENTE') NOT NULL DEFAULT 'MENOS_24H' AFTER duration,
  ADD COLUMN priority ENUM('BAIXA','MEDIA','ALTA','CRITICA') NOT NULL DEFAULT 'MEDIA' AFTER tempo_ocorrencia,
  ADD COLUMN sla_due_at DATETIME DEFAULT NULL AFTER priority;

UPDATE service_requests
SET tempo_ocorrencia = CASE duration
  WHEN 'hoje' THEN 'MENOS_24H'
  WHEN 'ultima_semana' THEN 'MAIS_3_DIAS'
  WHEN 'ultimo_mes' THEN 'MAIS_3_DIAS'
  WHEN 'mais_tempo' THEN 'MAIS_3_DIAS'
  ELSE 'MENOS_24H'
END;

UPDATE service_requests
SET priority = CASE tempo_ocorrencia
  WHEN 'MENOS_24H' THEN 'BAIXA'
  WHEN 'ENTRE_1_E_3_DIAS' THEN 'MEDIA'
  WHEN 'MAIS_3_DIAS' THEN 'ALTA'
  WHEN 'RECORRENTE' THEN 'CRITICA'
  ELSE 'MEDIA'
END;

ALTER TABLE service_requests
  DROP COLUMN duration;
