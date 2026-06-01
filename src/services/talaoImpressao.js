import { temApiConfigurada } from "../api/config";
import { calcularIvaTotalLinha, enriquecerItemIva, resolverIvaPercentualExibicao } from "../utils/ivaItem.js";
import { buildReceiptLabels, generalClientLabel, isGeneralClient, t } from "./i18nHelper.js";
import { getStoredLocale, intlLocale } from "./localeStorage.js";

function normalizarGavetaPin(valor) {
  return Number(valor) === 1 ? 1 : 0;
}

export function deveAbrirGavetaNaVenda(venda, configuracao) {
  const metodo = String(venda?.metodoPagamento ?? "");
  return metodo === "Dinheiro" && !!configuracao?.abrirGavetaAutomatico;
}

export function obterOpcoesGaveta(configuracao, opcoes = {}) {
  return {
    gavetaPin: normalizarGavetaPin(opcoes.gavetaPin ?? configuracao?.gavetaPin ?? 0),
    gavetaTempoOn: opcoes.gavetaTempoOn,
    gavetaTempoOff: opcoes.gavetaTempoOff,
  };
}

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
  return calcularIvaTotalLinha(item);
}

function obterTotalIvaVenda(venda) {
  return (venda?.itens || []).reduce((acc, item) => acc + obterIvaItem(item), 0);
}

function normalizarItem(item) {
  const enriquecido = enriquecerItemIva(item);
  return {
    nome: String(enriquecido?.nome || ""),
    quantidade: Number(enriquecido?.quantidade || 0),
    ivaPercentual: resolverIvaPercentualExibicao(enriquecido),
    subtotal: Number(enriquecido?.subtotal || 0),
    ivaTotal: Number(enriquecido?.ivaTotal ?? calcularIvaTotalLinha(enriquecido)),
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
    ...obterOpcoesGaveta(configuracao, opcoes),
  });
}

export async function abrirGavetaPosVenda({ venda, configuracao, opcoes = {} } = {}) {
  if (!deveAbrirGavetaNaVenda(venda, configuracao)) {
    return { ok: true, skipped: true };
  }
  return enviarAbrirGaveta({ configuracao, opcoes: obterOpcoesGaveta(configuracao, opcoes) });
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
    ...obterOpcoesGaveta(configuracao, opcoes),
  });
}

export { formatarMT, formatarIva, obterIvaItem, obterTotalIvaVenda, obterDescontoAplicado };
