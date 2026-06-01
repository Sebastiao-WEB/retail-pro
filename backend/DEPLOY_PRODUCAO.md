# Checklist de deploy — Produção (RetailPro)

Domínio de referência: `https://padaria.mozretailpos.com`  
Branch: `dev`

Use este guia quando o sistema **já está em produção** com stock, vendas e utilizadores registados.

---

## 1. O que vai subir (resumo)

| Área | Alterações principais | Migração BD nova? |
|------|------------------------|-------------------|
| API vendas | Paginação `GET /sales`, idempotência por `id`, conflito se reenvio com conteúdo diferente | Não |
| API stock | `GET /stock/availability` devolve `{ quantity, version }`; `POST /stock/adjust` | Não |
| Admin web | Código de barras duplicado com erro no campo; botão **Corrigir stock** (ajuste ±) | Não |
| POS Electron | Offline, quantidades no carrinho, stock após sync, paginação históricos | Não (só novo `.exe`) |

**Commits recentes (após `b9f8dae` no remoto):**

- `765b2bc` — Validação código de barras duplicado  
- `b8c2c96` — Ajuste de stock (+/-) após recarga errada  

Se produção **nunca** recebeu `b9f8dae` ou `fda2d11`, o POS **tem de ser atualizado** na mesma janela (ver secção 6).

---

## 2. Antes de começar

- [ ] Janela de manutenção combinada com a loja (5–15 min se migrate estiver limpo)
- [ ] Backup da base de dados (obrigatório)
- [ ] Backup do ficheiro `.env` do servidor
- [ ] Acesso SSH ao servidor e ao repositório Git
- [ ] Credenciais para testar login admin e um operador POS

### Backup (exemplo MySQL)

```bash
mysqldump -u USUARIO -p NOME_BASE_DADOS > backup_retailpro_$(date +%F_%H%M).sql
```

---

## 3. No computador de desenvolvimento

```bash
cd /caminho/retail-pro
git checkout dev
git pull origin dev
git push origin dev
```

Gerar instalador Windows (para distribuir aos caixas):

```bash
npm ci
npm run dist:win
# Saída: release/RetailPro POS Setup *.exe
```

---

## 4. No servidor — Backend Laravel

Ajuste `APP_PATH` para o caminho real (ex.: `/var/www/retail-pro`).

```bash
export APP_PATH=/var/www/retail-pro
cd "$APP_PATH"

# Opcional: modo manutenção
cd backend && php artisan down --retry=60 && cd ..

git fetch origin
git checkout dev
git pull origin dev

cd backend
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### Migrações (dados existentes)

```bash
php artisan migrate:status
```

- [ ] Rever linhas **Pending**
- [ ] Se houver pendentes aceitáveis, aplicar:

```bash
php artisan migrate --force
```

**Nota:** Os commits de ajuste de stock e código de barras **não criam** migrações novas. `migrate:status` só mostra migrações **antigas** ainda não corridas no servidor (balancetes, permissões, `cash_session_id`, etc.).

### Cache e permissões

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan permission:cache-reset
```

### Reiniciar serviços

```bash
sudo systemctl reload php8.2-fpm   # versão PHP do servidor
# Se usar filas:
# sudo systemctl restart retailpro-queue
```

```bash
php artisan up   # se usou artisan down
```

---

## 5. Variáveis críticas (.env produção)

Confirmar no servidor:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://padaria.mozretailpos.com

JWT_SECRET=...   # não vazio
```

Depois de alterar `.env`:

```bash
php artisan config:cache
```

---

## 6. POS Electron (mesma janela de deploy)

**Importante:** Se o backend passar a `b9f8dae` e o POS continuar antigo:

- `GET /sales` pode devolver só **10 vendas** por pedido (paginação)
- Stock remoto pode falhar se o cliente não entender o novo formato de availability

- [ ] Instalar o novo `RetailPro POS Setup *.exe` em cada caixa
- [ ] Confirmar `.env` / build com `VITE_API_URL=https://padaria.mozretailpos.com/api`
- [ ] Abrir turno, venda teste, fecho teste (opcional)

---

## 7. Testes pós-deploy

### Admin web

- [ ] Login backoffice
- [ ] **Produtos** → criar/editar com código de barras já existente → erro **no campo** (não modal SQL)
- [ ] **Recarregar stock** → **Corrigir** → ajuste `-1` em produto de teste → stock desce 1
- [ ] **Histórico de movimentos** → filtro tipo **Ajuste**
- [ ] **Histórico de fechos** → paginação (10 por página)

### API (opcional, com token JWT)

```bash
export API=https://padaria.mozretailpos.com/api/v1
export TOKEN=eyJ...

# Paginação vendas
curl -s -H "Authorization: Bearer $TOKEN" \
  "$API/sales?page=1&per_page=10" | jq '.meta'

# Stock com versão
curl -s -H "Authorization: Bearer $TOKEN" \
  "$API/stock/availability?location_id=UUID_LOC&product_ids=UUID_PROD" | jq '.data'
```

### POS

- [ ] Login operador
- [ ] Venda com vários scans do mesmo produto → quantidade correcta no servidor
- [ ] (Se aplicável) Venda offline → fila → volta internet → sync sem erro “Failed to fetch” permanente
- [ ] Stock no ecrã actualiza após sync

---

## 8. Rollback (se algo correr mal)

1. `php artisan down`
2. `git checkout <commit-anterior>` ou restaurar release anterior
3. `composer install --no-dev` + `npm run build`
4. `php artisan config:cache && php artisan route:cache`
5. Restaurar backup BD **só se** migrate tiver alterado dados de forma indesejada
6. Reinstalar versão anterior do instalador Windows nos caixas
7. `php artisan up`

---

## 9. Operação do dia-a-dia (após deploy)

### Corrigir recarga errada (ex.: entrou 10, queria 7)

1. Admin → **Recarregar stock**
2. **Corrigir** no produto
3. Localização correcta → ajuste **`-3`**
4. Nota: `Correção: registei 10, deviam ser 7`
5. A recarga original (+10) **mantém-se** no histórico; o ajuste (−3) aparece em **Movimentos** como **Ajuste**

### Monitorização

```bash
tail -f storage/logs/laravel.log
```

---

## 10. Comandos úteis (referência rápida)

| Acção | Comando |
|-------|---------|
| Estado migrações | `php artisan migrate:status` |
| Testes API (no servidor dev/staging) | `php artisan test --filter=Api` |
| Limpar cache | `php artisan optimize:clear` |
| Recriar cache produção | `config:cache`, `route:cache`, `view:cache` |

---

*Documento gerado para deploy com dados existentes em produção. Actualize o `APP_PATH` e nomes de serviço PHP conforme o servidor.*
