import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  abrirTurnoIntegrado,
  carregarHistoricoIntegrado,
  criarVendaIntegrada,
  fecharTurnoIntegrado,
  solicitarReversaoIntegrada,
} from "../src/services/integracaoApi";

vi.mock("../src/api", async () => {
  const atual = await vi.importActual("../src/api/config");
  return {
    ...atual,
    temApiConfigurada: vi.fn(() => true),
    garantirBackendDisponivel: vi.fn(),
    productsApi: { listar: vi.fn() },
    customersApi: { listar: vi.fn() },
    salesApi: {
      listar: vi.fn(),
      criar: vi.fn(),
      solicitarReversao: vi.fn(),
    },
    purchasesApi: { listar: vi.fn() },
    cashApi: {
      abrirSessao: vi.fn(),
      fecharSessao: vi.fn(),
    },
  };
});

import { cashApi, salesApi } from "../src/api";
import { ApiError } from "../src/api/httpClient";

describe("integracaoApi homologação", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("executa fluxo caixa -> venda -> reversão com mapeamento", async () => {
    cashApi.abrirSessao.mockResolvedValue({
      data: { id: "sessao-1", status: "OPEN", opening_balance: 1000 },
    });
    salesApi.criar.mockResolvedValue({
      data: { id: "venda-1", referencia: "VD-20260529-00001" },
    });
    salesApi.listar.mockResolvedValue({
      data: [
        {
          id: "venda-1",
          referencia: "VD-20260529-00001",
          cliente: "Cliente Geral",
          metodo_pagamento: "Dinheiro",
          total: 200,
          itens: [],
        },
      ],
    });
    salesApi.solicitarReversao.mockResolvedValue({
      data: { id: "rev-1", status: "PENDING" },
    });
    cashApi.fecharSessao.mockResolvedValue({
      data: { id: "sessao-1", status: "CLOSED" },
    });

    const abertura = await abrirTurnoIntegrado({
      register_id: "reg-1",
      opening_balance: 1000,
    });
    expect(abertura.ok).toBe(true);
    expect(abertura.data.id).toBe("sessao-1");

    await criarVendaIntegrada({
      cash_session_id: "sessao-1",
      register_id: "reg-1",
      total: 200,
      itens: [{ nome: "Produto", quantidade: 1, precoVenda: 200, subtotal: 200 }],
    });
    expect(salesApi.criar).toHaveBeenCalledOnce();

    const historico = await carregarHistoricoIntegrado({ cash_session_id: "sessao-1" });
    expect(historico.vendas).toHaveLength(1);
    expect(historico.vendas[0].metodoPagamento).toBe("Dinheiro");

    const reversao = await solicitarReversaoIntegrada({ venda_id: "venda-1", reason: "Teste" });
    expect(reversao.ok).toBe(true);

    const fecho = await fecharTurnoIntegrado("sessao-1", { closing_balance: 1200 });
    expect(fecho.ok).toBe(true);
  });

  it("reutiliza sessão aberta quando API responde 409", async () => {
    cashApi.abrirSessao.mockRejectedValue(
      new ApiError("Já existe sessão de caixa aberta.", 409, {
        data: { id: "sessao-existente", status: "OPEN" },
      })
    );

    const abertura = await abrirTurnoIntegrado({ register_id: "reg-1", opening_balance: 500 });

    expect(abertura.ok).toBe(true);
    expect(abertura.reutilizada).toBe(true);
    expect(abertura.data.id).toBe("sessao-existente");
  });
});
