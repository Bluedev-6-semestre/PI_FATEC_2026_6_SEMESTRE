<?php
/**
 * Menu lateral das páginas de gestão. Mostra apenas o que o usuário pode acessar.
 * Uso: $menuAtivo = 'funcionarios'; include 'menu.php';
 */
$menuAtivo = $menuAtivo ?? '';
$menuAuth = auth_user();
$menuItens = [
    'ocorrencias'  => ['ocorrencias.php',  'ocorrencia_icone.png',  'Ocorrências',  true],
    'fornecedores' => ['fornecedores.php', 'fornecedor_icone.png', 'Fornecedores', auth_can('ver_cadastros', $menuAuth)],
    'funcionarios' => ['funcionarios.php', 'func_icone.png',       'Funcionários', auth_can('ver_cadastros', $menuAuth)],
    'maquinas'     => ['maquinas.php',     'maquina_icone.png',    'Máquinas',     auth_can('ver_cadastros', $menuAuth)],
    'usuarios'     => ['usuarios.php',     'perfil.png',           'Usuários',     auth_is_admin($menuAuth)],
    'relatorios'   => ['relatorios.php',   'relatorio_icone.png',  'Relatórios',   auth_can('ver_relatorios', $menuAuth)],
];
?>
    <aside class="sidebar">
        <ul>
<?php foreach ($menuItens as $chave => [$href, $icone, $rotulo, $visivel]): ?>
<?php if ($visivel): ?>
            <li><a href="<?= $href ?>"<?= $menuAtivo === $chave ? ' class="ativo"' : '' ?>><img src="../Imagens/<?= $icone ?>" alt="<?= $rotulo ?>"> <?= $rotulo ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
        </ul>
    </aside>
