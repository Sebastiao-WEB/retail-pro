import { temApiConfigurada } from "../api/config";
import { companyApi } from "../api/modules/companyApi";
import { buildClosingReportLabels, t } from "./i18nHelper.js";
import { getStoredLocale, intlLocale } from "./localeStorage.js";

function formatarDataHora(valor) {
  if (!valor) return "";
  return new Date(valor).toLocaleString(intlLocale(getStoredLocale()));
}

function obterRodapeTalao(configuracao) {
  if (temApiConfigurada()) {
    return String(configuracao?.rodapeFacturas ?? "");
  }
  return String(configuracao?.rodapeFacturas || t("pos.receipt.defaultFooter"));
}

async function sincronizarConfiguracaoEmpresaRemota(configuracao) {
  if (!temApiConfigurada()) return configuracao;
  if (typeof configuracao?.hidratarDadosEmpresaRemotos === "function") {
    try {
      await configuracao.hidratarDadosEmpresaRemotos();
      return configuracao;
    } catch {
      return configuracao;
    }
  }
  try {
    const resposta = await companyApi.obterPerfil();
    if (resposta?.data) {
      return { ...configuracao, ...resposta.data };
    }
  } catch {
    // Mantem dados locais quando a API falhar.
  }
  return configuracao;
}

export function montarPayloadRelatorioFecho(dados, configuracao, opcoes = {}) {
  const fechadoEm = dados.fechadoEm || new Date().toISOString();
  const titulo = opcoes.segundaVia
    ? t("pos.closingReport.secondCopyTitle")
    : t("pos.closingReport.title");

  return {
    titulo,
    segundaVia: !!opcoes.segundaVia,
    larguraTalao: opcoes.larguraTalao || configuracao?.larguraTalao || "80mm",
    labels: buildClosingReportLabels(),
    empresa: {
      nome: String(configuracao?.nomeEmpresa || "RetailPro POS"),
      nuit: String(configuracao?.nif || ""),
      endereco: String(configuracao?.endereco || ""),
      telefone: String(configuracao?.telefone || ""),
      rodape: obterRodapeTalao(configuracao),
    },
    relatorio: {
      caixa: String(dados.caixa || ""),
      operador: String(dados.utilizador || dados.operador || ""),
      aberturaFormatada: formatarDataHora(dados.aberturaEm),
      fechoFormatada: formatarDataHora(fechadoEm),
      fundoInicial: Number(dados.fundoInicial || 0),
      totalVendido: Number(dados.totalVendido || 0),
      totalTransacoes: Number(dados.totalTransacoes || 0),
      ticketMedio: Number(dados.ticketMedio || 0),
      vendasDinheiro: Number(dados.vendasDinheiro || 0),
      vendasTransferencia: Number(dados.vendasTransferencia || 0),
      dinheiroEsperado: Number(dados.dinheiroEsperado || 0),
      dinheiroReal: Number(dados.dinheiroReal || 0),
      diferenca: Number(dados.diferenca || 0),
      justificativaDiferenca: String(dados.justificativaDiferenca || "").trim(),
    },
  };
}

export async function enviarRelatorioFechoParaImpressao({ relatorio, configuracao, opcoes = {} }) {
  if (!window.api?.imprimirRelatorioFecho) {
    return { ok: false, error: t("pos.printErrors.closingReportApiUnavailable") };
  }
  if (!configuracao?.impressoraPadrao) {
    return { ok: false, error: t("pos.printErrors.defaultPrinterNotSet") };
  }

  const configuracaoAtualizada = await sincronizarConfiguracaoEmpresaRemota(configuracao);
  const payload = montarPayloadRelatorioFecho(relatorio, configuracaoAtualizada, opcoes);
  const copies = Math.max(1, Number(opcoes.copies ?? configuracao.copiasImpressao ?? 1));

  return window.api.imprimirRelatorioFecho({
    relatorio: payload,
    deviceName: configuracao.impressoraPadrao,
    copies,
    corteAutomatico: opcoes.corteAutomatico ?? !!configuracao.corteAutomatico,
    larguraTalao: payload.larguraTalao,
  });
}
