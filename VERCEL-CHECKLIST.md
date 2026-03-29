# ✅ Checklist de Deployment - Vercel

Use este checklist antes de fazer deploy do backend na Vercel.

## 1. Preparação Local

- [ ] Todos os commits foram feitos e pushed para o repositório Git
- [ ] `.env` local contém todas as variáveis necessárias
- [ ] `php artisan test` passa sem erros
- [ ] Migrações rodam corretamente: `php artisan migrate`
- [ ] `./vendor/bin/pint` passou (código formatado)
- [ ] `.gitignore` inclui `/vendor`, `/node_modules`, `.env`

## 2. Configuração de Variáveis de Ambiente

### Banco de Dados PostgreSQL
- [ ] Banco de dados criado e acessível remotamente
- [ ] Host, porta, usuário e senha confirmados
- [ ] IP da Vercel adicionado ao whitelist (se necessário)
- [ ] `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` prontos

### Chave de Aplicação
- [ ] Executado: `php artisan key:generate --show`
- [ ] Valor copiado (formato `base64:xxx`)
- [ ] `APP_KEY` será adicionado na dashboard Vercel

### URLs
- [ ] `APP_URL` definida para o domínio Vercel (ex: `https://api.example.com`)
- [ ] `FRONTEND_URL` apontada para o frontend (pode ser Vercel também)
- [ ] `SAAS_URL`, `CUSTOMER_APP_URL`, `BACKOFFICE_URL` atualizadas

### Cache & Session (Opcional)
- Se usar Redis:
  - [ ] Redis acessível remotamente
  - [ ] `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` confirmados
  - [ ] `CACHE_STORE=redis`

- Se usar Database:
  - [ ] `CACHE_STORE=database`
  - [ ] `SESSION_DRIVER=cookie` (recomendado para serverless)

### Email (Optional)
- [ ] Escolher serviço: Mailgun, SendGrid, AWS SES, etc.
- [ ] Credenciais configuradas
- [ ] `MAIL_MAILER` atualizado (ex: `mailgun`)

## 3. Arquivos de Configuração

- [ ] ✅ `vercel.json` criado na raiz do backend
  - Verifica: `buildCommand`, `runtime`, `routes` configurados
- [ ] ✅ `api/index.php` criado
  - Verifica: Arquivo redireciona corretamente para `public/index.php`
- [ ] ✅ `.vercelignore` criado
  - Verifica: `/vendor`, `/node_modules`, `storage/*` excluídos

## 4. Configuração Vercel Dashboard

- [ ] Conta Vercel criada em https://vercel.com
- [ ] Projeto conectado ao repositório Git (GitHub, GitLab ou Bitbucket)
- [ ] Variáveis de ambiente adicionadas em **Settings > Environment Variables**:
  - [ ] `APP_NAME`
  - [ ] `APP_ENV=production`
  - [ ] `APP_DEBUG=false`
  - [ ] `APP_KEY` (com prefixo `base64:`)
  - [ ] `APP_URL`
  - [ ] `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - [ ] `CACHE_STORE` (se usar Redis)
  - [ ] `LOG_CHANNEL=stack`
  - [ ] `MAIL_MAILER` (se aplicável)

- [ ] Build Command verificado: `composer install && composer run vercel-build`
- [ ] Install Command: (deixar em branco)
- [ ] Output Directory: (deixar em branco)
- [ ] Node Version: (deixar em branco ou latest)

## 5. Banco de Dados

- [ ] PostgreSQL hospedado externamente (Amazon RDS, DigitalOcean, Render, etc.)
- [ ] Primeiro deploy vai rodar migrations automaticamente (`--force` ativado)
- [ ] Backup realizado antes do primeiro deploy
- [ ] Seed data (roles, permissions) será criado via migration

### Verificar Migrações
```bash
# Localmente, testar contra DB de produção
php artisan migrate --database=production --dry-run
```

## 6. Storage & Arquivos

- [ ] Se app salva arquivos: usar S3, Cloudinary ou similar
- [ ] **NÃO** depender de `storage/` local (efêmero na Vercel)
- [ ] `FILESYSTEM_DISK` configurado corretamente

## 7. Segurança

- [ ] `APP_DEBUG=false` em produção
- [ ] Senhas de banco de dados são fortes
- [ ] `APP_KEY` é único e secreto
- [ ] CORS/CSRF configurado corretamente em `config/cors.php`
- [ ] Rate limiting ativado se necessário

## 8. Primeiro Deploy

```bash
# Opção A: Via Git (automático)
# Apenas fazer git push e Vercel deploy automaticamente

# Opção B: Via Vercel CLI
vercel --prod
```

- [ ] Aguardar conclusão do build
- [ ] Verificar logs: Dashboard > Deployments > Build Logs
- [ ] Verificar erros: Dashboard > Deployments > Runtime Logs

## 9. Validação Pós-Deploy

- [ ] Acessar `https://<seu-projeto>.vercel.app/api/status` (se rota existir)
- [ ] Testar endpoints principais via Postman/Thunder Client
- [ ] Verificar database: conexão funcionando
- [ ] Verificar logs de erro: `vercel logs <project-name>`

```bash
# Exemplo: testar health check
curl https://api-production.vercel.app/api/health
```

- [ ] Testes de integração rodando contra produção
- [ ] Email funcionando (se aplicável)
- [ ] Cache funcionando

## 10. Configuração de Domínio Personalizado

- [ ] Domínio registrado (GoDaddy, Namecheap, etc.)
- [ ] DNS records apontados para Vercel:
  - [ ] `A` record ou `CNAME` configurado
  - [ ] Esperar propagação DNS (até 48h)
- [ ] Certificado SSL provisionado automaticamente
- [ ] Acessar `https://seu-dominio.com` funciona

## 11. Monitoramento Contínuo

- [ ] Erro logs monitorados regularmente
- [ ] Performance verificada (Web Vitals)
- [ ] Database connections verificadas
- [ ] Backups automáticos configurados no PostgreSQL

## 12. Rollback Plan

- [ ] Versão anterior do código tagueada no Git
- [ ] Backup do database feito antes de deploy
- [ ] Plano para reverter rápido se necessário:
  ```bash
  # Redeploy versão anterior
  vercel rollback
  ```

---

## 🚀 Pronto para Deploy?

Se todos os itens acima foram completados, execute:

```bash
git push origin main
```

Vercel fará o deployment automaticamente. Monitore em:
- https://vercel.com/dashboard

---

## 🆘 Problemas Comuns

| Problema | Solução |
|----------|---------|
| Build falha com "undefined function" | Remover chamadas a funções não-suportadas (como `proc_open`) |
| Database connection timeout | Verificar credentials, whitelist IPs, ou aumentar timeout |
| 404 em endpoints | Verificar `vercel.json` routes, especialmente o `api/index.php` |
| Erro de permissão em storage | Remover dependência de armazenamento local; usar S3 |
| Variáveis de ambiente undefined | Adicionar em **Settings > Environment Variables** |

---

**Última atualização:** 2026-03-28
