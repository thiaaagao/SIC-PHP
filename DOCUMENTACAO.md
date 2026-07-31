# Sistema P.S. (Problem Solving) — Profarma

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Fluxo do Processo](#2-fluxo-do-processo)
3. [Papéis e Permissões](#3-papéis-e-permissões)
4. [Arquitetura](#4-arquitetura)
5. [Estrutura de Arquivos](#5-estrutura-de-arquivos)
6. [Banco de Dados](#6-banco-de-dados)
7. [Configuração](#7-configuração)
8. [Telas do Sistema](#8-telas-do-sistema)
9. [Integração com Teams](#9-integração-com-teams)
10. [Guia de Uso](#10-guia-de-uso)
11. [Manutenção](#11-manutenção)

---

## 1. Visão Geral

Sistema interno para abertura, acompanhamento e resolução de chamados técnicos (Problem Solving) na Profarma.

**Stack:** PHP 8.2 + MariaDB/MySQL + Bootstrap 5  
**Servidor:** Apache (XAMPP) — porta 8080  
**Autenticação:** Controle por IP + login por papel (encarregado/suporte)  
**Notificação:** Webhook do Microsoft Teams (via Power Automate)

---

## 2. Fluxo do Processo

```
  USUÁRIO                       SISTEMA                       SUPORTE (SOLVED)
 ──────────                   ──────────                     ─────────────────

    │                              │                               │
    ├── Acessa open_ticket.php ────┤                               │
    │   Preenche: nome, categoria, │                               │
    │   problema, hostname, etc    │                               │
    │                              │                               │
    ├── Clica "Abrir P.S." ───────┤                               │
    │                              ├── Gera código PS-0001        │
    │                              ├── Insere no banco            │
    │                              ├── Envia card p/ Teams ───────┤
    │                              │   (🆕 Novo P.S.)             │
    │                              │                               │
    │  Vê confirmação na tela      │                               ├── Vê card no Teams
    │                              │                               ├── Acessa suporte
    │                              │                               │   (login: suporte / suporte@2026)
    │                              │                               ├── Vê todos os P.S.
    │                              │                               ├── Localiza pelo hostname/IP
    │                              │                               ├── Clica "Resolver"
    │                              │                               ├── Adiciona comentário
    │                              │                               └── Confirma
    │                              │                               │
    │                              ├── Atualiza status → resolved  │
    │                              ├── Envia card p/ Teams ────────┤
    │                              │   (✅ P.S. Resolvido)         │
    │                              │                               │
    │  (se encarregado logado)     │                               │
    │  ├── Acessa ticket.php       │                               │
    │  ├── Vê resolução            │                               │
    │  └── Avalia (★ 1-5)          │                               │
    │                              │                               │
```

---

## 3. Papéis e Permissões

| Papel | Ações | Cor da Navbar |
|-------|-------|---------------|
| **Visitante** (sem login) | Abrir P.S. | Azul (`bg-primary`) |
| **Encarregado** | Abrir P.S., ver todos, avaliar resolução (1-5 estrelas) | Verde (`bg-success`) |
| **Suporte Técnico (Solved)** | Ver todos, resolver P.S., comentar | Preto (`bg-dark`) |

### Credenciais Padrão

> Crie os usuarios via painel admin ou execute `db/schema.sql`. As senhas sao geradas com `password_hash()`.

---

## 4. Arquitetura

```
┌───────────────────────────────────────────────┐
│               Servidor Local                   │
│  ┌──────────┐    ┌────────────────────────┐   │
│  │ Apache   │───→│ ps-system/public/       │   │
│  │ :8080    │    │ (junction via htdocs)   │   │
│  └──────────┘    └─────────┬──────────────┘   │
│                            │                   │
│  ┌──────────┐              │                   │
│  │ MariaDB  │←─────────────┘                   │
│  │ MySQL    │                                  │
│  └──────────┘                                  │
│  ┌──────────────────────────────────────────┐  │
│  │ Microsoft Teams (via Power Automate)     │  │
│  └──────────────────────────────────────────┘  │
└───────────────────────────────────────────────┘
```

---

## 5. Estrutura de Arquivos

```
C:\Chamado-auto\ps-system\
├── config.php                    ← Configurações (DB, Teams, IPs)
├── db\
│   ├── schema.sql                ← Schema completo
│   └── migration.sql             ← Migração de tabelas
├── src\
│   ├── Auth.php                  ← Controle de IP + login por papel
│   ├── Database.php              ← Conexão PDO
│   └── TeamsNotification.php     ← Notificações Teams
├── public\
│   ├── index.php                 ← Dashboard (visível a todos)
│   ├── open_ticket.php           ← Abertura de P.S.
│   ├── ticket.php                ← Detalhes + comentários + avaliação
│   ├── login.php                 ← Login (encarregado/suporte)
│   ├── logout.php                ← Logout
│   ├── support.php               ← Painel do suporte (solved apenas)
│   ├── setup.php                 ← Diagnóstico do sistema
│   └── assets\
│       └── style.css             ← Estilos
└── DOCUMENTACAO.md               ← Esta documentação
```

---

## 6. Banco de Dados

### Tabela: `tickets`

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | INT PK | ID interno |
| code | VARCHAR(20) | Código público (PS-0001) |
| requester_name | VARCHAR(255) | Nome do solicitante |
| subcategory | ENUM | Hardware, Software, Rede, Coletor, Outros |
| description | TEXT | Descrição do problema |
| ip | VARCHAR(45) | IP do equipamento |
| hostname | VARCHAR(255) | Hostname |
| setor | VARCHAR(255) | Setor |
| conf | VARCHAR(10) | Número do conf (01-25) |
| status | ENUM | open, in_progress, resolved, closed |
| resolved_at | TIMESTAMP | Data da resolução |
| resolved_by | VARCHAR(255) | Quem resolveu |
| created_at | TIMESTAMP | Abertura |
| updated_at | TIMESTAMP | Última atualização |

### Tabela: `users`

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | INT PK | |
| username | VARCHAR(50) UNIQUE | Login |
| password | VARCHAR(255) | Senha (texto plano por simplicidade) |
| name | VARCHAR(255) | Nome real |
| role | ENUM | encarregado, solved |

### Tabela: `comments`

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | INT PK | |
| ticket_id | INT FK | Ticket relacionado |
| user_id | INT FK | Autor do comentário |
| comment | TEXT | Conteúdo |
| created_at | TIMESTAMP | |

### Tabela: `ratings`

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | INT PK | |
| ticket_id | INT FK | Ticket relacionado |
| user_id | INT FK | Encarregado que avaliou |
| rating | TINYINT | 1 a 5 |

### Comandos Úteis

```sql
-- Estatísticas por encarregado
SELECT u.name, AVG(r.rating) as media, COUNT(r.id) as avaliacoes
FROM ratings r JOIN users u ON r.user_id = u.id GROUP BY u.name;

-- Tickets com maior número de comentários
SELECT code, COUNT(c.id) as comentarios FROM tickets t
LEFT JOIN comments c ON c.ticket_id = t.id
GROUP BY t.id ORDER BY comentarios DESC;
```

---

## 7. Configuração

### `config.php`

```php
define('TEAMS_WEBHOOK_URL', 'https://...powerautomate...');  // Webhook
define('ALLOWED_IPS', ['10.0.0.0/8', '192.168.0.0/16', ...]); // Redes liberadas
```

---

## 8. Telas do Sistema

### 8.1. `setup.php` — Diagnóstico
Mostra status do banco, webhook, IP, usuários cadastrados e credenciais.

### 8.2. `index.php` — Dashboard
- Cards de estatísticas (abertos, andamento, resolvidos, fechados)
- Busca por código, nome, hostname, setor, IP
- Filtro por status
- Navbar muda de cor conforme papel do usuário

### 8.3. `open_ticket.php` — Abertura de P.S.
- Nome (auto-preenchido se logado)
- Subcategoria + problema pré-definido
- IP e hostname auto-detectados
- Se encarregado logado, nome fica readonly

### 8.4. `ticket.php` — Detalhes do P.S.
- Informações completas do ticket
- **Para encarregados**: botão de avaliar (★) se status = resolved
- **Para suporte**: formulário de comentário
- Lista de comentários anteriores

### 8.5. `login.php` — Login
- Seleção rápida de papel (Encarregado / Suporte Técnico)
- Preenche usuário automaticamente conforme escolha

### 8.6. `support.php` — Painel do Suporte
- Acesso restrito a usuários com papel `solved`
- Lista completa com destaque amarelo para tickets abertos
- Modal de resolução com campo de comentário

---

## 9. Integração com Teams

Cards enviados via Power Automate webhook:

### Novo P.S.
```
🆕 Novo P.S. Aberto #PS-0001
Categoria: Suporte Técnico / Hardware
Solicitante: João Silva | Setor: Expedição
Hostname: COL-001 | IP: 10.0.0.100
[Ver P.S.]
```

### Resolvido
```
✅ P.S. Resolvido #PS-0001
Resolvido por: Suporte TI
[Ver P.S.]
```

---

## 10. Guia de Uso

### Para qualquer usuário (abrir P.S.)
1. Acesse `http://localhost:8080/ps-system/`
2. Clique em **"Abrir P.S."**
3. Preencha nome, subcategoria, problema, hostname
4. Clique em **"Abrir P.S."**
5. Pronto! O suporte é notificado no Teams

### Para Encarregado (avaliar)
1. Faça login em `login.php` como `encarregado`
2. Navegue até um P.S. resolvido
3. Avalie clicando nas estrelas (1-5)

### Para Suporte Técnico (resolver)
1. Faça login em `login.php` como `suporte`
2. Acesse `support.php`
3. Veja os tickets em aberto (destacados em amarelo)
4. Clique em **"Resolver"**, adicione um comentário
5. Confirme — o Teams é notificado automaticamente

---

## 11. Manutenção

### Gerenciar Usuários

Para adicionar novo usuário:

```sql
INSERT INTO users (username, password, name, role) VALUES
('joao.encarregado', 'senha123', 'João Encarregado', 'encarregado'),
('maria.suporte', 'senha456', 'Maria Suporte', 'solved');
```

### Backup

```bash
C:\xampp\mysql\bin\mysqldump -u root ps_system > backup_%DATE%.sql
```

### Resetar Banco

```bash
C:\xampp\mysql\bin\mysql -u root < C:\Chamado-auto\ps-system\db\schema.sql
```

### Troubleshooting

| Problema | Causa | Solução |
|----------|-------|---------|
| "Acesso negado: IP" | IP não liberado | Adicione em `ALLOWED_IPS` no config.php |
| 302 no support.php | Não logado como solved | Faça login como `suporte` |
| Teams não notifica | Webhook inválido | Verifique `TEAMS_WEBHOOK_URL` |
| Tela em branco | Erro PHP | Cheque logs do Apache |

---

*Documentação v2.0 — Julho/2026 — Sistema P.S. Profarma*
