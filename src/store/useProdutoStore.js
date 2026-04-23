import { defineStore } from "pinia";
import { carregarProdutosIntegrado } from "../services/integracaoApi";

function normalizarIva(valor) {
  const numero = Number(valor || 0);
  if (!Number.isFinite(numero)) return 0;
  return Math.max(0, Math.min(100, numero));
}

function normalizarIvaTipo(valor) {
  if (valor === "monetario") return "monetario";
  if (valor === "isento") return "isento";
  return "percentual";
}

function normalizarMonetario(valor) {
  const numero = Number(valor || 0);
  if (!Number.isFinite(numero)) return 0;
  return Math.max(0, Number(numero.toFixed(2)));
}

function calcularValorIvaUnitario(precoSemIva, ivaTipo, ivaValor) {
  const preco = Number(precoSemIva || 0);
  if (!Number.isFinite(preco) || preco < 0) return 0;
  if (ivaTipo === "isento") return 0;
  if (ivaTipo === "monetario") return normalizarMonetario(ivaValor);
  const percentual = normalizarIva(ivaValor);
  return Number((preco * (percentual / 100)).toFixed(2));
}

function normalizarProduto(produto) {
  const precoCompra = normalizarMonetario(produto?.precoCompra);
  const precoVenda = Number(produto?.precoVenda || 0);
  const ivaTipo = normalizarIvaTipo(produto?.ivaTipo);
  const ivaValorEntrada = produto?.ivaValor ?? produto?.ivaPercentual ?? 0;
  const ivaValor = ivaTipo === "percentual" ? normalizarIva(ivaValorEntrada) : normalizarMonetario(ivaValorEntrada);
  const valorIvaUnitario = calcularValorIvaUnitario(precoVenda, ivaTipo, ivaValor);
  const precoVendaComIva = Number((Math.max(0, Number(precoVenda || 0)) + valorIvaUnitario).toFixed(2));
  const ivaPercentual = ivaTipo === "percentual" ? ivaValor : 0;

  return {
    ...produto,
    precoCompra,
    precoVenda: Number.isFinite(precoVenda) ? precoVenda : 0,
    ivaTipo,
    ivaValor,
    ivaPercentual,
    valorIvaUnitario,
    precoVendaComIva,
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
