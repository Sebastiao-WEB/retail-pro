import {
  garantirBackendDisponivel,
  modoApiAtivo,
  temApiConfigurada,
  productsApi,
  customersApi,
  salesApi,
  stockApi,
  cashApi,
} from "../api";
import { ApiError } from "../api/httpClient";
import { t } from "./i18nHelper.js";
import {
  mapearCliente,
  mapearLista,
  mapearProduto,
  mapearVenda,
} from "../api/mappers";
import { obterClientes, obterProdutos, obterVendas } from "./dadosMockados";
import { normalizarMapaDisponibilidade } from "./stockDisponibilidade";

function normalizarLista(resposta) {
  if (Array.isArray(resposta)) return resposta;
  if (Array.isArray(resposta?.data)) return resposta.data;
  return [];
}

export async function carregarProdutosIntegrado(filtros = {}) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return obterProdutos();
  return mapearLista(mapearProduto, normalizarLista(await productsApi.listar(filtros)));
}

export async function consultarStockRemotoIntegrado({ location_id, product_ids = [] }) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return {};

  const resposta = await stockApi.consultarDisponibilidade({ location_id, product_ids });
  const dados = resposta?.data;
  if (!dados || typeof dados !== "object" || Array.isArray(dados)) {
    return {};
  }

  return normalizarMapaDisponibilidade(dados);
}

export async function carregarClientesIntegrado() {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return obterClientes();
  return mapearLista(mapearCliente, normalizarLista(await customersApi.listar()));
}

export async function carregarHistoricoIntegrado(filtros = {}) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) {
    const vendas = await obterVendas();
    return { vendas };
  }

  const porPagina = Math.min(50, Math.max(1, Number(filtros.per_page || 50)));
  let pagina = 1;
  let ultimaPagina = 1;
  const vendas = [];

  do {
    const vendasResp = await salesApi.listar({ ...filtros, page: pagina, per_page: porPagina });
    vendas.push(...mapearLista(mapearVenda, normalizarLista(vendasResp)));
    ultimaPagina = Math.max(1, Number(vendasResp?.meta?.last_page || 1));
    pagina += 1;
  } while (pagina <= ultimaPagina);

  return { vendas };
}

export async function criarVendaIntegrada(payload) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return null;
  return salesApi.criar(payload);
}

export async function solicitarReversaoIntegrada(payload) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return { ok: !modoApiAtivo() };
  try {
    await salesApi.solicitarReversao(payload);
    return { ok: true };
  } catch (erro) {
    return { ok: false, erro: erro?.message || t("api.reversalFailed"), status: erro?.status };
  }
}

export async function carregarSolicitacoesReversaoIntegrado() {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return [];
  const resposta = await salesApi.listarSolicitacoesReversao();
  return mapearLista(mapearSolicitacaoReversao, normalizarLista(resposta));
}

function mapearSolicitacaoReversao(item) {
  if (!item || typeof item !== "object") return item;
  const status = String(item.status || item.estado || "").toUpperCase();
  return {
    id: item.id,
    saleId: item.saleId || item.sale_id || item.vendaId || item.venda_id,
    status,
    reason: item.reason || item.motivo || "",
    requestedAt: item.requestedAt || item.requested_at || "",
    decidedAt: item.decidedAt || item.decided_at || "",
  };
}

function normalizarObjeto(resposta) {
  if (resposta && typeof resposta === "object" && !Array.isArray(resposta?.data)) {
    return resposta?.data && typeof resposta.data === "object" ? resposta.data : resposta;
  }
  return null;
}

export async function abrirTurnoIntegrado(payload) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return { ok: false };
  try {
    const resposta = await cashApi.abrirSessao(payload);
    return { ok: true, data: normalizarObjeto(resposta) };
  } catch (erro) {
    if (erro instanceof ApiError && erro.status === 409 && erro.payload?.data?.id) {
      return { ok: true, data: erro.payload.data, reutilizada: true };
    }
    return { ok: false, erro: erro?.message || t("api.openSessionFailed") };
  }
}

export async function fecharTurnoIntegrado(id, payload) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return { ok: false };
  try {
    const resposta = await cashApi.fecharSessao(id, payload);
    return { ok: true, data: normalizarObjeto(resposta) };
  } catch (erro) {
    return { ok: false, erro: erro?.message || t("api.closeSessionFailed") };
  }
}

export async function obterSessaoAtivaIntegrada(registerId) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return { ok: false };
  try {
    const resposta = await cashApi.sessaoAtiva(registerId || undefined);
    return { ok: true, data: normalizarObjeto(resposta) };
  } catch (erro) {
    return { ok: false, erro: erro?.message || t("api.activeSessionFailed") };
  }
}

function mapearHistoricoFechoSessao(item) {
  if (!item || typeof item !== "object") return item;
  const snapshot = item.reportSnapshot || item.report_snapshot || {};

  return {
    ...snapshot,
    cashSessionId: item.id || snapshot.cashSessionId || null,
    caixa: snapshot.caixa || item.registerName || "",
    utilizador: snapshot.utilizador || snapshot.operador || "",
    fechadoEm: snapshot.fechadoEm || item.closedAt || item.closed_at || "",
    aberturaEm: snapshot.aberturaEm || item.openedAt || item.opened_at || "",
    fundoInicial: Number(snapshot.fundoInicial ?? item.openingBalance ?? 0),
    totalVendido: Number(snapshot.totalVendido ?? 0),
    totalTransacoes: Number(snapshot.totalTransacoes ?? 0),
    ticketMedio: Number(snapshot.ticketMedio ?? 0),
    vendasDinheiro: Number(snapshot.vendasDinheiro ?? 0),
    vendasTransferencia: Number(snapshot.vendasTransferencia ?? 0),
    dinheiroEsperado: Number(snapshot.dinheiroEsperado ?? 0),
    dinheiroReal: Number(snapshot.dinheiroReal ?? item.closingBalance ?? 0),
    diferenca: Number(snapshot.diferenca ?? item.differenceAmount ?? 0),
    justificativaDiferenca: String(snapshot.justificativaDiferenca || item.note || ""),
  };
}

export async function carregarHistoricoFechosIntegrado(filtros = {}) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) {
    throw new Error(t("api.closingsHistoryApiOnly"));
  }

  const resposta = await cashApi.listar({
    ...(filtros.register_id ? { register_id: filtros.register_id } : {}),
    status: filtros.status || "CLOSED",
    page: filtros.page || 1,
    per_page: filtros.per_page || 10,
  });

  const lista = Array.isArray(resposta?.data) ? resposta.data : [];

  return {
    fechos: lista.map(mapearHistoricoFechoSessao),
    meta: resposta?.meta || {},
  };
}
