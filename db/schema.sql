CREATE DATABASE IF NOT EXISTS ps_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ps_system;

CREATE TABLE IF NOT EXISTS tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20),
  requester_name VARCHAR(255) NOT NULL,
  subcategory ENUM('Hardware','Software','Rede','Coletor','Outros') NOT NULL,
  description TEXT NOT NULL,
  ip VARCHAR(45),
  hostname VARCHAR(255),
  setor VARCHAR(255),
  conf VARCHAR(10),
  status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
  resolved_at TIMESTAMP NULL,
  resolved_by VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  role ENUM('encarregado','solved') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  user_id INT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ratings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO users (username, password, name, role) VALUES
('encarregado', 'encarregado@2026', 'Encarregado Geral', 'encarregado'),
('suporte', 'suporte@2026', 'Suporte TI', 'solved');
