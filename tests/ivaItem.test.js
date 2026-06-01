import { describe, expect, it } from "vitest";
import { calcularIvaTotalLinha, enriquecerItemIva, resolverIvaPercentualExibicao } from "../src/utils/ivaItem.js";

describe("ivaItem", () => {
  it("usa percentual directo quando existe", () => {
    expect(resolverIvaPercentualExibicao({ ivaPercentual: 16 })).toBe(16);
  });

  it("calcula percentual a partir de IVA monetario unitario", () => {
    expect(
      resolverIvaPercentualExibicao({
        valorIvaUnitario: 8,
        precoSemIva: 50,
      })
    ).toBe(16);
  });

  it("calcula montante de IVA com valor unitario", () => {
    expect(
      calcularIvaTotalLinha({
        quantidade: 2,
        valorIvaUnitario: 0.5,
        subtotal: 13,
      })
    ).toBe(1);
  });

  it("enriquece linha para talao com percentual correcto", () => {
    const linha = enriquecerItemIva({
      nome: "Leite",
      quantidade: 1,
      precoSemIva: 95,
      precoVenda: 110.2,
      valorIvaUnitario: 15.2,
      subtotal: 110.2,
    });

    expect(linha.ivaPercentual).toBe(16);
    expect(linha.ivaTotal).toBe(15.2);
  });
});
