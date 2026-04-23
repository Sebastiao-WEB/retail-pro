# Guia de Desenvolvimento da API (Laravel + Livewire)

Este documento explica como estruturar e implementar a API do RetailPro quando a versao web for feita com Laravel + Livewire, mantendo compatibilidade com o app desktop (Electron/Vue).

---

## Objetivo

Ter **um unico backend Laravel** servindo:

- Interface Web (Livewire) para administracao/gestao.
- API REST para POS desktop e futuras integracoes.

Resultado esperado:

- Regras de negocio centralizadas.
- Dados consistentes entre web e desktop.
- Evolucao mais rapida sem duplicacao de logica.

---

## Arquitetura recomendada

Separar claramente camadas web e API:

- `routes/web.php` -> telas Livewire (sessao web/cookies).
- `routes/api.php` -> endpoints REST versionados.
- `app/Http/Controllers/Web/*` -> controllers/telas web.
- `app/Http/Controllers/Api/V1/*` -> controllers da API.
- `app/Services/*` -> regras de negocio compartilhadas.
- `app/Repositories/*` (opcional) -> acesso a dados.
- `app/Http/Resources/*` -> padronizacao de respostas JSON.
- `app/Policies/*` e `app/Permissions/*` -> autorizacao por perfil.

> Regra pratica: controller fino, regra no service.

---

## Autenticacao recomendada (API)

Para compatibilidade com o desktop atual, usar fluxo com:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/logout`

Resposta de login esperada:

```json
{
  "access_token": "token",
  "refresh_token": "refresh-token",
  "token_type": "Bearer",
  "expires_in": 3600,
  "user": {
    "id": "d290f1ee-6c54-4b01-90e6-d701748f0851",
    "name": "Operador 01",
    "role": "CASHIER"
  }
}
```

Padrao definido para este projeto: **JWT com access + refresh token**.

### Implementacao recomendada no Laravel

- Pacote: `php-open-source-saver/jwt-auth` (fork ativo do tymon/jwt-auth).
- Guard dedicado para API em `config/auth.php` (ex: `guard => api`).
- Middleware JWT nas rotas protegidas de `routes/api.php`.
- Endpoint de refresh devolvendo novo `access_token` e, se aplicavel, novo `refresh_token`.

Exemplo de instalacao:

```bash
composer require php-open-source-saver/jwt-auth
php artisan vendor:publish --provider="PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret
```

> O frontend desktop ja esta preparado para enviar `Authorization: Bearer <token>` e renovar sessao automaticamente em `401`.

---

## Identificadores UUID

Padrao definido para o projeto:

- PK `id` em todas as tabelas: UUID.
- FK `*_id`: UUID.
- Endpoints que recebem `{id}` em rota devem aceitar UUID.
- Responses da API devem devolver UUID em `id`, `register_id`, `sale_id`, etc.

Exemplo de rota com UUID:

- `PUT /api/v1/products/d290f1ee-6c54-4b01-90e6-d701748f0851`

---

## Versionamento e padrao de rotas

Usar prefixo de versao:

- `/api/v1/...`

Exemplo de grupos:

- `auth/*`
- `products/*`
- `customers/*`
- `sales/*`
- `cash-sessions/*`
- `sale-reversal-requests/*`

Isso evita quebra quando surgir `/api/v2`.

---

## Mapeamento minimo de endpoints (Desktop POS)

Baseado no que o frontend ja consome:

### Auth
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/logout`

### Produtos
- `GET /api/v1/products`
- `POST /api/v1/products`
- `PUT /api/v1/products/{id}`

### Clientes
- `GET /api/v1/customers`
- `POST /api/v1/customers`
- `PUT /api/v1/customers/{id}`

### Vendas
- `GET /api/v1/sales`
- `POST /api/v1/sales`
- `POST /api/v1/sale-reversal-requests`

### Caixa
- `POST /api/v1/cash-sessions/open`
- `POST /api/v1/cash-sessions/{id}/close`
- `GET /api/v1/cash-sessions/{id}/movements`

---

## Fluxo de negocio critico

### 1) Abertura de caixa

- Validar se operador ja tem turno aberto.
- Gravar fundo inicial, horario e caixa.
- Retornar sessao de caixa ativa.

### 2) Venda

- Receber itens com snapshot de nome/preco/iva.
- Validar stock em transacao.
- Calcular subtotal, desconto, total, total_iva.
- Gravar pagamentos.
- Atualizar stock e movimentos.
- Retornar referencia publica da venda (ex: `public_id`).

### 3) Solicitar reversao

- Permitir apenas para vendas do turno/operador autorizado.
- Bloquear duplicidade de solicitacao pendente.
- Gravar motivo e status inicial `PENDING`.

### 4) Fecho de caixa

- Calcular esperado por metodo de pagamento.
- Receber valor real contado.
- Persistir diferenca e justificativa.
- Fechar turno com trilha de auditoria.

---

## Organizacao de codigo (exemplo)

Estrutura sugerida:

```text
app/
  Http/
    Controllers/
      Api/
        V1/
          AuthController.php
          ProductController.php
          CustomerController.php
          SaleController.php
          CashSessionController.php
          SaleReversalRequestController.php
    Resources/
      SaleResource.php
      ProductResource.php
  Services/
    AuthService.php
    SaleService.php
    CashSessionService.php
  Models/
    Sale.php
    SaleItem.php
    CashSession.php
```

---

## Padrão de resposta e erro

Sucesso:

```json
{
  "data": {},
  "meta": {}
}
```

Erro:

```json
{
  "message": "Descricao do erro",
  "errors": {
    "campo": ["motivo"]
  }
}
```

Codigos recomendados:

- `200` consulta/sucesso
- `201` criado
- `204` sem conteudo
- `401` nao autenticado
- `403` sem permissao
- `404` nao encontrado
- `409` conflito de regra (ex: reversao duplicada)
- `422` validacao

---

## Regras de seguranca

- Token curto para `access_token` (ex: 15-60 min).
- `refresh_token` com expiracao maior e rotacao.
- Revogar tokens no logout.
- Rate limit em login e refresh.
- Logar tentativas de acesso e eventos sensiveis.
- Usar Policies/Gates por perfil (`MANAGER`, `CASHIER`).

---

## Banco de dados e transacoes

Aplicar a modelagem de `MODELAGEM_BD_V2.md` e garantir:

- Chaves estrangeiras e indices nos campos de busca.
- Uso de `DB::transaction()` em venda/fecho/reversao.
- Snapshot dos itens vendidos (nome, preco, iva) para historico fiel.
- Movimento de stock com trilha (`stock_movements`).

---

## Convivencia Livewire + API sem conflito

- Livewire usa rotas `web` e middleware de sessao.
- POS desktop usa rotas `api` e middleware de token.
- Ambos chamam os mesmos Services internos.

Beneficio: a regra muda uma vez e reflete nos dois canais.

---

## Testes obrigatorios (backend)

Criar testes de feature para:

- Login, refresh e logout.
- Abertura/fecho de caixa.
- Criacao de venda com desconto e IVA.
- Bloqueio de venda sem stock.
- Solicitacao de reversao (incluindo duplicidade).
- Permissoes por perfil.

Criar testes de unidade para Services de calculo:

- total, iva, desconto, diferenca de caixa.

---

## Plano de implementacao em fases

### Fase 1 - Base tecnica
- Configurar auth JWT/refresh.
- Criar estrutura `Api/V1`.
- Implementar Resources e tratamento global de erros.

### Fase 2 - Fluxo operacional
- Endpoints de caixa (abrir/fechar).
- Endpoints de vendas e itens.
- Endpoints de historico e reversao.

### Fase 3 - Governanca
- Policies/permissoes.
- Auditoria e logs de operacao.
- Testes automatizados e CI.

### Fase 4 - Integracao final
- Apontar desktop para `VITE_API_MODE=api`.
- Validar fluxo completo com dados reais.
- Remover dependencias de mock gradualmente.

---

## Checklist rapido

- [ ] Rotas API versionadas em `/api/v1`
- [ ] Login/refresh/logout funcionando
- [ ] Venda gravando itens + IVA + pagamentos
- [ ] Abertura e fecho de caixa com diferenca
- [ ] Reversao com aprovacao gerencial
- [ ] Policies por perfil
- [ ] Testes de feature e unidade
- [ ] Documentacao de endpoints (Postman/Swagger)

---

## Referencias no projeto

- `INTEGRACAO_API_LARAVEL.md` -> integracao atual do frontend desktop
- `MODELAGEM_BD_V2.md` -> modelagem de banco recomendada
- `src/api/httpClient.js` -> contrato esperado de autenticacao/token no cliente

