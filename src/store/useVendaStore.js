import { defineStore } from "pinia";
import { obterCompras, obterVendas } from "../services/dadosMockados";

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
      const [vendas, compras] = await Promise.all([obterVendas(), obterCompras()]);
      this.vendas = vendas.map((venda, indice) => ({
        ...venda,
        referencia: venda.referencia || gerarReferenciaVenda(venda, indice),
        estado: venda.estado || "Concluida",
      }));
      this.compras = compras;
      this.hidratarSolicitacoes();
      this.carregado = true;
    },
    registarVenda(novaVenda) {
      const id = Date.now();
      this.vendas.unshift({
        ...novaVenda,
        id,
        referencia: novaVenda.referencia || gerarReferenciaVenda({ ...novaVenda, id }),
        estado: "Concluida",
      });
    },
    solicitarReversao({ vendaId, referencia, solicitadoPor, motivo }) {
      const existePendente = this.solicitacoesReversao.some((item) => item.vendaId === vendaId && item.estado === "Pendente");
      if (existePendente) {
        return { ok: false, erro: "Já existe uma solicitação pendente para esta venda." };
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
