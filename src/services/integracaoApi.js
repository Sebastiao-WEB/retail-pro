import { temApiConfigurada, productsApi, customersApi, salesApi, purchasesApi } from "../api";
import { obterClientes, obterCompras, obterProdutos, obterVendas } from "./dadosMockados";

function normalizarLista(resposta) {
  if (Array.isArray(resposta)) return resposta;
  if (Array.isArray(resposta?.data)) return resposta.data;
  return [];
}

export async function carregarProdutosIntegrado() {
  if (!temApiConfigurada()) return obterProdutos();
  try {
    return normalizarLista(await productsApi.listar());
  } catch {
    return obterProdutos();
  }
}

export async function carregarClientesIntegrado() {
  if (!temApiConfigurada()) return obterClientes();
  try {
    return normalizarLista(await customersApi.listar());
  } catch {
    return obterClientes();
  }
}

export async function carregarHistoricoIntegrado() {
  if (!temApiConfigurada()) {
    const [vendas, compras] = await Promise.all([obterVendas(), obterCompras()]);
    return { vendas, compras };
  }
  try {
    const [vendasResp, comprasResp] = await Promise.all([salesApi.listar(), purchasesApi.listar()]);
    return {
      vendas: normalizarLista(vendasResp),
      compras: normalizarLista(comprasResp),
    };
  } catch {
    const [vendas, compras] = await Promise.all([obterVendas(), obterCompras()]);
    return { vendas, compras };
  }
}

export async function criarVendaIntegrada(payload) {
  if (!temApiConfigurada()) return null;
  try {
    return await salesApi.criar(payload);
  } catch {
    return null;
  }
}

export async function solicitarReversaoIntegrada(payload) {
  if (!temApiConfigurada()) return { ok: false };
  try {
    await salesApi.solicitarReversao(payload);
    return { ok: true };
  } catch {
    return { ok: false };
  }
}

