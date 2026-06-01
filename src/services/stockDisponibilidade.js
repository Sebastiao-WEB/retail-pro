export function normalizarEntradaStock(valor) {
  if (valor && typeof valor === "object" && !Array.isArray(valor)) {
    return {
      quantity: Number(valor.quantity ?? 0),
      version: valor.version ? String(valor.version) : null,
    };
  }

  return {
    quantity: Number(valor ?? 0),
    version: null,
  };
}

export function normalizarMapaDisponibilidade(dados) {
  if (!dados || typeof dados !== "object" || Array.isArray(dados)) {
    return {};
  }

  return Object.fromEntries(
    Object.entries(dados).map(([productId, valor]) => [productId, normalizarEntradaStock(valor)])
  );
}

export function construirStockVersionsParaVenda(itens = [], produtos = []) {
  const indice = new Map(produtos.map((produto) => [produto.id, produto]));
  const versoes = {};

  for (const item of itens) {
    const produtoId = item?.produtoId;
    if (!produtoId || versoes[produtoId]) continue;

    const produto = indice.get(produtoId);
    if (produto?.stockVersion) {
      versoes[produtoId] = produto.stockVersion;
    }
  }

  return versoes;
}
