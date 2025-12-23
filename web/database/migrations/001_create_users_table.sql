-- Criação do banco e tabela de usuários para o portal web.

CREATE DATABASE IF NOT EXISTS prefeitura_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE prefeitura_app;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  cpf VARCHAR(14) NOT NULL UNIQUE,
  address TEXT NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  verification_token VARCHAR(64) DEFAULT NULL,
  email_verified_at DATETIME DEFAULT NULL,
  whatsapp_verified_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_verification_token (verification_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
