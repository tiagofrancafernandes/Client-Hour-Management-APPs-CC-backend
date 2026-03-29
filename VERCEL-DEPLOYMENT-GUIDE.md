# 🚀 Guia de Deployment - Vercel

## Status Atual

✅ **Backend preparado para Vercel**
✅ **Commit realizado:** `Configure backend for Vercel deployment with PHP serverless runtime`
✅ **Branch enviada para GitHub:** `task/setup-vercel`

Repositório: https://github.com/tiagofrancafernandes/Client-Hour-Management-APPs-CC-backend

## Próximos Passos - Criar Projeto na Vercel

### 1. Criar Novo Projeto Vercel

Acesse: https://vercel.com/new

1. Clique em **"Add GitHub Repository"**
2. Procure por: `Client-Hour-Management-APPs-CC-backend`
3. Clique em **"Import"**

### 2. Configurar Projeto

Na tela de configuração:

**Project Settings:**
- Framework Preset: **Other** (PHP/Laravel)
- Root Directory: **`backend`** (deixe vazio - ele está no root do repo)
- Build Command: (deixar vazio)
- Output Directory: (deixar vazio)
- Install Command: (deixar vazio)

### 3. Adicionar Variáveis de Ambiente

Antes de clicar em "Deploy", clique em **"Environment Variables"** e adicione:

#### Obrigatórias:

```
APP_NAME=Hours Ledger
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_KEY_HERE
APP_URL=https://seu-dominio.vercel.app
```

**Para gerar APP_KEY:**
```bash
php artisan key:generate --show
```
Copie o valor (ex: `base64:xxxxx...`)

#### Banco de Dados:

```
DB_CONNECTION=pgsql
DB_HOST=seu-postgresql-host.com
DB_PORT=5432
DB_DATABASE=seu_database
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

#### Cache (Opcional - se usar Redis):

```
CACHE_STORE=redis
REDIS_HOST=seu-redis-host.com
REDIS_PORT=6379
REDIS_PASSWORD=sua_senha_redis
```

Se não tiver Redis, use:
```
CACHE_STORE=database
```

#### Outras Variáveis Recomendadas:

```
LOG_CHANNEL=stack
SESSION_DRIVER=cookie
MAIL_MAILER=log
QUEUE_CONNECTION=database
```

### 4. Deploy

Clique em **"Deploy"**

- A Vercel fará build automaticamente
- Dependências serão instaladas via Composer
- Migrações rodarão automaticamente
- O app ficará disponível em: `https://seu-dominio.vercel.app`

## Monitoramento

### Verificar Status do Build

1. Vá para https://vercel.com/dashboard
2. Clique no projeto **Client-Hour-Management-APPs-CC-backend**
3. Abra a aba **Deployments**
4. Veja os logs em **Build Logs** e **Runtime Logs**

### Testar Endpoints

```bash
# Testar API
curl https://seu-dominio.vercel.app/api/health

# Ou via Postman
GET https://seu-dominio.vercel.app/api/health
```

## Possíveis Erros & Soluções

### ❌ Build failed: "SQLSTATE[08006]"
**Causa:** Conexão com banco de dados falhou
**Solução:** Verificar credenciais e whitelist de IPs

### ❌ Build failed: "undefined function proc_open"
**Causa:** Alguns comandos não suportados no Vercel
**Solução:** Remover de `vercel-build` script se necessário

### ❌ Error: "/api/index.php not found"
**Causa:** Estrutura de rotas incorreta
**Solução:** Verificar que `api/index.php` existe no repositório

## Domínio Personalizado

Após deployment bem-sucedido:

1. Dashboard Vercel → Project → **Settings > Domains**
2. Clique em **"Add Domain"**
3. Adicione seu domínio (ex: `api.hoursled ger.com`)
4. Configure registros DNS conforme instruções
5. SSL será provisionado automaticamente

## Variáveis de Produção - Exemplo Completo

```env
APP_NAME=Hours Ledger
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:abcdef1234567890...
APP_URL=https://api.hoursled ger.com
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=pgsql
DB_HOST=db.railway.app
DB_PORT=5432
DB_DATABASE=hours_ledger
DB_USERNAME=postgres
DB_PASSWORD=your_secure_password

CACHE_STORE=redis
REDIS_HOST=redis.railway.app
REDIS_PORT=25061
REDIS_PASSWORD=your_redis_password

SESSION_DRIVER=cookie
QUEUE_CONNECTION=database
LOG_CHANNEL=stack
MAIL_MAILER=log
```

## Próximas Ações

1. ✅ Gerar `APP_KEY` localmente
2. ⏳ Criar projeto Vercel
3. ⏳ Configurar variáveis de ambiente
4. ⏳ Fazer primeiro deploy
5. ⏳ Testar endpoints
6. ⏳ Configurar domínio personalizado

---

**Timestamp:** 2026-03-28
**Branch:** task/setup-vercel
**Repository:** Client-Hour-Management-APPs-CC-backend
