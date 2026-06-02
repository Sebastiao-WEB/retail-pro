/**
 * Resolve percentual de IVA e montante de IVA por linha de venda/talão.
 */

function normalizarIvaTipo(valor) {
  const texto = String(valor || "").toLowerCase();
  if (texto === "monetario" || texto === "monetário") return "monetario";
  if (texto === "isento") return "isento";
  return "percentual";
}

function normalizarIva(valor) {
  const numero = Number(valor || 0);
  if (!Number.isFinite(numero)) return 0;
  return Math.max(0, Math.min(100, numero));
}

function normalizarMonetario(valor) {
  const numero = Number(valor || 0);
  if (!Number.isFinite(numero)) return 0;
  return Math.max(0, Number(numero.toFixed(2)));
}

function obterPrecosItem(item) {
  const quantidade = Math.max(1, Number(item?.quantidade || 1));
  const precoVenda =
    Number(item?.precoVenda ?? item?.preco_venda ?? 0) ||
    Number((Number(item?.subtotal || 0) / quantidade).toFixed(2));
  const precoSemIva = Number(item?.precoSemIva ?? item?.preco_sem_iva ?? 0);
  const ivaPercentual = Number(item?.ivaPercentual ?? item?.iva_percentual ?? 0);
  const valorIvaUnitario = Number(item?.valorIvaUnitario ?? item?.valor_iva_unitario ?? 0);

  return { precoVenda, precoSemIva, ivaPercentual, valorIvaUnitario, quantidade };
}

export function snapshotPossuiIvaValido(item) {
  const { precoVenda, precoSemIva, ivaPercentual, valorIvaUnitario } = obterPrecosItem(item);
  if (ivaPercentual > 0 || valorIvaUnitario > 0) {
    return true;
  }
  return precoSemIva > 0 && precoVenda - precoSemIva > 0.004;
}

export function derivarIvaLinhaDeProduto(precoVendaComIva, produto) {
  const preco = Math.max(0, Number(precoVendaComIva || 0));
  if (!produto || preco <= 0) return null;

  const ivaTipo = normalizarIvaTipo(produto.ivaTipo);
  if (ivaTipo === "isento") {
    return {
      ivaTipo,
      precoSemIva: preco,
      ivaPercentual: 0,
      valorIvaUnitario: 0,
    };
  }

  if (ivaTipo === "monetario") {
    const valorIvaUnitario = normalizarMonetario(produto.valorIvaUnitario ?? produto.ivaValor ?? 0);
    const precoSemIva = Math.max(0, Number((preco - valorIvaUnitario).toFixed(2)));
    const ivaPercentual =
      precoSemIva > 0 ? Number(((valorIvaUnitario / precoSemIva) * 100).toFixed(2)) : 0;

    return { ivaTipo, precoSemIva, ivaPercentual, valorIvaUnitario };
  }

  const taxa = normalizarIva(produto.ivaPercentual || produto.ivaValor || 0);
  const precoSemIva = taxa > 0 ? Number((preco / (1 + taxa / 100)).toFixed(2)) : preco;
  const valorIvaUnitario = Number((preco - precoSemIva).toFixed(2));

  return {
    ivaTipo,
    precoSemIva,
    ivaPercentual: taxa,
    valorIvaUnitario,
  };
}

function resolverProdutoParaItem(item, produtos = []) {
  const lista = Array.isArray(produtos) ? produtos : [];
  const produtoId = item?.produtoId ?? item?.produto_id;
  if (produtoId) {
    const porId = lista.find((reg) => reg.id === produtoId);
    if (porId) return porId;
  }

  const nome = String(item?.nome || "")
    .trim()
    .toLowerCase();
  if (!nome) return null;

  return (
    lista.find((reg) => String(reg.nome || "").trim().toLowerCase() === nome) || null
  );
}

export function resolverIvaPercentualExibicao(item) {
  const ivaTipo = normalizarIvaTipo(item?.ivaTipo ?? item?.iva_tipo);
  const { precoVenda, precoSemIva, ivaPercentual, valorIvaUnitario } = obterPrecosItem(item);

  if (ivaPercentual > 0) {
    return Number(ivaPercentual.toFixed(2));
  }

  if (valorIvaUnitario > 0 && precoSemIva > 0) {
    return Number(((valorIvaUnitario / precoSemIva) * 100).toFixed(2));
  }

  const precoSemIvaInvalido =
    precoSemIva > 0 && precoVenda > 0 && precoVenda - precoSemIva <= 0.004;
  const precoSemIvaUtil = precoSemIvaInvalido ? 0 : precoSemIva;

  if (ivaTipo === "percentual" && ivaPercentual > 0) {
    return Number(ivaPercentual.toFixed(2));
  }

  if (precoVenda > precoSemIvaUtil && precoSemIvaUtil > 0) {
    return Number((((precoVenda - precoSemIvaUtil) / precoSemIvaUtil) * 100).toFixed(2));
  }

  return 0;
}

export function calcularIvaTotalLinha(item) {
  const quantidade = Number(item?.quantidade || 0);
  if (quantidade <= 0) return 0;

  const valorIvaUnitario = Number(item?.valorIvaUnitario ?? item?.valor_iva_unitario ?? 0);
  if (valorIvaUnitario > 0) {
    return Number((valorIvaUnitario * quantidade).toFixed(2));
  }

  const subtotal = Number(item?.subtotal || 0);
  const ivaPercentual = resolverIvaPercentualExibicao(item);
  if (ivaPercentual <= 0 || subtotal <= 0) return 0;

  return Number((subtotal - subtotal / (1 + ivaPercentual / 100)).toFixed(2));
}

export function enriquecerItemIva(item, produto = null) {
  let base = { ...item };
  const { precoVenda } = obterPrecosItem(base);

  if (!snapshotPossuiIvaValido(base) && produto) {
    const derivado = derivarIvaLinhaDeProduto(precoVenda, produto);
    if (derivado) {
      base = {
        ...base,
        precoVenda,
        ...derivado,
      };
    }
  }

  const ivaPercentual = resolverIvaPercentualExibicao(base);
  const valorIvaUnitario = Number(base?.valorIvaUnitario ?? base?.valor_iva_unitario ?? 0);
  const precoSemIvaBruto = Number(base?.precoSemIva ?? base?.preco_sem_iva ?? 0);
  const precoSemIva =
    precoSemIvaBruto > 0 && precoVenda - precoSemIvaBruto > 0.004 ? precoSemIvaBruto : 0;
  const quantidade = Number(base?.quantidade || 0);

  let valorIvaUnitarioResolvido = valorIvaUnitario;
  if (valorIvaUnitarioResolvido <= 0 && ivaPercentual > 0 && precoSemIva > 0) {
    valorIvaUnitarioResolvido = Number((precoSemIva * (ivaPercentual / 100)).toFixed(2));
  } else if (valorIvaUnitarioResolvido <= 0 && ivaPercentual > 0 && precoVenda > 0) {
    valorIvaUnitarioResolvido = Number(
      (precoVenda - precoVenda / (1 + ivaPercentual / 100)).toFixed(2)
    );
  }

  return {
    ...base,
    precoVenda,
    precoSemIva: precoSemIva || base.precoSemIva || 0,
    ivaPercentual,
    valorIvaUnitario: valorIvaUnitarioResolvido,
    ivaTotal: calcularIvaTotalLinha({
      ...base,
      ivaPercentual,
      valorIvaUnitario: valorIvaUnitarioResolvido,
      quantidade,
    }),
  };
}

export function aplicarIvaItensVenda(venda, produtos = []) {
  if (!venda || typeof venda !== "object") return venda;

  return {
    ...venda,
    itens: (venda.itens || []).map((item) =>
      enriquecerItemIva(item, resolverProdutoParaItem(item, produtos))
    ),
  };
}
