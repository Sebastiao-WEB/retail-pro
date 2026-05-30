import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { useProdutoStore } from "../src/store/useProdutoStore";

vi.mock("../src/api", () => ({
  temApiConfigurada: vi.fn(() => true),
}));

vi.mock("../src/services/integracaoApi", () => ({
  carregarProdutosIntegrado: vi.fn(async (filtros = {}) => {
    const termo = String(filtros.search || "").toLowerCase();
    const produtos = [
      { id: "p1", nome: "Leite UHT", codigoBarras: "111", precoVenda: 50, stock: 10 },
      { id: "p2", nome: "Pao", codigoBarras: "222", precoVenda: 10, stock: 5 },
    ];
    if (!termo) return produtos;
    return produtos.filter((produto) => `${produto.nome} ${produto.codigoBarras}`.toLowerCase().includes(termo));
  }),
}));

describe("useProdutoStore busca remota", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it("consulta backend a cada pesquisa e limita resultados exibidos", async () => {
    const produtoStore = useProdutoStore();

    const resultados = await produtoStore.buscarProdutos({ search: "Leite", source_location_id: "loc-1" });

    expect(resultados).toHaveLength(1);
    expect(resultados[0].nome).toBe("Leite UHT");
    expect(produtoStore.resultadosPesquisa).toHaveLength(1);
    expect(produtoStore.produtos.find((item) => item.id === "p1")?.nome).toBe("Leite UHT");
  });

  it("limpa resultados quando pesquisa fica vazia", async () => {
    const produtoStore = useProdutoStore();
    await produtoStore.buscarProdutos({ search: "Leite" });
    produtoStore.limparPesquisa();
    expect(produtoStore.resultadosPesquisa).toEqual([]);
  });
});
