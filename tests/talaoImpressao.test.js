import { afterEach, describe, expect, it, vi } from "vitest";
import {
  abrirGavetaPosVenda,
  deveAbrirGavetaNaVenda,
  montarPayloadTalao,
  obterOpcoesGaveta,
} from "../src/services/talaoImpressao.js";

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

  it("detecta vendas em dinheiro elegiveis para abrir gaveta", () => {
    expect(
      deveAbrirGavetaNaVenda(vendaBase, { abrirGavetaAutomatico: true })
    ).toBe(true);
    expect(
      deveAbrirGavetaNaVenda({ ...vendaBase, metodoPagamento: "Transferência" }, { abrirGavetaAutomatico: true })
    ).toBe(false);
    expect(
      deveAbrirGavetaNaVenda(vendaBase, { abrirGavetaAutomatico: false })
    ).toBe(false);
  });

  it("normaliza porta DK definida nas configuracoes", () => {
    expect(obterOpcoesGaveta({ gavetaPin: "1" }).gavetaPin).toBe(1);
    expect(obterOpcoesGaveta({ gavetaPin: 0 }).gavetaPin).toBe(0);
  });

  it("mostra percentual e total de IVA no talao quando detalharIva activo", () => {
    const talao = montarPayloadTalao(
      {
        ...vendaBase,
        itens: [
          {
            nome: "Leite",
            quantidade: 1,
            precoSemIva: 95,
            precoVenda: 110.2,
            valorIvaUnitario: 15.2,
            subtotal: 110.2,
          },
        ],
      },
      { nomeEmpresa: "Empresa Demo" },
      { detalharIva: true }
    );

    expect(talao.venda.itens[0].ivaPercentual).toBe(16);
    expect(talao.venda.itens[0].ivaTotal).toBe(15.2);
    expect(talao.venda.totalIva).toBe(15.2);
    expect(talao.detalharIva).toBe(true);
  });

  it("nao trata valorIvaUnitario zero como bloqueio do percentual", () => {
    const talao = montarPayloadTalao({
      ...vendaBase,
      itens: [{ nome: "Bolacha", quantidade: 1, ivaPercentual: 16, subtotal: 116 }],
    });

    expect(talao.venda.itens[0].ivaPercentual).toBe(16);
    expect(talao.venda.totalIva).toBeGreaterThan(0);
  });

  it("nao abre gaveta quando venda nao e em dinheiro", async () => {
    const resultado = await abrirGavetaPosVenda({
      venda: { ...vendaBase, metodoPagamento: "Transferência" },
      configuracao: { abrirGavetaAutomatico: true },
    });
    expect(resultado).toEqual({ ok: true, skipped: true });
  });
});
