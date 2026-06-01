# Setup do zero — RetailPro (clone + VPS)

Guia para instalar **do zero** com `git clone`, base de dados importada e **assets do admin** (`public/build`) gerados no PC e enviados no Git.

Domínio de exemplo: `https://padaria.mozretailpos.com`  
Repositório: [https://github.com/Sebastiao-WEB/retail-pro.git](https://github.com/Sebastiao-WEB/retail-pro.git)  
Branch: `dev` (produção padaria) ou `main` (default no GitHub)

---

## O que vai onde

| Componente | Onde corre |
|------------|------------|
| API + Admin (Laravel) | VPS — `backend/public` |
| MySQL | VPS |
| Assets Vite (`public/build`) | Build no **PC** → commit com `git add -f` → `git pull` na VPS |
| POS Electron | Build no **PC** → ZIP/exe nas caixas Windows (**não** na VPS) |

---

## Parte A — Repositório no PC (desenvolvimento)

### A1. Clone

```bash
git clone git@github.com:SEU_ORG/retail-pro.git
cd retail-pro
git checkout dev
```

### A2. Assets do admin (obrigatório para o backoffice)

```bash
cd backend
npm ci
npm run build
test -f public/build/manifest.json && echo "Build OK"
```

### A3. POS (opcional, nas caixas)

Na **raiz** do projeto (`retail-pro/`), ficheiro `.env`:

```env
VITE_API_MODE=api
VITE_API_URL=https://padaria.mozretailpos.com/api
VITE_API_VERSION=v1
VITE_API_TIMEOUT_MS=15000
```

```bash
cd ..   # raiz retail-pro
npm install
npm run build
npm run dist:win:zip
# Saída: release/RetailPro POS-0.0.0-win.zip
```

### A4. Commit e push (código + assets)

```bash
cd /caminho/retail-pro
git add backend/
git add -f backend/public/build
git commit -m "build: assets admin + alterações"
git push origin dev
```

> `public/build` está no `.gitignore`; **`git add -f`** é necessário para o push incluir os assets.

---

## Parte B — VPS (servidor novo)

Substitua `APP_PATH`, `DOMINIO`, credenciais MySQL e URL do repositório.

### B1. Pacotes (Ubuntu)

```bash
sudo apt update
sudo apt install -y nginx mysql-server git unzip \
  php8.3-fpm php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl \
  php8.3-zip php8.3-bcmath php8.3-intl composer certbot python3-certbot-nginx
```

Confirme a versão: `php -v` (o projeto pede PHP **^8.3**).

### B2. MySQL — base e utilizador

```bash
sudo mysql -e "CREATE DATABASE padaria_montepuez CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'retailpro'@'localhost' IDENTIFIED BY 'SENHA_FORTE';"
sudo mysql -e "GRANT ALL ON padaria_montepuez.* TO 'retailpro'@'localhost'; FLUSH PRIVILEGES;"
```

### B3. Importar dump (base já criada, sem CREATE DATABASE)

No PC, corrija collation se o dump veio do MySQL 8.4 (Windows):

```bash
sed 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' database.sql > database_vps.sql
scp database_vps.sql root@IP_VPS:/tmp/
```

Na VPS:

```bash
grep -v '^CREATE DATABASE' /tmp/database_vps.sql | grep -v '^USE \`padaria_montepuez\`' > /tmp/import.sql
mysql -u retailpro -p padaria_montepuez < /tmp/import.sql
```

Se o nome da base na VPS for outro, use esse nome no `mysql ...` e ajuste o `grep` do `USE`.

### B4. Clone do projeto

```bash
sudo mkdir -p /var/www/html
sudo chown $USER:$USER /var/www/html
cd /var/www/html
git clone git@github.com:SEU_ORG/retail-pro.git
cd retail-pro
git checkout dev
git pull origin dev
```

Confirme que existe `backend/public/build/manifest.json` (veio no Git com `git add -f` no PC).

Se **não** existir, no PC volte à parte A2–A4 ou na VPS (com Node instalado): `cd backend && npm ci && npm run build`.

### B5. Laravel — `.env` e instalação

```bash
cd /var/www/html/retail-pro/backend
cp .env.production.example .env
nano .env
```

Exemplo mínimo:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://padaria.mozretailpos.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=padaria_montepuez
DB_USERNAME=retailpro
DB_PASSWORD=SENHA_FORTE

JWT_SECRET=
SESSION_SECURE_COOKIE=true
```

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan jwt:secret
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache public/build
```

### B6. Nginx

Ficheiro `/etc/nginx/sites-available/retailpro`:

```nginx
server {
    listen 80;
    server_name padaria.mozretailpos.com;
    root /var/www/html/retail-pro/backend/public;

    index index.php;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

```bash
sudo ln -sf /etc/nginx/sites-available/retailpro /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d padaria.mozretailpos.com
```

### B7. DNS

Registo **A** de `padaria.mozretailpos.com` → IP da VPS.

### B8. Testes

- [ ] `https://padaria.mozretailpos.com` — login admin (utilizadores do dump, ex. `admin`)
- [ ] Admin com **CSS** (se sem estilo → falta `public/build`)
- [ ] `php artisan migrate:status` — sem migrações críticas em Pending inesperado

---

## Parte C — Caixas Windows (POS)

1. Instalar `release/RetailPro POS-0.0.0-win.zip` (build feito no PC com `VITE_API_URL` correcto).
2. Login operador → abrir turno → venda teste.
3. No admin: produtos com **Por peso (kg)** se `unidade_venda` já existir após `migrate`.

---

## Atualizações depois do setup

### Só backend (código + assets no Git)

**PC:**

```bash
cd retail-pro/backend && npm ci && npm run build
cd .. && git add backend/ && git add -f backend/public/build
git commit -m "..." && git push origin dev
```

**VPS:**

```bash
cd /var/www/html/retail-pro
git pull origin dev
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl reload php8.3-fpm
```

Ver também: `DEPLOY_PRODUCAO.md` (deploy com dados já em produção).

### Só assets (hotfix CSS/JS)

PC: `npm run build` → `git add -f backend/public/build` → push  
VPS: `git pull` → `php artisan view:cache`

---

## Problemas frequentes

| Problema | Solução |
|----------|---------|
| Admin sem estilo | `public/build` não está na VPS — A2 + A4 ou `npm run build` na VPS |
| `Unknown collation utf8mb4_0900_ai_ci` | `sed` no dump (parte B3) |
| `Vite manifest not found` | Falta `public/build/manifest.json` |
| Import falha linha 19 | Remover `CREATE DATABASE` do SQL |
| POS não liga à API | Rebuild Electron com `VITE_API_URL` no `.env` da raiz |
| **Mixed Content** (HTTPS bloqueia HTTP) | Ver secção abaixo |

### Mixed Content / Livewire não grava (produtos, etc.)

O site abre em `https://` mas pedidos Livewire/assets vão para `http://`.

**Na VPS, em `backend/.env`:**

```env
APP_URL=https://padaria.mozretailpos.com
SESSION_SECURE_COOKIE=true
```

**Nunca** `APP_URL=http://...` em produção com SSL.

Depois:

```bash
php artisan config:clear
php artisan config:cache
```

**Nginx** (dentro do `server` HTTPS) deve passar o protocolo:

```nginx
proxy_set_header X-Forwarded-Proto $scheme;   # se usar proxy
# Para PHP-FPM directo, inclua no site:
fastcgi_param HTTPS on;
fastcgi_param HTTP_X_FORWARDED_PROTO https;
```

Faça `git pull` com a versão que inclui `trustProxies` e `URL::forceScheme('https')` no `AppServiceProvider`.

`ERR_BLOCKED_BY_CLIENT` = extensão do browser (AdBlock) ou bloqueio do script `cloudflareinsights.com` — **não impede** gravar; pode ignorar.

### Cloudflare (domínio atrás da CF)

No painel Cloudflare → **SSL/TLS** → modo **Full** ou **Full (strict)** — **não** use **Flexible** (utilizador em HTTPS, Laravel no servidor vê HTTP → URLs `http://` no Livewire).

No `.env` da VPS:

```env
APP_URL=https://padaria.mozretailpos.com
APP_ENV=production
FORCE_HTTPS=true
```

```bash
php artisan config:clear && php artisan optimize:clear && php artisan config:cache
```

Confirme: `php artisan tinker --execute="echo url('/');"`

---

*Ajuste caminhos (`/var/www/html/retail-pro`), PHP-FPM (`php8.3`) e domínio conforme o servidor.*
