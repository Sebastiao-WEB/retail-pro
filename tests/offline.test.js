import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "../src/api/httpClient";
import {
  isErroConectividadeMensagem,
  isErroNegocioVenda,
  isErroRedeOuIndisponivel,
} from "../src/services/offline/networkError";
import {
  salvarCatalogoOffline,
  carregarCatalogoOffline,
  temCatalogoOffline,
} from "../src/services/offline/catalogCache";
import {
  adicionarFilaPendente,
  contarFilaPendente,
  enfileirarVendaPendente,
  listarFilaPendente,
  remapearCashSessionNaFila,
  removerFilaPendente,
} from "../src/services/offline/pendingQueue";

describe("offline/networkError", () => {
  it("identifica erro de rede por ApiError sem status", () => {
    expect(isErroRedeOuIndisponivel(new ApiError("timeout", 0))).toBe(true);
    expect(isErroRedeOuIndisponivel(new ApiError("stock", 422))).toBe(false);
  });

  it("identifica erro de negócio de venda", () => {
    expect(isErroNegocioVenda(new ApiError("stock", 422))).toBe(true);
  });

  it("identifica mensagens de conectividade", () => {
    expect(isErroConectividadeMensagem("Falha de comunicação com o servidor")).toBe(true);
    expect(isErroConectividadeMensagem("Stock insuficiente")).toBe(false);
  });
});

describe("offline/catalogCache", () => {
  beforeEach(() => {
    const memoria = new Map();
    vi.stubGlobal("localStorage", {
      getItem: (chave) => memoria.get(chave) ?? null,
      setItem: (chave, valor) => memoria.set(chave, String(valor)),
      removeItem: (chave) => memoria.delete(chave),
    });
  });

  it("guarda e recupera catálogo por localização", () => {
    const filtros = { source_location_id: "loc-1" };
    salvarCatalogoOffline(filtros, [{ id: "p1", nome: "Pão", stock: 3 }]);
    expect(temCatalogoOffline(filtros)).toBe(true);
    expect(carregarCatalogoOffline(filtros).produtos).toHaveLength(1);
  });
});

describe("offline/pendingQueue", () => {
  beforeEach(() => {
    const memoria = new Map();
    vi.stubGlobal("localStorage", {
      getItem: (chave) => memoria.get(chave) ?? null,
      setItem: (chave, valor) => memoria.set(chave, String(valor)),
      removeItem: (chave) => memoria.delete(chave),
    });
  });

  it("enfileira vendas e remapeia sessão de caixa", () => {
    enfileirarVendaPendente({
      id: "venda-1",
      cash_session_id: "sessao-local",
      itens: [],
    });
    expect(contarFilaPendente()).toBe(1);

    remapearCashSessionNaFila("sessao-local", "sessao-servidor");
    expect(listarFilaPendente()[0].payload.cash_session_id).toBe("sessao-servidor");

    removerFilaPendente(listarFilaPendente()[0].id);
    expect(contarFilaPendente()).toBe(0);
  });

  it("ordena abertura de caixa antes de vendas", () => {
    adicionarFilaPendente({ tipo: "sale", payload: { id: "v1" } });
    adicionarFilaPendente({ tipo: "cash_open", payload: { register_id: "r1" }, criadoEm: "2026-01-01T08:00:00Z" });
    expect(listarFilaPendente()[0].tipo).toBe("cash_open");
  });
});
