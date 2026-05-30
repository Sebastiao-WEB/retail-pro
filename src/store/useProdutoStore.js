import { defineStore } from "pinia";
import { temApiConfigurada } from "../api";
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
    resultadosPesquisa: [],
    carregado: false,
    emProcessamento: false,
    pesquisaEmCurso: false,
  }),
  getters: {
    produtosComStockBaixo: (state) => state.produtos.filter((produto) => produto.stock <= 10),
    totalStock: (state) => state.produtos.reduce((acc, produto) => acc + produto.stock, 0),
  },
  actions: {
    async sincronizarProdutos(filtros = {}) {
      this.emProcessamento = true;
      try {
        const produtos = await carregarProdutosIntegrado(filtros);
        this.produtos = produtos.map((produto) => normalizarProduto(produto));
        this.carregado = true;
      } finally {
        this.emProcessamento = false;
      }
    },
    async carregarProdutos(filtros = {}) {
      return this.sincronizarProdutos(filtros);
    },
    limparPesquisa() {
      this.resultadosPesquisa = [];
      this.pesquisaEmCurso = false;
    },
    mesclarProdutosConsultados(produtos) {
      produtos.forEach((produto) => {
        const indice = this.produtos.findIndex((reg) => reg.id === produto.id);
        if (indice === -1) {
          this.produtos.push(produto);
          return;
        }
        this.produtos[indice] = produto;
      });
    },
    async buscarProdutos(filtros = {}) {
      const termo = String(filtros.search || "").trim();
      if (!termo) {
        this.limparPesquisa();
        return [];
      }

      this.pesquisaEmCurso = true;
      try {
        let produtos = await carregarProdutosIntegrado(filtros);
        if (!temApiConfigurada()) {
          const consulta = termo.toLowerCase();
          produtos = produtos.filter((produto) =>
            `${produto.nome || ""} ${produto.codigoBarras || ""}`.toLowerCase().includes(consulta)
          );
        }
        const normalizados = produtos.map((produto) => normalizarProduto(produto));
        this.resultadosPesquisa = normalizados.slice(0, 5);
        this.mesclarProdutosConsultados(normalizados);
        return this.resultadosPesquisa;
      } finally {
        this.pesquisaEmCurso = false;
      }
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
