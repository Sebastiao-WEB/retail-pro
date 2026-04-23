import { defineStore } from "pinia";
import { obterCompras, obterVendas } from "../services/dadosMockados";

export const useVendaStore = defineStore("vendas", {
  state: () => ({
    vendas: [],
    compras: [],
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
  },
  actions: {
    async carregarHistorico() {
      if (this.carregado) return;
      const [vendas, compras] = await Promise.all([obterVendas(), obterCompras()]);
      this.vendas = vendas;
      this.compras = compras;
      this.carregado = true;
    },
    registarVenda(novaVenda) {
      this.vendas.unshift({
        ...novaVenda,
        id: Date.now(),
      });
    },
    registarCompra(novaCompra) {
      this.compras.unshift({
        ...novaCompra,
        id: Date.now(),
      });
    },
  },
});
