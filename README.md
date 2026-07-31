# S.I.C. - Sistema de Informacao e Chamados

Sistema web para abertura, acompanhamento e resolucao de chamados de TI, seguindo boas praticas ITIL.

**Stack:** PHP 8.2 + MariaDB/MySQL + Bootstrap 5 + Chart.js  
**Servidor:** Apache (XAMPP, porta 8080)

---

## Funcionalidades

- **Abertura de chamados** - Visitantes (por IP) ou usuarios autenticados
- **Fila de atendimento** - Suporte TI visualiza e resolve chamados
- **Atribuicao** - Designar responsavel por chamado
- **SLA por prioridade** - Baixa (24h), Media (4h), Alta (2h), Critica (1h)
- **Timer SLA ao vivo** - Contador regressivo no detalhe do chamado
- **Comentarios** - Discussao entre equipe e solicitante
- **Anexos** - Upload com validacao MIME (finfo) + preview de imagens
- **Avaliacao** - Nota 1-5 estrelas apos resolucao
- **Dashboard ITIL** - 7 graficos (Chart.js) com metricas de desempenho
- **Exportacao CSV** - Relatorios filtrados para Excel
- **Notificacoes Teams** - Webhook via Power Automate
- **Dark/Light Mode** - Toggle com persistencia no localStorage
- **API REST** - Abertura de chamados via JSON
- **LGPD** - Politica de privacidade, consentimento, anonimizacao
- **Auditoria** - Logs de acoes e acessos

---

## Requisitos

- PHP 8.2+
- MariaDB ou MySQL
- Apache (XAMPP recomendado)
- XAMPP na porta 8080

---

## Instalacao

### 1. Clonar o repositorio

```bash
git clone https://github.com/thiaaagao/SIC-PHP.git
```

### 2. Configurar o XAMPP

O XAMPP deve rodar na **porta 8080**. Edite `C:\xampp\apache\conf\httpd.conf`:

```apache
Listen 8080
ServerName localhost:8080
```

### 3. Criar o junction (atalho)

```powershell
# No PowerShell como Administrador
New-Item -ItemType Junction -Path "C:\xampp\htdocs\ps-system" -Target "C:\caminho\para\SIC-PHP"
```

### 4. Criar o banco de dados

Acesse o phpMyAdmin (`http://localhost:8080/phpmyadmin`) e execute:

```sql
SOURCE db/schema.sql;
```

Ou crie manualmente o banco `ps_system` e importe o arquivo `db/schema.sql`.

### 5. Configurar o webhook do Teams (opcional)

Edite `config.php` e substitua:

```php
define('TEAMS_WEBHOOK_URL', 'https://seu-webhook-power-automate.aqui');
```

Para criar o webhook:
1. Abra o Microsoft Teams
2. Crie um Fluxo no Power Automate
3. Use o trigger "Manually flow a flow"
4. Copie a URL de webhook gerada

### 6. Acessar o sistema

```
http://localhost:8080/ps-system/
```

O `index.php` redireciona automaticamente para o login.

---

## Credenciais

Os usuarios sao criados pelo `db/schema.sql`. Para criar novos usuarios, acesse o painel admin.

| Papel | Nivel | Descricao |
|-------|-------|-----------|
| Admin | 3 | Gerencia usuarios, categorias, auditoria, exportacao |
| Suporte TI | 2 | Atende e resolve chamados, comenta, anexa |
| Encarregado | 1 | Abre chamados e avalia resolucoes |
| Visitante | 0 | Abre chamado sem login (por IP autorizado) |

> Para criar o usuario admin, acesse `admin/users.php` apos o primeiro login como suporte.

---

## Zerar o Banco de Dados

### Opcao 1: Recriar do zero

```sql
DROP DATABASE IF EXISTS ps_system;
SOURCE db/schema.sql;
```

### Opcao 2: Manter estrutura, limpar dados

```sql
USE ps_system;

-- Limpar todos os dados (cuidado!)
TRUNCATE TABLE ticket_attachments;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE access_logs;
TRUNCATE TABLE comments;
TRUNCATE TABLE ratings;
TRUNCATE TABLE tickets;

-- Recriar usuarios padrao
DELETE FROM users;
SOURCE db/schema.sql;  -- So os INSERTs de usuarios
```

### Opcao 3: Via phpMyAdmin

1. Acesse `http://localhost:8080/phpmyadmin`
2. Selecione o banco `ps_system`
3. Aba "Operacoes" > "Remover tabela" (para cada tabela)
4. Importe `db/schema.sql`

---

## Estrutura do Projeto

```
SIC-PHP/
├── config.php              # Configuracoes gerais, funcoes SLA, logAccess()
├── db/
│   ├── schema.sql          # Schema completo do banco
│   └── migration.sql       # Migracoes
├── src/
│   ├── Auth.php            # Autenticacao, roles, CSRF, sessao
│   ├── Database.php        # Conexao PDO singleton
│   ├── Category.php        # CRUD categorias/subcategorias
│   ├── Sector.php          # CRUD setores
│   ├── AuditLog.php        # Log de auditoria
│   ├── Pagination.php      # Helper de paginacao
│   ├── NavHelper.php       # Badge no navbar
│   ├── RateLimit.php       # Rate limiting por IP
│   ├── EmailNotification.php  # Notificacoes por email
│   ├── TeamsNotification.php  # Notificacoes Teams
│   ├── GLPILookup.php      # Busca hostname no GLPI
│   └── Network.php         # IP do cliente
├── public/
│   ├── index.php           # Redirect para login/dashboard
│   ├── login.php           # Login com selecao de papel
│   ├── open_ticket.php     # Abertura (visitante + logado)
│   ├── ticket.php          # Detalhe do chamado
│   ├── ticket_print.php    # Versao para impressao
│   ├── support.php         # Fila de atendimento
│   ├── analytics.php       # Dashboard ITIL (7 graficos)
│   ├── download.php        # Download de anexos
│   ├── logout.php          # Logout
│   ├── privacy.php         # Politica LGPD
│   ├── setup.php           # Diagnostico do sistema
│   ├── api/
│   │   └── abrir_chamado.php  # API REST
│   ├── admin/
│   │   ├── index.php       # Painel admin
│   │   ├── users.php       # Gerenciar usuarios
│   │   ├── categories.php  # Gerenciar categorias
│   │   ├── sectors.php     # Gerenciar setores
│   │   ├── tickets.php     # Gerenciar tickets
│   │   ├── audit.php       # Log de auditoria
│   │   ├── access_logs.php # Log de acessos
│   │   ├── export.php      # Exportar CSV
│   │   └── delete_user.php # Exclusao com LGPD
│   └── assets/
│       ├── style.css       # Estilos base
│       ├── theme.css       # Dark/Light mode
│       ├── theme.js        # Toggle de tema
│       ├── toast.js        # Notificacoes toast
│       ├── toast.css       # Estilos toast
│       ├── app.js          # Loading states
│       ├── shortcuts.js    # Atalhos de teclado
│       └── dropzone.js     # Drag and drop upload
└── storage/
    ├── uploads/            # Anexos dos chamados
    └── ratelimit/          # Dados de rate limiting
```

---

## Atalhos de Teclado

| Tecla | Acao |
|-------|------|
| `N` | Novo chamado |
| `/` | Focar busca |
| `1`-`4` | Filtrar por status (Suporte) |
| `ESC` | Voltar / Fechar modal |

---

## API REST

### Criar chamado

```http
POST /api/abrir_chamado.php
Content-Type: application/json

{
  "requester_name": "Nome do solicitante",
  "subcategory": "Hardware",
  "description": "Descricao do problema",
  "ip": "10.195.1.100",
  "hostname": "DSK71001",
  "setor": "Conferencia",
  "conf": "Computador 01"
}
```

**Campos obrigatorios:** `requester_name`, `subcategory`, `description`

**Resposta:**
```json
{
  "ok": true,
  "id": 1,
  "code": "PS-0001",
  "url": "http://localhost:8080/ps-system/ticket.php?id=1"
}
```

---

## Seguranca

- Senhas com bcrypt
- CSRF em todos os forms POST
- Rate limiting (5 tentativas/min)
- Session timeout (30 minutos)
- Headers de seguranca (CSP, X-Frame, X-XSS, nosniff)
- SQL injection prevention (prepared statements)
- XSS prevention (htmlspecialchars)
- Upload com validacao MIME (finfo)
- Honeypot anti-bot no form de visitante
- Controle de acesso por IP

---

## Comandos Uteis

```powershell
# Verificar sintaxe PHP
& "C:\xampp\php\php.exe" -n -l arquivo.php

# Testar login via curl
$html = curl.exe -s -c cookies.txt "http://localhost:8080/ps-system/login.php?role=admin"
$token = [regex]::Match($html, 'name="csrf_token" value="([^"]+)"').Groups[1].Value
curl.exe -s -c cookies.txt -b cookies.txt -d "username=admin&password=SENHA&csrf_token=$token" -X POST -L "http://localhost:8080/ps-system/login.php?role=admin"
```

---

## Licenca

Uso interno
