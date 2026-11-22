# Backoffice web — usuários e permissões (via microserviços)

Os usuários ficam no **user-service** (banco `bluedev_user`). O web fala com ele pelo **API Gateway**.

- Endereço do gateway: variável de ambiente `GATEWAY_URL` (padrão `http://localhost:8080`).
- Login: `POST /api/usuarios/login`. Usuários iniciais criados pela migration V2: `ADM` / `1234` e `FUNC` / `123`.
- A cada página o web relê o usuário no user-service; desativar uma conta ou mudar permissões vale na hora.
- Telas: `usuarios.php` (lista com busca e filtros), `adicionar_usuario.php` (criar/editar), `remover_usuario.php`. Só administrador.
- Funcionários, fornecedores e máquinas do web ainda usam o banco próprio do PHP (`bd.php`), até existirem serviços para eles.
- A pasta `api/` do PHP foi removida (o Mobile passa a usar os microserviços).

Permissões do funcionário: `ver_cadastros`, `registrar_ocorrencias`, `gerenciar_ocorrencias` (só as próprias), `ver_relatorios`. O administrador tem acesso total.
