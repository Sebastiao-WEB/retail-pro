import { describe, expect, it } from "vitest";
import { extrairItensPorQuantidade } from "../src/utils/mesaItens.js";

describe("extrairItensPorQuantidade", () => {
  const itensOrigem = [
    {
      id: "item-1",
      produtoId: "prod-1",
      nome: "Cerveja",
      quantidade: 3,
      precoVenda: 80,
      subtotal: 240,
      unidadeVenda: "UN",
    },
    {
      id: "item-2",
      produtoId: "prod-2",
      nome: "Refrigerante",
      quantidade: 1,
      precoVenda: 50,
      subtotal: 50,
      unidadeVenda: "UN",
    },
  ];

  it("extrai subset de itens e mantém o restante na comanda", () => {
    const resultado = extrairItensPorQuantidade(itensOrigem, [
      { produtoId: "prod-1", itemId: "item-1", quantidade: 2 },
    ]);

    expect(resultado.itensExtraidos).toHaveLength(1);
    expect(resultado.itensExtraidos[0].quantidade).toBe(2);
    expect(resultado.itensExtraidos[0].subtotal).toBe(160);
    expect(resultado.itensRestantes).toHaveLength(2);
    expect(resultado.itensRestantes.find((item) => item.produtoId === "prod-1")?.quantidade).toBe(1);
    expect(resultado.itensRestantes.find((item) => item.produtoId === "prod-2")?.quantidade).toBe(1);
  });

  it("remove item completo quando quantidade igual ao disponível", () => {
    const resultado = extrairItensPorQuantidade(itensOrigem, [
      { produtoId: "prod-2", itemId: "item-2", quantidade: 1 },
    ]);

    expect(resultado.itensExtraidos[0].nome).toBe("Refrigerante");
    expect(resultado.itensRestantes).toHaveLength(1);
    expect(resultado.itensRestantes[0].produtoId).toBe("prod-1");
  });

  it("rejeita quantidade superior ao disponível", () => {
    expect(() =>
      extrairItensPorQuantidade(itensOrigem, [
        { produtoId: "prod-1", itemId: "item-1", quantidade: 5 },
      ])
    ).toThrow(/superior ao disponível/);
  });
});
