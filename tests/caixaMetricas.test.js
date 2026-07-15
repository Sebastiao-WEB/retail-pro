import { describe, expect, it } from "vitest";
import { arredondarMoeda, calcularDiferencaProjetada } from "../src/services/caixaMetricas";

describe("arredondarMoeda", () => {
  it("arredonda valores monetarios para 2 casas decimais", () => {
    expect(arredondarMoeda(10.456)).toBe(10.46);
    expect(arredondarMoeda("208.333")).toBe(208.33);
    expect(arredondarMoeda(null)).toBe(0);
  });
});

describe("calcularDiferencaProjetada", () => {
  it("retorna null quando dinheiro real não foi informado", () => {
    expect(calcularDiferencaProjetada({ dinheiroReal: null, dinheiroEsperado: 1000 })).toBeNull();
    expect(calcularDiferencaProjetada({ dinheiroReal: "", dinheiroEsperado: 1000 })).toBeNull();
  });

  it("calcula diferença corretamente quando existe contagem real", () => {
    expect(calcularDiferencaProjetada({ dinheiroReal: 1200, dinheiroEsperado: 1000 })).toBe(200);
    expect(calcularDiferencaProjetada({ dinheiroReal: 900, dinheiroEsperado: 1000 })).toBe(-100);
    expect(calcularDiferencaProjetada({ dinheiroReal: 1000.006, dinheiroEsperado: 1000 })).toBe(0.01);
  });
});

