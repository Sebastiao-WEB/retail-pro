import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { useSessaoStore } from "../src/store/useSessaoStore";

const abrirTurnoIntegrado = vi.fn();
const fecharTurnoIntegrado = vi.fn();
const obterSessaoAtivaIntegrada = vi.fn();

vi.mock("../src/api", () => ({
  temApiConfigurada: vi.fn(() => true),
}));

vi.mock("../src/services/integracaoApi", () => ({
  abrirTurnoIntegrado: (...args) => abrirTurnoIntegrado(...args),
  fecharTurnoIntegrado: (...args) => fecharTurnoIntegrado(...args),
  obterSessaoAtivaIntegrada: (...args) => obterSessaoAtivaIntegrada(...args),
}));

vi.mock("../src/services/authStorage", () => ({
  limparTokens: vi.fn(),
  salvarTokens: vi.fn(),
}));

describe("useSessaoStore", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    abrirTurnoIntegrado.mockReset();
    fecharTurnoIntegrado.mockReset();
    obterSessaoAtivaIntegrada.mockReset();
    const memoria = new Map();
    vi.stubGlobal("localStorage", {
      getItem: (chave) => memoria.get(chave) ?? null,
      setItem: (chave, valor) => memoria.set(chave, String(valor)),
      removeItem: (chave) => memoria.delete(chave),
    });
  });

  it("abre turno remoto e guarda cashSessionId", async () => {
    abrirTurnoIntegrado.mockResolvedValue({
      ok: true,
      data: { id: "sessao-uuid-1", status: "OPEN", opening_balance: 500 },
    });

    const sessaoStore = useSessaoStore();
    sessaoStore.registerId = "register-uuid-1";

    const resultado = await sessaoStore.abrirTurno({ fundoInicial: 500 });

    expect(resultado.remotoOk).toBe(true);
    expect(sessaoStore.turnoAberto).toBe(true);
    expect(sessaoStore.cashSessionId).toBe("sessao-uuid-1");
    expect(sessaoStore.fundoInicial).toBe(500);
  });

  it("reutiliza sessão existente quando API retorna 409", async () => {
    abrirTurnoIntegrado.mockResolvedValue({
      ok: true,
      reutilizada: true,
      data: { id: "sessao-existente", status: "OPEN", opening_balance: 200 },
    });

    const sessaoStore = useSessaoStore();
    sessaoStore.registerId = "register-uuid-1";

    const resultado = await sessaoStore.abrirTurno({ fundoInicial: 200 });

    expect(resultado.remotoOk).toBe(true);
    expect(sessaoStore.cashSessionId).toBe("sessao-existente");
  });

  it("fecha turno remoto e limpa cashSessionId", async () => {
    fecharTurnoIntegrado.mockResolvedValue({
      ok: true,
      data: { id: "sessao-uuid-1", status: "CLOSED" },
    });

    const sessaoStore = useSessaoStore();
    sessaoStore.turnoAberto = true;
    sessaoStore.cashSessionId = "sessao-uuid-1";
    sessaoStore.aberturaEm = new Date().toISOString();

    const resultado = await sessaoStore.fecharTurno({
      dinheiroReal: 1500,
      justificativaDiferenca: "",
      fechadoEm: new Date().toISOString(),
    });

    expect(fecharTurnoIntegrado).toHaveBeenCalledWith(
      "sessao-uuid-1",
      expect.objectContaining({
        closing_balance: 1500,
        report_snapshot: expect.objectContaining({ dinheiroReal: 1500 }),
      })
    );

    expect(resultado.remotoOk).toBe(true);
    expect(sessaoStore.turnoAberto).toBe(false);
    expect(sessaoStore.cashSessionId).toBeNull();
  });

  it("nao persiste historico de fecho no localStorage", () => {
    const sessaoStore = useSessaoStore();
    sessaoStore.utilizador = "Operador";
    sessaoStore.salvar();

    const salvo = JSON.parse(localStorage.getItem("retailpro:sessao") || "{}");
    expect(salvo.historicoFecho).toBeUndefined();
  });

  it("sincroniza turno remoto ativo", async () => {
    obterSessaoAtivaIntegrada.mockResolvedValue({
      ok: true,
      data: {
        id: "sessao-sync",
        status: "OPEN",
        opening_balance: 300,
        opened_at: "2026-05-29T08:00:00.000Z",
      },
    });

    const sessaoStore = useSessaoStore();
    sessaoStore.registerId = "register-uuid-1";

    const resultado = await sessaoStore.sincronizarTurnoRemoto();

    expect(resultado.remotoOk).toBe(true);
    expect(resultado.existeTurno).toBe(true);
    expect(sessaoStore.cashSessionId).toBe("sessao-sync");
    expect(sessaoStore.fundoInicial).toBe(300);
  });
});
