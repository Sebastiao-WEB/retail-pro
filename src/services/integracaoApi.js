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
import { obterClientes, obterCompras, obterProdutos, obterVendas } from "./dadosMockados";

function normalizarLista(resposta) {
  if (Array.isArray(resposta)) return resposta;
  if (Array.isArray(resposta?.data)) return resposta.data;
  return [];
}

export async function carregarProdutosIntegrado() {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return obterProdutos();
  return normalizarLista(await productsApi.listar());
}

export async function carregarClientesIntegrado() {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) return obterClientes();
  return normalizarLista(await customersApi.listar());
}

export async function carregarHistoricoIntegrado() {
  garantirBackendDisponivel();
  if (!temApiConfigurada()) {
    const [vendas, compras] = await Promise.all([obterVendas(), obterCompras()]);
    return { vendas, compras };
  }
  const [vendasResp, comprasResp] = await Promise.all([salesApi.listar(), purchasesApi.listar()]);
  return {
    vendas: normalizarLista(vendasResp),
    compras: normalizarLista(comprasResp),
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

