# Controle de acesso do Backoffice

Esta alteração mantém o layout, os estilos e as funcionalidades visuais existentes. O trabalho foi concentrado nas verificações de permissão feitas pelo servidor.

## Regras de acesso

| Área / funcionalidade | ADM | FUNC |
|---|---:|---:|
| Página inicial administrativa | Sim | Não |
| Página inicial do funcionário | Não | Sim |
| Funcionários | Sim | Não |
| Fornecedores | Sim | Não |
| Máquinas (telas de gestão) | Sim | Não |
| Ocorrências | Todas | Apenas as próprias |
| Criar/editar ocorrência | Sim | Sim, apenas as próprias ao editar |
| Relatórios | Todos | Apenas os próprios |
| Cadastrar/alterar/excluir funcionários | Sim | Não |
| Cadastrar/alterar/excluir fornecedores | Sim | Não |
| Cadastrar/alterar/excluir máquinas | Sim | Não |
| Excluir ocorrências | Sim | Não |

## O que foi alterado

- Criado `auth.php` para centralizar a validação de sessão e de perfil.
- Protegidas as páginas administrativas e seus formulários/ações, inclusive quando alguém tenta abrir o endereço diretamente.
- Protegidas as páginas compartilhadas de ocorrências e relatórios contra acesso sem login.
- Corrigida a edição de ocorrência para que um funcionário não consiga editar uma ocorrência de outro funcionário alterando o ID na URL.
- Reforçadas as APIs de máquinas e fornecedores para impedir que o perfil FUNC faça operações administrativas de escrita.
- O login Web passa a regenerar o ID da sessão após autenticação.
- Nenhum arquivo CSS foi alterado e o HTML visual das telas foi preservado.

## Como testar

1. Entre com o perfil `FUNC`.
2. Tente abrir diretamente `funcionarios.php`, `fornecedores.php` e `maquinas.php`: o servidor deve responder com acesso negado (HTTP 403).
3. Tente abrir `inicial_adm.php`: o acesso também deve ser negado.
4. Entre com o perfil `ADM` e confirme que as telas administrativas continuam acessíveis.
5. Com `FUNC`, confirme que ocorrências e relatórios continuam disponíveis, mas limitados aos dados do próprio funcionário.
6. Tente editar uma ocorrência de outro usuário alterando o `id` na URL: a aplicação deve negar o acesso.

## Observação sobre autenticação

O mecanismo de login e as credenciais de demonstração que já existiam no projeto não foram substituídos nesta etapa. O controle de perfil protege as rotas com base na sessão criada pelo login atual. Antes de colocar o sistema em produção para um cliente, as credenciais fixas de demonstração devem ser substituídas por autenticação e gestão de usuários persistidas no banco, com senhas armazenadas por hash e perfis definidos por usuário.
