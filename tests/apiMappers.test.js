import { describe, expect, it } from "vitest";
import {
  mapearCliente,
  mapearCompra,
  mapearProduto,
  mapearVenda,
} from "../src/api/mappers";

describe("api mappers", () => {
  it("mapeia produto em snake_case para formato POS", () => {
    const produto = mapearProduto({
      id: "uuid-prod",
      name: "Leite 1L",
      codigo_barras: "5601000000012",
      category: "Laticínios",
      purchase_price: 70,
      sale_price: 90,
      tax_type: "PERCENTUAL",
      tax_value: 16,
      tax_rate: 16,
      stock_quantity: 50,
    });

    expect(produto.nome).toBe("Leite 1L");
    expect(produto.codigoBarras).toBe("5601000000012");
    expect(produto.precoCompra).toBe(70);
    expect(produto.precoVenda).toBe(90);
    expect(produto.ivaTipo).toBe("percentual");
    expect(produto.stock).toBe(50);
  });

  it("mantém produto já em camelCase português", () => {
    const produto = mapearProduto({
      id: "uuid-prod",
      nome: "Pão francês",
      codigoBarras: "0001",
      precoVenda: 8,
      ivaTipo: "isento",
      stock: 120,
    });

    expect(produto.nome).toBe("Pão francês");
    expect(produto.ivaTipo).toBe("isento");
  });

  it("mapeia cliente e venda em snake_case", () => {
    const cliente = mapearCliente({
      id: "uuid-cli",
      name: "Cliente Geral",
      phone: "840000000",
    });
    expect(cliente.nome).toBe("Cliente Geral");
    expect(cliente.telefone).toBe("840000000");

    const venda = mapearVenda({
      id: "uuid-venda",
      sale_number: "VD-20260529-00001",
      customer: "Cliente Geral",
      payment_method: "Dinheiro",
      status: "COMPLETED",
      subtotal_amount: 100,
      total_amount: 100,
      items: [
        {
          product_id: "uuid-prod",
          name: "Leite",
          quantity: 2,
          unit_price: 50,
          line_total: 100,
        },
      ],
    });

    expect(venda.referencia).toBe("VD-20260529-00001");
    expect(venda.metodoPagamento).toBe("Dinheiro");
    expect(venda.itens[0].nome).toBe("Leite");
    expect(venda.itens[0].quantidade).toBe(2);
  });

  it("mapeia compra em snake_case", () => {
    const compra = mapearCompra({
      id: "uuid-compra",
      supplier: "Fornecedor A",
      total_amount: 5000,
      created_at: "2026-05-29T10:00:00Z",
      items: [{ name: "Arroz 5kg", quantity: 10, unit_cost: 500 }],
    });

    expect(compra.fornecedor).toBe("Fornecedor A");
    expect(compra.total).toBe(5000);
    expect(compra.itens[0].custoUnitario).toBe(500);
  });
});
