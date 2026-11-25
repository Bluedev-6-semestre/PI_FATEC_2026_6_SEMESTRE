CREATE TABLE ocorrencias (
                             id INT AUTO_INCREMENT PRIMARY KEY,
                             usuario_id INT NOT NULL,
                             maquina_id INT NULL,
                             titulo VARCHAR(100) NOT NULL,
                             tipo VARCHAR(50) NOT NULL,
                             status VARCHAR(50) NOT NULL,
                             descricao TEXT,
                             imagem VARCHAR(255) NULL,
                             data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;