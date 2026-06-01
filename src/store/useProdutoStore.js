import { defineStore } from "pinia";
import { temApiConfigurada } from "../api";
import { carregarProdutosIntegrado, consultarStockRemotoIntegrado } from "../services/integracaoApi";
import {
  carregarCatalogoOffline,
  limparCatalogosOffline,
  salvarCatalogoOffline,
} from "../services/offline/catalogCache";
import { isErroRedeOuIndisponivel, redeDisponivel } from "../services/offline/networkError";
import { normalizarQuantidadeVenda, normalizarUnidadeVenda } from "../utils/produtoQuantidade";

function normalizarIva(valor) {
  const numero = Number(valor || 0);
  if (!Number.isFinite(numero)) return 0;
  return Math.max(0, Math.min(100, numero));
}

function normalizarIvaTipo(valor) {
  const texto = String(valor || "").toLowerCase();
  if (texto === "monetario" || texto === "monetário") return "monetario";
  if (texto === "isento") return "isento";
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
    unidadeVenda: normalizarUnidadeVenda(produto?.unidadeVenda ?? produto?.unidade_venda),
    precoCompra,
    precoVenda: Number.isFinite(precoVenda) ? precoVenda : 0,
    ivaTipo,
    ivaValor,
    ivaPercentual,
    valorIvaUnitario,
    precoVendaComIva,
  };
}

function chaveCatalogoPos(filtros = {}) {
  return String(filtros.source_location_id || filtros.location_id || "global");
}

export const useProdutoStore = defineStore("produtos", {
  state: () => ({
    produtos: [],
    resultadosPesquisa: [],
    carregado: false,
    emProcessamento: false,
    pesquisaEmCurso: false,
    catalogoPosChave: null,
    indiceCodigosBarras: {},
  }),
  getters: {
    produtosComStockBaixo: (state) => state.produtos.filter((produto) => produto.stock <= 10),
    totalStock: (state) => state.produtos.reduce((acc, produto) => acc + produto.stock, 0),
    catalogoPosPronto: (state) => state.carregado && state.produtos.length > 0,
  },
  actions: {
    reconstruirIndiceCodigosBarras() {
      const indice = {};
      for (const produto of this.produtos) {
        const codigo = String(produto.codigoBarras || "").trim();
        if (codigo) indice[codigo] = produto;
      }
      this.indiceCodigosBarras = indice;
    },
    mesclarProdutosConsultados(produtos) {
      produtos.forEach((produto) => {
        const normalizado = normalizarProduto(produto);
        const indice = this.produtos.findIndex((reg) => reg.id === normalizado.id);
        if (indice === -1) {
          this.produtos.push(normalizado);
          return;
        }
        this.produtos[indice] = normalizado;
      });
      this.reconstruirIndiceCodigosBarras();
    },
    aplicarCatalogoLocal(produtos, filtros = {}) {
      this.produtos = produtos.map((produto) => normalizarProduto(produto));
      this.catalogoPosChave = chaveCatalogoPos(filtros);
      this.reconstruirIndiceCodigosBarras();
      this.carregado = true;
      return this.produtos;
    },
    limparCatalogoPos() {
      this.produtos = [];
      this.resultadosPesquisa = [];
      this.carregado = false;
      this.catalogoPosChave = null;
      this.indiceCodigosBarras = {};
      limparCatalogosOffline();
    },
    carregarCatalogoDeCache(filtros = {}) {
      const cache = carregarCatalogoOffline(filtros);
      if (!cache?.produtos?.length) return null;
      return this.aplicarCatalogoLocal(cache.produtos, filtros);
    },
    async sincronizarProdutos(filtros = {}) {
      this.emProcessamento = true;
      try {
        const produtos = await carregarProdutosIntegrado(filtros);
        this.aplicarCatalogoLocal(produtos, filtros);
        salvarCatalogoOffline(filtros, this.produtos);
        return this.produtos;
      } catch (erro) {
        const cache = this.carregarCatalogoDeCache(filtros);
        if (cache && isErroRedeOuIndisponivel(erro)) {
          return cache;
        }
        throw erro;
      } finally {
        this.emProcessamento = false;
      }
    },
    async carregarProdutos(filtros = {}) {
      return this.sincronizarProdutos(filtros);
    },
    async garantirCatalogoPos(filtros = {}, { forcar = false } = {}) {
      const chave = chaveCatalogoPos(filtros);
      if (!forcar && this.catalogoPosPronto && this.catalogoPosChave === chave) {
        return this.produtos;
      }
      return this.sincronizarProdutos(filtros);
    },
    limparPesquisa() {
      this.resultadosPesquisa = [];
      this.pesquisaEmCurso = false;
    },
    resolverPorCodigoBarras(codigo) {
      const normalizado = String(codigo || "").trim();
      if (!normalizado) return null;
      return this.indiceCodigosBarras[normalizado] || null;
    },
    pesquisarLocalmente(termo, limite = 5) {
      const consulta = String(termo || "").trim().toLowerCase();
      if (!consulta) return [];

      return this.produtos
        .filter((produto) => `${produto.nome || ""} ${produto.codigoBarras || ""}`.toLowerCase().includes(consulta))
        .slice(0, limite)
        .map((produto) => normalizarProduto(produto));
    },
    async resolverPorCodigoBarrasComFallback(codigo, filtros = {}) {
      const normalizado = String(codigo || "").trim();
      if (!normalizado) return null;

      const local = this.resolverPorCodigoBarras(normalizado);
      if (local) return local;

      if (!temApiConfigurada()) return null;

      try {
        const produtos = await carregarProdutosIntegrado({
          ...filtros,
          barcode: normalizado,
        });
        const normalizados = produtos.map((produto) => normalizarProduto(produto));
        if (normalizados.length) {
          this.mesclarProdutosConsultados(normalizados);
        }
      } catch (erro) {
        if (!isErroRedeOuIndisponivel(erro)) throw erro;
      }
      return this.resolverPorCodigoBarras(normalizado);
    },
    async buscarProdutos(filtros = {}) {
      const termo = String(filtros.search || "").trim();
      if (!termo) {
        this.limparPesquisa();
        return [];
      }

      this.pesquisaEmCurso = true;
      try {
        const podeUsarCacheLocal =
          this.catalogoPosPronto &&
          this.catalogoPosChave === chaveCatalogoPos(filtros) &&
          (!temApiConfigurada() || !redeDisponivel());

        if (podeUsarCacheLocal) {
          this.resultadosPesquisa = this.pesquisarLocalmente(termo);
          return this.resultadosPesquisa;
        }

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
        } catch (erro) {
          if (this.catalogoPosPronto && isErroRedeOuIndisponivel(erro)) {
            this.resultadosPesquisa = this.pesquisarLocalmente(termo);
            return this.resultadosPesquisa;
          }
          throw erro;
        }
      } finally {
        this.pesquisaEmCurso = false;
      }
    },
    async atualizarStockRemoto(productIds, filtros = {}) {
      const ids = [...new Set((productIds || []).filter(Boolean))];
      const locationId = filtros.source_location_id || filtros.location_id;
      if (!temApiConfigurada() || !locationId || !ids.length) {
        return;
      }

      let stocks = {};
      try {
        stocks = await consultarStockRemotoIntegrado({
          location_id: locationId,
          product_ids: ids,
        });
      } catch (erro) {
        if (!isErroRedeOuIndisponivel(erro)) throw erro;
        return;
      }

      Object.entries(stocks).forEach(([productId, entrada]) => {
        const stock = Number(entrada?.quantity ?? entrada ?? 0);
        const stockVersion = entrada?.version ? String(entrada.version) : null;
        const produto = this.produtos.find((reg) => reg.id === productId);
        if (produto) {
          produto.stock = stock;
          produto.stockVersion = stockVersion;
        }
        const resultado = this.resultadosPesquisa.find((reg) => reg.id === productId);
        if (resultado) {
          resultado.stock = stock;
          resultado.stockVersion = stockVersion;
        }
      });
    },
    adicionarProduto(produto) {
      this.produtos.unshift(normalizarProduto(produto));
      this.reconstruirIndiceCodigosBarras();
    },
    atualizarProduto(produtoAtualizado) {
      const indice = this.produtos.findIndex((produto) => produto.id === produtoAtualizado.id);
      if (indice === -1) return;
      this.produtos[indice] = normalizarProduto(produtoAtualizado);
      this.reconstruirIndiceCodigosBarras();
    },
    aplicarVenda(itensVenda) {
      itensVenda.forEach((item) => {
        const produto = this.produtos.find((reg) => reg.id === item.produtoId);
        if (!produto) return;
        const quantidade = normalizarQuantidadeVenda(item.quantidade, produto.unidadeVenda) ?? 0;
        if (quantidade <= 0) return;
        const novoStock = Math.max(0, produto.stock - quantidade);
        produto.stock =
          normalizarUnidadeVenda(produto.unidadeVenda) === "KG"
            ? Math.round(novoStock * 1000) / 1000
            : novoStock;
      });
    },
    reporStock(produtoId, quantidade) {
      const produto = this.produtos.find((reg) => reg.id === produtoId);
      if (!produto) return;
      produto.stock += quantidade;
    },
  },
});
