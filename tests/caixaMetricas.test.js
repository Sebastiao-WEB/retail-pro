import { describe, expect, it } from "vitest";
import { calcularDiferencaProjetada } from "../src/services/caixaMetricas";

describe("calcularDiferencaProjetada", () => {
  it("retorna null quando dinheiro real não foi informado", () => {
    expect(calcularDiferencaProjetada({ dinheiroReal: null, dinheiroEsperado: 1000 })).toBeNull();
    expect(calcularDiferencaProjetada({ dinheiroReal: "", dinheiroEsperado: 1000 })).toBeNull();
  });

  it("calcula diferença corretamente quando existe contagem real", () => {
    expect(calcularDiferencaProjetada({ dinheiroReal: 1200, dinheiroEsperado: 1000 })).toBe(200);
    expect(calcularDiferencaProjetada({ dinheiroReal: 900, dinheiroEsperado: 1000 })).toBe(-100);
  });
});

