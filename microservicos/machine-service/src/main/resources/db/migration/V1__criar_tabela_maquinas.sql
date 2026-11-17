CREATE TABLE maquinas (
                          id INT AUTO_INCREMENT PRIMARY KEY,
                          usuario_id INT NOT NULL,
                          nome VARCHAR(100) NOT NULL,
                          codigo VARCHAR(50) NOT NULL,
                          modelo VARCHAR(100) NOT NULL,
                          fabricante VARCHAR(100) NOT NULL,
                          numero_serie VARCHAR(100) NOT NULL,
                          data_aquisicao DATE NOT NULL,
                          localizacao VARCHAR(150) NOT NULL,
                          status_maquina VARCHAR(50) NOT NULL,
                          descricao TEXT,
                          data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                          data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;