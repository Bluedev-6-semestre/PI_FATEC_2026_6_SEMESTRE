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

    // Os usuários agora ficam no user-service (banco bluedev_user), acessado pelo
    // gateway (veja gateway.php). Aqui as tabelas guardam só o id do usuário
    // (usuario_id), sem chave estrangeira.

    $pdo->exec("CREATE TABLE IF NOT EXISTS fornecedores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        cnpj VARCHAR(20) NOT NULL,        
        categoria VARCHAR(50) NOT NULL,
        telefone VARCHAR(20) NOT NULL,
        endereco VARCHAR(255) NOT NULL
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
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
         FOREIGN KEY (maquina_id) REFERENCES maquinas(id)
     )");
     $stmt = $pdo->prepare(
         "SELECT IS_NULLABLE FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = 'ocorrencias' AND COLUMN_NAME = 'maquina_id'"
     );
     $stmt->execute(['dbname' => $dbname]);
     if ($stmt->fetchColumn() === 'NO') {
         $pdo->exec("ALTER TABLE ocorrencias MODIFY maquina_id INT NULL");
     }

    // Bancos criados antes da migração para o user-service têm chaves estrangeiras
    // para a tabela local "usuarios", que não existe mais. Remove-as.
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = :dbname AND REFERENCED_TABLE_NAME = 'usuarios'"
    );
    $stmt->execute(['dbname' => $dbname]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fk) {
        $pdo->exec('ALTER TABLE `' . $fk['TABLE_NAME'] . '` DROP FOREIGN KEY `' . $fk['CONSTRAINT_NAME'] . '`');
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
