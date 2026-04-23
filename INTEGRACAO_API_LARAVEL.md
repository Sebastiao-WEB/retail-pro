# Integracao API Laravel (Base)

## Modo de uso

Esta aplicacao agora suporta dois modos:

- `mock` (padrao): continua usando dados locais simulados.
- `api`: usa backend Laravel e faz fallback automatico para mock em caso de falha.

Configure no `.env`:

```env
VITE_API_MODE=api
VITE_API_URL=http://localhost:8000/api
VITE_API_TIMEOUT_MS=15000
```

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

