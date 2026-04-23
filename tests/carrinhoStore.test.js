import { beforeEach, describe, expect, it } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { useCarrinhoStore } from "../src/store/useCarrinhoStore";

describe("useCarrinhoStore", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it("adiciona produto com preço final (preço + IVA)", () => {
    const carrinho = useCarrinhoStore();
    carrinho.adicionarProduto({
      id: 10,
      nome: "Produto Teste",
      precoVenda: 100,
      precoVendaComIva: 116,
      ivaPercentual: 16,
    });

    expect(carrinho.itens).toHaveLength(1);
    expect(carrinho.itens[0].precoVenda).toBe(116);
    expect(carrinho.subtotal).toBe(116);
  });

  it("aplica desconto percentual no total", () => {
    const carrinho = useCarrinhoStore();
    carrinho.adicionarProduto({ id: 1, nome: "A", precoVenda: 100, precoVendaComIva: 100, ivaPercentual: 0 });
    carrinho.adicionarProduto({ id: 2, nome: "B", precoVenda: 50, precoVendaComIva: 50, ivaPercentual: 0 });

    carrinho.definirDesconto({ tipo: "percentual", valor: 10 });

    expect(carrinho.subtotal).toBe(150);
    expect(carrinho.valorDesconto).toBe(15);
    expect(carrinho.total).toBe(135);
  });
});

