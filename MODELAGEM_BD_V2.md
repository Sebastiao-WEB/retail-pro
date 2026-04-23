# Modelagem BD v2 - RetailPro POS (Laravel, Multi-Caixa)

## Objetivo
Esta versao 2 foi desenhada para:
- operar bem em POS real com multi-caixa;
- suportar reversao de vendas com aprovacao;
- manter rastreabilidade de stock, pagamentos e caixa;
- reduzir retrabalho na integracao com API Laravel.

---

## Convencoes recomendadas
- PK: `id` UUID (string), sem auto increment.
- FK: `*_id` UUID (referenciando PK UUID).
- Datas padrao Laravel: `created_at`, `updated_at`.
- Soft delete onde fizer sentido: `deleted_at`.
- Valores monetarios: `decimal(14,2)`.
- Taxas: `decimal(5,2)` (ex.: IVA 16.00).
- Campos de estado com `enum` ou `check` conforme banco.

### Padrao de identificadores (obrigatorio)

- Todas as tabelas devem usar `id` UUID como chave primaria.
- Todas as relacoes (`*_id`) devem ser UUID.
- Nao usar id incremental em tabelas operacionais.
- Em Laravel migrations, preferir:
  - `$table->uuid('id')->primary();`
  - `$table->foreignUuid('store_id')->constrained('stores');`
  - ou `$table->uuid('store_id');` + `foreign(...)` conforme necessidade.

---

## 1) Estrutura organizacional

### Tabela: `stores`
- id
- name
- code (unique)
- nuit
- phone
- email
- address
- is_active (boolean)
- created_at
- updated_at

**Finalidade:** representa cada unidade/filial da empresa.
**Uso no fluxo:** toda operacao (venda, compra, stock, caixa) deve estar vinculada a uma loja para garantir segregacao e relatorios corretos por unidade.

### Tabela: `registers` (caixa fisico)
- id
- store_id (fk -> stores.id)
- code (unique por loja)  // ex.: CX-01
- name                    // ex.: Caixa 01
- is_active (boolean)
- created_at
- updated_at

**Finalidade:** cadastra os caixas fisicos/terminais de atendimento existentes na loja.
**Uso no fluxo:** identifica em qual posto a venda ocorreu e qual sessao de caixa deve receber os movimentos financeiros.

### Tabela: `warehouses` (armazens)
- id
- store_id (fk -> stores.id)
- code (unique por loja)              // ex.: ARM-CENTRAL
- name                                // ex.: Armazem Central
- is_active (boolean)
- created_at
- updated_at

**Finalidade:** cadastra armazens fisicos ligados a cada loja.
**Uso no fluxo:** permite separar stock de loja (ponto de venda) e stock de retaguarda (armazem), incluindo transferencias internas.

### Tabela: `stock_locations` (localizacoes de stock)
- id
- store_id (fk -> stores.id)
- warehouse_id (fk -> warehouses.id, nullable)
- register_id (fk -> registers.id, nullable)
- code (unique por loja)              // ex.: LOC-CX01, LOC-ARM-CENTRAL
- name
- type (STORE_FLOOR, WAREHOUSE, DAMAGE, RETURN_AREA, TRANSIT)
- is_saleable (boolean)
- is_active (boolean)
- created_at
- updated_at

**Finalidade:** tabela unica para representar qualquer local onde existe stock.
**Uso no fluxo:** viabiliza controle granular de entrada/saida por local, sem limitar o sistema a apenas `store_id`.

---

## 2) Utilizadores e permissoes

### Tabela: `users`
- id
- name
- email (unique)
- password
- role (ADMIN, MANAGER, CASHIER)
- is_active (boolean)
- last_login_at (nullable)
- created_at
- updated_at

**Finalidade:** guarda operadores e gestores que acessam o sistema.
**Uso no fluxo:** define quem executou cada acao (abertura, venda, reversao, fecho) e qual permissao cada perfil possui.

---

## 3) Catalogo de produtos

### Tabela: `categories`
- id
- name
- created_at
- updated_at

**Finalidade:** organiza os produtos em grupos logicos.
**Uso no fluxo:** facilita busca, filtros, relatorios e manutencao do catalogo.

### Tabela: `products`
- id
- category_id (fk -> categories.id, nullable)
- name
- sku (nullable)
- barcode (unique, nullable)
- unit                     // un, kg, lt...
- purchase_price           // preco de compra/custo atual
- sale_price               // preco de venda base (antes do IVA)
- tax_type                 // PERCENTUAL, MONETARIO, ISENTO
- tax_value                // valor do IVA (percentual ou monetario)
- tax_rate                 // percentual efetivo (cache; 0 quando nao percentual)
- min_stock
- stock_quantity           // cache global por loja (opcional)
- is_active (boolean)
- created_at
- updated_at
- deleted_at (nullable)

**Finalidade:** catalogo mestre de itens vendidos/comprados.
**Uso no fluxo:** fornece preco de compra, preco de venda, regra de IVA, unidade e parametros de stock para POS, compras e historico.
**Nota:** com multi-localizacao, o saldo oficial por local deve ficar em `stock_balances`.
**Regra IVA recomendada:** quando `tax_type = ISENTO`, persistir `tax_value = 0` e `tax_rate = 0`.

---

## 4) Clientes e fornecedores

### Tabela: `customers`
- id
- name
- phone (nullable)
- email (nullable)
- address (nullable)
- nuit (nullable)
- is_active (boolean)
- created_at
- updated_at

**Finalidade:** cadastro de clientes para faturacao e historico.
**Uso no fluxo:** vincula vendas a um cliente (inclusive "Cliente Geral") e permite consultas/reimpressao por cliente.

### Tabela: `suppliers`
- id
- name
- phone (nullable)
- email (nullable)
- address (nullable)
- nuit (nullable)
- is_active (boolean)
- created_at
- updated_at

**Finalidade:** cadastro de fornecedores de mercadoria.
**Uso no fluxo:** suporta processo de compras, reposicao de stock e rastreio de origem de custos.

---

## 5) Compras

### Tabela: `purchases`
- id
- store_id (fk -> stores.id)
- supplier_id (fk -> suppliers.id)
- user_id (fk -> users.id)
- destination_location_id (fk -> stock_locations.id) // destino da entrada
- status (DRAFT, RECEIVED, CANCELLED)
- subtotal_amount
- discount_amount
- tax_amount
- total_amount
- note (nullable)
- created_at
- updated_at

**Finalidade:** cabecalho da compra (entrada de mercadoria).
**Uso no fluxo:** controla status da compra, totais financeiros, relacionamento com fornecedor/operador e local de entrada do stock.

### Tabela: `purchase_items`
- id
- purchase_id (fk -> purchases.id)
- product_id (fk -> products.id)
- product_name_snapshot
- quantity
- unit_cost
- tax_rate
- tax_amount
- line_total
- created_at
- updated_at

**Finalidade:** itens detalhados de cada compra.
**Uso no fluxo:** registra quantidade/custo/IVA por produto e alimenta movimentacao de stock de entrada.

---

## 6) Sessao de caixa (multi-caixa)

### Tabela: `cash_sessions`
- id
- store_id (fk -> stores.id)
- register_id (fk -> registers.id)
- user_id (fk -> users.id)              // operador que abriu
- opened_by (fk -> users.id)            // geralmente o mesmo user_id
- closed_by (fk -> users.id, nullable)
- opening_balance
- expected_balance (nullable)           // calculado no fecho
- closing_balance (nullable)            // contado no fecho
- difference_amount (nullable)
- status (OPEN, CLOSED, CANCELLED)
- opened_at
- closed_at (nullable)
- note (nullable)
- created_at
- updated_at

**Finalidade:** representa o turno de caixa do operador.
**Uso no fluxo:** centraliza abertura, fecho, saldo esperado/real e bloqueia vendas fora de sessao ativa.

### Tabela: `cash_movements`
- id
- cash_session_id (fk -> cash_sessions.id)
- type (IN, OUT, ADJUSTMENT)
- amount
- description
- reference_type (nullable)             // SALE, REFUND, MANUAL...
- reference_id (nullable)
- performed_by (fk -> users.id)
- created_at
- updated_at

**Finalidade:** razao detalhado de entradas/saidas de dinheiro da sessao.
**Uso no fluxo:** permite conciliacao e auditoria do caixa (venda, sangria, reforco, ajuste manual, etc.).

---

## 7) Vendas

### Tabela: `sales`
- id
- public_id (uuid unique)               // id publico para API/app
- sale_number (unique por loja/dia)     // ex.: VD-20260423-00045
- store_id (fk -> stores.id)
- register_id (fk -> registers.id)
- cash_session_id (fk -> cash_sessions.id)
- source_location_id (fk -> stock_locations.id) // de onde o stock sai
- customer_id (fk -> customers.id, nullable)
- user_id (fk -> users.id)              // operador
- status (DRAFT, COMPLETED, CANCELLED, REVERSED)
- subtotal_amount
- discount_amount
- tax_amount
- total_amount
- note (nullable)
- created_at
- updated_at

**Finalidade:** cabecalho da venda.
**Uso no fluxo:** guarda referencia publica, valores consolidados e vinculos de auditoria (loja, caixa, sessao, operador, cliente).

### Tabela: `sale_items`
- id
- sale_id (fk -> sales.id)
- product_id (fk -> products.id, nullable)
- product_name_snapshot
- barcode_snapshot (nullable)
- unit_snapshot
- quantity
- unit_price                            // preco unitario bruto da linha
- line_discount_amount
- tax_rate
- tax_amount
- line_subtotal
- line_total
- created_at
- updated_at

**Finalidade:** detalhamento item a item da venda.
**Uso no fluxo:** preserva snapshot de produto/preco/IVA no momento da venda para historico fiel, mesmo que cadastro mude depois.

### Tabela: `payment_methods`
- id
- code (CASH, TRANSFER, MPESA, CARD, CREDIT...)
- name
- is_active (boolean)
- created_at
- updated_at

**Finalidade:** lista de formas de pagamento aceitas.
**Uso no fluxo:** padroniza recebimentos no POS e consolida relatorios por metodo (dinheiro, transferencia, M-Pesa, cartao, etc.).

### Tabela: `sale_payments`
- id
- sale_id (fk -> sales.id)
- payment_method_id (fk -> payment_methods.id)
- amount
- reference (nullable)                  // nr transacao, etc
- metadata_json (nullable)
- created_at
- updated_at

**Finalidade:** pagamentos registrados para cada venda.
**Uso no fluxo:** suporta venda com um ou varios metodos e guarda referencia de transacoes externas quando necessario.

---

## 8) Reversao / estorno

### Tabela: `sale_reversal_requests`
- id
- sale_id (fk -> sales.id)
- requested_by (fk -> users.id)
- approved_by (fk -> users.id, nullable)
- status (PENDING, APPROVED, REJECTED, CANCELLED)
- reason (nullable)
- requested_at
- decided_at (nullable)
- created_at
- updated_at

**Finalidade:** fila de solicitacoes de estorno/reversao.
**Uso no fluxo:** implementa controle de aprovacao gerencial antes de reverter uma venda concluida.

### Tabela: `sale_reversals` (opcional, mas recomendado)
- id
- sale_id (fk -> sales.id)
- reversal_request_id (fk -> sale_reversal_requests.id, nullable)
- reversed_by (fk -> users.id)
- reason (nullable)
- stock_restored (boolean)
- created_at
- updated_at

**Finalidade:** registro oficial das reversoes executadas.
**Uso no fluxo:** cria trilha de auditoria separada da solicitacao, com quem reverteu, motivo e impacto no stock.

---

## 9) Stock (fonte oficial por localizacao)

### Tabela: `stock_balances` (cache por local/produto)
- id
- store_id (fk -> stores.id)
- location_id (fk -> stock_locations.id)
- product_id (fk -> products.id)
- quantity
- min_stock (nullable)
- max_stock (nullable)
- updated_at

**Finalidade:** cache rapido do saldo atual por produto em cada localizacao.
**Uso no fluxo:** evita somar historico inteiro de movimentos em consultas operacionais (POS, reposicao, dashboard de stock).

### Tabela: `stock_movements`
- id
- store_id (fk -> stores.id)
- product_id (fk -> products.id)
- from_location_id (fk -> stock_locations.id, nullable)
- to_location_id (fk -> stock_locations.id, nullable)
- type (IN, OUT, TRANSFER, ADJUSTMENT, RETURN)
- quantity
- unit_cost (nullable)
- reference_type                        // PURCHASE_ITEM, SALE_ITEM, TRANSFER_ITEM, REVERSAL, COUNT, MANUAL
- reference_id
- note (nullable)
- performed_by (fk -> users.id, nullable)
- created_at
- updated_at

**Finalidade:** livro razao oficial de inventario.
**Uso no fluxo:** toda entrada/saida/transferencia/ajuste gera movimento aqui, sempre indicando origem e destino quando aplicavel.

### Tabela: `stock_transfers`
- id
- store_id (fk -> stores.id)
- from_location_id (fk -> stock_locations.id)
- to_location_id (fk -> stock_locations.id)
- requested_by (fk -> users.id)
- approved_by (fk -> users.id, nullable)
- status (DRAFT, PENDING, APPROVED, IN_TRANSIT, RECEIVED, CANCELLED)
- note (nullable)
- requested_at
- approved_at (nullable)
- completed_at (nullable)
- created_at
- updated_at

**Finalidade:** cabecalho das transferencias entre locais (armazem <-> loja, loja <-> loja).
**Uso no fluxo:** controla aprovacao, expedicao e recebimento da transferencia, garantindo rastreabilidade operacional.

### Tabela: `stock_transfer_items`
- id
- stock_transfer_id (fk -> stock_transfers.id)
- product_id (fk -> products.id)
- product_name_snapshot
- quantity_requested
- quantity_sent (nullable)
- quantity_received (nullable)
- created_at
- updated_at

**Finalidade:** itens de cada transferencia de stock.
**Uso no fluxo:** permite diferenciar solicitado, enviado e recebido, cobrindo perdas/divergencias no trajeto.

### Tabela: `stock_counts` (inventario)
- id
- store_id (fk -> stores.id)
- location_id (fk -> stock_locations.id)
- performed_by (fk -> users.id)
- status (DRAFT, IN_PROGRESS, POSTED, CANCELLED)
- note (nullable)
- counted_at (nullable)
- posted_at (nullable)
- created_at
- updated_at

**Finalidade:** cabecalho de contagens fisicas de inventario.
**Uso no fluxo:** sustenta auditoria periodica do stock e ajustes oficiais com rastreabilidade.

### Tabela: `stock_count_items`
- id
- stock_count_id (fk -> stock_counts.id)
- product_id (fk -> products.id)
- expected_quantity
- counted_quantity
- difference_quantity
- unit_cost (nullable)
- difference_value (nullable)
- created_at
- updated_at

**Finalidade:** itens contados em cada inventario.
**Uso no fluxo:** identifica divergencias por produto e alimenta ajustes em `stock_movements` (tipo `ADJUSTMENT`/`COUNT`).

---

## 10) Configuracao operacional (recomendado)

### Tabela: `register_settings`
- id
- register_id (fk -> registers.id)
- default_printer_name (nullable)
- receipt_width (58mm, 80mm)
- auto_cut (boolean)
- copies (int)
- created_at
- updated_at

**Finalidade:** preferencias operacionais por caixa fisico.
**Uso no fluxo:** permite configurar impressora e parametros de talao por terminal, mantendo consistencia no atendimento.

---

## Indices essenciais

- `products(barcode)` unique.
- `sales(public_id)` unique.
- `sales(sale_number)` unique.
- `sales(cash_session_id, created_at)`.
- `sale_items(sale_id)`.
- `sale_payments(sale_id)`.
- `cash_sessions(register_id, status, opened_at)`.
- `cash_movements(cash_session_id, created_at)`.
- `stock_balances(location_id, product_id)` unique.
- `stock_movements(product_id, created_at)`.
- `stock_movements(from_location_id, created_at)`.
- `stock_movements(to_location_id, created_at)`.
- `stock_movements(reference_type, reference_id)`.
- `stock_transfers(from_location_id, to_location_id, status)`.
- `stock_transfer_items(stock_transfer_id)`.
- `stock_counts(location_id, status, created_at)`.
- `sale_reversal_requests(sale_id, status)`.

---

## Regras transacionais (muito importante)

### Finalizar venda
1. Criar `sales`.
2. Criar `sale_items`.
3. Criar `sale_payments`.
4. Criar `stock_movements` (origem = `source_location_id`, tipo OUT) por item.
5. Atualizar `stock_balances` da localizacao de origem.
6. Criar `cash_movements` tipo IN quando pagamento em dinheiro.
7. Commit unico.

### Reverter venda aprovada
1. Validar status da venda.
2. Criar `sale_reversals`.
3. Criar `stock_movements` tipo RETURN (destino = localizacao de venda) por item.
4. Ajustar saldo/caixa (`cash_movements` OUT quando aplicavel).
5. Atualizar `sales.status = REVERSED`.
6. Commit unico.

### Transferencia entre armazem e loja
1. Criar `stock_transfers` e `stock_transfer_items`.
2. Aprovar transferencia.
3. Ao expedir, criar `stock_movements` tipo TRANSFER (from -> to) e baixar saldo da origem.
4. Ao receber, confirmar quantidades e atualizar saldo destino.
5. Em divergencia, registrar ajuste com justificativa.

### Fecho de caixa
1. Calcular esperado = abertura + entradas - saidas.
2. Gravar contado e diferenca.
3. Fechar `cash_sessions.status = CLOSED`.
4. Nao permitir novas vendas na sessao fechada.

---

## Ordem sugerida de migrations (Laravel)
1. stores
2. users
3. registers
4. warehouses
5. stock_locations
6. categories
7. products
8. customers
9. suppliers
10. payment_methods
11. purchases
12. purchase_items
13. cash_sessions
14. cash_movements
15. sales
16. sale_items
17. sale_payments
18. sale_reversal_requests
19. sale_reversals
20. stock_balances
21. stock_movements
22. stock_transfers
23. stock_transfer_items
24. stock_counts
25. stock_count_items
26. register_settings

---

## Observacao final para teu cenário
Para operacao multi-caixa real, o ponto mais critico e sempre amarrar toda venda em:
- `store_id`
- `register_id`
- `cash_session_id`
- `source_location_id`
- `user_id`

Sem isso, auditoria e conciliacao de caixa ficam inconsistentes.

