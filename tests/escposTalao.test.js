import { describe, expect, it } from "vitest";
import {
  encodeTexto,
  formatarMoeda,
  formatarPrecoCurto,
  gerarBufferAbrirGaveta,
  gerarBufferEscpos,
  larguraColunas,
  montarBlocoPrecoItem,
  montarLinhaEsquerdaDireita,
} from "../electron/escposTalao.js";
import { montarPayloadTalao } from "../src/services/talaoImpressao.js";

describe("escposTalao", () => {
  it("codifica acentuacao portuguesa em WPC1252/latin1", () => {
    expect(encodeTexto("Pão francês")).toEqual(Buffer.from([0x50, 0xe3, 0x6f, 0x20, 0x66, 0x72, 0x61, 0x6e, 0x63, 0xea, 0x73]));
  });

  it("usa largura segura por tipo de papel", () => {
    expect(larguraColunas("58mm")).toBe(32);
    expect(larguraColunas("80mm")).toBe(42);
  });

  it("formata precos compactos sem separador de milhar", () => {
    expect(formatarPrecoCurto(70)).toBe("70,00");
    expect(formatarPrecoCurto(1234.5)).toBe("1234,50");
    expect(formatarMoeda(70)).toBe("70,00 MT");
  });

  it("mantem item numa unica linha", () => {
    const linha = montarLinhaEsquerdaDireita(
      "Bolacha Maria",
      montarBlocoPrecoItem({ nome: "Bolacha Maria", quantidade: 1, ivaPercentual: 0, subtotal: 70 }, 42, false),
      42
    );

    expect(linha.length).toBe(42);
    expect(linha.includes("\n")).toBe(false);
    expect(linha.startsWith("Bolacha Maria")).toBe(true);
    expect(linha.trimEnd()).toMatch(/1\s+0%\s+70,00$/);
  });

  it("mantem totais numa unica linha", () => {
    const linha = montarLinhaEsquerdaDireita("TOTAL", formatarMoeda(1234.56), 42);
    expect(linha.length).toBe(42);
    expect(linha.includes("\n")).toBe(false);
    expect(linha.startsWith("TOTAL")).toBe(true);
    expect(linha.trimEnd()).toContain("1234,56 MT");
  });

  it("gera buffer sem quebra nos precos dos itens e resumo", () => {
    const talao = montarPayloadTalao(
      {
        cliente: "José",
        metodoPagamento: "Dinheiro",
        subtotal: 70,
        descontoAplicado: 0,
        total: 70,
        valorPago: 100,
        troco: 30,
        data: "2026-05-29T10:00:00.000Z",
        itens: [{ nome: "Bolacha Maria", quantidade: 1, ivaPercentual: 0, subtotal: 70 }],
      },
      {
        nomeEmpresa: "Empresa Demo",
        rodapeFacturas: "Obrigado pela preferência.",
        larguraTalao: "80mm",
      }
    );

    const buffer = gerarBufferEscpos(talao, { corteAutomatico: false });
    const linhas = buffer
      .toString("latin1")
      .split("\n")
      .map((linha) => linha.trimEnd())
      .filter(Boolean);

    const linhaItem = linhas.find((linha) => linha.includes("Bolacha Maria"));
    const linhaTotal = linhas.find((linha) => linha.startsWith("Total") && !linha.startsWith("Total IVA"));

    expect(linhaItem?.length).toBeLessThanOrEqual(42);
    expect(linhaItem).toMatch(/Bolacha Maria\s+1\s+0%\s+70,00/);
    expect(linhaTotal?.length).toBeLessThanOrEqual(42);
    expect(linhaTotal).toMatch(/Total\s+70,00 MT/);
    expect(linhas.some((linha) => linha.startsWith("Desconto"))).toBe(false);
  });

  it("exibe linha de desconto apenas quando houver valor", () => {
    const talao = montarPayloadTalao(
      {
        cliente: "Cliente",
        metodoPagamento: "Dinheiro",
        subtotal: 100,
        descontoAplicado: 10,
        total: 90,
        valorPago: 100,
        troco: 10,
        data: "2026-05-29T10:00:00.000Z",
        itens: [{ nome: "Item", quantidade: 1, ivaPercentual: 0, subtotal: 100 }],
      },
      { nomeEmpresa: "Empresa Demo", larguraTalao: "80mm" }
    );

    const buffer = gerarBufferEscpos(talao, { corteAutomatico: false });
    const texto = buffer.toString("latin1");

    expect(texto).toMatch(/Desconto\s+- 10,00 MT/);
  });

  it("infere desconto quando subtotal e total divergem sem descontoAplicado", () => {
    const talao = montarPayloadTalao(
      {
        cliente: "Cliente",
        metodoPagamento: "Dinheiro",
        subtotal: 100,
        descontoAplicado: 0,
        total: 90,
        valorPago: 100,
        troco: 10,
        data: "2026-05-29T10:00:00.000Z",
        itens: [{ nome: "Item", quantidade: 1, ivaPercentual: 0, subtotal: 100 }],
      },
      { nomeEmpresa: "Empresa Demo", larguraTalao: "80mm" }
    );

    expect(talao.venda.descontoAplicado).toBe(10);

    const buffer = gerarBufferEscpos(talao, { corteAutomatico: false });
    const texto = buffer.toString("latin1");

    expect(texto).toMatch(/Desconto\s+- 10,00 MT/);
  });

  it("mantem ordem do resumo: subtotal, total, valor pago, troco", () => {
    const talao = montarPayloadTalao(
      {
        cliente: "Cliente",
        metodoPagamento: "Dinheiro",
        subtotal: 100,
        descontoAplicado: 10,
        total: 90,
        valorPago: 100,
        troco: 10,
        data: "2026-05-29T10:00:00.000Z",
        itens: [{ nome: "Item", quantidade: 1, ivaPercentual: 0, subtotal: 100 }],
      },
      { nomeEmpresa: "Empresa Demo", larguraTalao: "80mm" }
    );

    const buffer = gerarBufferEscpos(talao, { corteAutomatico: false });
    const texto = buffer.toString("latin1");

    const posSubtotal = texto.indexOf("Subtotal");
    const posDesconto = texto.indexOf("Desconto");
    const posTotal = texto.search(/Total\s+\d/);
    const posValorPago = texto.indexOf("Valor Pago");
    const posTroco = texto.indexOf("Troco");

    expect(posSubtotal).toBeGreaterThanOrEqual(0);
    expect(posDesconto).toBeGreaterThan(posSubtotal);
    expect(posTotal).toBeGreaterThan(posDesconto);
    expect(posValorPago).toBeGreaterThan(posTotal);
    expect(posTroco).toBeGreaterThan(posValorPago);
  });

  it("inclui comando ESC p para gaveta em vendas em dinheiro", () => {
    const talao = montarPayloadTalao(
      {
        cliente: "Cliente",
        metodoPagamento: "Dinheiro",
        subtotal: 50,
        total: 50,
        valorPago: 50,
        troco: 0,
        data: "2026-05-29T10:00:00.000Z",
        itens: [{ nome: "Item", quantidade: 1, ivaPercentual: 0, subtotal: 50 }],
      },
      { nomeEmpresa: "Empresa Demo", larguraTalao: "80mm" }
    );

    const buffer = gerarBufferEscpos(talao, { corteAutomatico: false, abrirGaveta: true, gavetaPin: 0 });
    expect(buffer.includes(Buffer.from([0x1b, 0x70, 0x00, 0x19, 0xfa]))).toBe(true);
  });

  it("gera buffer standalone para abrir gaveta", () => {
    const buffer = gerarBufferAbrirGaveta({ pin: 1, tempoOn: 100, tempoOff: 200 });
    expect(buffer.equals(Buffer.from([0x1b, 0x70, 0x01, 0x32, 0x64]))).toBe(true);
  });

  it("normaliza porta DK quando pin vem como string", () => {
    const buffer = gerarBufferAbrirGaveta({ pin: "1", tempoOn: 100, tempoOff: 200 });
    expect(buffer.equals(Buffer.from([0x1b, 0x70, 0x01, 0x32, 0x64]))).toBe(true);
  });
});
