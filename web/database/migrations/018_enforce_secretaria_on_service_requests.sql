USE prefeitura_app;

INSERT INTO secretarias (nome, slug, ativa, created_at)
SELECT 'Secretaria Geral', 'secretaria-geral', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM secretarias);

UPDATE secretarias
SET ativa = 1
WHERE id = (
    SELECT id FROM (
        SELECT id FROM secretarias ORDER BY id ASC LIMIT 1
    ) AS t
)
AND NOT EXISTS (SELECT 1 FROM secretarias WHERE ativa = 1);

SET @default_secretaria_id = (
    SELECT id FROM secretarias WHERE ativa = 1 ORDER BY id ASC LIMIT 1
);

UPDATE service_requests sr
LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
SET sr.secretaria_id = ss.secretaria_id
WHERE sr.secretaria_id IS NULL;

UPDATE service_requests
SET secretaria_id = @default_secretaria_id
WHERE secretaria_id IS NULL;

ALTER TABLE service_requests
  MODIFY secretaria_id BIGINT UNSIGNED NOT NULL;
