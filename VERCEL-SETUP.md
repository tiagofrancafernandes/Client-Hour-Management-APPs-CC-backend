# Configuração Vercel - Backend Laravel

Este guia descreve como fazer deploy do backend Laravel na Vercel usando funções serverless.

## Arquivos de Configuração Criados

✅ **vercel.json** - Configuração do projeto (define runtime PHP 8.2 e rotas HTTP)
✅ **api/index.php** - Ponto de entrada para funções serverless
✅ **.vercelignore** - Arquivos excluídos do upload
✅ **composer.json** - Script `vercel-build` para otimizações de produção

## Pré-requisitos

1. **Conta Vercel** - https://vercel.com
2. **Vercel CLI** (opcional, para deploy local):
   ```bash
   npm i -g vercel
   ```
3. **Databases Externas**:
   - PostgreSQL (não pode estar localmente)
   - Redis (para cache)
   - Considere usar serviços como: PlanetScale, AWS RDS, ElephantSQL, Upstash, etc.

## Processo de Deployment

### 1. Preparar o Repositório Git

```bash
# Certifique-se de que o backend está em um repositório Git
cd backend
git init
git add .
git commit -m "Initial commit for Vercel deployment"
git remote add origin <seu-repositorio>
git push -u origin main
```

**Importante:** O projeto backend deve estar em um repositório Git acessível pela Vercel (GitHub, GitLab, Bitbucket).

### 2. Conectar à Vercel

#### Opção A: Via Dashboard Vercel (Recomendado)

1. Acesse https://vercel.com/dashboard
2. Clique em "Add New Project"
3. Selecione seu repositório Git
4. Configure conforme abaixo

#### Opção B: Via Vercel CLI

```bash
# Login na Vercel
vercel login

# Deploy
vercel
```

### 3. Configurar Variáveis de Ambiente

Na dashboard Vercel, acesse **Settings > Environment Variables** e adicione:

#### Variáveis Obrigatórias

```
APP_NAME=Hour Ledger
APP_ENV=production
APP_KEY=base64:<sua-chave-gerada>
APP_URL=https://<seu-dominio>.vercel.app
APP_DEBUG=false
```

**Gerar APP_KEY:**
```bash
php artisan key:generate --show
# Cópia valor (ex: base64:xxx...)
```

#### Variáveis de Banco de Dados

```
DB_CONNECTION=pgsql
DB_HOST=<seu-host-postgresql>
DB_PORT=5432
DB_DATABASE=<seu-database>
DB_USERNAME=<seu-usuario>
DB_PASSWORD=<sua-senha>
```

#### Variáveis de Cache/Redis (Opcional)

```
CACHE_STORE=redis
REDIS_HOST=<seu-host-redis>
REDIS_PORT=6379
REDIS_PASSWORD=<sua-senha-ou-vazio>
```

Se não usar Redis:
```
CACHE_STORE=database
QUEUE_CONNECTION=database
```

#### Outras Variáveis Recomendadas

```
LOG_CHANNEL=stack
SESSION_DRIVER=cookie
FILESYSTEM_DISK=local
MAIL_MAILER=log
```

### 4. Configurar Build

Na dashboard Vercel, em **Settings > Build & Development Settings**, você pode deixar os campos padrão:

**Build Command:** (deixar vazio - Vercel automaticamente roda `composer install`)

**Install Command:** (deixar vazio)

**Output Directory:** (deixar vazio)

**Nota:** O preset `vercel-php` automaticamente:
1. Instala dependências do `composer.json`
2. Executa migrações (via script no `composer.json`)
3. Otimiza a aplicação

### 5. Configurar Domínio Personalizado (Opcional)

1. Acesse **Settings > Domains**
2. Adicione seu domínio
3. Configure DNS records conforme instruções

## Desafios Comuns & Soluções

### ❌ Erro: "Call to undefined function proc_open()"

**Causa:** Algumas extensões PHP não estão disponíveis no Vercel.

**Solução:** Evite usar comandos que requerem `proc_open()` durante o build.

### ❌ Erro: "SQLSTATE[08006]" (Conexão com DB)

**Causa:** Variáveis de banco de dados incorretas ou DB inacessível.

**Solução:**
1. Verifique credenciais no `.env.production`
2. Confirme que DB aceita conexões remotas
3. Verifique whitelist de IPs do Vercel (se aplicável)

### ❌ Erro: "Permission denied" em storage/

**Causa:** Pasta storage não tem permissões.

**Solução:** Adicione ao `vercel.json`:
```json
{
  "buildCommand": "composer install && mkdir -p storage/logs storage/framework/{cache,sessions,views} && chmod -R 777 storage && php artisan migrate --force"
}
```

### ⚠️ Migrações de Banco de Dados

Certifique-se de que:
1. Seu DB está acessível remotamente
2. Migrações rodam automaticamente no build (`--force`)
3. Considere backups antes de cada deploy

### ⚠️ Armazenamento de Arquivos

Vercel tem **armazenamento efêmero** (apagado a cada deploy):
- ❌ **NÃO** salve arquivos em `storage/`
- ✅ Use serviços como: AWS S3, Cloudinary, Vercel KV, etc.

## Testar Localmente (Opcional)

```bash
# Instalar Vercel CLI
npm i -g vercel

# Build localmente como Vercel faria
vercel build --prod

# Rodar funções localmente
vercel dev
```

## Monitoramento

Após deploy:

1. **Logs Vercel**: Dashboard > Deployments > Logs
2. **Erros em Runtime**: Abra a URL do app e verifique browser console
3. **Database**: Verifique que migrações rodaram

## Variáveis de Exemplo Completo

```env
# Application
APP_NAME=Hour Ledger
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:xxx...
APP_URL=https://api-production.vercel.app
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

# Database
DB_CONNECTION=pgsql
DB_HOST=db.example.com
DB_PORT=5432
DB_DATABASE=hours_ledger_prod
DB_USERNAME=postgres_user
DB_PASSWORD=secure_password_here

# Cache & Session
CACHE_STORE=database
SESSION_DRIVER=cookie
SESSION_LIFETIME=120

# Queue
QUEUE_CONNECTION=database

# Mail
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@hoursled ger.com

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error
```

## Referências

- [Vercel PHP Runtime](https://vercel.com/docs/runtimes/php)
- [Laravel on Serverless](https://laravel.com/docs/12/deployment)
- [Vercel Environment Variables](https://vercel.com/docs/concepts/projects/environment-variables)
