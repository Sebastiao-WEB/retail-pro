function pick(obj, ...keys) {
  if (!obj || typeof obj !== "object") return undefined;
  for (const key of keys) {
    if (obj[key] !== undefined && obj[key] !== null) return obj[key];
  }
  return undefined;
}

function normalizarIvaTipo(valor) {
  const texto = String(valor || "").toLowerCase();
  if (texto === "monetario" || texto === "monetário") return "monetario";
  if (texto === "isento") return "isento";
  return "percentual";
}

export function mapearProduto(item) {
  if (!item || typeof item !== "object") return item;
  const ivaTipo = normalizarIvaTipo(pick(item, "ivaTipo", "iva_tipo", "tax_type"));
  const ivaValor = pick(item, "ivaValor", "iva_valor", "tax_value");
  const ivaPercentual = pick(item, "ivaPercentual", "iva_percentual", "tax_rate");

  const unidadeBruta = pick(item, "unidadeVenda", "unidade_venda", "sale_unit");
  const unidadeVenda =
    String(unidadeBruta || "")
      .toUpperCase()
      .trim() === "KG"
      ? "KG"
      : "UN";

  return {
    id: pick(item, "id"),
    nome: pick(item, "nome", "name") ?? "",
    codigoBarras: pick(item, "codigoBarras", "codigo_barras", "barcode") ?? "",
    categoria: pick(item, "categoria", "category") ?? "",
    unidadeVenda,
    precoCompra: Number(pick(item, "precoCompra", "preco_compra", "purchase_price") ?? 0),
    precoVenda: Number(pick(item, "precoVenda", "preco_venda", "sale_price") ?? 0),
    ivaTipo,
    ivaValor: Number(ivaValor ?? ivaPercentual ?? 0),
    ivaPercentual: Number(ivaPercentual ?? (ivaTipo === "percentual" ? ivaValor : 0) ?? 0),
    stock: Number(pick(item, "stock", "stock_quantity") ?? 0),
  };
}

export function mapearCliente(item) {
  if (!item || typeof item !== "object") return item;
  return {
    id: pick(item, "id"),
    nome: pick(item, "nome", "name") ?? "",
    telefone: pick(item, "telefone", "phone") ?? "",
    email: pick(item, "email") ?? "",
    nuit: pick(item, "nuit") ?? "",
  };
}

function mapearItemVenda(item) {
  if (!item || typeof item !== "object") return item;
  const precoVenda = Number(pick(item, "precoVenda", "preco_venda", "unit_price") ?? 0);
  let precoSemIva = Number(pick(item, "precoSemIva", "preco_sem_iva") ?? 0);
  const ivaTipo = normalizarIvaTipo(pick(item, "ivaTipo", "iva_tipo", "tax_type"));
  let ivaPercentual = Number(pick(item, "ivaPercentual", "iva_percentual", "tax_rate") ?? 0);
  let valorIvaUnitario = Number(pick(item, "valorIvaUnitario", "valor_iva_unitario", "tax_amount") ?? 0);

  if (
    ivaPercentual <= 0 &&
    valorIvaUnitario <= 0 &&
    precoSemIva > 0 &&
    precoVenda > 0 &&
    precoVenda - precoSemIva <= 0.004
  ) {
    precoSemIva = 0;
  }

  if (
    ivaPercentual > 0 &&
    precoVenda > 0 &&
    (precoSemIva <= 0 || precoVenda - precoSemIva <= 0.004)
  ) {
    precoSemIva = Number((precoVenda / (1 + ivaPercentual / 100)).toFixed(2));
    if (valorIvaUnitario <= 0) {
      valorIvaUnitario = Number((precoVenda - precoSemIva).toFixed(2));
    }
  }

  if (ivaPercentual <= 0 && valorIvaUnitario > 0 && precoSemIva > 0) {
    ivaPercentual = Number(((valorIvaUnitario / precoSemIva) * 100).toFixed(2));
  } else if (ivaPercentual <= 0 && precoVenda > precoSemIva && precoSemIva > 0) {
    ivaPercentual = Number((((precoVenda - precoSemIva) / precoSemIva) * 100).toFixed(2));
  } else if (ivaPercentual > 0 && precoSemIva <= 0 && precoVenda > 0) {
    const precoSemIvaDerivado = Number((precoVenda / (1 + ivaPercentual / 100)).toFixed(2));
    return {
      produtoId: pick(item, "produtoId", "produto_id", "product_id"),
      nome: pick(item, "nome", "name", "product_name_snapshot") ?? "",
      quantidade: Number(pick(item, "quantidade", "quantity") ?? 0),
      precoVenda,
      precoSemIva: precoSemIvaDerivado,
      ivaTipo,
      ivaPercentual,
      valorIvaUnitario: valorIvaUnitario > 0 ? valorIvaUnitario : Number((precoVenda - precoSemIvaDerivado).toFixed(2)),
      subtotal: Number(pick(item, "subtotal", "line_total", "line_subtotal") ?? 0),
    };
  }

  return {
    produtoId: pick(item, "produtoId", "produto_id", "product_id"),
    nome: pick(item, "nome", "name", "product_name_snapshot") ?? "",
    quantidade: Number(pick(item, "quantidade", "quantity") ?? 0),
    precoVenda,
    precoSemIva,
    ivaTipo,
    ivaPercentual,
    valorIvaUnitario,
    subtotal: Number(pick(item, "subtotal", "line_total", "line_subtotal") ?? 0),
  };
}

export function mapearVenda(item) {
  if (!item || typeof item !== "object") return item;
  const itens = Array.isArray(item.itens)
    ? item.itens.map(mapearItemVenda)
    : Array.isArray(item.items)
      ? item.items.map(mapearItemVenda)
      : [];

  return {
    id: pick(item, "id"),
    referencia: pick(item, "referencia", "referencia_venda", "sale_number") ?? "",
    cliente: pick(item, "cliente", "customer") ?? "",
    caixa: pick(item, "caixa", "register") ?? "",
    operador: pick(item, "operador", "operator") ?? "",
    metodoPagamento: pick(item, "metodoPagamento", "metodo_pagamento", "payment_method") ?? "",
    estado: pick(item, "estado", "status") ?? "Concluida",
    subtotal: Number(pick(item, "subtotal", "subtotal_amount") ?? 0),
    descontoAplicado: Number(pick(item, "descontoAplicado", "desconto_aplicado", "discount_amount") ?? 0),
    total: Number(pick(item, "total", "total_amount") ?? 0),
    valorPago: Number(pick(item, "valorPago", "valor_pago") ?? 0),
    troco: Number(pick(item, "troco", "change_amount") ?? 0),
    data: pick(item, "data", "created_at") ?? "",
    createdAt: pick(item, "createdAt", "created_at") ?? "",
    userId: pick(item, "userId", "user_id"),
    cashSessionId: pick(item, "cashSessionId", "cash_session_id"),
    registerId: pick(item, "registerId", "register_id"),
    sourceLocationId: pick(item, "sourceLocationId", "source_location_id"),
    itens,
  };
}

export function mapearCompra(item) {
  if (!item || typeof item !== "object") return item;
  const itens = Array.isArray(item.itens)
    ? item.itens.map((linha) => ({
        nome: pick(linha, "nome", "name", "product_name_snapshot") ?? "",
        quantidade: Number(pick(linha, "quantidade", "quantity") ?? 0),
        custoUnitario: Number(pick(linha, "custoUnitario", "custo_unitario", "unit_cost") ?? 0),
      }))
    : Array.isArray(item.items)
      ? item.items.map((linha) => ({
          nome: pick(linha, "nome", "name", "product_name_snapshot") ?? "",
          quantidade: Number(pick(linha, "quantidade", "quantity") ?? 0),
          custoUnitario: Number(pick(linha, "custoUnitario", "custo_unitario", "unit_cost") ?? 0),
        }))
      : [];

  return {
    id: pick(item, "id"),
    fornecedor: pick(item, "fornecedor", "supplier") ?? "",
    total: Number(pick(item, "total", "total_amount") ?? 0),
    data: pick(item, "data", "created_at") ?? "",
    itens,
  };
}

export function mapearSessaoCaixa(item) {
  if (!item || typeof item !== "object") return item;
  return {
    id: pick(item, "id"),
    registerId: pick(item, "registerId", "register_id"),
    status: pick(item, "status") ?? "",
    openingBalance: Number(pick(item, "openingBalance", "opening_balance") ?? 0),
    closingBalance: Number(pick(item, "closingBalance", "closing_balance") ?? 0),
    differenceAmount: Number(pick(item, "differenceAmount", "difference_amount") ?? 0),
    openedAt: pick(item, "openedAt", "opened_at") ?? "",
    closedAt: pick(item, "closedAt", "closed_at") ?? "",
  };
}

export function mapearLista(mapper, lista) {
  if (!Array.isArray(lista)) return [];
  return lista.map(mapper);
}
