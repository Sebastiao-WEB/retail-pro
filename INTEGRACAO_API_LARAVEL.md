# Integracao API Laravel (Base)

## Modo de uso

Esta aplicacao agora suporta dois modos:

- `mock` (padrao): continua usando dados locais simulados.
- `api`: usa backend Laravel e faz fallback automatico para mock em caso de falha.

Configure no `.env`:

```env
VITE_API_MODE=api
VITE_API_URL=http://localhost:8000/api
VITE_API_VERSION=v1
VITE_API_TIMEOUT_MS=15000
```

Observacao:
- Se `VITE_API_URL` ja terminar com versao (ex.: `/api/v1`), a aplicacao nao duplica o prefixo.

## Autenticacao JWT

- Login via `POST /auth/login` deve retornar:
  - `access_token`
  - `refresh_token` (opcional, recomendado)
  - `token_type` (`Bearer`)
  - `expires_in` (opcional)
  - `user`
- O app salva token em `retailpro:token` e refresh em `retailpro:refresh_token`.
- O `httpClient` envia automaticamente `Authorization: Bearer <token>`.
- Em `401`, tenta refresh em `/auth/refresh` (se refresh token existir).
- Se refresh falhar, limpa sessao/token e redireciona para login.
- Backend recomendado: `php-open-source-saver/jwt-auth`.
- Rotas protegidas devem usar middleware JWT no grupo da API.

---

## Camada criada

- `src/api/httpClient.js` - cliente HTTP base, timeout, erro padronizado.
- `src/api/modules/*` - modulos por dominio (auth, products, customers, sales, purchases, cash).
- `src/services/integracaoApi.js` - ponte API/fallback mock.

---

## Endpoints esperados (base)

### Auth
- `POST /auth/login`

### Produtos
- `GET /products`
- `POST /products`
- `PUT /products/{id}`

### Clientes
- `GET /customers`
- `POST /customers`
- `PUT /customers/{id}`

### Vendas
- `GET /sales`
- `POST /sales`
- `POST /sale-reversal-requests`

### Compras
- `GET /purchases`

### Caixa
- `POST /cash-sessions/open`
- `POST /cash-sessions/{id}/close`
- `GET /cash-sessions/{id}/movements`

---

## Stores conectados

- `useProdutoStore` -> carrega produtos via integracao API.
- `useClienteStore` -> carrega clientes via integracao API.
- `useVendaStore` -> carrega historico, cria venda e solicita reversao via integracao API.

Se a API falhar, o fluxo continua operacional com mock local.

---

## Validação rápida (executada)

Integração validada com backend em `http://127.0.0.1:8000/api/v1`:

- `POST /auth/login` -> 200
- `GET /products` -> 200
- `GET /customers` -> 200
- `GET /sales` -> 200
- `GET /purchases` -> 200
- `GET /users` -> 200
- `PATCH /users/{id}/status` -> 200
- `POST /sales` -> 201

### Observações

- A camada API do desktop agora suporta versionamento via `VITE_API_VERSION`.
- Se `VITE_API_URL` já tiver `/v1`, o prefixo não é duplicado.
- Endpoints de `cash-sessions/*` permanecem preparados no cliente, mas ainda não integrados ao fluxo principal da UI.

