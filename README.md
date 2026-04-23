# RetailPro POS (Desktop)

Sistema POS desktop focado em operacao de caixa, construido com Electron + Vue 3 + Pinia, com fluxo otimizado para atendimento rapido e pronto para integracao com backend Laravel.

---

## Principais funcionalidades

- Login de operador com atribuicao de caixa.
- Abertura e fecho de turno com controle de diferencas.
- Ponto de venda com catalogo, carrinho, desconto e validacao de stock.
- Impressao de talao (Electron), incluindo dados de IVA.
- Historico de vendas com detalhes, reimpressao e solicitacao de reversao.
- Dashboard de caixa com indicadores do turno e ultimas vendas.
- Configuracoes locais de impressao persistidas no `localStorage`.
- Camada de API base pronta para integracao com Laravel (com fallback para mock).

---

## Stack

- **Frontend:** Vue 3 (Composition API), Pinia, Vue Router
- **Desktop:** Electron
- **Build:** Vite
- **Estilo:** TailwindCSS
- **Testes:** Vitest

---

## Requisitos

- Node.js 18+
- npm 9+

---

## Instalacao

```bash
npm install
```

---

## Scripts

- `npm run dev` -> inicia app desktop (Electron + Vite)
- `npm run dev:web` -> inicia apenas frontend web
- `npm run electron:dev` -> dev desktop com Electron
- `npm run build` -> build de producao
- `npm run preview` -> preview da build
- `npm run electron` -> executa Electron direto
- `npm run test` -> roda testes Vitest
- `npm run test:watch` -> Vitest em modo watch

---

## Modos de dados (Mock/API)

A aplicacao suporta dois modos:

- **mock** (padrao): usa dados simulados locais.
- **api**: usa backend Laravel, com fallback para mock em caso de falha.

Configure no `.env`:

```env
VITE_API_MODE=api
VITE_API_URL=http://localhost:8000/api
VITE_API_TIMEOUT_MS=15000
```

Detalhes dos endpoints e integracao:
- ver `INTEGRACAO_API_LARAVEL.md`

Modelagem recomendada do banco:
- ver `MODELAGEM_BD_V2.md`

---

## Fluxo operacional (resumo)

1. Operador faz login e seleciona o caixa.
2. Abre turno com fundo inicial.
3. Registra vendas no POS.
4. Finaliza com ou sem impressao.
5. Consulta historico, reimprime ou solicita reversao.
6. No fim do dia, fecha turno com contagem real e justificativa de diferenca (se houver).

---

## Estrutura de pastas (resumo)

- `src/views` -> telas (POS, historico, configuracoes, login)
- `src/store` -> estado global com Pinia
- `src/components` -> componentes reutilizaveis
- `src/services` -> regras auxiliares, mocks e integracao
- `src/api` -> cliente HTTP e modulos de endpoints
- `electron/` -> processo principal, preload e recursos desktop
- `tests/` -> testes de regressao

---

## Estado atual da integracao Laravel

Ja implementado:
- cliente HTTP base com timeout e tratamento de erro
- modulos de API por dominio (auth, produtos, clientes, vendas, compras, caixa)
- fallback automatico para mock
- stores principais conectados a integracao

Proximo passo recomendado:
- integrar login real (`/auth/login`) com token JWT/Sanctum
- sincronizar abertura/fecho de caixa direto na API
- persistencia oficial de vendas/stock no backend

---

## Observacoes importantes

- A aplicacao foi desenhada para operacao **multi-caixa**.
- O modulo de impressao depende de ambiente Electron para acesso as impressoras locais.
- Configuracoes de impressao e sessao sao persistidas localmente para continuidade operacional.

