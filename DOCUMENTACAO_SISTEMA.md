# P.S. Profarma — Documentacao Completa do Sistema

> Sistema de Problem Solving para gestao de chamados de TI na Profarma.
> Versao: 2.0 | Ultima atualizacao: Julho 2026

---

## Sumario

1. [Visao Geral](#1-visao-geral)
2. [Arquitetura](#2-arquitetura)
3. [Estrutura de Diretorios](#3-estrutura-de-diretorios)
4. [Ambiente e Configuracao](#4-ambiente-e-configuracao)
5. [Banco de Dados](#5-banco-de-dados)
6. [Modelo de Acesso e Roles](#6-modelo-de-acesso-e-roles)
7. [Fluxos do Sistema](#7-fluxos-do-sistema)
8. [Integracoes](#8-integracoes)
9. [Seguranca e LGPD](#9-seguranca-e-lgpd)
10. [UX & UI](#10-ux--ui)
11. [API](#11-api)
12. [Mapa de Funcionalidades por Pagina](#12-mapa-de-funcionalidades-por-pagina)
13. [Credenciais e Chaves](#13-credenciais-e-chaves)
14. [Guia de Desenvolvimento](#14-guia-de-desenvolvimento)
15. [Checklist de Auditoria](#15-checklist-de-auditoria)

---

## 1. Visao Geral

### O que e

O P.S. Profarma e um sistema web interno para abertura, acompanhamento e resolucao de chamados de TI, seguindo boas praticas ITIL (Service Operation). Qualquer pessoa na rede da Profarma (visitante) ou usuario autenticado pode abrir um chamado.

### Publico-alvo

| Perfil | Acesso |
|--------|--------|
| Funcionarios (visitantes) | Abertura de chamado por IP autorizado |
| Encarregados | Abertura + avaliacao de chamados |
| Suporte TI | Atendimento, resolucao, anexos, relatorios |
| Admin | Gerenciamento completo (usuarios, categorias, auditoria, export) |

### Stack Tecnologica

| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP 8.2 |
| Banco | MariaDB/MySQL (XAMPP) |
| Frontend | Bootstrap 5.3, Chart.js 4.x |
| Servidor | Apache (XAMPP, porta 8080) |
| Notificacoes | Microsoft Teams via Power Automate |
| Integracao | GLPI (consulta de hostname por IP) |
| Dark Mode | CSS Custom Properties + localStorage |

---

## 2. Arquitetura

```
┌─────────────────────────────────────────────────────────┐
│                      CLIENTE                            │
│  Navegador (Bootstrap 5 + Chart.js + Theme JS)          │
└──────────────┬──────────────────────────┬───────────────┘
               │ HTTP (porta 8080)        │
┌──────────────▼──────────────────────────▼───────────────┐
│                    APACHE (XAMPP)                        │
│  ┌─────────────────────────────────────────────────┐    │
│  │  PHP 8.2 — Public Document Root                 │    │
│  │                                                  │    │
│  │  index.php    ← Dashboard (tickets abertos)      │    │
│  │  login.php    ← Autenticacao com selecao de role │    │
│  │  open_ticket.php ← Abertura (visitante/logado)   │    │
│  │  ticket.php   ← Detalhes + comentarios + SLA     │    │
│  │  support.php  ← Fila de atendimento              │    │
│  │  analytics.php ← KPIs ITIL (7 graficos)          │    │
│  │  admin/*.php  ← Painel administrativo            │    │
│  │  api/*.php    ← Endpoint REST (abertura JSON)    │    │
│  │  download.php ← Download de anexos               │    │
│  │  privacy.php  ← Politica de privacidade (LGPD)   │    │
│  └──────────────────────┬──────────────────────────┘    │
│                         │                                │
│  ┌──────────────────────▼──────────────────────────┐    │
│  │  Camada de Servico (src/)                        │    │
│  │  Auth.php        ← Autenticacao + roles + CSRF   │    │
│  │  Database.php    ← PDO singleton                 │    │
│  │  Category.php    ← CRUD categorias/subcategorias │    │
│  │  AuditLog.php    ← Registro de auditoria         │    │
│  │  RateLimit.php   ← Rate limiting file-based      │    │
│  │  Network.php     ← IP + hostname detection       │    │
│  │  GLPILookup.php  ← Consulta GLPI (hostname)      │    │
│  │  TeamsNotification.php ← Notificacoes Teams      │    │
│  └──────────────────────┬──────────────────────────┘    │
│                         │                                │
│  ┌──────────────────────▼──────────────────────────┐    │
│  │  config.php — Constantes + Headers + Funcoes     │    │
│  │  Timezone, DB, Teams Webhook, IPs, Setores, SLA  │    │
│  └──────────────────────┬──────────────────────────┘    │
└─────────────────────────┼────────────────────────────────┘
                          │ PDO (prepared statements)
┌─────────────────────────▼────────────────────────────────┐
│                   MARIADB / MYSQL                         │
│                                                          │
│  ps_system                                               │
│  ├── tickets           (chamados)                        │
│  ├── users             (usuarios)                        │
│  ├── comments          (comentarios)                     │
│  ├── ratings           (avaliacoes)                      │
│  ├── categories        (categorias)                      │
│  ├── subcategories     (subcategorias)                   │
│  ├── ticket_attachments (anexos)                         │
│  ├── audit_logs        (log de auditoria)                │
│  └── access_logs       (log de acessos)                  │
│                                                          │
│  glpi_db (somente leitura)                               │
│  ├── glpi_ipaddresses                                    │
│  ├── glpi_networknames                                   │
│  ├── glpi_networkports                                   │
│  └── glpi_computers                                      │
└──────────────────────────────────────────────────────────┘
```

---

## 3. Estrutura de Diretorios

```
ps-system/
├── config.php                  Configuracao geral + funcoes utilitarias
├── DOCUMENTACAO_SISTEMA.md     Este arquivo
│
├── db/
│   ├── schema.sql              Schema inicial (tickets, users, comments, ratings)
│   └── migration.sql           Migrations (priority, assigned_to, categories, etc.)
│
├── src/
│   ├── Database.php            Singleton PDO
│   ├── Auth.php                Autenticacao, roles, CSRF, sessao
│   ├── Category.php            CRUD categorias e subcategorias
│   ├── AuditLog.php            Registro de acoes
│   ├── RateLimit.php           Rate limiting (file-based)
│   ├── Network.php             Deteccao de IP e hostname
│   ├── GLPILookup.php          Consulta hostname via GLPI
│   └── TeamsNotification.php   Notificacoes Microsoft Teams
│
├── public/                     Document root do Apache
│   ├── index.php               Dashboard principal
│   ├── login.php               Login com selecao de papel
│   ├── logout.php              Encerramento de sessao
│   ├── open_ticket.php         Abertura de chamado (visitante + logado)
│   ├── ticket.php              Detalhes do chamado + SLA + anexos
│   ├── support.php             Fila de atendimento
│   ├── analytics.php           Dashboard ITIL (7 graficos)
│   ├── download.php            Download de anexos
│   ├── privacy.php             Politica de privacidade (LGPD)
│   ├── setup.php               Setup inicial do banco
│   │
│   ├── admin/
│   │   ├── index.php           Painel admin (metricas)
│   │   ├── users.php           Gerenciamento de usuarios + LGPD
│   │   ├── tickets.php         Gerenciamento de chamados
│   │   ├── categories.php      CRUD categorias/subcategorias
│   │   ├── audit.php           Visualizacao do log de auditoria
│   │   ├── access_logs.php     Visualizacao do log de acessos
│   │   ├── export.php          Exportacao CSV
│   │   └── delete_user.php     Exclusao/anonimizacao de usuario
│   │
│   ├── api/
│   │   └── abrir_chamado.php   Endpoint REST para abertura de chamado
│   │
│   └── assets/
│       ├── style.css           Estilos base (275 linhas)
│       ├── theme.css           Dark/Light mode (177 linhas, CSS vars)
│       └── theme.js            Toggle de tema (localStorage)
│
└── storage/
    ├── ratelimit/              Arquivos JSON de rate limit
    └── uploads/                Anexos de chamados
```

---

## 4. Ambiente e Configuracao

### Configuracao do Servidor

| Parametro | Valor |
|-----------|-------|
| Servidor | Apache (XAMPP) |
| Porta | 8080 |
| PHP | 8.2 |
| MariaDB | porta 3306 |
| Timezone | `America/Sao_Paulo` |
| Document Root | `C:\xampp\htdocs\ps-system` (junction) |
| Diretorio real | `C:\Chamado-auto\ps-system\public` |

### Junction (Link Simbolico)

```powershell
# Criar junction (executar uma vez)
New-Item -ItemType Junction -Path "C:\xampp\htdocs\ps-system" -Target "C:\Chamado-auto\ps-system\public"
```

### Variaveis de Ambiente (config.php)

```php
// Banco de Dados
DB_HOST  = '127.0.0.1'
DB_NAME  = 'ps_system'
DB_USER  = 'root'
DB_PASS  = ''  // vazio no XAMPP

// Teams Webhook (Power Automate)
TEAMS_WEBHOOK_URL = 'https://default25b01...'

// IPs permitidos (CIDR)
ALLOWED_IPS = ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']

// Setores fixos
SECTOR_LIST = ['Conferencia', 'Expedicao', 'Controlado', 'Sala de Reuniao', 'Postos (Loop 1/2/3)']

// SLA padrao (horas)
SLA_HOURS = 4
BASE_URL  = 'http://localhost:8080/ps-system'
```

### Configuracoes de Sessao

| Parametro | Valor | Descricao |
|-----------|-------|-----------|
| `cookie_httponly` | 1 | Cookie inacessivel via JS |
| `cookie_secure` | 0 | Permite HTTP (desenvolvimento) |
| `cookie_samesite` | Lax | Protecao contra CSRF basico |
| `use_strict_mode` | 1 | Rejeita session IDs invalidos |
| `use_only_cookies` | 1 | Sem session IDs via URL |
| `gc_maxlifetime` | 1800 | Sessao expira apos 30 min |

### Headers de Seguranca (enviados em todas as paginas)

| Header | Valor |
|--------|-------|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net;` |

---

## 5. Banco de Dados

### Tabela `tickets`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT AUTO_INCREMENT PK | ID interno |
| `code` | VARCHAR(20) | Codigo publico (ex: `PS-0001`) |
| `requester_name` | VARCHAR(255) NOT NULL | Nome de quem abriu |
| `subcategory` | ENUM('Hardware','Software','Rede','Coletor','Outros') | Subcategoria |
| `description` | TEXT NOT NULL | Descricao do problema |
| `ip` | VARCHAR(45) | IP do cliente |
| `hostname` | VARCHAR(255) | Hostname da estacao |
| `setor` | VARCHAR(255) | Setor |
| `conf` | VARCHAR(10) | Numero da conf |
| `status` | ENUM('open','in_progress','resolved','closed') | Status |
| `priority` | ENUM('low','medium','high','critical') | Prioridade |
| `assigned_to` | INT FK -> users.id | Atribuido a |
| `resolved_at` | TIMESTAMP NULL | Data de resolucao |
| `resolved_by` | VARCHAR(255) | Quem resolveu |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | Data de criacao |
| `updated_at` | TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Ultima atualizacao |

### Tabela `users`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT AUTO_INCREMENT PK | |
| `username` | VARCHAR(50) UNIQUE NOT NULL | Login |
| `password` | VARCHAR(255) NOT NULL | Hash bcrypt |
| `name` | VARCHAR(255) NOT NULL | Nome de exibicao |
| `role` | ENUM('admin','suporte_ti','encarregado') | Papel |
| `created_at` | TIMESTAMP | |

### Tabela `comments`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT AUTO_INCREMENT PK | |
| `ticket_id` | INT FK -> tickets(id) ON DELETE CASCADE | |
| `user_id` | INT FK -> users(id) | |
| `comment` | TEXT NOT NULL | Conteudo do comentario |
| `created_at` | TIMESTAMP | |

### Tabela `ratings`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT AUTO_INCREMENT PK | |
| `ticket_id` | INT FK -> tickets(id) ON DELETE CASCADE | |
| `user_id` | INT FK -> users(id) | |
| `rating` | TINYINT NOT NULL | 1-5 estrelas |
| `created_at` | TIMESTAMP | |

### Tabela `categories`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT AUTO_INCREMENT PK | |
| `name` | VARCHAR UNIQUE | Nome da categoria |
| `active` | TINYINT DEFAULT 1 | 1=ativa, 0=inativa |

### Tabela `subcategories`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT AUTO_INCREMENT PK | |
| `category_id` | INT FK -> categories(id) ON DELETE CASCADE | Categoria pai |
| `name` | VARCHAR | Nome da subcategoria |
| `active` | TINYINT DEFAULT 1 | |

### Tabela `ticket_attachments`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT AUTO_INCREMENT PK | |
| `ticket_id` | INT FK -> tickets(id) ON DELETE CASCADE | |
| `filename` | VARCHAR | Nome seguro no disco (`att_64a1b2c3.jpg`) |
| `original_name` | VARCHAR | Nome original do arquivo |
| `mime_type` | VARCHAR | MIME detectado via finfo |
| `size` | INT | Tamanho em bytes |
| `uploaded_by` | INT FK -> users(id) | Quem enviou |
| `created_at` | TIMESTAMP | |

### Tabela `audit_logs`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT AUTO_INCREMENT PK | |
| `user_id` | INT NULL FK -> users(id) | |
| `action` | VARCHAR | Tipo da acao |
| `entity_type` | VARCHAR | Tipo da entidade |
| `entity_id` | INT NULL | ID da entidade |
| `details` | TEXT NULL | Detalhes legiveis |
| `ip` | VARCHAR | IP do cliente |
| `created_at` | TIMESTAMP | |

**Acoes registradas:** `login`, `login_failed`, `ticket_create`, `ticket_resolve`, `ticket_assign`, `ticket_priority`, `ticket_update`, `ticket_rate`, `comment_add`, `user_delete`, `category_create`, `category_update`, `category_delete`, `subcategory_create`, `subcategory_update`, `subcategory_delete`

### Tabela `access_logs`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT AUTO_INCREMENT PK | |
| `user_id` | INT NULL FK -> users(id) | NULL para visitantes |
| `page` | VARCHAR | Nome do arquivo acessado |
| `ip` | VARCHAR | IP do cliente |
| `user_agent` | VARCHAR(255) | User-Agent |
| `created_at` | TIMESTAMP | |

### Dados Iniciais (Seed)

**Categorias padrao:**
| Categoria | Subcategorias |
|-----------|--------------|
| Hardware | Impressora, Monitor, Teclado, Mouse, Computador, Outros |
| Software | Sistema, Aplicativo, Configuracao, Atualizacao, Outros |
| Rede | Internet, Intranet, Wi-Fi, Cabo, Configuracao, Outros |
| Coletor | Leitor, Bateria, Configuracao, Outros |
| Outros | Solicitacao, D duvida, Manutencao, Outros |

**Usuarios iniciais (criados pelo schema.sql):**
| Usuario | Papel |
|---------|-------|
| `admin` | Admin (Master) |
| `suporte` | Suporte TI |
| `encarregado` | Encarregado |

> Senhas sao geradas com `password_hash()` no schema. Crie os usuarios via painel admin ou execute o schema.sql.

---

## 6. Modelo de Acesso e Roles

### Hierarquia de Roles

```
Admin (Master)          Nivel 3   ← Acesso total
    │
Suporte TI              Nivel 2   ← Atendimento + relatorios
    │
Encarregado             Nivel 1   ← Abertura + avaliacao
    │
Visitante               Nivel 0   ← Abertura apenas (por IP)
```

### Matriz de Permissoes

| Funcionalidade | Visitante | Encarregado | Suporte TI | Admin |
|----------------|:---------:|:-----------:|:----------:|:-----:|
| Abrir chamado (visitante) | ✅ | - | - | - |
| Abrir chamado (logado) | - | ✅ | ✅ | ✅ |
| Avaliar chamado (1-5 estrelas) | - | ✅ | ✅ | ✅ |
| Comentar | - | ✅ | ✅ | ✅ |
| Ver fila de atendimento | - | - | ✅ | ✅ |
| Resolver chamado | - | - | ✅ | ✅ |
| Atribuir chamado | - | - | ✅ | ✅ |
| Alterar prioridade | - | - | ✅ | ✅ |
| Enviar anexos | - | - | ✅ | ✅ |
| Dashboard ITIL (analytics) | - | - | ✅ | ✅ |
| Relatorios CSV | - | - | - | ✅ |
| Gerenciar usuarios | - | - | - | ✅ |
| Gerenciar categorias | - | - | - | ✅ |
| Log de auditoria | - | - | - | ✅ |
| Log de acessos | - | - | - | ✅ |
| Excluir usuario (LGPD) | - | - | - | ✅ |

### Navbar por Role

| Role | Cor da Navbar | Classe Bootstrap |
|------|--------------|-----------------|
| Admin | Preto | `bg-dark` |
| Suporte TI | Azul | `bg-primary` |
| Encarregado | Verde | `bg-success` |
| Visitante/Default | Cinza | `bg-secondary` |

---

## 7. Fluxos do Sistema

### 7.1 Fluxo de Login

```
visitante acessa login.php
    │
    ▼
Exibe selecao de papel (Encarregado / Suporte TI / Admin)
    │
    ▼
Clica em um papel → login.php?role=admin
    │
    ▼
Exibe formulario com usuario pre-preenchido + campo senha
    │
    ▼
Clica "Trocar papel" → volta para selecao
    │
    ▼
Preenche senha → Submit POST
    │
    ▼
Valida CSRF token
    │
    ▼
Rate limit check (5 tentativas/min por IP)
    │
    ▼
password_verify(senha, hash_bcrypt)
    │
    ├── Sucesso → session_start() → $_SESSION['user'] → log audit → redirect suporte.php
    │
    └── Falha → log audit (login_failed) → mensagem de erro
```

### 7.2 Fluxo de Abertura de Chamado (Visitante)

```
visitante acessa open_ticket.php (por IP autorizado)
    │
    ▼
isVisitor = !$user (sem sessao)
    │
    ▼
Exibe formulario simplificado:
  - Nome
  - Setor (select dos setores fixos)
  - Descricao
  - Honeypot campo "website" (escondido, bots preenchem)
  - Checkbox "Li e aceito a Politica de Privacidade" (marcado por padrao)
    │
    ▼
Submit POST
    │
    ▼
Valida CSRF
    │
    ▼
Rate limit (5/min)
    │
    ▼
Valida honeypot (se preenchido = bot → bloqueia)
    │
    ▼
Valida consentimento (checkbox obrigatorio)
    │
    ▼
Cria ticket:
  - code = PS-{proximo_id}
  - status = 'open'
  - priority = 'medium' (padrao visitante)
  - subcategory = 'Outros' (fixo para visitante)
  - IP e hostname auto-detectados
    │
    ▼
Teams notification (sendNewTicket)
    │
    ▼
Mensagem de sucesso com codigo do ticket
```

### 7.3 Fluxo de Abertura de Chamado (Logado)

```
usuario logado acessa open_ticket.php
    │
    ▼
isVisitor = false
    │
    ▼
Exibe formulario completo:
  - Nome (auto-preenchido)
  - Categoria (select dinamico do DB)
  - Subcategoria (cascade, filtra por categoria selecionada)
  - Setor (select)
  - Conf (select 01-25)
  - Hostname (auto-detectado)
  - IP (auto-detectado)
  - Prioridade (Baixa/Media/Alta/Critica)
  - Descricao
  - Honeypot + Consentimento
    │
    ▼
Submit POST
    │
    ▼
Valida CSRF + Rate limit + Honeypot + Consentimento
    │
    ▼
Valida categoria/subcategoria contra DB (Category::getAllWithSubs)
    │
    ▼
Cria ticket + Teams notification + audit log
```

### 7.4 Fluxo de Atendimento (Suporte TI)

```
suporte acessa support.php
    │
    ▼
Lista todos os tickets (join com assigned_name)
    │
    ▼
Filtros: status, busca por codigo/nome
    │
    ▼
Acoes disponiveis por ticket:
  │
  ├── "Ver" → ticket.php?id=X (detalhes completos)
  │
  ├── "Resolver" (modal com campos):
  │     - Select quem resolveu (nome)
  │     - Comentario opcional
  │     → POST → status='resolved' + resolved_at + resolved_by
  │     → Teams notification (sendResolved)
  │     → audit log (ticket_resolve)
  │
  ├── "Atribuir" (modal):
  │     - Select suporte_TI do sistema
  │     → POST → assigned_to = user.id
  │     → audit log (ticket_assign)
  │
  └── Prioridade: exibida como badge colorido
        - low: cinza
        - medium: azul
        - high: laranja
        - critical: vermelho
```

### 7.5 Fluxo de Detalhes do Chamado (ticket.php)

```
usuario acessa ticket.php?id=X
    │
    ▼
Carrega ticket + comentarios + anexos + avaliacao
    │
    ▼
Exibe:
  - Codigo, status, prioridade, categoria
  - SLA timer (contagem regressiva em tempo real via JS)
  - Descricao completa
  - IP e hostname (com link GLPI se disponivel)
  - Setor e Conf
  - Data de criacao e resolucao
  - Botao "Resolver" (se suporte+)
  - Select de prioridade (se suporte+)
  - Botao "Atribuir" (se suporte+)
    │
    ▼
Acoes:
  │
  ├── Comentar:
  │     - Textarea + botao
  │     → POST → INSERT INTO comments
  │     → audit log (comment_add)
  │
  ├── Anexar arquivo:
  │     - Input file (max 5MB, JPG/PNG/GIF/PDF/TXT)
  │     - Validacao via finfo (MIME real, nao $_FILES['type'])
  │     - Nome seguro: uniqid('att_') + extensao original
  │     → POST → move_uploaded_file + INSERT
  │
  ├── Download anexo:
  │     - Verifica ownership (criador ou quem enviou) ou nivel suporte+
  │     - Content-Disposition: attachment
  │     - Nome sanitizado contra header injection
  │
  └── Avaliar (1-5 estrelas):
        - Click na estrela
        → POST → INSERT INTO ratings
        → audit log (ticket_rate)
```

### 7.6 Fluxo SLA

```
SLA por prioridade:
  - critical: 1 hora
  - high:     2 horas
  - medium:   4 horas (padrao)
  - low:      24 horas

Status do SLA:
  - ok:        <= 75% do tempo SLA (verde)
  - warning:   <= 100% do tempo SLA (amarelo/laranja)
  - breached:  > 100% do tempo SLA (vermelho)

Timer ao vivo (ticket.php):
  - JS atualiza a cada 1 minuto
  - Exibe tempo decorrido: "2h 30m"
  - Cor muda conforme status SLA
  - Apos resolucao, para de contar
```

### 7.7 Fluxo Dark/Light Mode

```
Pagina carrega
    │
    ▼
theme.js executa imediatamente:
    │
    ├── Verifica localStorage('ps-theme')
    │     ├── Tem valor → usa ele
    │     └── Sem valor → verifica prefers-color-scheme
    │
    ▼
Aplica tema:
    - document.documentElement.setAttribute('data-theme', theme)
    - Salva no localStorage
    - Atualiza icone do botao (☀/☾)
    - Dispara CustomEvent('themeChanged')
    │
    ▼
Clique no botao #themeToggle:
    - Le tema atual
    - Inverte (dark ↔ light)
    - Repete processo de aplicacao
    │
    ▼
Mudanca de tema do SO:
    - Se usuario NAO escolheu manualmente → acompanha o SO
    - Se escolheu → mantem a escolha
    │
    ▼
Chart.js (analytics.php):
    - Escuta 'themeChanged'
    - Re-aplica cores dos graficos
```

---

## 8. Integracoes

### 8.1 Microsoft Teams (Power Automate)

**URL do Webhook:** Power Automate direct workflow URL (config.php)

**Notificacoes enviadas:**

| Evento | Cor | Conteudo |
|--------|-----|----------|
| Novo chamado | Azul (#0076D7) | Codigo, categoria, solicitante, conf, hostname, IP, descricao, botao "Ver P.S." |
| Chamado resolvido | Verde (#28A745) | Codigo, quem resolveu, solicitante, problema, botao "Ver P.S." |

**Formato:** MessageCard (Microsoft Teams format) via HTTP POST com `file_get_contents('php://input')`.

### 8.2 GLPI (Consulta de Hostname)

**Banco consultado:** `glpi_db` (somente leitura)

**Fluxo de resolucao de hostname:**

```
IP do cliente
    │
    ▼
GLPILookup::getHostnameByIp($ip)
    │
    ├── 1. Consulta glpi_ipaddresses → glpi_networknames → glpi_networkports → glpi_computers
    │     (JOIN completo, exclui registros deletados)
    │
    ├── 2. Se nao encontrar → DNS reverse lookup (gethostbyaddr)
    │
    └── 3. Se localhost → gethostname()
```

**Tabelas GLPI consultadas:**
- `glpi_ipaddresses`
- `glpi_networknames`
- `glpi_networkports`
- `glpi_computers`

### 8.3 Exportacao CSV

**Endpoint:** `admin/export.php`

**Filtros:**
- Status (aberto, em andamento, resolvido, fechado)
- Data inicio
- Data fim

**Formato:** CSV com BOM UTF-8 (`\xEF\xBB\xBF`) para compatibilidade com Excel.

**Campos exportados:** Codigo, Solicitante, Subcategoria, Status, Prioridade, IP, Hostname, Setor, Conf, Data Criacao, Resolvido em, Resolvido por, Atribuido a.

---

## 9. Seguranca e LGPD

### 9.1 Medidas de Seguranca

| Medida | Implementacao |
|--------|--------------|
| Senhas | Bcrypt via `password_hash()` / `password_verify()` |
| CSRF | Token em `$_SESSION['csrf']`, validado com `hash_equals()` em todos os POSTs |
| Rate Limit | File-based, 5 tentativas/min por IP, storage em `storage/ratelimit/` |
| Session Security | httponly, samesite=Lax, strict mode, timeout 30min |
| SQL Injection | Todas queries usam `prepare()` + `execute()` com bound params |
| XSS | Saida com `htmlspecialchars()` |
| Headers | X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, CSP, Referrer-Policy |
| Upload | `finfo_file()` para MIME real, whitelist de extensoes, max 5MB, nome seguro |
| Honeypot | Campo `website` escondido contra bots (formularios visitante e logado) |
| Anonimizacao | Delete de usuario anonimiza dados (nome → Ex-Usuario-{id}, comentarios → REMOVIDO-LGPD) |

### 9.2 Compliance LGPD

| Requisito | Implementacao |
|-----------|--------------|
| **Privacidade** | Pagina `privacy.php` com politica completa (8 secoes) |
| **Consentimento** | Checkbox obrigatorio "Li e aceito a Politica de Privacidade" (marcado por padrao no form访客) |
| **Dados coletados** | Nome, IP, hostname, setor, conf, descricao, categoria, prioridade |
| **Base legal** | Interesse legitimo (Art. 7, IX da LGPD) |
| **Compartilhamento** | Equipe TI, GLPI, Teams (via Power Automate) |
| **Direito ao esquecimento** | Admin pode excluir usuario → dados anonimizados, ratings deletados, comentarios substituidos por "REMOVIDO-LGPD" |
| **Registro de acessos** | Tabela `access_logs` com user_id, pagina, IP, user_agent, timestamp |
| **Registro de auditoria** | Tabela `audit_logs` com todas as acoes (login, CRUD, resolucao, etc.) |

### 9.3 Auditoria

**Acoes registradas no `audit_logs`:**

| Acao | Descricao |
|------|-----------|
| `login` | Login bem-sucedido |
| `login_failed` | Tentativa de login falhou |
| `ticket_create` | Chamado aberto |
| `ticket_resolve` | Chamado resolvido |
| `ticket_assign` | Chamado atribuido |
| `ticket_priority` | Prioridade alterada |
| `ticket_update` | Chamado atualizado |
| `ticket_rate` | Chamado avaliado |
| `comment_add` | Comentario adicionado |
| `user_delete` | Usuario excluido (anonimizado) |
| `category_create/update/delete` | CRUD de categorias |
| `subcategory_create/update/delete` | CRUD de subcategorias |

---

## 10. UX & UI

### 10.1 Design System

| Elemento | Estilo |
|----------|--------|
| Fonte | `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif` |
| Framework | Bootstrap 5.3.3 (CDN) |
| Graficos | Chart.js 4.x (CDN) |
| Layout | Container max-width 1100px |
| Bordas | Border-radius 8px (cards), 6px (botoes/inputs) |
| Sombras | `0 2px 8px rgba(0,0,0,0.08)` (cards) |
| Espacamento | 24px margin-bottom (secoes), 16px gap (grid) |

### 10.2 Paleta de Cores

**Light Mode:**
| Uso | Cor |
|-----|-----|
| Fundo | `#ffffff` |
| Fundo secundario | `#f8f9fa` |
| Navbar | `#212529` (preto) |
| Texto principal | `#212529` |
| Texto secundario | `#6c757d` |
| Links | `#0d6efd` (azul Bootstrap) |
| Borda | `#dee2e6` |

**Dark Mode:**
| Uso | Cor |
|-----|-----|
| Fundo | `#121212` |
| Fundo secundario | `#1e1e1e` |
| Navbar | `#0d1117` |
| Texto principal | `#e0e0e0` |
| Texto secundario | `#b0b0b0` |
| Links | `#6ea8fe` |
| Borda | `#333333` |

### 10.3 Badges de Status

| Status | Cor Bootstrap | Cor Visual |
|--------|--------------|------------|
| Aberto | `bg-danger` | Vermelho |
| Em andamento | `bg-warning` | Amarelo/Laranja |
| Resolvido | `bg-success` | Verde |
| Fechado | `bg-secondary` | Cinza |

### 10.4 Badges de Prioridade

| Prioridade | Cor | Badge |
|------------|-----|-------|
| Baixa | Cinza | `bg-secondary` |
| Media | Azul | `bg-primary` |
| Alta | Laranja | `bg-warning text-dark` |
| Critica | Vermelho | `bg-danger` |

### 10.5 Layout do Dashboard (index.php)

```
┌──────────────────────────────────────────┐
│  NAVBAR (cor = papel do usuario)         │
│  P.S. Profarma    [Abrir] [Sair] [☀/☾] │
├──────────────────────────────────────────┤
│  Resumo: [Abertos] [Andamento] [Resolv] │
│                                          │
│  Lista de tickets (tabela responsiva)    │
│  - Codigo | Solicitante | Status | SLA   │
│  - Data | Acoes                          │
├──────────────────────────────────────────┤
│  FOOTER                                  │
└──────────────────────────────────────────┘
```

### 10.6 Layout do Login (login.php)

```
┌──────────────────────────────────────────┐
│  NAVBAR (cinza)                          │
├──────────────────────────────────────────┤
│                                          │
│     ┌────────────────────────┐           │
│     │     P.S. Profarma      │           │
│     │   Acesse o sistema     │           │
│     │                        │           │
│     │  [Encarregado]         │           │
│     │  [Suporte TI]          │           │
│     │  [Admin]               │           │
│     │                        │           │
│     │  ── ou ──              │           │
│     │                        │           │
│     │  Usuario: [admin    ]  │           │
│     │  Senha:   [******** ]  │           │
│     │  [Entrar]              │           │
│     │                        │           │
│     │  Politica de Privacidade│           │
│     └────────────────────────┘           │
│                                          │
└──────────────────────────────────────────┘
```

### 10.7 Layout do Analytics (analytics.php)

```
┌──────────────────────────────────────────┐
│  NAVBAR                                  │
├──────────────────────────────────────────┤
│  Dashboard ITIL - Indicadores            │
│                                          │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐   │
│  │Total │ │Aberto│ │Resolv│ │Avali │   │
│  │  15  │ │  3   │ │  10  │ │  8   │   │
│  └──────┘ └──────┘ └──────┘ └──────┘   │
│                                          │
│  ┌─────────────────┐ ┌─────────────────┐ │
│  │ Tendencia       │ │ Status          │ │
│  │ (Line Chart)    │ │ (Doughnut)      │ │
│  └─────────────────┘ └─────────────────┘ │
│                                          │
│  ┌─────────────────┐ ┌─────────────────┐ │
│  │ Por Categoria   │ │ Por Problema    │ │
│  │ (Bar Chart)     │ │ (Horizontal Bar)│ │
│  └─────────────────┘ └─────────────────┘ │
│                                          │
│  ┌─────────────────┐ ┌─────────────────┐ │
│  │ Nota Media      │ │ Avaliacao/Cat   │ │
│  │ (Polar Area)    │ │ (Radar)         │ │
│  └─────────────────┘ └─────────────────┘ │
│                                          │
│  ┌──────────────────────────────────┐    │
│  │ Produtividade por Tecnico        │    │
│  │ (Bar Chart)                      │    │
│  └──────────────────────────────────┘    │
└──────────────────────────────────────────┘
```

### 10.8 Responsividade

- **Mobile-first**: Grids colapsam para 1 coluna em telas < 768px
- **Tables**: Scroll horizontal em mobile
- **Cards**: Padding responsivo
- **Navbar**: Collapse em mobile (hamburger menu)

### 10.9 Dark Mode - Detalhes Tecnicos

- **Implementacao**: CSS Custom Properties em `theme.css` (177 linhas)
- **Persistencia**: `localStorage` com chave `ps-theme`
- **Deteccao automatica**: `prefers-color-scheme: dark` do SO
- **Toggle**: Botao circular na navbar (`#themeToggle`), Unicode ☀ (claro) / ☾ (escuro)
- **Eventos**: CustomEvent `themeChanged` para re-render de Chart.js
- **Paginas com toggle**: Todas as 11 paginas autenticadas
- **Paginas sem toggle**: login.php, privacy.php, open_ticket.php (publicas)
- **Componentes estilizados**: body, navbar, cards, forms, tables, list-groups, alerts, modals, badges, buttons, pagination, links, scrollbars, metricas ITIL

---

## 11. API

### Endpoint: `POST /api/abrir_chamado.php`

**Content-Type:** `application/json`

**Rate Limit:** 5 requisicoes/minuto por IP

**Body:**

```json
{
    "requester_name": "Nome do solicitante",
    "subcategory": "Hardware",
    "description": "Descricao do problema",
    "ip": "10.195.1.100",
    "hostname": "DSK71001",
    "setor": "Conferencia",
    "conf": "01"
}
```

**Campos obrigatorios:** `requester_name`, `subcategory`, `description`

**Campos opcionais:** `ip`, `hostname`, `setor`, `conf` (auto-detectados se ausentes)

**Respostas:**

| Status | Descricao |
|--------|-----------|
| 201 | Chamado criado com sucesso |
| 400 | Campo obrigatorio ausente ou subcategoria invalida |
| 405 | Metodo nao permitido (nao-POST) |
| 429 | Rate limit excedido |

**Exemplo de uso:**

```powershell
curl -X POST http://localhost:8080/ps-system/api/abrir_chamado.php `
  -H "Content-Type: application/json" `
  -d '{"requester_name":"Joao","subcategory":"Hardware","description":"Impressora nao imprime"}'
```

---

## 12. Mapa de Funcionalidades por Pagina

| Pagina | Visitante | Encarregado | Suporte TI | Admin | Descricao |
|--------|:---------:|:-----------:|:----------:|:-----:|-----------|
| `login.php` | ✅ | ✅ | ✅ | ✅ | Autenticacao com selecao de papel |
| `open_ticket.php` | ✅ | ✅ | ✅ | ✅ | Abertura de chamado |
| `privacy.php` | ✅ | ✅ | ✅ | ✅ | Politica de privacidade LGPD |
| `logout.php` | - | ✅ | ✅ | ✅ | Encerrar sessao |
| `index.php` | - | ✅ | ✅ | ✅ | Dashboard com tickets |
| `ticket.php` | - | ✅ | ✅ | ✅ | Detalhes + SLA + anexos |
| `support.php` | - | - | ✅ | ✅ | Fila de atendimento |
| `analytics.php` | - | - | ✅ | ✅ | KPIs ITIL (7 graficos) |
| `download.php` | - | - | ✅ | ✅ | Download de anexos |
| `admin/index.php` | - | - | - | ✅ | Painel administrativo |
| `admin/users.php` | - | - | - | ✅ | Gerenciar usuarios |
| `admin/tickets.php` | - | - | - | ✅ | Gerenciar chamados |
| `admin/categories.php` | - | - | - | ✅ | Gerenciar categorias |
| `admin/audit.php` | - | - | - | ✅ | Log de auditoria |
| `admin/access_logs.php` | - | - | - | ✅ | Log de acessos |
| `admin/export.php` | - | - | - | ✅ | Exportacao CSV |
| `admin/delete_user.php` | - | - | - | ✅ | Excluir/anonimizar usuario |
| `api/abrir_chamado.php` | ✅ | ✅ | ✅ | ✅ | API REST para abertura |

---

## 13. Credenciais e Chaves

### Bancos de Dados

| Parametro | Valor |
|-----------|-------|
| Host | `127.0.0.1` (MariaDB/XAMPP) |
| Porta | `3306` |
| Usuario | `root` |
| Senha | *(vazio)* |
| Database principal | `ps_system` |
| Database GLPI | `glpi_db` (somente leitura) |

### Usuarios do Sistema

| Usuario | Nome | Papel |
|---------|------|-------|
| `admin` | Admin Master | admin |
| `suporte` | Suporte TI | suporte_ti |
| `encarregado` | Encarregado Geral | encarregado |

> Crie os usuarios via painel admin (`admin/users.php`) ou execute `db/schema.sql`.

### Webhook Teams

```
TEAMS_WEBHOOK_URL = YOUR_POWER_AUTOMATE_WEBHOOK_URL_HERE
```

> Configure sua URL de webhook do Power Automate em `config.php`.

### Maapeamento GLPI (IPs Fixos)

| Hostname | IP |
|----------|-----|
| DSK71001 | 10.195.1.101 |
| DSK71002 | 10.195.1.102 |
| DSK71003 | 10.195.1.103 |
| DSK71004 | 10.195.1.104 |
| DSK71005 | 10.195.1.105 |
| DSK71006 | 10.195.1.106 |
| DSK71007 | 10.195.1.107 |
| DSK71008 | 10.195.1.108 |
| DSK71009 | 10.195.1.109 |
| DSK71010 | 10.195.1.110 |
| DSK71011 | 10.195.1.111 |
| DSK71012 | 10.195.1.112 |
| DSK71025 | 10.195.1.125 |
| DSK71030 | 10.195.1.130 |
| DSK71031 | 10.195.1.131 |
| DSK71032 | 10.195.1.132 |
| DSK71040 | 10.195.1.140 |
| DSK71041 | 10.195.1.141 |
| DSK71050 | 10.195.1.150 |
| DSK71060 | 10.195.1.160 |
| DSK71061 | 10.195.1.161 |
| DSK71062 | 10.195.1.162 |
| NTB71023 | 10.195.1.123 |
| NTB71024 | 10.195.1.124 |

---

## 14. Guia de Desenvolvimento

### Estrutura de uma Nova Pagina

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

session_start();
Auth::requireMinLevel('suporte_ti'); // ou 'admin', 'encarregado'
logAccess();

$db = Database::getInstance();
$user = Auth::getUser();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagina - P.S. Profarma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand navbar-dark <?= Auth::navbarBg() ?>">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">P.S. Profarma</a>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="open_ticket.php" class="btn btn-outline-light btn-sm">Abrir P.S.</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
                <button id="themeToggle" class="btn-theme-toggle"></button>
            </div>
        </div>
    </nav>
    <div class="container my-4">
        <!-- Conteudo -->
    </div>
</body>
</html>
```

### Convencoes de Codigo

- **Naming**: camelCase para funcoes, PascalCase para classes, snake_case para variaveis PHP
- **SQL**: Sempre `prepare()` + `execute()` com bound params
- **CSRF**: Todo form POST deve ter `<?= Auth::csrfField() ?>` e `Auth::validateCsrf()` no handler
- **Rate Limit**: Aplicar em forms publicos e API
- **Audit Log**: Registrar todas as acoes de escrita
- **Access Log**: Chamar `logAccess()` apos `session_start()` em toda pagina autenticada
- **Dark Mode**: Incluir `theme.css` + `theme.js` + botao `#themeToggle` em paginas autenticadas
- **Comentarios**: Nao adicionar comentarios no codigo salvo pedido explicito

### Como Adicionar Nova Categoria

1. Acessar `admin/categories.php`
2. Clicar em "Nova Categoria"
3. Digitar nome e salvar
4. Clicar em "+" na categoria para adicionar subcategorias
5. As categorias ficam disponiveis imediatamente no form de abertura

### Como Testar

```powershell
# Verificar sintaxe PHP
& "C:\xampp\php\php.exe" -n -l arquivo.php

# Testar endpoint via curl (substitua SENHA pela senha do usuario)
$html = curl.exe -s -c cookies.txt "http://localhost:8080/ps-system/login.php?role=admin"
$token = [regex]::Match($html, 'name="csrf_token" value="([^"]+)"').Groups[1].Value
curl.exe -s -c cookies.txt -b cookies.txt -d "username=admin&password=SENHA&csrf_token=$token" -X POST -L -o NUL "http://localhost:8080/ps-system/login.php?role=admin"

# Testar pagina autenticada
curl.exe -s -b cookies.txt -o NUL -w "%{http_code}" "http://localhost:8080/ps-system/pagina.php"
```

---

## 15. Checklist de Auditoria

### Seguranca

- [x] Senhas com bcrypt
- [x] CSRF em todos os forms POST (7 handlers)
- [x] Rate limit em forms publicos e API
- [x] Session timeout 30min
- [x] Headers de seguranca (5 headers)
- [x] SQL injection prevention (prepared statements)
- [x] XSS prevention (htmlspecialchars)
- [x] Upload com finfo (MIME real)
- [x] Honeypot anti-bot
- [x] Ownership check em download.php

### LGPD

- [x] Pagina de privacidade
- [x] Consentimento obrigatorio
- [x] Direito ao esquecimento (anonimizacao)
- [x] Log de acessos
- [x] Log de auditoria

### Funcionalidades

- [x] Login com selecao de papel
- [x] Abertura访客 + logado
- [x] Fila de atendimento
- [x] Detalhes com SLA timer
- [x] Comentarios
- [x] Anexos (upload/download)
- [x] Avaliacao (1-5 estrelas)
- [x] Atribuicao de chamado
- [x] Prioridade com SLA variavel
- [x] Dashboard ITIL (7 graficos)
- [x] Exportacao CSV
- [x] CRUD categorias/subcategorias
- [x] Gerenciamento de usuarios
- [x] Log de auditoria
- [x] Log de acessos
- [x] Dark/Light mode
- [x] Teams notifications
- [x] GLPI hostname lookup
- [x] API REST para abertura

### Arquivos

| Tipo | Quantidade |
|------|-----------|
| PHP | 28 arquivos |
| CSS | 3 arquivos (style.css, theme.css, theme.js) |
| SQL | 2 arquivos (schema.sql, migration.sql) |
| Total linhas PHP | ~180.000 bytes |
| Total linhas CSS | ~450 linhas |

---

*Documento gerado automaticamente em Julho de 2026.*
*Sistema P.S. Profarma v2.0*
