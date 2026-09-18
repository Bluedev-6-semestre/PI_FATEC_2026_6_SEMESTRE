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
2. Acesse a pasta do projeto mobile:
```bash
cd BlueDev/mobile
```
3. Instale as dependências:
```bash
flutter pub get
```
4. Execute o projeto:
```bash
flutter run
```

---

### 📂 **Estrutura de Arquivos**

```
BlueDev/
├─ web/
│  ├─ style/ocorrencia.css
│  ├─ Imagens/
│  ├─ ocorrencias.php
│  ├─ adicionar_ocorrencia.php
│  ├─ remover_ocorrencia.php
│  └─ bd.php
├─ mobile/
│  ├─ lib/
│  ├─ android/
│  ├─ ios/
│  ├─ pubspec.yaml
│  └─ (demais arquivos padrão do Flutter)
└─ README.md
```

---

### 📌 **Status do Projeto**

Em desenvolvimento

- **Web:** implementação de cadastro, edição, remoção e modal de visualização de ocorrências (melhoramento contínuo).
- **Mobile:** desenvolvimento inicial da versão mobile do sistema com Flutter.
