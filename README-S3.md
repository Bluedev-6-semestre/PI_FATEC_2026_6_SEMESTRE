# BlueDev — armazenamento de imagens no Amazon S3

## Escopo

A integração foi implementada no `occurrence-service`, pois este é o único serviço cuja entidade Java atualmente possui um campo `imagem` utilizado no fluxo da API. O `machine-service` possui uma coluna `imagem` na migration inicial, mas não possui campo correspondente na entidade, DTO ou endpoints; ela não foi alterada. `user-service` e `api-gateway` não receberam lógica de armazenamento.

O bucket deve permanecer privado. O banco MariaDB armazena somente a chave do objeto (por exemplo, `ocorrencias/42/UUID.jpg`). URLs pré-assinadas são geradas sob demanda e nunca persistidas.

## Endpoints de imagem

Todos os endpoints continuam acessíveis pelo API Gateway, sob `/api/ocorrencias`.

### Enviar ou substituir uma imagem

`POST /api/ocorrencias/{id}/imagem`

- Content-Type: `multipart/form-data`.
- Campo obrigatório: `arquivo` (um arquivo).
- Formatos aceitos: JPEG, PNG, WEBP e GIF.
- Limite padrão: 5 MB, configurável por `AWS_S3_MAX_FILE_SIZE`.
- A ocorrência precisa existir.
- O nome do objeto é gerado com UUID e salvo no prefixo `ocorrencias/{id}/`.

Exemplo com cURL:

```bash
curl -X POST "http://localhost:8080/api/ocorrencias/42/imagem" \
  -F "arquivo=@./foto.jpg;type=image/jpeg"
```

Resposta `201 Created` (valores ilustrativos):

```json
{
  "chave": "ocorrencias/42/0f7c2a0e-1b47-4e64-a7e4-ae1d4f9c2b0d.jpg",
  "url": "https://bucket.s3.regiao.amazonaws.com/...assinatura-temporaria...",
  "expiracaoEmSegundos": 900
}
```

A substituição envia primeiro o novo objeto, grava a nova chave no MariaDB e só então tenta excluir o objeto anterior, após o commit. Se a transação falhar, o novo objeto é excluído como compensação. Se a exclusão posterior falhar, o erro é registrado no log e o objeto antigo poderá precisar de limpeza manual.

### Obter uma URL de acesso

`GET /api/ocorrencias/{id}/imagem`

Resposta `200 OK`:

```json
{
  "chave": "ocorrencias/42/0f7c2a0e-1b47-4e64-a7e4-ae1d4f9c2b0d.jpg",
  "url": "https://...",
  "expiracaoEmSegundos": 900
}
```

A URL deve ser consumida antes de expirar. Quando expirar, solicite outra URL ao mesmo endpoint. As respostas de `GET /api/ocorrencias` e `GET /api/ocorrencias/{id}` mantêm o campo `imagem`, que contém a chave armazenada. Para obter uma URL temporária, chame `GET /api/ocorrencias/{id}/imagem`.

### Excluir somente a imagem

`DELETE /api/ocorrencias/{id}/imagem`

Retorna `204 No Content`. A referência é removida do banco e o objeto S3 é excluído após o commit.

### Criar/atualizar ocorrência

Os endpoints JSON existentes permanecem: `POST /api/ocorrencias` e `PUT /api/ocorrencias/{id}`. A imagem não é enviada nesses JSONs; envie os dados da ocorrência e, em seguida, use o endpoint multipart. A propriedade de resposta `imagem` representa a chave S3 para novos uploads. A URL temporária é obtida pelo endpoint `GET /api/ocorrencias/{id}/imagem`.

## Configuração

O `occurrence-service` lê as variáveis abaixo. O arquivo `.env.example` contém valores ilustrativos sem credenciais. Copie para `.env` e informe o nome real do bucket.

| Variável | Padrão | Descrição |
|---|---|---|
| `AWS_REGION` | `us-east-1` | Região AWS onde o bucket foi criado. |
| `AWS_S3_BUCKET` | vazio | Nome do bucket privado. Obrigatório para operações S3. |
| `AWS_S3_PRESIGNED_URL_EXPIRATION` | `15m` | Expiração da URL pré-assinada. Máximo aceito: 7 dias. |
| `AWS_S3_MAX_FILE_SIZE` | `5MB` | Tamanho máximo do arquivo. |
| `AWS_S3_MAX_REQUEST_SIZE` | `6MB` | Limite HTTP multipart, deve ser maior que o limite do arquivo. |

Não existem access keys ou secret keys na aplicação. O AWS SDK usa `DefaultCredentialsProvider`, que procura credenciais na cadeia padrão (variáveis de ambiente, profile local, credenciais de container e IAM Role da EC2, entre outras fontes suportadas).

### Execução local

1. Instale e configure AWS CLI com um perfil que tenha as permissões descritas abaixo (`aws configure` ou SSO, conforme o ambiente).
2. Para executar a aplicação diretamente pela IDE/Gradle, o SDK encontra o perfil padrão em `~/.aws`. Defina `AWS_PROFILE` se utilizar outro perfil.
3. Para executar o serviço dentro do Docker localmente, disponibilize o perfil/arquivos de configuração AWS ao container por um mecanismo local não versionado (por exemplo, um override local do Compose que monte `~/.aws` como somente leitura em `/root/.aws`). Não adicione esses arquivos ao repositório.
4. Configure as variáveis do `.env` e execute `docker compose up --build`.

O Compose principal passa apenas as configurações não secretas de região, bucket, expiração e limite. Não injeta access keys.

## Preparação AWS para o responsável pelo deploy

### 1. Criar o bucket

1. No console AWS, abra Amazon S3 e crie um bucket com nome globalmente único.
2. Escolha a mesma região que será usada em `AWS_REGION`.
3. Mantenha **Block all public access** habilitado (os quatro controles de bloqueio).
4. Não habilite ACLs públicas nem crie bucket policy permitindo acesso público.
5. Mantenha o bucket privado. A aplicação acessa objetos usando a role e URLs pré-assinadas.
6. Mantenha a criptografia padrão do S3 habilitada (SSE-S3) ou aplique a política de criptografia corporativa adotada pelo ambiente.

### 2. Criar a política IAM mínima

Crie uma policy substituindo `NOME-DO-BUCKET` pelo bucket criado e associe-a à IAM Role da instância EC2. O prefixo limita as operações aos objetos usados pelo serviço de ocorrências.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ManageOccurrenceImages",
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::NOME-DO-BUCKET/ocorrencias/*"
    }
  ]
}
```

`s3:ListBucket` não é necessário para os fluxos implementados. Não conceda `s3:*`. Se futuramente o serviço passar a listar objetos, reavalie a política separadamente.

### 3. Associar a IAM Role à EC2

1. Crie uma IAM Role confiável para o serviço EC2 (`ec2.amazonaws.com`).
2. Associe a policy acima à role.
3. Crie/associe o instance profile, se o console não o fizer automaticamente.
4. Anexe a role à instância EC2.
5. Não configure access keys estáticas na EC2, no Compose, no Dockerfile ou na aplicação. O AWS SDK obterá credenciais temporárias via metadata da instância.

### 4. Configurar e executar na EC2

Na pasta que contém `docker-compose.yml`, crie um `.env` local (não versionado):

```dotenv
AWS_REGION=sa-east-1
AWS_S3_BUCKET=nome-real-do-bucket
AWS_S3_PRESIGNED_URL_EXPIRATION=15m
AWS_S3_MAX_FILE_SIZE=5MB
AWS_S3_MAX_REQUEST_SIZE=6MB
```

Ajuste `AWS_REGION` para a região real do bucket. O MariaDB permanece no container e usa o volume Docker existente; não é necessário migrá-lo para RDS.

Execute:

```bash
docker compose build occurrence-service api-gateway
docker compose up -d
docker compose logs -f occurrence-service
```

A role precisa estar anexada à EC2 antes de testar upload. Não é necessário montar `~/.aws` na EC2.

### 5. Verificar a integração

1. Confirme que `occurrence-service` iniciou sem erro de configuração. A ausência de bucket não impede o serviço de iniciar, mas operações de imagem retornam `503` até `AWS_S3_BUCKET` ser configurado.
2. Crie uma ocorrência pelo `POST /api/ocorrencias`.
3. Envie uma imagem com `POST /api/ocorrencias/{id}/imagem` pelo Swagger ou cURL.
4. Confirme no S3 que o objeto está no prefixo `ocorrencias/{id}/`.
5. Confirme no MariaDB que `ocorrencias.imagem` contém somente a chave do objeto, não URL ou bytes.
6. Consulte `GET /api/ocorrencias/{id}/imagem` e abra a URL retornada antes da expiração.
7. Teste substituição e exclusão; verifique os logs em caso de falha ao remover objetos antigos.

## Banco de dados e imagens legadas

Não foi criada migration. A tabela `ocorrencias` já possui `imagem VARCHAR(255)`, suficiente para as chaves UUID geradas. Nenhuma migration existente foi alterada. Valores antigos em `imagem` são preservados e não são apagados ou enviados automaticamente ao S3. Esses valores são devolvidos sem alteração; o endpoint de URL pré-assinada só reconhece chaves geradas pelo novo fluxo UUID. Faça qualquer migração de imagens legadas como uma atividade separada e controlada.

A coluna `maquinas.imagem` da migration inicial não foi modificada porque a entidade e os endpoints de máquinas não a utilizam atualmente.

## Testes

Foi adicionada uma suíte unitária para validar arquivo vazio, MIME não permitido, limite de tamanho, divergência entre MIME e assinatura do arquivo e reconhecimento das chaves administradas. Os testes não dependem de bucket real. A execução nesta sessão não foi possível porque o Gradle Wrapper tentou baixar Gradle 9.8.2 e o ambiente de execução estava sem acesso DNS/rede (`UnknownHostException: services.gradle.org`). Execute `./gradlew test` no ambiente com acesso à distribuição Gradle e Maven Central antes do merge/deploy.
