USE prefeitura_app;

ALTER TABLE users
  ADD COLUMN user_type ENUM('populacao','gestor','admin') NOT NULL DEFAULT 'populacao' AFTER address,
  ADD INDEX idx_users_user_type (user_type);

-- Ajuste usuários existentes, se necessário:
-- UPDATE users SET user_type = 'populacao' WHERE user_type IS NULL;
