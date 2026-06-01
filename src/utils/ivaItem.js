/**
 * Resolve percentual de IVA e montante de IVA por linha de venda/talão.
 */
export function resolverIvaPercentualExibicao(item) {
  const percentualDirecto = Number(item?.ivaPercentual ?? item?.iva_percentual ?? 0);
  if (percentualDirecto > 0) {
    return Number(percentualDirecto.toFixed(2));
  }

  const valorIvaUnitario = Number(item?.valorIvaUnitario ?? item?.valor_iva_unitario ?? 0);
  const precoSemIva = Number(item?.precoSemIva ?? item?.preco_sem_iva ?? 0);
  if (valorIvaUnitario > 0 && precoSemIva > 0) {
    return Number(((valorIvaUnitario / precoSemIva) * 100).toFixed(2));
  }

  const precoComIva = Number(item?.precoVenda ?? item?.preco_venda ?? 0);
  if (precoComIva > precoSemIva && precoSemIva > 0) {
    return Number((((precoComIva - precoSemIva) / precoSemIva) * 100).toFixed(2));
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

export function enriquecerItemIva(item) {
  const ivaPercentual = resolverIvaPercentualExibicao(item);
  const valorIvaUnitario = Number(item?.valorIvaUnitario ?? item?.valor_iva_unitario ?? 0);
  const precoSemIva = Number(item?.precoSemIva ?? item?.preco_sem_iva ?? 0);
  const quantidade = Number(item?.quantidade || 0);

  let valorIvaUnitarioResolvido = valorIvaUnitario;
  if (valorIvaUnitarioResolvido <= 0 && ivaPercentual > 0 && precoSemIva > 0) {
    valorIvaUnitarioResolvido = Number((precoSemIva * (ivaPercentual / 100)).toFixed(2));
  }

  return {
    ...item,
    precoSemIva,
    ivaPercentual,
    valorIvaUnitario: valorIvaUnitarioResolvido,
    ivaTotal: calcularIvaTotalLinha({
      ...item,
      ivaPercentual,
      valorIvaUnitario: valorIvaUnitarioResolvido,
      quantidade,
    }),
  };
}
