import { describe, expect, it } from "vitest";
import {
  construirStockVersionsParaVenda,
  normalizarMapaDisponibilidade,
} from "../src/services/stockDisponibilidade";

describe("stockDisponibilidade", () => {
  it("normaliza resposta nova com quantity e version", () => {
    const mapa = normalizarMapaDisponibilidade({
      p1: { quantity: 12, version: "2026-05-31T10:00:00.000000Z" },
    });
    expect(mapa.p1.quantity).toBe(12);
    expect(mapa.p1.version).toContain("2026-05-31");
  });

  it("mantém compatibilidade com resposta numérica antiga", () => {
    expect(normalizarMapaDisponibilidade({ p1: 8 }).p1.quantity).toBe(8);
  });

  it("monta stockVersions a partir dos produtos do carrinho", () => {
    const versoes = construirStockVersionsParaVenda(
      [{ produtoId: "p1", quantidade: 2 }],
      [{ id: "p1", stockVersion: "v1" }]
    );
    expect(versoes).toEqual({ p1: "v1" });
  });
});
