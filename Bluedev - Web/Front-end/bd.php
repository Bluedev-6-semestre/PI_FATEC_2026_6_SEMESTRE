<?php
$host   = getenv('MYSQLHOST')     ?: 'localhost';
$port   = getenv('MYSQLPORT')     ?: '3306';
$dbname = getenv('MYSQLDATABASE') ?: 'bluedev';
$user   = getenv('MYSQLUSER')     ?: 'root';
$pass   = getenv('MYSQLPASSWORD') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE `$dbname`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL
    )");

    // Evolução da tabela de usuários (gerenciamento real de usuários e permissões).
    // 'tipo_usuario' é o mesmo campo usado pelo user-service (ADMIN / FUNCIONARIO).
    $colunasUsuarios = [
        'tipo_usuario'   => "VARCHAR(20) NOT NULL DEFAULT 'FUNCIONARIO'",
        'nome'           => 'VARCHAR(100) NULL',
        'ativo'          => 'TINYINT(1) NOT NULL DEFAULT 1',
        'permissoes'     => 'TEXT NULL',
        'funcionario_id' => 'INT NULL',
        'created_at'     => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'
    ];
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = 'usuarios'"
    );
    $stmt->execute(['dbname' => $dbname]);
    $existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($colunasUsuarios as $coluna => $definicao) {
        if (!in_array($coluna, $existentes, true)) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN $coluna $definicao");
            if ($coluna === 'tipo_usuario') {
                $pdo->exec("UPDATE usuarios SET tipo_usuario = 'ADMIN' WHERE usuario = 'ADM'");
            }
        }
    }

    // Usuários iniciais (somente criados se ainda não existirem).
    $usuarios = [
        ['usuario' => 'ADM', 'senha' => password_hash('1234', PASSWORD_DEFAULT), 'tipo' => 'ADMIN', 'nome' => 'Administrador'],
        ['usuario' => 'FUNC', 'senha' => password_hash('123', PASSWORD_DEFAULT), 'tipo' => 'FUNCIONARIO', 'nome' => 'Funcionário']
    ];

    foreach ($usuarios as $u) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = :usuario");
        $stmt->execute(['usuario' => $u['usuario']]);
        if ($stmt->rowCount() == 0) {
            $stmt_insert = $pdo->prepare(
                "INSERT INTO usuarios (usuario, senha, tipo_usuario, nome) VALUES (:usuario, :senha, :tipo, :nome)"
            );
            $stmt_insert->execute([
                'usuario' => $u['usuario'],
                'senha' => $u['senha'],
                'tipo' => $u['tipo'],
                'nome' => $u['nome']
            ]);
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS fornecedores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        cnpj VARCHAR(20) NOT NULL,        
        categoria VARCHAR(50) NOT NULL,
        telefone VARCHAR(20) NOT NULL,
        endereco VARCHAR(255) NOT NULL,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS funcionarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        idade INT NOT NULL,
        funcao VARCHAR(100) NOT NULL,
        usuario_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

   $pdo->exec("CREATE TABLE IF NOT EXISTS maquinas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    modelo VARCHAR(100) NULL,
    fabricante VARCHAR(100) NULL,
    numero_serie VARCHAR(100) NULL,
    data_aquisicao DATE NULL,
    localizacao VARCHAR(150) NULL,
    status_maquina VARCHAR(50) NOT NULL,
    descricao TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
)");

    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = 'maquinas'
         AND COLUMN_NAME IN ('modelo', 'fabricante', 'numero_serie', 'data_aquisicao', 'localizacao')"
    );
    $stmt->execute(['dbname' => $dbname]);
    $nullableMachineFields = [
        'modelo' => 'VARCHAR(100)',
        'fabricante' => 'VARCHAR(100)',
        'numero_serie' => 'VARCHAR(100)',
        'data_aquisicao' => 'DATE',
        'localizacao' => 'VARCHAR(150)'
    ];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $name = $column['COLUMN_NAME'];
        if ($column['IS_NULLABLE'] === 'NO' && isset($nullableMachineFields[$name])) {
            $pdo->exec('ALTER TABLE maquinas MODIFY ' . $name . ' '
                . $nullableMachineFields[$name] . ' NULL');
        }
    }

     $pdo->exec("CREATE TABLE IF NOT EXISTS ocorrencias (
         id INT AUTO_INCREMENT PRIMARY KEY,
         usuario_id INT NOT NULL,
         maquina_id INT NULL,
         titulo VARCHAR(100) NOT NULL,
         tipo VARCHAR(50) NOT NULL,
         status VARCHAR(50) NOT NULL,
         descricao TEXT,
         imagem VARCHAR(255) NULL,
         data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
         FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
         FOREIGN KEY (maquina_id) REFERENCES maquinas(id)
     )");
     $pdo->exec("CREATE TABLE IF NOT EXISTS assinaturas (
         id INT AUTO_INCREMENT PRIMARY KEY,
         usuario_id INT NOT NULL,
         mp_preapproval_id VARCHAR(100) NULL UNIQUE,
         external_reference VARCHAR(100) NOT NULL,
         plano VARCHAR(100) NOT NULL,
         valor DECIMAL(10,2) NOT NULL,
         status VARCHAR(20) NOT NULL,
         init_point TEXT NULL,
         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
         updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
     )");
     $stmt = $pdo->prepare(
         "SELECT IS_NULLABLE FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = 'ocorrencias' AND COLUMN_NAME = 'maquina_id'"
     );
     $stmt->execute(['dbname' => $dbname]);
     if ($stmt->fetchColumn() === 'NO') {
         $pdo->exec("ALTER TABLE ocorrencias MODIFY maquina_id INT NULL");
     }


} catch (PDOException $e) {
    error_log($e->getMessage());
    if (isset($_SERVER['SCRIPT_NAME']) &&
        strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false &&
        function_exists('api_respond')) {
        api_respond(
            ['erro' => 'Não foi possível conectar ao banco de dados. Verifique se o MySQL está em execução.'],
            500
        );
    }
    die("Erro ao conectar ou criar banco/tabelas no MySQL: " . $e->getMessage());
}
?>
