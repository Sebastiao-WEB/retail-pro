import { defineStore } from "pinia";
import { temApiConfigurada } from "../api";
import {
  carregarHistoricoIntegrado,
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
      } catch (erro) {
        if (!isErroRedeOuIndisponivel(erro)) throw erro;
      }
      this.hidratarSolicitacoes();
      this.carregado = true;
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
    async solicitarReversao({ vendaId, referencia, solicitadoPor, motivo }) {
      const existePendente = this.solicitacoesReversao.some((item) => item.vendaId === vendaId && item.estado === "Pendente");
      if (existePendente) {
        return { ok: false, erro: t("api.reversalAlreadyPending") };
      }
      const remoto = await solicitarReversaoIntegrada({ venda_id: vendaId, reason: motivo || "" });
      if (remoto?.ok === false && remoto?.erro) {
        return { ok: false, erro: remoto.erro };
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
      this.salvarSolicitacoes();
      return { ok: true };
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
