function formatarMT(valor) {
  return `${new Intl.NumberFormat("pt-MZ", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(valor || 0))} MT`;
}

function formatarIva(valor) {
  const numero = Number(valor || 0);
  return `${Number.isFinite(numero) ? numero : 0}%`;
}

function obterIvaItem(item) {
  const subtotal = Number(item?.subtotal || 0);
  const ivaPercentual = Number(item?.ivaPercentual || 0);
  if (Number.isFinite(item?.valorIvaUnitario)) {
    return Number(item.valorIvaUnitario || 0) * Number(item?.quantidade || 0);
  }
  if (ivaPercentual <= 0 || subtotal <= 0) return 0;
  return subtotal - subtotal / (1 + ivaPercentual / 100);
}

function obterTotalIvaVenda(venda) {
  return (venda?.itens || []).reduce((acc, item) => acc + obterIvaItem(item), 0);
}

function normalizarItem(item) {
  return {
    nome: String(item?.nome || ""),
    quantidade: Number(item?.quantidade || 0),
    ivaPercentual: Number(item?.ivaPercentual || 0),
    subtotal: Number(item?.subtotal || 0),
    ivaTotal: obterIvaItem(item),
  };
}

export function montarPayloadTalao(venda, configuracao, opcoes = {}) {
  const itens = (venda?.itens || []).map(normalizarItem);
  const metodoPagamento = String(venda?.metodoPagamento || "");
  const dataIso = venda?.data || new Date().toISOString();

  return {
    titulo: opcoes.segundaVia ? "2a via do Talao" : "Talao de Venda",
    segundaVia: !!opcoes.segundaVia,
    detalharIva: !!opcoes.detalharIva,
    larguraTalao: opcoes.larguraTalao || configuracao?.larguraTalao || "80mm",
    empresa: {
      nome: String(configuracao?.nomeEmpresa || "RetailPro POS"),
      nuit: String(configuracao?.nif || ""),
      endereco: String(configuracao?.endereco || ""),
      telefone: String(configuracao?.telefone || ""),
      rodape: String(configuracao?.rodapeFacturas || "Obrigado pela preferencia."),
    },
    venda: {
      referencia: String(venda?.referencia || ""),
      cliente: String(venda?.cliente || "Cliente Geral"),
      metodoPagamento,
      data: dataIso,
      dataFormatada: new Date(dataIso).toLocaleString("pt-MZ"),
      itens,
      subtotal: Number(venda?.subtotal ?? venda?.total ?? 0),
      descontoAplicado: Number(venda?.descontoAplicado || 0),
      totalIva: obterTotalIvaVenda(venda),
      total: Number(venda?.total || 0),
      valorPago: Number(venda?.valorPago || 0),
      troco: Number(venda?.troco || 0),
      pagamentoDinheiro: metodoPagamento === "Dinheiro",
    },
    formatado: {
      subtotal: formatarMT(venda?.subtotal ?? venda?.total ?? 0),
      desconto: formatarMT(venda?.descontoAplicado || 0),
      totalIva: formatarMT(obterTotalIvaVenda(venda)),
      total: formatarMT(venda?.total || 0),
      valorPago: formatarMT(venda?.valorPago || 0),
      troco: formatarMT(venda?.troco || 0),
    },
  };
}

export async function enviarTalaoParaImpressao({ venda, configuracao, opcoes = {} }) {
  if (!window.api?.imprimirTalao) {
    return { ok: false, error: "API de impressao indisponivel no desktop." };
  }
  if (!configuracao?.impressoraPadrao) {
    return { ok: false, error: "Impressora padrao nao configurada." };
  }

  const talao = montarPayloadTalao(venda, configuracao, opcoes);
  const copies = Math.max(1, Number(opcoes.copies ?? configuracao.copiasImpressao ?? 1));

  return window.api.imprimirTalao({
    talao,
    deviceName: configuracao.impressoraPadrao,
    copies,
    corteAutomatico: opcoes.corteAutomatico ?? !!configuracao.corteAutomatico,
    larguraTalao: talao.larguraTalao,
  });
}

export { formatarMT, formatarIva, obterIvaItem, obterTotalIvaVenda };
