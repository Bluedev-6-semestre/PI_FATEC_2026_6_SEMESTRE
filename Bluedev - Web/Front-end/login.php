<?php
session_start();

$usuarios = [
    'ADM'  => ['senha' => '1234', 'id' => 1],
    'FUNC' => ['senha' => '123', 'id' => 2]
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = strtoupper($_POST['usuario']); 
    $senha   = $_POST['senha'];

    if (isset($usuarios[$usuario]) && $usuarios[$usuario]['senha'] === $senha) {
        $_SESSION['loggedin'] = true;
        $_SESSION['usuario'] = $usuario;
        $_SESSION['usuario_id'] = $usuarios[$usuario]['id'];

        if ($usuario === "ADM") {
            $_SESSION['tipo_usuario'] = 'ADM';
            header("Location: inicial_adm.php");
            exit();
        } elseif ($usuario === "FUNC") {
            $_SESSION['tipo_usuario'] = 'FUNC';
            header("Location: inicial_func.php");
            exit();
        }
    } else {
        echo "Usuário ou senha inválidos.";
    }
} else {
    header("Location: index.html");
    exit();
}
?>
