-- Gerenciamento de usuarios: nome, situacao (ativo), permissoes e vinculo com funcionario.
ALTER TABLE usuarios
    ADD COLUMN nome VARCHAR(100) NULL,
    ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN permissoes VARCHAR(255) NULL,
    ADD COLUMN funcionario_id INT NULL,
    ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

-- Usuarios iniciais (senhas: ADM = 1234, FUNC = 123; troque apos o primeiro acesso).
INSERT IGNORE INTO usuarios (usuario, senha, tipo_usuario, nome) VALUES
    ('ADM', '$2y$10$wAbu6ZDnO6dh5IzsObOQ7Oyk9d.vu4tP/Iuun3g38xMUMQoY1wTJm', 'ADMIN', 'Administrador'),
    ('FUNC', '$2y$10$jQWwqbfac/rron43sy3Fhe39bU8SyuRtezi2TgFhUzBGlNJ8gVV2m', 'FUNCIONARIO', 'Funcionario');
