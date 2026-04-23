import { defineStore } from "pinia";
import {
  carregarHistoricoIntegrado,
  criarVendaIntegrada,
  solicitarReversaoIntegrada,
} from "../services/integracaoApi";

const CHAVE_REVERSOES = "retailpro:reversoes-venda";

function gerarReferenciaVenda(venda, indice = 0) {
  const data = new Date(venda?.data || Date.now());
  const ano = data.getFullYear();
  const mes = String(data.getMonth() + 1).padStart(2, "0");
  const dia = String(data.getDate()).padStart(2, "0");
  const idBase = Number(venda?.id || Date.now() + indice);
  const sequencia = String(Math.abs(idBase) % 100000).padStart(5, "0");
  return `VD-${ano}${mes}${dia}-${sequencia}`;
}

export const useVendaStore = defineStore("vendas", {
  state: () => ({
    vendas: [],
    compras: [],
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
    async carregarHistorico() {
      if (this.carregado) return;
      const { vendas, compras } = await carregarHistoricoIntegrado();
      this.vendas = vendas.map((venda, indice) => ({
        ...venda,
        referencia: venda.referencia || gerarReferenciaVenda(venda, indice),
        estado: venda.estado || "Concluida",
      }));
      this.compras = compras;
      this.hidratarSolicitacoes();
      this.carregado = true;
    },
    async registarVenda(novaVenda) {
      const id = Date.now();
      const vendaLocal = {
        ...novaVenda,
        id,
        referencia: novaVenda.referencia || gerarReferenciaVenda({ ...novaVenda, id }),
        estado: "Concluida",
      };
      this.vendas.unshift(vendaLocal);
      await criarVendaIntegrada(vendaLocal);
    },
    async solicitarReversao({ vendaId, referencia, solicitadoPor, motivo }) {
      const existePendente = this.solicitacoesReversao.some((item) => item.vendaId === vendaId && item.estado === "Pendente");
      if (existePendente) {
        return { ok: false, erro: "Já existe uma solicitação pendente para esta venda." };
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
    registarCompra(novaCompra) {
      this.compras.unshift({
        ...novaCompra,
        id: Date.now(),
      });
    },
  },
});
