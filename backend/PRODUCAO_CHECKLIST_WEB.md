# Checklist de Go-Live Web (Laravel + Livewire)

## 1. Ambiente (obrigatório)

- Definir variáveis de produção:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_URL=https://seu-dominio`
  - `LOG_LEVEL=warning`
- Configurar banco de produção (MySQL/PostgreSQL).
- Garantir `APP_KEY` e `JWT_SECRET` exclusivos do ambiente.

## 2. Segurança

- Forçar HTTPS no servidor/reverse proxy.
- Definir `SESSION_SECURE_COOKIE=true`.
- Revisar `SESSION_DOMAIN` e `SESSION_SAME_SITE`.
- Restringir CORS para domínios permitidos.
- Validar política de senhas e rate limit em auth/API.

## 3. Build e dependências

- Instalar dependências:
  - `composer install --no-dev --optimize-autoloader`
  - `npm ci`
  - `npm run build`

## 4. Banco e dados

- Rodar migrações:
  - `php artisan migrate --force`
- Seed inicial (somente se necessário):
  - `php artisan db:seed --force`

## 5. Otimização Laravel

- Aplicar caches:
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan event:cache`
  - `php artisan view:cache`

## 6. Filas e jobs

- Definir driver de fila para produção.
- Subir workers com Supervisor/systemd.
- Monitorar falhas (`failed_jobs`).

## 7. Saúde e testes mínimos

- Rodar testes antes do deploy:
  - `php artisan test`
- Validar login web, dashboard e módulos críticos.
- Validar login JWT e endpoints críticos da API v1.

## 8. Operação pós-deploy

- Monitorar logs de erro em tempo real.
- Criar rotina de backup (BD + storage crítico).
- Definir rollback (release anterior + restore DB quando aplicável).
