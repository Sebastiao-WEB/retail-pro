# Histórico de Continuidade - RetailPro POS

Este arquivo serve para retomar rapidamente o contexto no Cursor (em outro computador), sem perder o histórico de decisões técnicas e o ponto atual do projeto.

---

## 1) Estado atual do projeto

- Projeto principal: **RetailPro POS (desktop)** com Electron + Vue 3 + Pinia.
- Integração com backend Laravel está em andamento, com suporte a:
  - modo `mock` (fallback)
  - modo `api` (JWT)
- Branch atual: `main`
- Situação local no momento da criação deste arquivo: **sem alterações pendentes**.

Últimos commits relevantes:

1. `31779de` - Padroniza documentação UUID e refina catálogo de produtos no POS.
2. `4332d05` - Reforça precisão de venda no POS por caixa e localização.
3. `b561ed8` - Evolui modelagem de stock para multi-localização.
4. `548a064` - Documenta estratégia oficial de API com JWT para Laravel.

---

## 2) Decisões importantes já tomadas

### Autenticação/API

- A API vai usar **JWT** (`access_token` + `refresh_token`).
- O frontend já está preparado para:
  - enviar `Authorization: Bearer <token>`
  - tentar refresh automático em `401`
  - limpar sessão e redirecionar para login se refresh falhar.

### Banco de dados

- Padrão oficial: **UUID em todas as PKs e FKs** (`id`, `*_id`).
- Sem ID incremental nas tabelas operacionais.

### Stock e logística

- Modelo evoluiu para multi-localização:
  - loja + armazém
  - transferências
  - inventário/contagem
- Fluxo operacional desejado:
  - POS vende da localização de loja (não do armazém direto).
  - Armazém abastece loja via transferência.

### Produtos no POS

- Cadastro/edição de produtos não é mais fluxo do POS.
- Tela de produtos no POS está focada em **consulta operacional**.
- Campos de produto suportados no contrato:
  - `precoCompra`
  - `precoVenda`
  - IVA com `ivaTipo` (`percentual` | `monetario` | `isento`) e `ivaValor`.

---

## 3) Arquivos de referência principais

- `MODELAGEM_BD_V2.md`
  - modelagem completa da BD (multi-caixa, stock, transferências, UUID).

- `GUIA_API_LARAVEL_LIVEWIRE.md`
  - guia de implementação backend (Laravel + Livewire + API JWT).

- `CONTRATO_API_FRONTEND_POS.md`
  - contrato de API esperado pelo frontend:
    - endpoints consumidos
    - formato de sucesso/erro
    - payloads de request/response esperados
    - regras críticas para não quebrar o POS.

- `INTEGRACAO_API_LARAVEL.md`
  - visão resumida da integração API no frontend.

---

## 4) Alterações de frontend já aplicadas para precisão operacional

- Sessão (`useSessaoStore`) guarda metadados de operação:
  - `registerId`, `registerCodigo`
  - `sourceLocationId`, `sourceLocationCodigo`, `sourceLocationNome`

- Login (`LoginView`) já extrai esses dados do payload da API quando presentes.

- POS (`PosView`) valida origem de stock em modo API:
  - bloqueia finalização sem `sourceLocationId`
  - envia dados de `register` e `source_location` no payload da venda.

---

## 5) O que falta / próximos passos recomendados

1. **Backend Laravel**
   - Implementar endpoints do contrato em `CONTRATO_API_FRONTEND_POS.md`.
   - Garantir respostas JSON com `message` em erros.
   - Validar vínculo `register -> source_location` no backend ao criar venda.

2. **Mapeamento de campos**
   - Ideal padronizar API em `snake_case` e criar mapper frontend para reduzir ambiguidade.

3. **Fluxo de caixa remoto**
   - Integrar abertura/fecho de caixa com endpoints `cash-sessions/*` (hoje parte ainda local).

4. **Homologação**
   - Rodar ponta a ponta em `VITE_API_MODE=api` com dados reais.

---

## 6) Como retomar rapidamente no Cursor (em casa)

1. Abrir projeto.
2. Ler nesta ordem:
   - `HISTORICO_CONTINUIDADE_CURSOR.md` (este arquivo)
   - `CONTRATO_API_FRONTEND_POS.md`
   - `MODELAGEM_BD_V2.md`
   - `GUIA_API_LARAVEL_LIVEWIRE.md`
3. Validar `.env`:
   - `VITE_API_MODE=api` (ou `mock` para fallback)
   - `VITE_API_URL`
   - `VITE_API_TIMEOUT_MS`
4. Rodar:
   - `npm install`
   - `npm run test`
   - `npm run electron:dev` (ou `npm run dev:web`)

---

## 7) Nota final

Se precisar continuar exatamente de onde parou, peça ao agente:

> "continue a partir do HISTORICO_CONTINUIDADE_CURSOR.md e implemente os próximos endpoints da API conforme CONTRATO_API_FRONTEND_POS.md"

