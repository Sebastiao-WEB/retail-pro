import { defineStore } from "pinia";
import { temApiConfigurada } from "../api";
import {
  carregarHistoricoIntegrado,
  carregarSolicitacoesReversaoIntegrado,
  criarVendaIntegrada,
  solicitarReversaoIntegrada,
} from "../services/integracaoApi";
import { isErroNegocioVenda, isErroRedeOuIndisponivel } from "../services/offline/networkError";
import { vendaJaRegistadaNoServidor } from "../services/offline/saleServerCheck";
import { enfileirarVendaPendente } from "../services/offline/pendingQueue";
import { extrairVendaApi } from "../services/offline/salePayload";
import { mapearVenda } from "../api/mappers";
import { useSessaoStore } from "./useSessaoStore";
import { t } from "../services/i18nHelper.js";

const CHAVE_REVERSOES = "retailpro:reversoes-venda";
const UUID_VENDA_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

function mapearEstadoReversaoRemota(status) {
  const valor = String(status || "").toUpperCase();
  if (valor === "APPROVED") return "Aprovada";
  if (valor === "REJECTED") return "Cancelada";
  return "Pendente";
}

export function vendaTemIdValidoParaReversao(venda) {
  return UUID_VENDA_REGEX.test(String(venda?.id || "").trim());
}

export function motivoBloqueioReversao(venda, solicitacoesReversao = []) {
  if (!venda) return t("api.reversalFailed");
  if (venda.pendenteSync) return t("api.reversalSaleNotSynced");
  if (!vendaTemIdValidoParaReversao(venda)) return t("api.reversalInvalidSaleId");
  if (String(venda.estado || "") === "Revertida") return t("history.sales.toast.alreadyReverted");
  const pendente = solicitacoesReversao.some((item) => item.vendaId === venda.id && item.estado === "Pendente");
  if (pendente) return t("api.reversalAlreadyPending");
  return "";
}

export function podeSolicitarReversao(venda, solicitacoesReversao = []) {
  return !motivoBloqueioReversao(venda, solicitacoesReversao);
}

function gerarIdLocal() {
  if (typeof globalThis.crypto?.randomUUID === "function") {
    return globalThis.crypto.randomUUID();
  }
  return `local-${Date.now()}-${Math.floor(Math.random() * 100000)}`;
}

function gerarSequenciaId(id, indice = 0) {
  const texto = String(id || "").trim();
  if (!texto) return String((Date.now() + indice) % 100000).padStart(5, "0");

  if (/^\d+$/.test(texto)) {
    return String(Number(texto) % 100000).padStart(5, "0");
  }

  const hash = [...texto].reduce((acc, char) => ((acc * 31 + char.charCodeAt(0)) >>> 0), 0);
  return String(hash % 100000).padStart(5, "0");
}

function gerarReferenciaVenda(venda, indice = 0) {
  const data = new Date(venda?.data || Date.now());
  const ano = data.getFullYear();
  const mes = String(data.getMonth() + 1).padStart(2, "0");
  const dia = String(data.getDate()).padStart(2, "0");
  const sequencia = gerarSequenciaId(venda?.id, indice);
  return `VD-${ano}${mes}${dia}-${sequencia}`;
}

export function vendaPertenceTurnoAtual(venda, sessao) {
  if (!venda || !sessao) return false;

  const sessaoId = sessao.cashSessionId;
  const vendaSessaoId = venda.cashSessionId || venda.cash_session_id || null;
  if (sessaoId && vendaSessaoId) {
    return vendaSessaoId === sessaoId;
  }

  const abertura = sessao.aberturaEm ? new Date(sessao.aberturaEm).getTime() : NaN;
  if (!Number.isFinite(abertura)) {
    return !!venda.pendenteSync && sessao.turnoAberto;
  }

  const dataVenda = venda.data ? new Date(venda.data).getTime() : NaN;
  return Number.isFinite(dataVenda) && dataVenda >= abertura;
}

function normalizarVendaHistorico(venda, indice = 0) {
  return {
    ...venda,
    referencia: venda.referencia || gerarReferenciaVenda(venda, indice),
    estado: venda.estado || "Concluida",
    pendenteSync: false,
  };
}

export const useVendaStore = defineStore("vendas", {
  state: () => ({
    vendas: [],
    solicitacoesReversao: [],
    reversoesEmCurso: {},
    carregado: false,
  }),
  getters: {
    receitaTotal: (state) => state.vendas.reduce((acc, venda) => acc + venda.total, 0),
    vendasHoje: (state) => {
      const hoje = new Date().toDateString();
      return state.vendas.filter((venda) => new Date(venda.data).toDateString() === hoje);
    },
    totalVendasHoje() {
      return this.vendasHoje.reduce((acc, venda) => acc + venda.total, 0);
    },
    solicitacoesPendentes: (state) => state.solicitacoesReversao.filter((item) => item.estado === "Pendente"),
  },
  actions: {
    hidratarSolicitacoes() {
      try {
        const raw = localStorage.getItem(CHAVE_REVERSOES);
        this.solicitacoesReversao = raw ? JSON.parse(raw) : [];
      } catch {
        this.solicitacoesReversao = [];
      }
    },
    salvarSolicitacoes() {
      localStorage.setItem(CHAVE_REVERSOES, JSON.stringify(this.solicitacoesReversao));
    },
    deduplicarSolicitacoesReversao() {
      const porVenda = new Map();
      for (const item of this.solicitacoesReversao) {
        const vendaId = item?.vendaId;
        if (!vendaId) continue;
        const actual = porVenda.get(vendaId);
        const dataItem = String(item.dataSolicitacao || "");
        const dataActual = String(actual?.dataSolicitacao || "");
        if (!actual || dataItem >= dataActual) {
          porVenda.set(vendaId, item);
        }
      }
      this.solicitacoesReversao = [...porVenda.values()];
    },
    inserirOuActualizarVenda(venda) {
      if (!venda?.id) return;
      const indice = this.vendas.findIndex((item) => item.id === venda.id);
      if (indice >= 0) {
        this.vendas[indice] = { ...this.vendas[indice], ...venda };
        return;
      }
      this.vendas.unshift(venda);
    },
    mesclarVendasPendentesLocais(vendasRemotas = []) {
      const idsRemotos = new Set(vendasRemotas.map((v) => v.id));
      const pendentesLocais = this.vendas.filter((v) => v.pendenteSync && !idsRemotos.has(v.id));
      return [...pendentesLocais, ...vendasRemotas];
    },
    mesclarVendasTurnoAtual(vendasRemotas = [], sessaoStore) {
      const remotas = Array.isArray(vendasRemotas) ? vendasRemotas : [];
      const idsRemotos = new Set(remotas.map((v) => v.id));
      const locaisTurno = this.vendas.filter(
        (v) => !idsRemotos.has(v.id) && vendaPertenceTurnoAtual(v, sessaoStore)
      );
      const porId = new Map();
      for (const venda of [...locaisTurno, ...remotas]) {
        if (venda?.id) porId.set(venda.id, venda);
      }
      return [...porId.values()].sort(
        (a, b) => new Date(b.data || 0).getTime() - new Date(a.data || 0).getTime()
      );
    },
    async carregarHistoricoRemoto(sessaoStore) {
      const filtrosPrimarios = {};
      if (sessaoStore.cashSessionId) {
        filtrosPrimarios.cash_session_id = sessaoStore.cashSessionId;
      } else if (sessaoStore.registerId) {
        filtrosPrimarios.register_id = sessaoStore.registerId;
      }

      let { vendas } = await carregarHistoricoIntegrado(filtrosPrimarios);

      if (sessaoStore.cashSessionId && vendas.length === 0 && sessaoStore.registerId) {
        const fallback = await carregarHistoricoIntegrado({ register_id: sessaoStore.registerId });
        const inicio = sessaoStore.aberturaEm ? new Date(sessaoStore.aberturaEm).getTime() : NaN;
        vendas = fallback.vendas.filter((venda) => {
          if (venda.cashSessionId === sessaoStore.cashSessionId) return true;
          if (!Number.isFinite(inicio)) return true;
          const dataVenda = venda.data ? new Date(venda.data).getTime() : NaN;
          return Number.isFinite(dataVenda) && dataVenda >= inicio;
        });
      }

      return vendas;
    },
    async sincronizarHistorico() {
      const sessaoStore = useSessaoStore();
      sessaoStore.hidratar();
      try {
        if (temApiConfigurada()) {
          const vendasRemotas = await this.carregarHistoricoRemoto(sessaoStore);
          const normalizadas = vendasRemotas.map((venda, indice) => normalizarVendaHistorico(venda, indice));
          const mescladas = this.mesclarVendasTurnoAtual(normalizadas, sessaoStore);
          this.vendas = this.mesclarVendasPendentesLocais(mescladas);
        }
        await this.sincronizarSolicitacoesReversao();
      } catch (erro) {
        if (!isErroRedeOuIndisponivel(erro)) throw erro;
      }
      this.hidratarSolicitacoes();
      this.carregado = true;
    },
    async sincronizarSolicitacoesReversao() {
      if (!temApiConfigurada()) return;
      this.hidratarSolicitacoes();
      try {
        const remotas = await carregarSolicitacoesReversaoIntegrado();
        const maisRecentePorVenda = new Map();

        for (const item of remotas) {
          if (!item?.saleId) continue;
          const actual = maisRecentePorVenda.get(item.saleId);
          const dataItem = String(item.requestedAt || item.decidedAt || "");
          const dataActual = String(actual?.requestedAt || actual?.decidedAt || "");
          if (!actual || dataItem >= dataActual) {
            maisRecentePorVenda.set(item.saleId, item);
          }
        }

        for (const [saleId, item] of maisRecentePorVenda) {
          const estadoLocal = mapearEstadoReversaoRemota(item.status);
          let local = this.solicitacoesReversao.find((s) => s.vendaId === saleId);

          if (!local) {
            local = {
              id: item.id || Date.now(),
              idRemoto: item.id,
              vendaId: saleId,
              referencia: "",
              solicitadoPor: "",
              motivo: item.reason || "",
              estado: estadoLocal,
              dataSolicitacao: item.requestedAt || new Date().toISOString(),
            };
            this.solicitacoesReversao.unshift(local);
          } else {
            local.estado = estadoLocal;
            local.idRemoto = item.id || local.idRemoto;
            if (item.reason) local.motivo = item.reason;
          }

          if (item.status === "APPROVED") {
            const venda = this.vendas.find((v) => v.id === saleId);
            if (venda) venda.estado = "Revertida";
          }
        }

        for (const local of this.solicitacoesReversao) {
          if (local.estado !== "Pendente") continue;
          const remota = maisRecentePorVenda.get(local.vendaId);
          if (remota && remota.status !== "PENDING") {
            local.estado = mapearEstadoReversaoRemota(remota.status);
            if (remota.status === "APPROVED") {
              const venda = this.vendas.find((v) => v.id === local.vendaId);
              if (venda) venda.estado = "Revertida";
            }
          }
        }

        this.deduplicarSolicitacoesReversao();
        this.salvarSolicitacoes();
      } catch (erro) {
        if (!isErroRedeOuIndisponivel(erro)) throw erro;
      }
    },
    async carregarHistorico() {
      return this.sincronizarHistorico();
    },
    async registarVenda(novaVenda) {
      const sessaoStore = useSessaoStore();
      sessaoStore.hidratar();

      const id = novaVenda?.id || gerarIdLocal();
      const vendaNormalizada = {
        ...novaVenda,
        id,
        referencia: novaVenda.referencia || gerarReferenciaVenda({ ...novaVenda, id }),
        operador: novaVenda.operador || sessaoStore.utilizador || "",
        caixa: novaVenda.caixa || sessaoStore.caixaAtribuido || "",
        cashSessionId: novaVenda.cashSessionId || novaVenda.cash_session_id || sessaoStore.cashSessionId,
        registerId: novaVenda.registerId || novaVenda.register_id || sessaoStore.registerId,
        data: novaVenda.data || new Date().toISOString(),
        estado: "Concluida",
      };

      if (temApiConfigurada()) {
        try {
          const resposta = await criarVendaIntegrada(vendaNormalizada);
          const dadosApi = extrairVendaApi(resposta);
          const vendaRemota = mapearVenda({ ...vendaNormalizada, ...dadosApi });
          this.inserirOuActualizarVenda({
            ...vendaRemota,
            id: vendaRemota.id || id,
            referencia: vendaRemota.referencia || vendaNormalizada.referencia,
            cashSessionId: vendaRemota.cashSessionId || vendaNormalizada.cashSessionId,
            pendenteSync: false,
          });
          if (vendaRemota.cashSessionId && vendaRemota.cashSessionId !== sessaoStore.cashSessionId) {
            sessaoStore.cashSessionId = vendaRemota.cashSessionId;
            sessaoStore.salvar();
          }
          await this.sincronizarHistorico();
          return { modo: "online" };
        } catch (erro) {
          if (isErroNegocioVenda(erro) || !isErroRedeOuIndisponivel(erro)) {
            throw erro;
          }

          if (await vendaJaRegistadaNoServidor(id)) {
            await this.sincronizarHistorico();
            return { modo: "online-recuperado" };
          }

          enfileirarVendaPendente(vendaNormalizada);
          this.vendas.unshift({ ...vendaNormalizada, pendenteSync: true });
          return { modo: "offline" };
        }
      }

      this.vendas.unshift(vendaNormalizada);
      return { modo: "local" };
    },
    async solicitarReversao({ vendaId, referencia, solicitadoPor, motivo, venda }) {
      const vendaRef = venda || this.vendas.find((item) => item.id === vendaId) || { id: vendaId };
      const bloqueio = motivoBloqueioReversao(vendaRef, this.solicitacoesReversao);
      if (bloqueio) {
        return { ok: false, erro: bloqueio };
      }

      if (this.reversoesEmCurso[vendaId]) {
        return { ok: false, erro: t("api.reversalInProgress") };
      }

      this.reversoesEmCurso[vendaId] = true;
      try {
        if (temApiConfigurada()) {
          const remoto = await solicitarReversaoIntegrada({ venda_id: vendaId, reason: motivo || "" });
          if (remoto?.ok === false && remoto?.erro) {
            if (remoto.status === 409 || remoto.status === 404) {
              await this.sincronizarSolicitacoesReversao();
            }
            return { ok: false, erro: remoto.erro };
          }
          await this.sincronizarSolicitacoesReversao();
          return { ok: true };
        }

        this.solicitacoesReversao.unshift({
          id: Date.now(),
          vendaId,
          referencia,
          solicitadoPor,
          motivo: motivo || "",
          estado: "Pendente",
          dataSolicitacao: new Date().toISOString(),
        });
        this.deduplicarSolicitacoesReversao();
        this.salvarSolicitacoes();
        return { ok: true };
      } finally {
        delete this.reversoesEmCurso[vendaId];
      }
    },
    aprovarReversao(idSolicitacao, gerente) {
      const solicitacao = this.solicitacoesReversao.find((item) => item.id === idSolicitacao);
      if (!solicitacao || solicitacao.estado !== "Pendente") return;
      solicitacao.estado = "Aprovada";
      solicitacao.decisaoPor = gerente || "Gerente";
      solicitacao.dataDecisao = new Date().toISOString();
      const venda = this.vendas.find((item) => item.id === solicitacao.vendaId);
      if (venda) {
        venda.estado = "Revertida";
        venda.revertidaEm = new Date().toISOString();
      }
      this.salvarSolicitacoes();
    },
    cancelarReversao(idSolicitacao, gerente) {
      const solicitacao = this.solicitacoesReversao.find((item) => item.id === idSolicitacao);
      if (!solicitacao || solicitacao.estado !== "Pendente") return;
      solicitacao.estado = "Cancelada";
      solicitacao.decisaoPor = gerente || "Gerente";
      solicitacao.dataDecisao = new Date().toISOString();
      this.salvarSolicitacoes();
    },
  },
});
