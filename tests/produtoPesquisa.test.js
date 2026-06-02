import { describe, expect, it } from "vitest";
import {
  normalizarCodigoBarras,
  pareceCodigoBarras,
  pesquisarProdutosNoCatalogo,
  resolverProdutoPorCodigoBarras,
} from "../src/utils/produtoPesquisa.js";

const catalogo = [
  { id: 1, nome: "Tomate", codigoBarras: "5601000000012" },
  { id: 2, nome: "Cebola", codigoBarras: "5601000000029" },
  { id: 3, nome: "Pão francês", codigoBarras: "5601000000036" },
];

describe("produtoPesquisa", () => {
  it("normaliza codigo de barras removendo espacos", () => {
    expect(normalizarCodigoBarras("5601 0000 00012")).toBe("5601000000012");
  });

  it("detecta termo numerico como codigo de barras", () => {
    expect(pareceCodigoBarras("5601000000012")).toBe(true);
    expect(pareceCodigoBarras("tomate")).toBe(false);
  });

  it("resolve produto por codigo exacto no indice ou lista", () => {
    const indice = { 5601000000012: catalogo[0] };
    expect(resolverProdutoPorCodigoBarras(catalogo, indice, "5601000000012")?.nome).toBe("Tomate");
    expect(resolverProdutoPorCodigoBarras(catalogo, {}, "5601000000029")?.nome).toBe("Cebola");
  });

  it("pesquisa por nome sem limite de cinco resultados", () => {
    const muitos = Array.from({ length: 20 }, (_, indice) => ({
      id: indice + 10,
      nome: `Produto ${indice}`,
      codigoBarras: `5601000000${String(indice).padStart(3, "0")}`,
    }));

    const resultados = pesquisarProdutosNoCatalogo(muitos, "Produto");
    expect(resultados.length).toBe(20);
  });

  it("prioriza correspondencia exacta de codigo de barras", () => {
    const resultados = pesquisarProdutosNoCatalogo(catalogo, "5601000000012");
    expect(resultados[0]?.nome).toBe("Tomate");
  });

  it("encontra produto por nome parcial ignorando acentos", () => {
    const resultados = pesquisarProdutosNoCatalogo(catalogo, "pao");
    expect(resultados[0]?.nome).toBe("Pão francês");
  });
});
