import { defineStore } from "pinia";
import { carregarProdutosIntegrado } from "../services/integracaoApi";

function normalizarIva(valor) {
  const numero = Number(valor || 0);
  if (!Number.isFinite(numero)) return 0;
  return Math.max(0, Math.min(100, numero));
}

function calcularPrecoComIva(precoSemIva, ivaPercentual) {
  const preco = Number(precoSemIva || 0);
  if (!Number.isFinite(preco)) return 0;
  return Number((preco * (1 + ivaPercentual / 100)).toFixed(2));
}

function normalizarProduto(produto) {
  const precoVenda = Number(produto?.precoVenda || 0);
  const ivaPercentual = normalizarIva(produto?.ivaPercentual);
  return {
    ...produto,
    precoVenda: Number.isFinite(precoVenda) ? precoVenda : 0,
    ivaPercentual,
    precoVendaComIva: calcularPrecoComIva(precoVenda, ivaPercentual),
  };
}

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
      const produtos = await carregarProdutosIntegrado();
      this.produtos = produtos.map((produto) => normalizarProduto(produto));
      this.carregado = true;
      this.emProcessamento = false;
    },
    adicionarProduto(produto) {
      this.produtos.unshift({
        ...normalizarProduto(produto),
        id: Date.now(),
      });
    },
    atualizarProduto(produtoAtualizado) {
      const indice = this.produtos.findIndex((produto) => produto.id === produtoAtualizado.id);
      if (indice === -1) return;
      this.produtos[indice] = normalizarProduto(produtoAtualizado);
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
