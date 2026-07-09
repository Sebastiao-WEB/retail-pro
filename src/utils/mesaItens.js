import { normalizarQuantidadeVenda } from "./produtoQuantidade";

export function calcularSubtotalMesa(quantidade, precoVenda) {
  return Number((quantidade * precoVenda).toFixed(2));
}

export function chaveItemMesa(item) {
  if (!item) return "";
  return String(item.produtoId || item.id || "");
}

export function deduplicarItensPedido(itens) {
  const mapa = new Map();

  for (const item of itens || []) {
    const id = item?.id;
    if (!id) {
      mapa.set(`__sem_id_${mapa.size}`, { ...item });
      continue;
    }

    const existente = mapa.get(id);
    if (!existente) {
      mapa.set(id, { ...item });
      continue;
    }

    const quantidade = Number((Number(existente.quantidade || 0) + Number(item.quantidade || 0)).toFixed(3));
    mapa.set(id, {
      ...existente,
      quantidade,
      subtotal: calcularSubtotalMesa(quantidade, existente.precoVenda),
    });
  }

  return [...mapa.values()];
}

export function consolidarItensPedido(itens) {
  const semChave = [];
  const porProduto = new Map();

  for (const item of deduplicarItensPedido(itens)) {
    const chave = chaveItemMesa(item);
    if (!chave) {
      semChave.push(item);
      continue;
    }

    const existente = porProduto.get(chave);
    if (!existente) {
      porProduto.set(chave, { ...item });
      continue;
    }

    const quantidade = Number((Number(existente.quantidade || 0) + Number(item.quantidade || 0)).toFixed(3));
    porProduto.set(chave, {
      ...existente,
      quantidade,
      subtotal: calcularSubtotalMesa(quantidade, existente.precoVenda),
    });
  }

  return [...porProduto.values(), ...semChave];
}

export function agregarQuantidadesTransferencia(entradas, itensOrigem = []) {
  const itens = consolidarItensPedido(itensOrigem);
  const mapa = new Map();

  for (const entrada of entradas || []) {
    const quantidade = Number(entrada.quantidade || 0);
    if (quantidade <= 0) continue;

    let chave = entrada.produtoId ? String(entrada.produtoId) : "";
    if (!chave) {
      const itemId = entrada.itemId || entrada.id;
      const item = itens.find((linha) => linha.id === itemId);
      chave = chaveItemMesa(item) || String(itemId || "");
    }

    if (!chave) continue;
    mapa.set(chave, Number(((mapa.get(chave) || 0) + quantidade).toFixed(3)));
  }

  return [...mapa.entries()].map(([chave, quantidade]) => {
    const item = itens.find((linha) => chaveItemMesa(linha) === chave);
    return {
      chave,
      produtoId: item?.produtoId || null,
      itemId: item?.id || null,
      quantidade,
    };
  });
}
