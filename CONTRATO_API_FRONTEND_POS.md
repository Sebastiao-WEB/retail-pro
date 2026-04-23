# Contrato de API Esperado pelo Frontend POS

Este documento define, de forma pratica, como o frontend do RetailPro POS espera consumir a API.
O objetivo e permitir que o backend Laravel responda exatamente no formato esperado, evitando quebrar o fluxo.

---

## 1) Regras gerais de comunicacao

- Base URL lida de `VITE_API_URL` (ex.: `http://localhost:8000/api`).
- Modo API ativo quando `VITE_API_MODE=api`.
- Timeout padrao: `VITE_API_TIMEOUT_MS` (padrao 15000ms).
- Header enviado pelo frontend:
  - `Content-Type: application/json`
  - `Accept: application/json`
  - `Authorization: Bearer <access_token>` (quando logado)

### Identificadores (UUID obrigatorio)

- O backend deve usar UUID em `id` e em todos os campos relacionais (`*_id`).
- Rotas com parametro `{id}` devem receber UUID.
- Exemplos deste documento usam UUID para refletir o padrao oficial.

### Formato de listas (importante)

Para endpoints de listagem, o frontend aceita **dois formatos**:

1. Array direto:

```json
[
  { "id": "d290f1ee-6c54-4b01-90e6-d701748f0851" }
]
```

2. Envelope com `data`:

```json
{
  "data": [
    { "id": "d290f1ee-6c54-4b01-90e6-d701748f0851" }
  ]
}
```

---

## 2) Formato padrao de sucesso e erro

## Sucesso (recomendado)

- Listagens: `[]` ou `{ "data": [] }`
- Operacoes de escrita: objeto JSON com dados criados/atualizados

Exemplo:

```json
{
  "message": "Venda criada com sucesso.",
  "data": {
    "id": "8f8f8b4b-8893-4dd8-a465-0e3f474fd998"
  }
}
```

## Erro (recomendado)

O frontend usa `message` para exibir feedback.

```json
{
  "message": "Descricao do erro",
  "errors": {
    "campo": [
      "Motivo detalhado"
    ]
  }
}
```

Status recomendados:

- `400` requisicao invalida
- `401` nao autenticado/token expirado
- `403` sem permissao
- `404` nao encontrado
- `409` conflito de regra de negocio
- `422` validacao
- `500` erro interno

---

## 3) Autenticacao JWT (em uso real)

## 3.1 `POST /auth/login`

### Request esperado

```json
{
  "username": "operador",
  "password": "123456",
  "register_code": "Caixa 01"
}
```

### Response de sucesso esperado

```json
{
  "access_token": "jwt-token",
  "refresh_token": "refresh-token",
  "token_type": "Bearer",
  "expires_in": 3600,
  "user": {
    "id": "6f8c21f1-8e8d-4655-8d4b-c51dd75f7e9b",
    "name": "Operador 01",
    "role": "CASHIER",
    "caixa_atribuido": "Caixa 01",
    "register": {
      "id": "af1f056f-b084-4f44-9e46-b7f5552f0cf9",
      "code": "CX-01",
      "name": "Caixa 01",
      "source_location": {
        "id": "f9b95ac2-cbd9-4b3d-a72e-2f660f24e4f2",
        "code": "LOC-CX01",
        "name": "Loja - Caixa 01"
      }
    }
  }
}
```

Campos minimos obrigatorios para login funcionar:

- `access_token` (obrigatorio)
- `user.name` (ou frontend usa username informado)

Campos importantes para precisao operacional no POS:

- `user.register.id`
- `user.register.code`
- `user.register.name`
- `user.register.source_location.id`

Alias aceitos pelo frontend para localizacao de stock:

- `user.source_location`
- `user.stock_location`
- `user.default_stock_location`
- `user.register.source_location`
- `user.register.stock_location`

## 3.2 `POST /auth/refresh`

### Request

```json
{
  "refresh_token": "refresh-token"
}
```

### Response de sucesso

```json
{
  "access_token": "novo-access-token",
  "refresh_token": "novo-ou-mesmo-refresh-token",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

## 3.3 `POST /auth/logout`

Sem payload especifico no frontend.
Resposta recomendada:

```json
{
  "message": "Sessao encerrada com sucesso."
}
```

---

## 4) Endpoints atualmente usados pelo frontend

## 4.1 Produtos

### `GET /products`

Query params possiveis:

- `search`
- `store_id`

### Campos esperados por item

```json
{
  "id": "d290f1ee-6c54-4b01-90e6-d701748f0851",
  "nome": "Pão francês",
  "codigoBarras": "5601000000012",
  "categoria": "Padaria",
  "precoCompra": 6,
  "precoVenda": 8,
  "ivaTipo": "isento",
  "ivaValor": 0,
  "ivaPercentual": 0,
  "stock": 120
}
```

Observacao: o frontend usa esses nomes em portugues (`nome`, `codigoBarras`, `precoCompra`, `precoVenda`, `ivaTipo`, `ivaValor`, `stock`).
Se a API devolver outro formato (ex.: `name`, `barcode`, `sale_price`), sera necessario adaptar o frontend ou criar transformacao.

## 4.2 Clientes

### `GET /customers`

Query params possiveis:

- `search`

### Campos esperados por item

```json
{
  "id": "7f75f7f7-c4d3-4d2f-8d8d-6b7d1f9f6aa1",
  "nome": "Cliente Geral",
  "telefone": "000000000",
  "email": "cliente@demo.co.mz",
  "nuit": "400000099"
}
```

## 4.3 Historico de vendas

### `GET /sales`

Query params possiveis:

- `cash_session_id`
- `register_id`
- `from`
- `to`

### Campos esperados por venda

```json
{
  "id": "1b1454ce-0e22-4f66-8e56-5d394e6f5a64",
  "referencia": "VD-20260423-00045",
  "cliente": "Cliente Geral",
  "caixa": "Caixa 01",
  "operador": "Operador 01",
  "metodoPagamento": "Dinheiro",
  "estado": "Concluida",
  "subtotal": 730,
  "descontoAplicado": 0,
  "total": 730,
  "valorPago": 750,
  "troco": 20,
  "data": "2026-04-23T10:20:00Z",
  "itens": [
    {
      "produtoId": "d290f1ee-6c54-4b01-90e6-d701748f0851",
      "nome": "Pão francês",
      "quantidade": 2,
      "precoVenda": 8,
      "precoSemIva": 6.9,
      "ivaPercentual": 16,
      "valorIvaUnitario": 1.1,
      "subtotal": 16
    }
  ]
}
```

## 4.4 Criar venda

### `POST /sales`

Payload atual enviado pelo frontend (exemplo real):

```json
{
  "id": "8f8f8b4b-8893-4dd8-a465-0e3f474fd998",
  "referencia": "VD-20260423-12345",
  "cliente": "Cliente Geral",
  "caixa": "Caixa 01",
  "registerId": "af1f056f-b084-4f44-9e46-b7f5552f0cf9",
  "registerCodigo": "CX-01",
  "sourceLocationId": "f9b95ac2-cbd9-4b3d-a72e-2f660f24e4f2",
  "sourceLocationCodigo": "LOC-CX01",
  "sourceLocationNome": "Loja - Caixa 01",
  "register_id": "af1f056f-b084-4f44-9e46-b7f5552f0cf9",
  "source_location_id": "f9b95ac2-cbd9-4b3d-a72e-2f660f24e4f2",
  "operador": "Operador 01",
  "turnoAbertura": "2026-04-23T08:00:00Z",
  "subtotal": 730,
  "descontoTipo": "valor",
  "descontoValor": 0,
  "descontoAplicado": 0,
  "total": 730,
  "metodoPagamento": "Dinheiro",
  "valorPago": 750,
  "troco": 20,
  "data": "2026-04-23T10:20:00Z",
  "itens": [
    {
      "produtoId": "d290f1ee-6c54-4b01-90e6-d701748f0851",
      "nome": "Pão francês",
      "precoVenda": 8,
      "precoSemIva": 6.9,
      "ivaPercentual": 16,
      "valorIvaUnitario": 1.1,
      "quantidade": 2,
      "subtotal": 16
    }
  ]
}
```

Resposta de sucesso recomendada:

```json
{
  "message": "Venda registada com sucesso.",
  "data": {
    "id": "8f8f8b4b-8893-4dd8-a465-0e3f474fd998",
    "referencia": "VD-20260423-1201",
    "status": "COMPLETED"
  }
}
```

## 4.5 Solicitar reversao

### `POST /sale-reversal-requests`

Request enviado:

```json
{
  "venda_id": "1b1454ce-0e22-4f66-8e56-5d394e6f5a64",
  "reason": "Cliente desistiu"
}
```

Observacao importante:

- O frontend atualmente envia `venda_id` (portugues), nao `sale_id`.
- Se o backend usar `sale_id`, aceitar alias ou ajustar frontend.

Resposta de sucesso recomendada:

```json
{
  "message": "Solicitação de reversão criada.",
  "data": {
    "id": "c81192e6-dcb8-48a2-943a-73eaf0476a82",
    "status": "PENDING"
  }
}
```

---

## 5) Endpoint de compras (em uso para historico)

## `GET /purchases`

Query params possivel:

- `store_id`

Campos esperados (minimo para historico local):

```json
{
  "id": "4f20b4a8-413d-4020-9ef4-a1af5f1456d0",
  "fornecedor": "Distribuidora Central",
  "total": 5450,
  "data": "2026-04-22T11:00:00Z",
  "itens": [
    {
      "nome": "Leite integral 1L",
      "quantidade": 50,
      "custoUnitario": 70
    }
  ]
}
```

---

## 6) Endpoints ja preparados no frontend, mas ainda nao utilizados no fluxo atual

- `POST /products`
- `PUT /products/{id}`
- `POST /customers`
- `PUT /customers/{id}`
- `POST /cash-sessions/open`
- `POST /cash-sessions/{id}/close`
- `GET /cash-sessions/{id}/movements`

Esses endpoints existem no cliente API, mas o fluxo de POS atual ainda opera abertura/fecho de caixa localmente.

---

## 7) Regras criticas para nao quebrar o POS

- Sempre retornar JSON (inclusive em erro).
- Sempre preencher `message` em erros.
- Em `401`, retornar `message` claro (o frontend tentara refresh automaticamente).
- Garantir `access_token` no login.
- Para venda em modo API, garantir `source_location_id` vinculado ao caixa no backend.
- Manter consistencia de nomes de campos esperados pelo frontend atual.

---

## 8) Sugestao para padronizacao futura

Para reduzir ambiguidade de nomes, recomenda-se evoluir para um contrato unico em `snake_case` na API e criar um mapper no frontend.
Enquanto esse mapper nao existe, este documento representa o contrato atual que o frontend consome.

