<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/usuarios_regras.php';
$auth = auth_require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$modo = $id ? 'editar' : 'adicionar';

$dados = [
    'usuario' => '',
    'nome' => '',
    'tipo' => 'FUNC',
    'ativo' => true,
    'permissoes' => auth_permissoes_padrao(),
    'funcionario_id' => ''
];

if ($id) {
    $atual = usuarios_buscar($pdo, $id);
    if (!$atual) {
        die("Usuário não encontrado.");
    }
    $dados = array_merge($dados, $atual);
    $dados['funcionario_id'] = $atual['funcionario_id'] ?? '';
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_csrf_check();
    $entrada = [
        'usuario' => $_POST['usuario'] ?? '',
        'nome' => $_POST['nome'] ?? '',
        'senha' => $_POST['senha'] ?? '',
        'tipo' => $_POST['tipo'] ?? '',
        'ativo' => isset($_POST['ativo']),
        'permissoes' => isset($_POST['permissoes']) && is_array($_POST['permissoes']) ? $_POST['permissoes'] : [],
        'funcionario_id' => $_POST['funcionario_id'] ?? ''
    ];
    [$erro, $novo] = usuarios_validar($pdo, $entrada, $id);
    if (!$erro && $id) {
        $erro = usuarios_verificar_protecao($pdo, $auth, $id, $novo);
    }
    if (!$erro) {
        $erro = $id ? usuarios_atualizar($pdo, $id, $novo) : usuarios_criar($pdo, $novo)[0];
        if (!$erro) {
            $_SESSION['flash_usuarios'] = ['tipo' => 'ok', 'texto' => 'Usuário salvo com sucesso.'];
            header('Location: usuarios.php');
            exit();
        }
    }
    // Mantém o que foi digitado (menos a senha).
    $dados = array_merge($dados, [
        'usuario' => $entrada['usuario'],
        'nome' => $entrada['nome'],
        'tipo' => $entrada['tipo'],
        'ativo' => $entrada['ativo'],
        'permissoes' => $entrada['permissoes'],
        'funcionario_id' => $entrada['funcionario_id']
    ]);
}

$funcionarios = $pdo->query("SELECT id, nome FROM funcionarios ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$editandoASiMesmo = $id === $auth['id'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ucfirst($modo) ?> Usuário</title>
<link rel="stylesheet" href="style/style_base.css">
<link rel="stylesheet" href="style/admin.css">
</head>
<body>
    <div class="navbar">
        <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
        <a href="logout.php" class="logout-icon">
            <img src="../Imagens/icone_sair.png" alt="Sair">
        </a>
        <a href="usuarios.php" class="voltar-icon">
            <img src="../Imagens/voltar.png" alt="Voltar">
        </a>
    </div>

    <div class="container">
<?php $menuAtivo = 'usuarios'; include 'menu.php'; ?>

        <main class="main-content">
            <div class="titulo-pagina">
                <h2><?= strtoupper($modo) ?> USUÁRIO</h2>
            </div>

            <?php if ($erro): ?>
                <p class="erro"><?= htmlspecialchars($erro) ?></p>
            <?php endif; ?>

            <form method="POST" class="form-funcionario form-usuario" autocomplete="off">
                <?= auth_csrf_field() ?>

                <label for="usuario">Usuário (login)</label>
                <input type="text" id="usuario" name="usuario" required maxlength="50"
                       value="<?= htmlspecialchars($dados['usuario']) ?>">

                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" maxlength="100"
                       value="<?= htmlspecialchars((string) $dados['nome']) ?>">

                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" minlength="6" maxlength="72"
                       autocomplete="new-password" <?= $id ? '' : 'required' ?>>
                <?php if ($id): ?><small>Deixe em branco para manter a senha atual.</small><?php endif; ?>

                <label for="tipo">Perfil (nível de acesso)</label>
                <select id="tipo" name="tipo" <?= $editandoASiMesmo ? 'data-proprio="1"' : '' ?>>
                    <option value="ADM" <?= $dados['tipo'] === 'ADM' ? 'selected' : '' ?>>Administrador</option>
                    <option value="FUNC" <?= $dados['tipo'] === 'FUNC' ? 'selected' : '' ?>>Funcionário</option>
                </select>

                <fieldset id="blocoPermissoes">
                    <legend>Permissões do funcionário</legend>
                    <?php foreach (auth_permissoes_disponiveis() as $chave => $rotulo): ?>
                        <label class="grupo-check">
                            <input type="checkbox" name="permissoes[]" value="<?= $chave ?>"
                                <?= in_array($chave, (array) $dados['permissoes'], true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($rotulo) ?>
                        </label>
                    <?php endforeach; ?>
                    <small>Administradores têm acesso total e não precisam de permissões individuais.</small>
                </fieldset>

                <label for="funcionario_id">Funcionário vinculado (opcional)</label>
                <select id="funcionario_id" name="funcionario_id">
                    <option value="">Nenhum</option>
                    <?php foreach ($funcionarios as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= (string) $dados['funcionario_id'] === (string) $f['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="grupo-check">
                    <input type="checkbox" name="ativo" value="1" <?= $dados['ativo'] ? 'checked' : '' ?>>
                    Conta ativa (pode entrar no sistema)
                </label>

                <button type="submit"><?= $modo === 'editar' ? 'SALVAR ALTERAÇÕES' : 'ADICIONAR' ?></button>
            </form>
        </main>
    </div>

<script>
const tipo = document.getElementById('tipo');
const bloco = document.getElementById('blocoPermissoes');
function atualizarPermissoes() { bloco.style.display = tipo.value === 'FUNC' ? '' : 'none'; }
tipo.addEventListener('change', atualizarPermissoes);
atualizarPermissoes();
</script>
</body>
</html>
