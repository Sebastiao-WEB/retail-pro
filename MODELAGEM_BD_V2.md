# Modelagem BD v2 - RetailPro POS (Laravel, Multi-Caixa)

## Objetivo
Esta versao 2 foi desenhada para:
- operar bem em POS real com multi-caixa;
- suportar reversao de vendas com aprovacao;
- manter rastreabilidade de stock, pagamentos e caixa;
- reduzir retrabalho na integracao com API Laravel.

---

## Convencoes recomendadas
- PK: `id` bigint unsigned auto increment.
- FK: `*_id` bigint unsigned.
- Datas padrao Laravel: `created_at`, `updated_at`.
- Soft delete onde fizer sentido: `deleted_at`.
- Valores monetarios: `decimal(14,2)`.
- Taxas: `decimal(5,2)` (ex.: IVA 16.00).
- Campos de estado com `enum` ou `check` conforme banco.

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

### Tabela: `registers` (caixa fisico)
- id
- store_id (fk -> stores.id)
- code (unique por loja)  // ex.: CX-01
- name                    // ex.: Caixa 01
- is_active (boolean)
- created_at
- updated_at

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

---

## 3) Catalogo de produtos

### Tabela: `categories`
- id
- name
- created_at
- updated_at

### Tabela: `products`
- id
- category_id (fk -> categories.id, nullable)
- name
- sku (nullable)
- barcode (unique, nullable)
- unit                     // un, kg, lt...
- cost_price               // custo atual
- sale_price               // preco base (sem desconto de venda)
- tax_rate                 // iva padrao do produto
- min_stock
- stock_quantity           // cache rapido (fonte oficial = movements)
- is_active (boolean)
- created_at
- updated_at
- deleted_at (nullable)

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

---

## 5) Compras

### Tabela: `purchases`
- id
- store_id (fk -> stores.id)
- supplier_id (fk -> suppliers.id)
- user_id (fk -> users.id)
- status (DRAFT, RECEIVED, CANCELLED)
- subtotal_amount
- discount_amount
- tax_amount
- total_amount
- note (nullable)
- created_at
- updated_at

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

---

## 7) Vendas

### Tabela: `sales`
- id
- public_id (uuid unique)               // id publico para API/app
- sale_number (unique por loja/dia)     // ex.: VD-20260423-00045
- store_id (fk -> stores.id)
- register_id (fk -> registers.id)
- cash_session_id (fk -> cash_sessions.id)
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

### Tabela: `payment_methods`
- id
- code (CASH, TRANSFER, MPESA, CARD, CREDIT...)
- name
- is_active (boolean)
- created_at
- updated_at

### Tabela: `sale_payments`
- id
- sale_id (fk -> sales.id)
- payment_method_id (fk -> payment_methods.id)
- amount
- reference (nullable)                  // nr transacao, etc
- metadata_json (nullable)
- created_at
- updated_at

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

### Tabela: `sale_reversals` (opcional, mas recomendado)
- id
- sale_id (fk -> sales.id)
- reversal_request_id (fk -> sale_reversal_requests.id, nullable)
- reversed_by (fk -> users.id)
- reason (nullable)
- stock_restored (boolean)
- created_at
- updated_at

---

## 9) Stock (fonte oficial de movimentacao)

### Tabela: `stock_movements`
- id
- store_id (fk -> stores.id)
- product_id (fk -> products.id)
- type (IN, OUT, ADJUSTMENT, RETURN)
- quantity
- unit_cost (nullable)
- reference_type                        // PURCHASE_ITEM, SALE_ITEM, REVERSAL, MANUAL
- reference_id
- note (nullable)
- performed_by (fk -> users.id, nullable)
- created_at
- updated_at

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
- `stock_movements(product_id, created_at)`.
- `stock_movements(reference_type, reference_id)`.
- `sale_reversal_requests(sale_id, status)`.

---

## Regras transacionais (muito importante)

### Finalizar venda
1. Criar `sales`.
2. Criar `sale_items`.
3. Criar `sale_payments`.
4. Criar `stock_movements` tipo OUT por item.
5. Atualizar `products.stock_quantity` (cache).
6. Criar `cash_movements` tipo IN quando pagamento em dinheiro.
7. Commit unico.

### Reverter venda aprovada
1. Validar status da venda.
2. Criar `sale_reversals`.
3. Criar `stock_movements` tipo RETURN (ou IN) por item.
4. Ajustar saldo/caixa (`cash_movements` OUT quando aplicavel).
5. Atualizar `sales.status = REVERSED`.
6. Commit unico.

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
4. categories
5. products
6. customers
7. suppliers
8. payment_methods
9. purchases
10. purchase_items
11. cash_sessions
12. cash_movements
13. sales
14. sale_items
15. sale_payments
16. sale_reversal_requests
17. sale_reversals
18. stock_movements
19. register_settings

---

## Observacao final para teu cenário
Para operacao multi-caixa real, o ponto mais critico e sempre amarrar toda venda em:
- `store_id`
- `register_id`
- `cash_session_id`
- `user_id`

Sem isso, auditoria e conciliacao de caixa ficam inconsistentes.

