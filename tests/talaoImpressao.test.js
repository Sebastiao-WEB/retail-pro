import { afterEach, describe, expect, it, vi } from "vitest";
import { montarPayloadTalao } from "../src/services/talaoImpressao.js";

vi.mock("../src/api/config.js", () => ({
  temApiConfigurada: vi.fn(() => false),
}));

import { temApiConfigurada } from "../src/api/config.js";

const vendaBase = {
  cliente: "Cliente",
  metodoPagamento: "Dinheiro",
  subtotal: 100,
  total: 100,
  data: "2026-05-29T10:00:00.000Z",
  itens: [{ nome: "Item", quantidade: 1, ivaPercentual: 0, subtotal: 100 }],
};

describe("talaoImpressao", () => {
  afterEach(() => {
    vi.mocked(temApiConfigurada).mockReturnValue(false);
  });

  it("usa rodape local no modo mock", () => {
    const talao = montarPayloadTalao(vendaBase, {
      nomeEmpresa: "Empresa Demo",
      rodapeFacturas: "Rodape local do POS",
    });

    expect(talao.empresa.rodape).toBe("Rodape local do POS");
  });

  it("usa rodape da API quando backend configurado", () => {
    vi.mocked(temApiConfigurada).mockReturnValue(true);

    const talao = montarPayloadTalao(vendaBase, {
      nomeEmpresa: "Empresa Demo",
      rodapeFacturas: "Rodape vindo do banco de dados",
    });

    expect(talao.empresa.rodape).toBe("Rodape vindo do banco de dados");
  });

  it("nao usa fallback mock quando API esta activa e rodape vem vazio", () => {
    vi.mocked(temApiConfigurada).mockReturnValue(true);

    const talao = montarPayloadTalao(vendaBase, {
      nomeEmpresa: "Empresa Demo",
      rodapeFacturas: "",
    });

    expect(talao.empresa.rodape).toBe("");
  });
});
