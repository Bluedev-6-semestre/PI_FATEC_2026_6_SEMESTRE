# 📌 PI_FATEC_2026_6_SEMESTRE
## BlueDev – Sistema de Lembretes e Ocorrências

---

### **Descrição**

O **BlueDev** é uma aplicação para gestão de alertas corretivos e preventivos em empresas, atualmente em fase de expansão: a versão **web** está em melhoramento contínuo e uma versão **mobile** está em desenvolvimento com Flutter.

**Funcionalidades principais:**

- Registro de ocorrências pelos funcionários.
- Visualização detalhada das ocorrências em **modal interativo**, sem sair da página.
- Administração e acompanhamento das ocorrências por gestores, incluindo **validação, edição e exclusão**.
- **Remoção em lote** com confirmação de segurança.

O sistema contribui para **agilidade, organização e rastreabilidade** dos processos internos da empresa.

---

### **Equipe**

| Frente  | Responsáveis                          | Status                  |
|---------|----------------------------------------|--------------------------|
| Web     | Caio Betegheli, Matheus Guedes         | Em melhoramento          |
| Mobile  | Davi Boneli, Melissa Vieira            | Em desenvolvimento       |

---

### **Tecnologias Utilizadas**

**Web:**
- **PHP** – Backend e lógica de negócios.
- **JavaScript (ES6)** – Funcionalidades dinâmicas e modais.
- **HTML5** – Estrutura das páginas.
- **CSS3** – Layout responsivo e estilização.

**Mobile:**
- **Flutter** – Framework para desenvolvimento multiplataforma (iOS/Android) a partir de uma única base de código.
- **Dart** – Linguagem de programação utilizada pelo Flutter.

---

### **Funcionalidades**

- Cadastro, edição e remoção de ocorrências.
- **Modal para visualização completa** da ocorrência.
- Diferenciação de acesso entre **administradores e usuários comuns**.
- Interface **responsiva e amigável**.

---

### **Público-alvo**

- **Funcionários** – registram alertas e ocorrências.
- **Administradores/Gestores** – validam, acompanham e rastreiam ações corretivas e preventivas.

---

### **Objetivos do Projeto**

- Melhorar a comunicação de problemas internos.
- Aumentar a eficiência na resolução de ocorrências.
- Garantir **rastreabilidade** e histórico completo das ações.
- Oferecer interface interativa com **modais** para maior usabilidade.
- Expandir o acesso ao sistema por meio de uma versão mobile em Flutter.

---

### **Instalação**

**Web:**
1. Clone este repositório:
```bash
git clone https://github.com/seu-usuario/BlueDev.git
```
2. Configure o banco de dados em `web/bd.php`.
3. Suba os arquivos da pasta `web/` em um servidor com suporte a PHP.

**Mobile:**
1. Certifique-se de ter o [Flutter](https://docs.flutter.dev/get-started/install) instalado.
2. Configure o PHP e o MySQL e publique `Bluedev - Web/Front-end` em um servidor acessível pelo dispositivo. A API fica na pasta `api/`.
3. No PowerShell aberto na raiz do repositório, entre na pasta do projeto mobile e confirme que o manifesto existe:
```powershell
Set-Location ".\Bluedev - Mobile"
Test-Path .\pubspec.yaml
```
4. Gere os projetos nativos Android/iOS:
```bash
flutter create --platforms=android,ios .
```
5. Instale as dependências e execute:
```bash
flutter pub get
flutter run
```
6. Na tela de login, informe a URL completa da API, terminando em `/api`. Use HTTPS em dispositivos reais; no emulador Android, `10.0.2.2` aponta para o computador hospedeiro.
7. Para compilar uma versão Android de produção, confirme que `android/app/src/main/AndroidManifest.xml` declara a permissão `INTERNET`. Em desenvolvimento local com HTTP, habilite tráfego sem TLS no Android; prefira HTTPS fora do ambiente de desenvolvimento.

---

### 📂 **Estrutura de Arquivos**

```
PI_FATEC_2026_6_SEMESTRE/
├─ Bluedev - Web/
│  └─ Front-end/
│     ├─ api/                 # API JSON para o aplicativo mobile
│     ├─ bd.php
│     └─ ocorrencias.php
├─ Bluedev - Mobile/
│  ├─ lib/
│  │  ├─ api_client.dart
│  │  └─ main.dart
│  └─ pubspec.yaml
└─ README.md
```

---

### 📌 **Status do Projeto**

Em desenvolvimento

- **Web:** implementação de cadastro, edição, remoção e modal de visualização de ocorrências (melhoramento contínuo).
- **Mobile:** aplicativo Flutter integrado à API PHP para login e gestão de ocorrências.
