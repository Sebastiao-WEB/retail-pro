import { temApiConfigurada } from "../api/config";
import { buildReceiptLabels, generalClientLabel, isGeneralClient, t } from "./i18nHelper.js";
import { getStoredLocale, intlLocale } from "./localeStorage.js";

function formatarMT(valor) {
  const locale = intlLocale(getStoredLocale());
  return `${new Intl.NumberFormat(locale, {
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

function obterDescontoAplicado(venda) {
  const explicito = Number(
    venda?.descontoAplicado ?? venda?.desconto_aplicado ?? venda?.discount_amount ?? 0
  );
  if (Number.isFinite(explicito) && explicito > 0) return explicito;

  const subtotal = Number(venda?.subtotal ?? 0);
  const total = Number(venda?.total ?? 0);
  if (subtotal > total && total >= 0) {
    return Number((subtotal - total).toFixed(2));
  }

  return 0;
}

function obterRodapeTalao(configuracao) {
  if (temApiConfigurada()) {
    return String(configuracao?.rodapeFacturas ?? "");
  }
  return String(configuracao?.rodapeFacturas || t("pos.receipt.defaultFooter"));
}

function traduzirMetodoPagamento(metodo) {
  if (metodo === "Dinheiro") return t("pos.payment.cash");
  if (metodo === "Transferência") return t("pos.payment.transfer");
  return metodo;
}

function nomeClienteTalao(nome) {
  return isGeneralClient(nome) ? generalClientLabel() : String(nome || generalClientLabel());
}

async function sincronizarConfiguracaoEmpresaRemota(configuracao) {
  if (!temApiConfigurada()) return configuracao;
  if (typeof configuracao?.hidratarDadosEmpresaRemotos !== "function") return configuracao;
  try {
    await configuracao.hidratarDadosEmpresaRemotos();
  } catch {
    // Mantem dados locais quando a API falhar.
  }
  return configuracao;
}

export function montarPayloadTalao(venda, configuracao, opcoes = {}) {
  const itens = (venda?.itens || []).map(normalizarItem);
  const metodoPagamento = String(venda?.metodoPagamento || "");
  const dataIso = venda?.data || new Date().toISOString();
  const subtotal = Number(venda?.subtotal ?? venda?.total ?? 0);
  const total = Number(venda?.total || 0);
  const descontoAplicado = obterDescontoAplicado({ ...venda, subtotal, total });

  return {
    titulo: opcoes.segundaVia ? t("pos.receipt.secondCopyTitle") : t("pos.receipt.saleTitle"),
    segundaVia: !!opcoes.segundaVia,
    detalharIva: !!opcoes.detalharIva,
    larguraTalao: opcoes.larguraTalao || configuracao?.larguraTalao || "80mm",
    labels: buildReceiptLabels(),
    empresa: {
      nome: String(configuracao?.nomeEmpresa || "RetailPro POS"),
      nuit: String(configuracao?.nif || ""),
      endereco: String(configuracao?.endereco || ""),
      telefone: String(configuracao?.telefone || ""),
      rodape: obterRodapeTalao(configuracao),
    },
    venda: {
      referencia: String(venda?.referencia || ""),
      cliente: nomeClienteTalao(venda?.cliente),
      metodoPagamento: traduzirMetodoPagamento(metodoPagamento),
      data: dataIso,
      dataFormatada: new Date(dataIso).toLocaleString(intlLocale(getStoredLocale())),
      itens,
      subtotal,
      descontoAplicado,
      totalIva: obterTotalIvaVenda(venda),
      total,
      valorPago: Number(venda?.valorPago || 0),
      troco: Number(venda?.troco || 0),
      pagamentoDinheiro: metodoPagamento === "Dinheiro",
    },
    formatado: {
      subtotal: formatarMT(subtotal),
      desconto: formatarMT(descontoAplicado),
      totalIva: formatarMT(obterTotalIvaVenda(venda)),
      total: formatarMT(venda?.total || 0),
      valorPago: formatarMT(venda?.valorPago || 0),
      troco: formatarMT(venda?.troco || 0),
    },
  };
}

export async function enviarTalaoParaImpressao({ venda, configuracao, opcoes = {} }) {
  if (!window.api?.imprimirTalao) {
    return { ok: false, error: t("pos.printErrors.receiptApiUnavailable") };
  }
  if (!configuracao?.impressoraPadrao) {
    return { ok: false, error: t("pos.printErrors.defaultPrinterNotSet") };
  }

  const configuracaoAtualizada = await sincronizarConfiguracaoEmpresaRemota(configuracao);
  const talao = montarPayloadTalao(venda, configuracaoAtualizada, opcoes);
  const copies = Math.max(1, Number(opcoes.copies ?? configuracao.copiasImpressao ?? 1));

  return window.api.imprimirTalao({
    talao,
    deviceName: configuracao.impressoraPadrao,
    copies,
    corteAutomatico: opcoes.corteAutomatico ?? !!configuracao.corteAutomatico,
    larguraTalao: talao.larguraTalao,
    abrirGaveta: opcoes.abrirGaveta ?? !!configuracao.abrirGavetaAutomatico,
    gavetaPin: opcoes.gavetaPin ?? configuracao.gavetaPin ?? 0,
  });
}

export async function enviarAbrirGaveta({ configuracao, opcoes = {} } = {}) {
  if (!window.api?.abrirGaveta) {
    return { ok: false, error: t("pos.printErrors.receiptApiUnavailable") };
  }
  if (!configuracao?.impressoraPadrao) {
    return { ok: false, error: t("pos.printErrors.defaultPrinterNotSet") };
  }

  return window.api.abrirGaveta({
    deviceName: configuracao.impressoraPadrao,
    gavetaPin: opcoes.gavetaPin ?? configuracao.gavetaPin ?? 0,
  });
}

export { formatarMT, formatarIva, obterIvaItem, obterTotalIvaVenda, obterDescontoAplicado };
