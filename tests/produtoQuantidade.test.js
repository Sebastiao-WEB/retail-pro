import { describe, expect, it } from "vitest";
import {
  formatarQuantidadeExibicao,
  gramasParaKg,
  normalizarQuantidadeVenda,
  parseQuantidadeTexto,
  UNIDADE_VENDA_KG,
  vendidoPorPeso,
} from "../src/utils/produtoQuantidade.js";

describe("produtoQuantidade", () => {
  it("identifica produto vendido por peso", () => {
    expect(vendidoPorPeso({ unidadeVenda: "KG" })).toBe(true);
    expect(vendidoPorPeso({ unidadeVenda: "UN" })).toBe(false);
  });

  it("converte gramas para kg", () => {
    expect(gramasParaKg(700)).toBe(0.7);
    expect(gramasParaKg("350")).toBe(0.35);
  });

  it("interpreta quantidade em kg com virgula", () => {
    expect(parseQuantidadeTexto("0,7", UNIDADE_VENDA_KG)).toBe(0.7);
    expect(parseQuantidadeTexto("1.2", UNIDADE_VENDA_KG)).toBe(1.2);
  });

  it("normaliza unidades inteiras e peso", () => {
    expect(normalizarQuantidadeVenda(2.8, "UN")).toBe(2);
    expect(normalizarQuantidadeVenda(0.7004, "KG")).toBe(0.7);
  });

  it("formata exibição em gramas ou kg", () => {
    expect(formatarQuantidadeExibicao(0.7, "KG")).toBe("700 g");
    expect(formatarQuantidadeExibicao(2, "KG")).toMatch(/2.*kg/);
    expect(formatarQuantidadeExibicao(3, "UN")).toBe("3");
  });
});
