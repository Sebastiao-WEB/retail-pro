import { describe, expect, it } from "vitest";
import { mapearProduto } from "../src/api/mappers/index.js";

describe("mapearProduto", () => {
  it("preserva unidadeVenda KG da API", () => {
    const produto = mapearProduto({
      id: "p1",
      nome: "Frango",
      unidadeVenda: "KG",
      precoVenda: 450,
      stock: 10,
    });

    expect(produto.unidadeVenda).toBe("KG");
  });

  it("aceita unidade_venda em snake_case", () => {
    const produto = mapearProduto({
      id: "p2",
      nome: "Peixe",
      unidade_venda: "KG",
    });

    expect(produto.unidadeVenda).toBe("KG");
  });

  it("assume UN quando unidade nao e KG", () => {
    expect(mapearProduto({ id: "p3", unidadeVenda: "UN" }).unidadeVenda).toBe("UN");
    expect(mapearProduto({ id: "p4" }).unidadeVenda).toBe("UN");
  });
});
