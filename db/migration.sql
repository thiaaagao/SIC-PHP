USE ps_system;

-- Migracao: Adicionar campos de status aos usuarios
-- Execute este arquivo se o banco ja existe sem os campos de status

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS status ENUM('active','inactive','locked') DEFAULT 'active' AFTER role,
    ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL AFTER status,
    ADD COLUMN IF NOT EXISTS failed_attempts INT DEFAULT 0 AFTER locked_until,
    ADD COLUMN IF NOT EXISTS force_password_change TINYINT DEFAULT 0 AFTER failed_attempts,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
