import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { useVendaStore } from "../src/store/useVendaStore";

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

  it("impede criar duas solicitações pendentes para a mesma venda", () => {
    const vendaStore = useVendaStore();
    vendaStore.vendas = [{ id: 100, cliente: "Cliente", total: 100, data: new Date().toISOString(), metodoPagamento: "Dinheiro" }];

    const primeira = vendaStore.solicitarReversao({
      vendaId: 100,
      referencia: "VD-TESTE-1",
      solicitadoPor: "Operador",
      motivo: "",
    });
    const segunda = vendaStore.solicitarReversao({
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

