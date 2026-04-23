import { defineStore } from "pinia";

export const useCarrinhoStore = defineStore("carrinho", {
  state: () => ({
    itens: [],
    metodoPagamento: "Dinheiro",
    descontoTipo: "valor",
    descontoValor: 0,
  }),
  getters: {
    subtotal: (state) => state.itens.reduce((acc, item) => acc + item.subtotal, 0),
    valorDesconto: (state) => {
      const subtotal = state.itens.reduce((acc, item) => acc + item.subtotal, 0);
      const descontoBase = Number(state.descontoValor || 0);
      if (!descontoBase || subtotal <= 0) return 0;
      if (state.descontoTipo === "percentual") {
        const percentual = Math.max(0, Math.min(100, descontoBase));
        return subtotal * (percentual / 100);
      }
      return Math.max(0, Math.min(subtotal, descontoBase));
    },
    total: (state) => {
      const subtotal = state.itens.reduce((acc, item) => acc + item.subtotal, 0);
      const descontoBase = Number(state.descontoValor || 0);
      if (!descontoBase || subtotal <= 0) return subtotal;
      if (state.descontoTipo === "percentual") {
        const percentual = Math.max(0, Math.min(100, descontoBase));
        return subtotal - subtotal * (percentual / 100);
      }
      return subtotal - Math.max(0, Math.min(subtotal, descontoBase));
    },
    quantidadeItens: (state) => state.itens.reduce((acc, item) => acc + item.quantidade, 0),
  },
  actions: {
    adicionarProduto(produto) {
      const ivaPercentual = Number(produto.ivaPercentual || 0);
      const precoUnitario = Number(produto.precoVendaComIva ?? produto.precoVenda ?? 0);
      const valorIvaUnitario = Number((precoUnitario - Number(produto.precoVenda || 0)).toFixed(2));
      const itemExistente = this.itens.find((item) => item.produtoId === produto.id);
      if (itemExistente) {
        itemExistente.quantidade += 1;
        itemExistente.subtotal = itemExistente.quantidade * itemExistente.precoVenda;
        return;
      }

      this.itens.push({
        produtoId: produto.id,
        nome: produto.nome,
        precoVenda: precoUnitario,
        precoSemIva: Number(produto.precoVenda || 0),
        ivaPercentual: Number.isFinite(ivaPercentual) ? ivaPercentual : 0,
        valorIvaUnitario: Number.isFinite(valorIvaUnitario) ? valorIvaUnitario : 0,
        quantidade: 1,
        subtotal: precoUnitario,
      });
    },
    removerProduto(produtoId) {
      this.itens = this.itens.filter((item) => item.produtoId !== produtoId);
    },
    aumentarQuantidade(produtoId) {
      const item = this.itens.find((reg) => reg.produtoId === produtoId);
      if (!item) return;
      item.quantidade += 1;
      item.subtotal = item.quantidade * item.precoVenda;
    },
    diminuirQuantidade(produtoId) {
      const item = this.itens.find((reg) => reg.produtoId === produtoId);
      if (!item) return;
      if (item.quantidade <= 1) {
        this.removerProduto(produtoId);
        return;
      }
      item.quantidade -= 1;
      item.subtotal = item.quantidade * item.precoVenda;
    },
    definirQuantidade(produtoId, quantidade) {
      const item = this.itens.find((reg) => reg.produtoId === produtoId);
      if (!item) return;
      const quantidadeNormalizada = Number.isFinite(quantidade) ? Math.floor(quantidade) : 1;
      if (quantidadeNormalizada <= 0) {
        this.removerProduto(produtoId);
        return;
      }
      item.quantidade = quantidadeNormalizada;
      item.subtotal = item.quantidade * item.precoVenda;
    },
    definirMetodoPagamento(valor) {
      this.metodoPagamento = valor;
    },
    definirDesconto({ tipo, valor }) {
      this.descontoTipo = tipo === "percentual" ? "percentual" : "valor";
      const numero = Number(valor || 0);
      this.descontoValor = Number.isFinite(numero) ? numero : 0;
    },
    limparCarrinho() {
      this.itens = [];
      this.metodoPagamento = "Dinheiro";
      this.descontoTipo = "valor";
      this.descontoValor = 0;
    },
  },
});
