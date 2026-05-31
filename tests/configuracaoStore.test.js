import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { useConfiguracaoStore } from "../src/store/useConfiguracaoStore";

vi.mock("../src/api/config", () => ({
  temApiConfigurada: vi.fn(() => false),
}));

vi.mock("../src/api/modules/companyApi", () => ({
  companyApi: {
    obterPerfil: vi.fn(),
    atualizarPerfil: vi.fn(),
  },
}));

describe("useConfiguracaoStore", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    const memoria = new Map();
    vi.stubGlobal("localStorage", {
      getItem: (chave) => memoria.get(chave) ?? null,
      setItem: (chave, valor) => memoria.set(chave, String(valor)),
      removeItem: (chave) => memoria.delete(chave),
    });
  });

  it("salva apenas campos serializaveis no localStorage", () => {
    const store = useConfiguracaoStore();
    store.definirImpressoraPadrao("EPSON TM-T20");
    store.definirLarguraTalao("58mm");

    expect(() => store.salvar()).not.toThrow();

    const guardado = JSON.parse(localStorage.getItem("retailpro:configuracoes"));
    expect(guardado.impressoraPadrao).toBe("EPSON TM-T20");
    expect(guardado.larguraTalao).toBe("58mm");
    expect(guardado).not.toHaveProperty("carregado");
    expect(guardado).not.toHaveProperty("$id");
  });
});
