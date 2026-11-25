# BlueDev Web — integração com API Gateway

O frontend PHP consome os dados do BlueDev exclusivamente pelo API Gateway. Não há conexão PDO/MySQL no frontend.

## Escopo

- Usuários: autenticação e gerenciamento (listagem, cadastro, edição e exclusão) pelo `user-service`.
- Máquinas: listagem, consulta de detalhes, cadastro, edição e exclusão pelo `machine-service`.
- Ocorrências: listagem, consulta, cadastro, edição e exclusão pelo `occurrence-service`.
- Fornecedores e funcionários foram removidos do frontend, conforme o escopo definido.

## Configuração

Configure a variável de ambiente `API_BASE_URL` no ambiente que executa o PHP. Exemplo:

```ini
API_BASE_URL=http://localhost:8080
```

O arquivo `.env.example` contém o valor local padrão como referência. O PHP não carrega arquivos `.env` automaticamente; configure a variável no Apache/PHP ou no ambiente de execução. Se não for definida, o cliente utiliza `http://localhost:8080`.

Antes de abrir o frontend, inicie o backend na raiz do projeto:

```bash
docker compose up -d --build
```

Confirme que o Gateway responde em `http://localhost:8080` e que os três microserviços estão saudáveis. O servidor web PHP deve conseguir alcançar esse endereço.

## Camada de integração

- `includes/api_client.php`: cliente HTTP centralizado, JSON, timeout, interpretação de status HTTP e log técnico. Usa cURL quando disponível e streams HTTP nativos como alternativa.
- `includes/auth.php`: sessão PHP e verificações de acesso.
- `login.php`: envia as credenciais para `POST /api/usuarios/login`; nenhuma senha é armazenada na sessão.

## Endpoints utilizados

| Método | Endpoint | Uso no frontend |
|---|---|---|
| POST | `/api/usuarios/login` | Autenticação |
| GET | `/api/usuarios` | Listagem de usuários (ADM) |
| GET | `/api/usuarios/{id}` | Detalhes e edição (ADM) |
| POST | `/api/usuarios` | Cadastro (ADM) |
| PUT | `/api/usuarios/{id}` | Edição (ADM) |
| DELETE | `/api/usuarios/{id}` | Exclusão (ADM) |
| GET | `/api/maquinas` | Resumo das máquinas (id e nome) |
| GET | `/api/maquinas/{id}` | Detalhes, proprietário e edição |
| POST | `/api/maquinas` | Cadastro |
| PUT | `/api/maquinas/{id}` | Edição |
| DELETE | `/api/maquinas/{id}` | Exclusão |
| GET | `/api/ocorrencias` | Listagem e relatórios |
| GET | `/api/ocorrencias/{id}` | Detalhes e edição |
| POST | `/api/ocorrencias` | Cadastro |
| PUT | `/api/ocorrencias/{id}` | Edição e atualização de status |
| DELETE | `/api/ocorrencias/{id}` | Exclusão |

O contrato atual de `user-service` utiliza `ADMIN` e `FUNCIONARIO` em `tipoUsuario`. O frontend normaliza `ADMIN` para `ADM`, preservando as verificações de permissão legadas.

## Imagens de ocorrências

O endpoint atual recebe `imagem` como texto, não como `multipart/form-data`. Por isso, o PHP mantém o upload local em `uploads/` e envia à API o caminho relativo salvo. A exibição continua sendo feita pelo frontend. O backend não recebe os bytes do arquivo.

## Limitações conhecidas do contrato atual

- O frontend original possuía somente o fluxo de login de usuários. Foi adicionada uma tela administrativa de usuários para consumir as operações CRUD já disponíveis no `user-service`.
- A listagem de máquinas retorna somente `id` e `nome`. Para montar os cards e filtrar pelo `usuarioId`, o PHP consulta os detalhes de cada máquina.
- A API de ocorrências não possui filtro por usuário, status ou período. O PHP aplica esses filtros sobre os dados retornados.
- O backend atual não aplica autenticação/autorização nos endpoints de máquinas e ocorrências. As verificações de sessão e papel no PHP preservam o comportamento da interface, mas não substituem autorização no servidor da API. O Gateway não deve ser exposto sem considerar essa limitação.
- O endpoint de ocorrências não possui upload multipart; o campo `imagem` é somente uma string.

## Testes

A validação estática pode ser feita com:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

Os fluxos completos de login e CRUD precisam ser exercitados com o Docker Compose do projeto ativo e usuários cadastrados no `user-service`.
