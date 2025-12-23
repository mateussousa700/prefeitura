USE prefeitura_app;

ALTER TABLE users
  ADD COLUMN neighborhood VARCHAR(120) NOT NULL AFTER address,
  ADD COLUMN zip VARCHAR(12) NOT NULL AFTER neighborhood;
