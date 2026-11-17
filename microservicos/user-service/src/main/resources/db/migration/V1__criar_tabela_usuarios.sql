CREATE TABLE usuarios (
                          id INT AUTO_INCREMENT PRIMARY KEY,
                          usuario VARCHAR(50) NOT NULL UNIQUE,
                          senha VARCHAR(255) NOT NULL,
                          tipo_usuario VARCHAR(20) NOT NULL DEFAULT 'FUNCIONARIO'
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;