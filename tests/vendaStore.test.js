import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { useVendaStore } from "../src/store/useVendaStore";

vi.mock("../src/services/integracaoApi", async () => {
  const atual = await vi.importActual("../src/services/integracaoApi");
  return {
    ...atual,
    solicitarReversaoIntegrada: vi.fn(async () => ({ ok: true })),
  };
});

describe("useVendaStore", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    const memoria = new Map();
    vi.stubGlobal("localStorage", {
      getItem: (chave) => memoria.get(chave) ?? null,
      setItem: (chave, valor) => memoria.set(chave, String(valor)),
      removeItem: (chave) => memoria.delete(chave),
    });
  });

  it("impede criar duas solicitações pendentes para a mesma venda", async () => {
    const vendaStore = useVendaStore();
    vendaStore.vendas = [{ id: 100, cliente: "Cliente", total: 100, data: new Date().toISOString(), metodoPagamento: "Dinheiro" }];

    const primeira = await vendaStore.solicitarReversao({
      vendaId: 100,
      referencia: "VD-TESTE-1",
      solicitadoPor: "Operador",
      motivo: "",
    });
    const segunda = await vendaStore.solicitarReversao({
      vendaId: 100,
      referencia: "VD-TESTE-1",
      solicitadoPor: "Operador",
      motivo: "",
    });

    expect(primeira.ok).toBe(true);
    expect(segunda.ok).toBe(false);
    expect(vendaStore.solicitacoesPendentes).toHaveLength(1);
  });
});

