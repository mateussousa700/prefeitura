ALTER TABLE `users`
ADD COLUMN `neighborhood` VARCHAR(120) NOT NULL AFTER `address`,
ADD COLUMN `zip` VARCHAR(12) NOT NULL AFTER `neighborhood`;

-- Para aplicar:
-- mysql -u root -p prefeitura_app < web/migrations/20240523_add_neighborhood_zip_to_users.sql
