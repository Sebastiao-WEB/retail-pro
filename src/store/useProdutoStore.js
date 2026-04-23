import { defineStore } from "pinia";
import { obterProdutos } from "../services/dadosMockados";

export const useProdutoStore = defineStore("produtos", {
  state: () => ({
    produtos: [],
    carregado: false,
    emProcessamento: false,
  }),
  getters: {
    produtosComStockBaixo: (state) => state.produtos.filter((produto) => produto.stock <= 10),
    totalStock: (state) => state.produtos.reduce((acc, produto) => acc + produto.stock, 0),
  },
  actions: {
    async carregarProdutos() {
      if (this.carregado) return;
      this.emProcessamento = true;
      this.produtos = await obterProdutos();
      this.carregado = true;
      this.emProcessamento = false;
    },
    adicionarProduto(produto) {
      this.produtos.unshift({
        ...produto,
        id: Date.now(),
      });
    },
    atualizarProduto(produtoAtualizado) {
      const indice = this.produtos.findIndex((produto) => produto.id === produtoAtualizado.id);
      if (indice === -1) return;
      this.produtos[indice] = { ...produtoAtualizado };
    },
    aplicarVenda(itensVenda) {
      itensVenda.forEach((item) => {
        const produto = this.produtos.find((reg) => reg.id === item.produtoId);
        if (!produto) return;
        produto.stock = Math.max(0, produto.stock - item.quantidade);
      });
    },
    reporStock(produtoId, quantidade) {
      const produto = this.produtos.find((reg) => reg.id === produtoId);
      if (!produto) return;
      produto.stock += quantidade;
    },
  },
});
