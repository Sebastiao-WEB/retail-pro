import { describe, expect, it } from "vitest";
import { extrairVendaApi, somarUnidadesVenda } from "../src/services/offline/salePayload";

describe("offline/salePayload", () => {
  it("soma unidades dos itens da venda", () => {
    expect(
      somarUnidadesVenda({
        itens: [{ quantidade: 1 }, { quantidade: 16 }],
      })
    ).toBe(17);
  });

  it("extrai objeto data da resposta da API", () => {
    expect(extrairVendaApi({ data: { id: "v1", itens: [] } })).toEqual({ id: "v1", itens: [] });
  });
});
