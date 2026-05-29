import { describe, expect, it } from "vitest";
import { gerarBufferEscposRelatorioFecho } from "../electron/escposTalao.js";
import { montarPayloadRelatorioFecho } from "../src/services/relatorioFechoImpressao.js";

describe("relatorioFechoImpressao", () => {
  it("monta payload com resumo do turno", () => {
    const payload = montarPayloadRelatorioFecho(
      {
        caixa: "Caixa 1",
        utilizador: "Operador A",
        aberturaEm: "2026-05-29T08:00:00.000Z",
        fechadoEm: "2026-05-29T18:00:00.000Z",
        fundoInicial: 1000,
        totalVendido: 2500,
        totalTransacoes: 12,
        ticketMedio: 208.33,
        vendasDinheiro: 1800,
        vendasTransferencia: 700,
        dinheiroEsperado: 2800,
        dinheiroReal: 2790,
        diferenca: -10,
        justificativaDiferenca: "Troco em falta",
        auditoriaVendas: [
          {
            data: "2026-05-29T10:00:00.000Z",
            total: 150,
            metodoPagamento: "Dinheiro",
            cliente: "Cliente",
          },
        ],
      },
      {
        nomeEmpresa: "Empresa Demo",
        rodapeFacturas: "Obrigado.",
        larguraTalao: "80mm",
      }
    );

    expect(payload.relatorio.caixa).toBe("Caixa 1");
    expect(payload.relatorio.totalVendido).toBe(2500);
    expect(payload.relatorio.justificativaDiferenca).toBe("Troco em falta");
    expect(payload.relatorio.vendas).toHaveLength(1);
  });

  it("gera buffer RAW com totais do fecho", () => {
    const payload = montarPayloadRelatorioFecho(
      {
        caixa: "Caixa 1",
        utilizador: "Operador A",
        aberturaEm: "2026-05-29T08:00:00.000Z",
        fechadoEm: "2026-05-29T18:00:00.000Z",
        fundoInicial: 1000,
        totalVendido: 2500,
        totalTransacoes: 12,
        ticketMedio: 208.33,
        vendasDinheiro: 1800,
        vendasTransferencia: 700,
        dinheiroEsperado: 2800,
        dinheiroReal: 2790,
        diferenca: -10,
        auditoriaVendas: [],
      },
      { nomeEmpresa: "Empresa Demo", larguraTalao: "80mm" }
    );

    const buffer = gerarBufferEscposRelatorioFecho(payload, { corteAutomatico: false });
    const texto = buffer.toString("latin1");

    expect(texto).toContain("Relatorio de Fecho de Caixa");
    expect(texto).toContain("Total vendido");
    expect(texto).toContain("2500,00 MT");
    expect(texto).toContain("Diferenca");
    expect(texto).toContain("-10,00 MT");
  });
});
