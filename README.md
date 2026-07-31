# S.I.C. - Sistema de Informacao e Chamados

Sistema web para abertura, acompanhamento e resolucao de chamados de TI, seguindo boas praticas ITIL.

**Stack:** PHP 8.2 + MariaDB/MySQL + Bootstrap 5 + Chart.js

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

## Opcoes de Deploy

### 1. Docker (Recomendado)

O mais rapido e portavel. Funciona em qualquer SO com Docker instalado.

#### Pre-requisitos
- Docker + Docker Compose

#### Instalacao

```bash
# Clonar
git clone https://github.com/thiaaagao/SIC-PHP.git
cd SIC-PHP

# Subir containers
docker-compose up -d

# Acessar
http://localhost:8080
```

O Docker cria automaticamente:
- Container MariaDB 11 com o banco `ps_system`
- Container PHP 8.2 + Apache
- Schema importado na primeira execucao

#### Comandos uteis

```bash
# Ver logs
docker-compose logs -f web

# Parar containers
docker-compose down

# Parar e apagar dados
docker-compose down -v

# Rebuild apos alteracoes
docker-compose up -d --build

# Acessar banco
docker exec -it sic-db mariadb -u root -proot ps_system
```

---

### 2. XAMPP (Windows)

Ideal para desenvolvimento local no Windows.

#### Pre-requisitos
- XAMPP com PHP 8.2+ e MariaDB/MySQL

#### Instalacao

```powershell
# 1. Configurar XAMPP na porta 8080
#    Editar C:\xampp\apache\conf\httpd.conf:
#    Listen 8080
#    ServerName localhost:8080

# 2. Criar junction
New-Item -ItemType Junction -Path "C:\xampp\htdocs\sic" -Target "C:\caminho\para\SIC-PHP"

# 3. Criar banco via phpMyAdmin
#    Acessar http://localhost:8080/phpmyadmin
#    Criar banco "ps_system"
#    Importar db/schema.sql
```

#### Acessar

```
http://localhost:8080/sic/
```

---

### 3. VPS / Servidor Linux (Producao)

Para ambientes de producao com Ubuntu/Debian.

#### Pre-requisitos
- Ubuntu 22.04+ ou Debian 12+
- Acesso SSH root ou sudo

#### Script de instalacao

```bash
#!/bin/bash
# setup-sic.sh - Instalar S.I.C. em VPS

set -e

echo "=== Atualizando sistema ==="
apt update && apt upgrade -y

echo "=== Instalando Apache + PHP + MariaDB ==="
apt install -y apache2 php8.2 libapache2-mod-php8.2 php8.2-mysql \
    php8.2-mbstring php8.2-xml php8.2-curl php8.2-gd mariadb-server

echo "=== Habilitando mod_rewrite ==="
a2enmod rewrite

echo "=== Configurando Apache ==="
cat > /etc/apache2/sites-available/sic.conf << 'EOF'
<VirtualHost *:80>
    ServerName sic.local
    DocumentRoot /var/www/sic/public
    
    <Directory /var/www/sic/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/sic_error.log
    CustomLog ${APACHE_LOG_DIR}/sic_access.log combined
</VirtualHost>
EOF

a2ensite sic.conf
systemctl reload apache2

echo "=== Configurando banco ==="
mysql -e "CREATE DATABASE IF NOT EXISTS ps_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'sic'@'localhost' IDENTIFIED BY 'SUA_SENHA_AQUI';"
mysql -e "GRANT ALL ON ps_system.* TO 'sic'@'localhost';"
mysql ps_system < /var/www/sic/db/schema.sql

echo "=== Configurando permissoes ==="
chown -R www-data:www-data /var/www/sic
chmod -R 755 /var/www/sic/storage

echo "=== S.I.C. instalado com sucesso! ==="
echo "Acesse: http://sic.local"
echo "Ou configure um dominio no DNS"
```

#### Configurar banco

Edite `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ps_system');
define('DB_USER', 'sic');
define('DB_PASS', 'SUA_SENHA_AQUI');
```

#### HTTPS (Let's Encrypt)

```bash
apt install -y certbot python3-certbot-apache
certbot --apache -d sic.suadominio.com
```

---

### 4. PHP Built-in Server (Desenvolvimento)

Para testes rapidos sem Apache/Nginx.

```bash
# Navegar ate a pasta public
cd SIC-PHP/public

# Iniciar servidor na porta 8080
php -S localhost:8080

# Acessar
http://localhost:8080
```

> **Nota:** O PHP built-in server nao processa `.htaccess`. Para producao, use Apache ou Nginx.

---

### 5. Nginx + PHP-FPM (Producao)

Alternativa leve ao Apache.

#### Instalacao

```bash
apt install -y nginx php8.2-fpm php8.2-mysql
```

#### Configuracao Nginx

```nginx
# /etc/nginx/sites-available/sic
server {
    listen 80;
    server_name sic.local;
    root /var/www/sic/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/sic /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

---

## Credenciais

Os usuarios sao criados pelo `db/schema.sql`.

| Papel | Nivel | Descricao |
|-------|-------|-----------|
| Admin | 3 | Gerencia usuarios, categorias, auditoria, exportacao |
| Suporte TI | 2 | Atende e resolve chamados, comenta, anexa |
| Encarregado | 1 | Abre chamados e avalia resolucoes |
| Visitante | 0 | Abre chamado sem login (por IP autorizado) |

> Para criar o usuario admin, acesse `admin/users.php` apos o primeiro login como suporte.

---

## Zerar o Banco de Dados

### Docker

```bash
docker-compose down -v
docker-compose up -d
```

### XAMPP / VPS

```sql
DROP DATABASE IF EXISTS ps_system;
CREATE DATABASE ps_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ps_system;
SOURCE db/schema.sql;
```

### Limpar dados (manter estrutura)

```sql
USE ps_system;
TRUNCATE TABLE ticket_attachments;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE access_logs;
TRUNCATE TABLE comments;
TRUNCATE TABLE ratings;
TRUNCATE TABLE tickets;
DELETE FROM users;
SOURCE db/schema.sql;
```

---

## Estrutura do Projeto

```
SIC-PHP/
├── Dockerfile              # Imagem Docker PHP+Apache
├── docker-compose.yml      # Stack completa (PHP+MariaDB)
├── .dockerignore           # Arquivos excluidos do Docker
├── config.php              # Configuracoes gerais, funcoes SLA
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
  "url": "http://localhost:8080/ticket.php?id=1"
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

```bash
# Verificar sintaxe PHP
php -l arquivo.php

# Testar login via curl
curl -c cookies.txt "http://localhost:8080/login.php?role=admin"
# Extrair token e fazer login
curl -b cookies.txt -d "username=admin&password=SENHA&csrf_token=TOKEN" \
    -X POST "http://localhost:8080/login.php?role=admin"

# Verificar banco (Docker)
docker exec -it sic-db mariadb -u root -proot ps_system -e "SELECT COUNT(*) FROM tickets;"
```

---

## Licenca

Uso interno
