import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { carregarProdutosIntegrado, consultarStockRemotoIntegrado } from "../src/services/integracaoApi";
import { useProdutoStore } from "../src/store/useProdutoStore";

vi.mock("../src/api", () => ({
  temApiConfigurada: vi.fn(() => true),
}));

vi.mock("../src/services/integracaoApi", () => ({
  carregarProdutosIntegrado: vi.fn(async (filtros = {}) => {
    const produtos = [
      { id: "p1", nome: "Leite UHT", codigoBarras: "111", precoVenda: 50, stock: 10 },
      { id: "p2", nome: "Pao", codigoBarras: "222", precoVenda: 10, stock: 5 },
    ];
    if (filtros.barcode) {
      return produtos.filter((produto) => produto.codigoBarras === filtros.barcode);
    }
    const termo = String(filtros.search || "").toLowerCase();
    if (!termo) return produtos;
    return produtos.filter((produto) => `${produto.nome} ${produto.codigoBarras}`.toLowerCase().includes(termo));
  }),
  consultarStockRemotoIntegrado: vi.fn(async ({ product_ids = [] }) =>
    Object.fromEntries(product_ids.map((id) => [id, id === "p1" ? 3 : 0]))
  ),
}));

describe("useProdutoStore busca remota", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it("consulta backend quando catalogo ainda nao foi carregado", async () => {
    const produtoStore = useProdutoStore();

    const resultados = await produtoStore.buscarProdutos({ search: "Leite", source_location_id: "loc-1" });

    expect(resultados).toHaveLength(1);
    expect(resultados[0].nome).toBe("Leite UHT");
    expect(carregarProdutosIntegrado).toHaveBeenCalledTimes(1);
  });

  it("pesquisa localmente sem chamar backend quando catalogo pos ja esta pronto", async () => {
    const produtoStore = useProdutoStore();
    await produtoStore.garantirCatalogoPos({ source_location_id: "loc-1" });
    vi.mocked(carregarProdutosIntegrado).mockClear();

    const resultados = await produtoStore.buscarProdutos({ search: "Leite", source_location_id: "loc-1" });

    expect(resultados).toHaveLength(1);
    expect(carregarProdutosIntegrado).not.toHaveBeenCalled();
  });

  it("resolve codigo de barras em memoria e so consulta backend em fallback", async () => {
    const produtoStore = useProdutoStore();
    await produtoStore.garantirCatalogoPos({ source_location_id: "loc-1" });
    vi.mocked(carregarProdutosIntegrado).mockClear();

    const local = await produtoStore.resolverPorCodigoBarrasComFallback("111", { source_location_id: "loc-1" });
    expect(local?.nome).toBe("Leite UHT");
    expect(carregarProdutosIntegrado).not.toHaveBeenCalled();

    const remoto = await produtoStore.resolverPorCodigoBarrasComFallback("999", { source_location_id: "loc-1" });
    expect(remoto).toBeNull();
    expect(carregarProdutosIntegrado).toHaveBeenCalledWith({
      source_location_id: "loc-1",
      barcode: "999",
    });
  });

  it("limpa resultados quando pesquisa fica vazia", async () => {
    const produtoStore = useProdutoStore();
    await produtoStore.buscarProdutos({ search: "Leite" });
    produtoStore.limparPesquisa();
    expect(produtoStore.resultadosPesquisa).toEqual([]);
  });

  it("actualiza stock remoto no cache local", async () => {
    const produtoStore = useProdutoStore();
    await produtoStore.garantirCatalogoPos({ source_location_id: "loc-1" });

    await produtoStore.atualizarStockRemoto(["p1"], { source_location_id: "loc-1" });

    expect(consultarStockRemotoIntegrado).toHaveBeenCalledWith({
      location_id: "loc-1",
      product_ids: ["p1"],
    });
    expect(produtoStore.produtos.find((item) => item.id === "p1")?.stock).toBe(3);
  });
});
