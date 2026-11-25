<?php
require_once __DIR__ . '/includes/auth.php';
if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . (is_admin() ? 'inicial_adm.php' : 'inicial_func.php'));
    exit();
}
$erroLogin = api_take_flash_error();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style/login.css">
</head>
<body>
    <div class="container">
        <div class="illustration-panel">
            <img src="../Imagens/logo.png" alt="Ilustração1">
            <img src="../Imagens/inicio.png" alt="Ilustração2">
        </div>

        <div class="login-panel">
            <div class="login-form">
                <h2>Login</h2>
                <?php if ($erroLogin): ?>
                    <p class="mensagem-erro" role="alert"><?= htmlspecialchars($erroLogin, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <form action="login.php" method="POST">
                    <div class="input-group">
                        <label for="usuario">
                            <img src="../Imagens/perfil.png" alt="Ícone de usuário" class="input-icon">
                            <input type="text" id="usuario" name="usuario" placeholder="Usuário" required>
                        </label>
                    </div>
                    <div class="input-group">
                        <label for="senha">
                            <img src="../Imagens/trancar.png" alt="Ícone de senha" class="input-icon">
                            <input type="password" id="senha" name="senha" placeholder="Senha" required>
                        </label>
                    </div>

                    <p class="help-link" id="openHelp">Precisa de ajuda?</p>

                    <button type="submit" class="login-button">Entrar</button>
                </form>
            </div>
        </div>
    </div>

    <div id="helpModal" class="modal">
        <div class="modal-content">
            <h3>Suporte</h3>
            <p>Entre em contato com o suporte:</p>
            <p><strong>suportebluedev@gmail.com</strong></p>
            <button class="close-btn" id="closeHelp">Fechar</button>
        </div>
    </div>

    <script>
        const openHelp = document.getElementById('openHelp');
        const modal = document.getElementById('helpModal');
        const closeHelp = document.getElementById('closeHelp');

        openHelp.addEventListener('click', () => {
            modal.style.display = 'flex';
        });

        closeHelp.addEventListener('click', () => {
            modal.style.display = 'none';
        });
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
</body>
</html>
