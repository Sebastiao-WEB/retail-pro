import { defineStore } from "pinia";
import { mesasApi, temApiConfigurada, ApiError } from "../api";
import { isErroRedeOuIndisponivel } from "../services/offline/networkError";
import { useSessaoStore } from "./useSessaoStore";
import { resolverIvaPercentualExibicao } from "../utils/ivaItem.js";
import {
  normalizarQuantidadeVenda,
  normalizarUnidadeVenda,
  quantidadeMinima,
} from "../utils/produtoQuantidade";

const CHAVE_MESAS = "retailpro:mesas";

function gerarIdLocal() {
  if (typeof globalThis.crypto?.randomUUID === "function") {
    return globalThis.crypto.randomUUID();
  }
  return `local-${Date.now()}-${Math.floor(Math.random() * 100000)}`;
}

function calcularSubtotal(quantidade, precoVenda) {
  return Number((quantidade * precoVenda).toFixed(2));
}

/** Backend remoto ainda sem módulo Mesas (404) ou indisponível — usa apenas localStorage. */
function ignorarErroSyncMesas(erro) {
  if (isErroRedeOuIndisponivel(erro)) return true;
  return erro instanceof ApiError && erro.status === 404;
}

function carregarEstado() {
  try {
    const raw = localStorage.getItem(CHAVE_MESAS);
    if (!raw) return { mesas: [], pedidos: {} };
    const parsed = JSON.parse(raw);
    return {
      mesas: Array.isArray(parsed.mesas) ? parsed.mesas : [],
      pedidos: parsed.pedidos && typeof parsed.pedidos === "object" ? parsed.pedidos : {},
    };
  } catch {
    return { mesas: [], pedidos: {} };
  }
}

function mapearMesaRemota(mesa) {
  return {
    id: mesa.id,
    codigo: mesa.code,
    nome: mesa.name || "",
    descricao: mesa.description || "",
    registerId: mesa.registerId,
    ocupada: !!mesa.occupied,
    pedidoAbertoId: mesa.openOrderId || null,
  };
}

function mapearPedidoRemoto(pedido) {
  if (!pedido) return null;
  return {
    id: pedido.id,
    mesaId: pedido.diningTableId,
    mesaCodigo: pedido.mesaCodigo || "",
    descricao: pedido.description || "",
    itens: (pedido.itens || []).map((item) => ({
      id: item.id,
      produtoId: item.produtoId,
      nome: item.nome,
      quantidade: Number(item.quantidade || 0),
      precoVenda: Number(item.precoVenda || 0),
      precoSemIva: item.precoSemIva != null ? Number(item.precoSemIva) : null,
      ivaTipo: item.ivaTipo || "isento",
      ivaPercentual: item.ivaPercentual != null ? Number(item.ivaPercentual) : 0,
      valorIvaUnitario: item.valorIvaUnitario != null ? Number(item.valorIvaUnitario) : 0,
      subtotal: Number(item.subtotal || 0),
    })),
    abertoEm: pedido.openedAt || new Date().toISOString(),
    status: pedido.status || "OPEN",
    pendenteSync: false,
  };
}

export const useMesaStore = defineStore("mesas", {
  state: () => {
    const estado = carregarEstado();
    return {
      mesas: estado.mesas,
      pedidos: estado.pedidos,
      mesaSeleccionadaId: null,
      aCarregar: false,
    };
  },
  getters: {
    mesaSeleccionada(state) {
      return state.mesas.find((mesa) => mesa.id === state.mesaSeleccionadaId) || null;
    },
    pedidoSeleccionado(state) {
      const mesaId = state.mesaSeleccionadaId;
      if (!mesaId) return null;
      return state.pedidos[mesaId] || null;
    },
    mesasOrdenadas(state) {
      return [...state.mesas].sort((a, b) => String(a.codigo).localeCompare(String(b.codigo)));
    },
    subtotalPedido() {
      const pedido = this.pedidoSeleccionado;
      if (!pedido) return 0;
      return pedido.itens.reduce((acc, item) => acc + Number(item.subtotal || 0), 0);
    },
    totalPedido() {
      return this.subtotalPedido;
    },
    mesasLivres() {
      return this.mesasOrdenadas.filter((mesa) => !this.pedidos[mesa.id]);
    },
    mesasOcupadas() {
      return this.mesasOrdenadas.filter((mesa) => !!this.pedidos[mesa.id]);
    },
  },
  actions: {
    salvar() {
      localStorage.setItem(
        CHAVE_MESAS,
        JSON.stringify({ mesas: this.mesas, pedidos: this.pedidos })
      );
    },
    seleccionarMesa(mesaId) {
      this.mesaSeleccionadaId = mesaId || null;
    },
    obterPedidoPorMesa(mesaId) {
      return this.pedidos[mesaId] || null;
    },
    actualizarOcupacaoMesas() {
      this.mesas = this.mesas.map((mesa) => ({
        ...mesa,
        ocupada: !!this.pedidos[mesa.id],
        pedidoAbertoId: this.pedidos[mesa.id]?.id || null,
      }));
      this.salvar();
    },
    async carregarMesas() {
      if (this.aCarregar) return;
      this.aCarregar = true;
      try {
        if (!temApiConfigurada()) {
          this.actualizarOcupacaoMesas();
          return;
        }

        const sessaoStore = useSessaoStore();
        sessaoStore.hidratar();
        const resposta = await mesasApi.listarMesas({ registerId: sessaoStore.registerId });
        const mesasRemotas = Array.isArray(resposta?.data) ? resposta.data : [];
        const mapaRemoto = new Map(mesasRemotas.map((mesa) => [mesa.id, mapearMesaRemota(mesa)]));

        const locais = this.mesas.filter((mesa) => !mapaRemoto.has(mesa.id));
        this.mesas = [...locais, ...mapaRemoto.values()];

        const pedidosResposta = await mesasApi.listarPedidos({
          registerId: sessaoStore.registerId,
          status: "OPEN",
        });
        const pedidosRemotos = Array.isArray(pedidosResposta?.data) ? pedidosResposta.data : [];
        const pedidosMap = { ...this.pedidos };

        for (const pedido of pedidosRemotos) {
          const normalizado = mapearPedidoRemoto(pedido);
          if (!normalizado?.mesaId) continue;
          const local = pedidosMap[normalizado.mesaId];
          if (!local || !local.pendenteSync) {
            pedidosMap[normalizado.mesaId] = normalizado;
          }
        }

        this.pedidos = pedidosMap;
        this.actualizarOcupacaoMesas();
      } catch (erro) {
        if (!ignorarErroSyncMesas(erro)) throw erro;
        this.actualizarOcupacaoMesas();
      } finally {
        this.aCarregar = false;
      }
    },
    async criarMesa({ codigo, nome = "", descricao = "" }) {
      const codigoNormalizado = String(codigo || "").trim().toUpperCase();
      if (!codigoNormalizado) throw new Error("Código da mesa é obrigatório.");
      if (this.mesas.some((mesa) => mesa.codigo === codigoNormalizado)) {
        throw new Error("Já existe uma mesa com este código.");
      }

      const sessaoStore = useSessaoStore();
      sessaoStore.hidratar();
      const nova = {
        id: gerarIdLocal(),
        codigo: codigoNormalizado,
        nome: String(nome || "").trim(),
        descricao: String(descricao || "").trim(),
        registerId: sessaoStore.registerId,
        ocupada: false,
        pedidoAbertoId: null,
      };

      if (temApiConfigurada()) {
        try {
          const resposta = await mesasApi.criarMesa({
            id: nova.id,
            code: nova.codigo,
            name: nova.nome || null,
            description: nova.descricao || null,
            registerId: sessaoStore.registerId,
          });
          if (resposta?.data) {
            Object.assign(nova, mapearMesaRemota(resposta.data));
          }
        } catch (erro) {
          if (!ignorarErroSyncMesas(erro)) throw erro;
        }
      }

      this.mesas.push(nova);
      this.salvar();
      return nova;
    },
    abrirPedido(mesaId, descricao = "") {
      if (this.pedidos[mesaId]) {
        throw new Error("Esta mesa já tem uma comanda aberta.");
      }

      const mesa = this.mesas.find((item) => item.id === mesaId);
      if (!mesa) throw new Error("Mesa não encontrada.");

      const pedido = {
        id: gerarIdLocal(),
        mesaId,
        mesaCodigo: mesa.codigo,
        descricao: String(descricao || "").trim(),
        itens: [],
        abertoEm: new Date().toISOString(),
        status: "OPEN",
        pendenteSync: true,
      };

      this.pedidos = { ...this.pedidos, [mesaId]: pedido };
      this.actualizarOcupacaoMesas();
      void this.sincronizarPedidoRemoto(pedido);
      return pedido;
    },
    adicionarProduto(mesaId, produto, quantidade = 1) {
      const pedido = this.pedidos[mesaId];
      if (!pedido) throw new Error("Abra a mesa antes de adicionar produtos.");

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

      const itemExistente = pedido.itens.find((item) => item.produtoId === produto.id);
      if (itemExistente) {
        const novaQuantidade = normalizarQuantidadeVenda(
          itemExistente.quantidade + quantidadeNormalizada,
          unidadeVenda
        );
        if (!novaQuantidade) return;
        itemExistente.quantidade = novaQuantidade;
        itemExistente.subtotal = calcularSubtotal(novaQuantidade, itemExistente.precoVenda);
      } else {
        pedido.itens.push({
          id: gerarIdLocal(),
          produtoId: produto.id,
          nome: produto.nome,
          unidadeVenda,
          quantidade: quantidadeNormalizada,
          precoVenda: precoUnitario,
          precoSemIva,
          ivaTipo: produto.ivaTipo || "isento",
          ivaPercentual,
          valorIvaUnitario,
          subtotal: calcularSubtotal(quantidadeNormalizada, precoUnitario),
        });
      }

      pedido.pendenteSync = true;
      this.pedidos = { ...this.pedidos, [mesaId]: { ...pedido } };
      this.salvar();
      void this.sincronizarPedidoRemoto(pedido);
    },
    removerItem(mesaId, itemId) {
      const pedido = this.pedidos[mesaId];
      if (!pedido) return;

      pedido.itens = pedido.itens.filter((item) => item.id !== itemId);
      pedido.pendenteSync = true;
      this.pedidos = { ...this.pedidos, [mesaId]: { ...pedido } };
      this.salvar();
      void this.sincronizarPedidoRemoto(pedido);
      void this.removerItemRemoto(pedido.id, itemId);
    },
    transferirItens({ deMesaId, paraMesaId, itemIds = [] }) {
      if (deMesaId === paraMesaId) throw new Error("Seleccione uma mesa de destino diferente.");

      const pedidoOrigem = this.pedidos[deMesaId];
      if (!pedidoOrigem) throw new Error("A mesa de origem não tem comanda aberta.");

      const mesaDestino = this.mesas.find((mesa) => mesa.id === paraMesaId);
      if (!mesaDestino) throw new Error("Mesa de destino não encontrada.");

      let pedidoDestino = this.pedidos[paraMesaId];
      if (!pedidoDestino) {
        pedidoDestino = this.abrirPedido(paraMesaId, `Transferência de ${pedidoOrigem.mesaCodigo}`);
      }

      const ids = itemIds.length ? itemIds : pedidoOrigem.itens.map((item) => item.id);
      const itensTransferir = pedidoOrigem.itens.filter((item) => ids.includes(item.id));
      if (!itensTransferir.length) throw new Error("Nenhum item seleccionado.");

      for (const item of itensTransferir) {
        const existente = pedidoDestino.itens.find((linha) => linha.produtoId === item.produtoId);
        if (existente) {
          existente.quantidade = Number((existente.quantidade + item.quantidade).toFixed(3));
          existente.subtotal = calcularSubtotal(existente.quantidade, existente.precoVenda);
        } else {
          pedidoDestino.itens.push({ ...item, id: gerarIdLocal() });
        }
      }

      pedidoOrigem.itens = pedidoOrigem.itens.filter((item) => !ids.includes(item.id));
      pedidoOrigem.pendenteSync = true;
      pedidoDestino.pendenteSync = true;

      const pedidosActualizados = { ...this.pedidos, [paraMesaId]: { ...pedidoDestino } };

      if (!pedidoOrigem.itens.length) {
        delete pedidosActualizados[deMesaId];
        void this.fecharPedidoRemoto(pedidoOrigem.id);
      } else {
        pedidosActualizados[deMesaId] = { ...pedidoOrigem };
      }

      this.pedidos = pedidosActualizados;
      this.actualizarOcupacaoMesas();
      void this.transferirRemoto(pedidoOrigem, paraMesaId, ids);
    },
    fecharPedidoLocal(mesaId, saleId = null) {
      const pedido = this.pedidos[mesaId];
      if (!pedido) return null;

      delete this.pedidos[mesaId];
      this.actualizarOcupacaoMesas();

      if (pedido.id && temApiConfigurada()) {
        void this.fecharPedidoRemoto(pedido.id, saleId);
      }

      return pedido;
    },
    montarVendaDoPedido(pedido, opcoes = {}) {
      const sessaoStore = useSessaoStore();
      sessaoStore.hidratar();
      const mesa = this.mesas.find((item) => item.id === pedido.mesaId);
      const total = pedido.itens.reduce((acc, item) => acc + Number(item.subtotal || 0), 0);

      return {
        id: opcoes.vendaId || gerarIdLocal(),
        cliente: `Mesa ${mesa?.codigo || pedido.mesaCodigo}`,
        itens: pedido.itens.map((item) => ({ ...item })),
        caixa: sessaoStore.caixaAtribuido,
        registerId: sessaoStore.registerId,
        cashSessionId: sessaoStore.cashSessionId,
        sourceLocationId: sessaoStore.sourceLocationId,
        operador: sessaoStore.utilizador,
        subtotal: total,
        descontoAplicado: 0,
        total,
        data: new Date().toISOString(),
        metodoPagamento: opcoes.metodoPagamento || "Dinheiro",
        valorPago: opcoes.valorPago ?? total,
        troco: opcoes.troco ?? 0,
        register_id: sessaoStore.registerId,
        source_location_id: sessaoStore.sourceLocationId,
        cash_session_id: sessaoStore.cashSessionId,
        mesaCodigo: mesa?.codigo || pedido.mesaCodigo,
        tableOrderId: pedido.id,
      };
    },
    async sincronizarPedidoRemoto(pedido) {
      if (!temApiConfigurada() || !pedido?.mesaId) return;

      const sessaoStore = useSessaoStore();
      sessaoStore.hidratar();

      try {
        let remoto = null;
        try {
          remoto = await mesasApi.abrirPedido({
            id: pedido.id,
            diningTableId: pedido.mesaId,
            description: pedido.descricao,
            registerId: sessaoStore.registerId,
            cashSessionId: sessaoStore.cashSessionId,
          });
        } catch (erroAbertura) {
          if (!ignorarErroSyncMesas(erroAbertura)) {
            const mensagem = String(erroAbertura?.message || "");
            if (!mensagem.includes("comanda aberta")) throw erroAbertura;
          }
        }

        if (pedido.itens.length) {
          await mesasApi.adicionarItens(pedido.id, {
            itens: pedido.itens.map((item) => ({
              id: item.id,
              produtoId: item.produtoId,
              nome: item.nome,
              quantidade: item.quantidade,
              precoVenda: item.precoVenda,
              precoSemIva: item.precoSemIva,
              ivaPercentual: item.ivaPercentual,
              valorIvaUnitario: item.valorIvaUnitario,
              ivaTipo: item.ivaTipo,
              subtotal: item.subtotal,
            })),
          });
        }

        pedido.pendenteSync = false;
        this.pedidos = { ...this.pedidos, [pedido.mesaId]: { ...pedido } };
        this.salvar();
        return remoto;
      } catch (erro) {
        if (!ignorarErroSyncMesas(erro)) throw erro;
      }
    },
    async removerItemRemoto(pedidoId, itemId) {
      if (!temApiConfigurada() || !pedidoId || !itemId) return;
      try {
        await mesasApi.removerItem(pedidoId, itemId);
      } catch (erro) {
        if (!ignorarErroSyncMesas(erro)) throw erro;
      }
    },
    async transferirRemoto(pedidoOrigem, paraMesaId, itemIds) {
      if (!temApiConfigurada() || !pedidoOrigem?.id) return;
      try {
        await mesasApi.transferir(pedidoOrigem.id, {
          toTableId: paraMesaId,
          itemIds,
        });
      } catch (erro) {
        if (!ignorarErroSyncMesas(erro)) throw erro;
        try {
          await mesasApi.sincronizarPedidos({
            registerId: useSessaoStore().registerId,
            cashSessionId: useSessaoStore().cashSessionId,
            pedidos: Object.values(this.pedidos).map((pedido) => ({
              id: pedido.id,
              diningTableId: pedido.mesaId,
              description: pedido.descricao,
              itens: pedido.itens,
            })),
          });
        } catch {
          // Backend sem módulo Mesas — mantém estado local.
        }
      }
    },
    async fecharPedidoRemoto(pedidoId, saleId = null) {
      if (!temApiConfigurada() || !pedidoId) return;
      try {
        await mesasApi.fecharPedido(pedidoId, { saleId });
      } catch (erro) {
        if (!ignorarErroSyncMesas(erro)) throw erro;
      }
    },
  },
});
