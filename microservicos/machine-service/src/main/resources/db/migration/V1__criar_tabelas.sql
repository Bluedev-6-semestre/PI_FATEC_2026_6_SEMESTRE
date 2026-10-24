
CREATE TABLE IF NOT EXISTS usuarios (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        usuario VARCHAR(50) NOT NULL UNIQUE,
                                        senha VARCHAR(255) NOT NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS fornecedores (
                                            id INT AUTO_INCREMENT PRIMARY KEY,
                                            usuario_id INT NOT NULL,
                                            nome VARCHAR(100) NOT NULL,
                                            cnpj VARCHAR(20) NOT NULL,
                                            categoria VARCHAR(50) NOT NULL,
                                            telefone VARCHAR(20) NOT NULL,
                                            endereco VARCHAR(255) NOT NULL,
                                            CONSTRAINT fk_fornecedores_usuarios
                                                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS funcionarios (
                                            id INT AUTO_INCREMENT PRIMARY KEY,
                                            nome VARCHAR(255) NOT NULL,
                                            idade INT NOT NULL,
                                            funcao VARCHAR(100) NOT NULL,
                                            usuario_id INT NULL,
                                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS maquinas (
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
                                            ON UPDATE CURRENT_TIMESTAMP,
                                        CONSTRAINT fk_maquinas_usuarios
                                            FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                                                ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS ocorrencias (
                                           id INT AUTO_INCREMENT PRIMARY KEY,
                                           usuario_id INT NOT NULL,
                                           maquina_id INT NULL,
                                           titulo VARCHAR(100) NOT NULL,
                                           tipo VARCHAR(50) NOT NULL,
                                           status VARCHAR(50) NOT NULL,
                                           descricao TEXT,
                                           imagem VARCHAR(255) NULL,
                                           data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                           CONSTRAINT fk_ocorrencias_usuarios
                                               FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
                                           CONSTRAINT fk_ocorrencias_maquinas
                                               FOREIGN KEY (maquina_id) REFERENCES maquinas(id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;
