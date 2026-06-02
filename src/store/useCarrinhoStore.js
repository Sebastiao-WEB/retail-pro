import { defineStore } from "pinia";
import { resolverIvaPercentualExibicao } from "../utils/ivaItem.js";
import {
  normalizarQuantidadeVenda,
  normalizarUnidadeVenda,
  passoQuantidade,
  quantidadeMinima,
} from "../utils/produtoQuantidade";

function calcularSubtotal(quantidade, precoVenda) {
  return Number((quantidade * precoVenda).toFixed(2));
}

export const useCarrinhoStore = defineStore("carrinho", {
  state: () => ({
    itens: [],
    sequenciaAdicao: 0,
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
    adicionarProduto(produto, quantidade = 1) {
      const unidadeVenda = normalizarUnidadeVenda(produto?.unidadeVenda);
      const precoSemIva = Number(produto.precoVenda || 0);
      const precoUnitario = Number(produto.precoVendaComIva ?? precoSemIva);
      const valorIvaUnitario = Number(
        produto.valorIvaUnitario ?? Number((precoUnitario - precoSemIva).toFixed(2))
      );
      const ivaPercentual = resolverIvaPercentualExibicao({
        ivaTipo: produto.ivaTipo,
        ivaPercentual: produto.ivaPercentual,
        valorIvaUnitario,
        precoSemIva,
        precoVenda: precoUnitario,
      });
      const quantidadeNormalizada =
        normalizarQuantidadeVenda(quantidade, unidadeVenda) ?? quantidadeMinima(unidadeVenda);

      const itemExistente = this.itens.find((item) => item.produtoId === produto.id);
      if (itemExistente) {
        const novaQuantidade = normalizarQuantidadeVenda(
          itemExistente.quantidade + quantidadeNormalizada,
          unidadeVenda
        );
        if (!novaQuantidade) return;
        itemExistente.quantidade = novaQuantidade;
        itemExistente.subtotal = calcularSubtotal(novaQuantidade, itemExistente.precoVenda);
        itemExistente.ordemAdicao = ++this.sequenciaAdicao;
        this.itens = [itemExistente, ...this.itens.filter((item) => item.produtoId !== produto.id)];
        return;
      }

      this.itens.unshift({
        produtoId: produto.id,
        nome: produto.nome,
        unidadeVenda,
        precoVenda: precoUnitario,
        precoSemIva,
        ivaTipo: produto.ivaTipo || "isento",
        ivaPercentual,
        valorIvaUnitario,
        ordemAdicao: ++this.sequenciaAdicao,
        quantidade: quantidadeNormalizada,
        subtotal: calcularSubtotal(quantidadeNormalizada, precoUnitario),
      });
    },
    removerProduto(produtoId) {
      this.itens = this.itens.filter((item) => item.produtoId !== produtoId);
    },
    aumentarQuantidade(produtoId) {
      const item = this.itens.find((reg) => reg.produtoId === produtoId);
      if (!item) return;
      const passo = passoQuantidade(item.unidadeVenda);
      const nova = normalizarQuantidadeVenda(item.quantidade + passo, item.unidadeVenda);
      if (!nova) return;
      item.quantidade = nova;
      item.subtotal = calcularSubtotal(nova, item.precoVenda);
    },
    diminuirQuantidade(produtoId) {
      const item = this.itens.find((reg) => reg.produtoId === produtoId);
      if (!item) return;
      const passo = passoQuantidade(item.unidadeVenda);
      const minimo = quantidadeMinima(item.unidadeVenda);
      if (item.quantidade - passo < minimo) {
        this.removerProduto(produtoId);
        return;
      }
      const nova = normalizarQuantidadeVenda(item.quantidade - passo, item.unidadeVenda);
      if (!nova) {
        this.removerProduto(produtoId);
        return;
      }
      item.quantidade = nova;
      item.subtotal = calcularSubtotal(nova, item.precoVenda);
    },
    definirQuantidade(produtoId, quantidade) {
      const item = this.itens.find((reg) => reg.produtoId === produtoId);
      if (!item) return;
      const quantidadeNormalizada = normalizarQuantidadeVenda(quantidade, item.unidadeVenda);
      if (!quantidadeNormalizada) {
        this.removerProduto(produtoId);
        return;
      }
      item.quantidade = quantidadeNormalizada;
      item.subtotal = calcularSubtotal(quantidadeNormalizada, item.precoVenda);
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
      this.sequenciaAdicao = 0;
      this.metodoPagamento = "Dinheiro";
      this.descontoTipo = "valor";
      this.descontoValor = 0;
    },
  },
});
