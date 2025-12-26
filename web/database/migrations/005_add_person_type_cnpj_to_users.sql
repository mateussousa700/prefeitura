USE prefeitura_app;

ALTER TABLE users
  ADD COLUMN person_type ENUM('pf','pj') NOT NULL DEFAULT 'pf' AFTER email,
  ADD COLUMN cnpj VARCHAR(18) DEFAULT NULL AFTER cpf,
  ADD INDEX idx_users_person_type (person_type),
  ADD UNIQUE INDEX idx_users_cnpj (cnpj);

ALTER TABLE users
  MODIFY cpf VARCHAR(14) DEFAULT NULL;
