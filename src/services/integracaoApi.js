import {
  garantirBackendDisponivel,
  modoApiAtivo,
  temApiConfigurada,
  productsApi,
  customersApi,
  salesApi,
  purchasesApi,
  cashApi,
} from "../api";
import { ApiError } from "../api/httpClient";
import {
  mapearCliente,
  mapearCompra,
  mapearLista,
  mapearProduto,
  mapearVenda,
} from "../api/mappers";
import { obterClientes, obterCompras, obterProdutos, obterVendas } from "./dadosMockados";

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

export async function carregarClientesIntegrado() {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return obterClientes();
  return mapearLista(mapearCliente, normalizarLista(await customersApi.listar()));
}

export async function carregarHistoricoIntegrado(filtros = {}) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) {
    const [vendas, compras] = await Promise.all([obterVendas(), obterCompras()]);
    return { vendas, compras };
  }
  const [vendasResp, comprasResp] = await Promise.all([
    salesApi.listar(filtros),
    purchasesApi.listar(filtros),
  ]);
  return {
    vendas: mapearLista(mapearVenda, normalizarLista(vendasResp)),
    compras: mapearLista(mapearCompra, normalizarLista(comprasResp)),
  };
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
    return { ok: false, erro: erro?.message || "Falha ao solicitar reversão na API." };
  }
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
    return { ok: false, erro: erro?.message || "Falha ao abrir sessão de caixa na API." };
  }
}

export async function fecharTurnoIntegrado(id, payload) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return { ok: false };
  try {
    const resposta = await cashApi.fecharSessao(id, payload);
    return { ok: true, data: normalizarObjeto(resposta) };
  } catch (erro) {
    return { ok: false, erro: erro?.message || "Falha ao fechar sessão de caixa na API." };
  }
}

export async function obterSessaoAtivaIntegrada(registerId) {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return { ok: false };
  try {
    const resposta = await cashApi.sessaoAtiva(registerId || undefined);
    return { ok: true, data: normalizarObjeto(resposta) };
  } catch (erro) {
    return { ok: false, erro: erro?.message || "Falha ao consultar sessão ativa na API." };
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
    throw new Error("Histórico de fechos disponível apenas com backend API.");
  }

  const resposta = await cashApi.listar({
    register_id: filtros.register_id,
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
