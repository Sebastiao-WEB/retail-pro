import { defineStore } from "pinia";
import { mesasApi, temApiConfigurada, ApiError } from "../api";
import { isErroRedeOuIndisponivel, redeDisponivel } from "../services/offline/networkError";
import { useSessaoStore } from "./useSessaoStore";
import { resolverIvaPercentualExibicao } from "../utils/ivaItem.js";
import {
  normalizarQuantidadeVenda,
  normalizarUnidadeVenda,
  quantidadeMinima,
} from "../utils/produtoQuantidade";

const CHAVE_MESAS = "retailpro:mesas";

let timerSincronizacaoMesas = null;
let syncMesasEmCurso = false;
let apiMesasIndisponivel = false;

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
    if (!raw) return { mesas: [], pedidos: {}, fechosPendentes: [] };
    const parsed = JSON.parse(raw);
    return {
      mesas: Array.isArray(parsed.mesas) ? parsed.mesas : [],
      pedidos: parsed.pedidos && typeof parsed.pedidos === "object" ? parsed.pedidos : {},
      fechosPendentes: Array.isArray(parsed.fechosPendentes) ? parsed.fechosPendentes : [],
    };
  } catch {
    return { mesas: [], pedidos: {}, fechosPendentes: [] };
  }
}

function normalizarCodigoMesa(codigo) {
  return String(codigo || "").trim().toUpperCase();
}

function mesclarMesasSemDuplicar(locais, remotas) {
  const resultado = new Map();

  for (const mesa of remotas) {
    resultado.set(normalizarCodigoMesa(mesa.codigo), { ...mesa, pendenteSync: false });
  }

  for (const mesa of locais) {
    const codigo = normalizarCodigoMesa(mesa.codigo);
    if (resultado.has(codigo)) continue;
    resultado.set(codigo, mesa);
  }

  return [...resultado.values()];
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
      fechosPendentes: estado.fechosPendentes,
      mesaSeleccionadaId: null,
      sincronizando: false,
    };
  },
  getters: {
    mesaSeleccionada(state) {
      return state.mesas.find((mesa) => mesa.id === state.mesaSeleccionadaId) || null;
    },
    pedidoSeleccionado(state) {
      const mesaId = state.mesaSeleccionadaId;
      if (!mesaId) return null;
      return this.obterPedidoDaMesa(mesaId);
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
      return this.mesasOrdenadas.filter((mesa) => !this.obterPedidoDaMesa(mesa.id));
    },
    mesasOcupadas() {
      return this.mesasOrdenadas.filter((mesa) => !!this.obterPedidoDaMesa(mesa.id));
    },
  },
  actions: {
    salvar() {
      localStorage.setItem(
        CHAVE_MESAS,
        JSON.stringify({
          mesas: this.mesas,
          pedidos: this.pedidos,
          fechosPendentes: this.fechosPendentes,
        })
      );
    },
    hidratarLocal() {
      const estado = carregarEstado();
      this.mesas = mesclarMesasSemDuplicar(estado.mesas, []);
      this.pedidos = estado.pedidos;
      this.fechosPendentes = estado.fechosPendentes;
      this.reconciliarPedidosComMesas();
      this.actualizarOcupacaoMesas();
    },
    agendarSincronizacaoMesas(delayMs = 2500) {
      if (!temApiConfigurada() || apiMesasIndisponivel) return;
      if (timerSincronizacaoMesas) {
        clearTimeout(timerSincronizacaoMesas);
      }
      timerSincronizacaoMesas = setTimeout(() => {
        timerSincronizacaoMesas = null;
        void this.sincronizarMesasBackground().catch(() => {
          // Estado local mantém-se; reconcilia na próxima janela de sync.
        });
      }, delayMs);
    },
    seleccionarMesa(mesaId) {
      this.mesaSeleccionadaId = mesaId || null;
    },
    obterPedidoPorMesa(mesaId) {
      return this.obterPedidoDaMesa(mesaId);
    },
    obterPedidoDaMesa(mesaId) {
      if (!mesaId) return null;
      if (this.pedidos[mesaId]) return this.pedidos[mesaId];

      const mesa = this.mesas.find((item) => item.id === mesaId);
      if (!mesa) return null;

      const codigo = normalizarCodigoMesa(mesa.codigo);
      return (
        Object.values(this.pedidos).find(
          (pedido) =>
            pedido.mesaId === mesaId ||
            normalizarCodigoMesa(pedido.mesaCodigo) === codigo
        ) || null
      );
    },
    reconciliarPedidosComMesas() {
      const mesasPorId = new Map(this.mesas.map((mesa) => [mesa.id, mesa]));
      const mesasPorCodigo = new Map(
        this.mesas.map((mesa) => [normalizarCodigoMesa(mesa.codigo), mesa])
      );
      const pedidosNormalizados = {};

      for (const pedido of Object.values(this.pedidos)) {
        let mesa = mesasPorId.get(pedido.mesaId);
        if (!mesa && pedido.mesaCodigo) {
          mesa = mesasPorCodigo.get(normalizarCodigoMesa(pedido.mesaCodigo));
        }
        if (!mesa) continue;

        const chave = mesa.id;
        const actual = pedidosNormalizados[chave];
        const candidato = {
          ...pedido,
          mesaId: mesa.id,
          mesaCodigo: mesa.codigo,
        };

        if (!actual) {
          pedidosNormalizados[chave] = candidato;
          continue;
        }

        const itensActual = actual.itens?.length || 0;
        const itensCandidato = candidato.itens?.length || 0;
        pedidosNormalizados[chave] =
          itensCandidato >= itensActual
            ? { ...actual, ...candidato, itens: candidato.itens?.length ? candidato.itens : actual.itens }
            : actual;
      }

      this.pedidos = pedidosNormalizados;
    },
    remapearPedidosParaMesa(mesaIdAntigo, mesaIdNovo) {
      if (!mesaIdAntigo || !mesaIdNovo || mesaIdAntigo === mesaIdNovo) return;

      const pedido = this.pedidos[mesaIdAntigo];
      if (!pedido) return;

      pedido.mesaId = mesaIdNovo;
      const pedidos = { ...this.pedidos };
      delete pedidos[mesaIdAntigo];
      pedidos[mesaIdNovo] = pedido;
      this.pedidos = pedidos;

      if (this.mesaSeleccionadaId === mesaIdAntigo) {
        this.mesaSeleccionadaId = mesaIdNovo;
      }
    },
    actualizarOcupacaoMesas() {
      this.mesas = this.mesas.map((mesa) => {
        const pedido = this.obterPedidoDaMesa(mesa.id);
        return {
          ...mesa,
          ocupada: !!pedido,
          pedidoAbertoId: pedido?.id || null,
        };
      });
      this.salvar();
    },
    async carregarMesas() {
      this.hidratarLocal();
      return this.sincronizarMesasBackground();
    },
    async sincronizarMesasBackground() {
      if (!temApiConfigurada() || apiMesasIndisponivel || syncMesasEmCurso || !redeDisponivel()) {
        return { ok: false, ignorado: true };
      }

      const sessaoStore = useSessaoStore();
      sessaoStore.hidratar();
      if (!sessaoStore.estaLogado) {
        return { ok: false, ignorado: true };
      }

      syncMesasEmCurso = true;
      this.sincronizando = true;

      try {
        await this.enviarMesasPendentes(sessaoStore);
        await this.enviarPedidosPendentes(sessaoStore);
        await this.enviarFechosPendentes();
        await this.receberEstadoRemoto(sessaoStore);
        this.salvar();
        return { ok: true };
      } catch (erro) {
        if (ignorarErroSyncMesas(erro)) {
          if (erro instanceof ApiError && erro.status === 404) {
            apiMesasIndisponivel = true;
          }
          return { ok: false, ignorado: true };
        }
        throw erro;
      } finally {
        syncMesasEmCurso = false;
        this.sincronizando = false;
      }
    },
    async enviarMesasPendentes(sessaoStore) {
      for (const mesa of this.mesas.filter((item) => item.pendenteSync)) {
        try {
          const resposta = await mesasApi.criarMesa({
            id: mesa.id,
            code: mesa.codigo,
            name: mesa.nome || null,
            description: mesa.descricao || null,
            registerId: sessaoStore.registerId,
          });
          if (resposta?.data) {
            const mapeada = mapearMesaRemota(resposta.data);
            if (mapeada.id !== mesa.id) {
              this.remapearPedidosParaMesa(mesa.id, mapeada.id);
            }
            Object.assign(mesa, mapeada);
          }
          mesa.pendenteSync = false;
        } catch (erro) {
          if (erro instanceof ApiError && erro.status === 422) {
            mesa.pendenteSync = false;
            continue;
          }
          if (ignorarErroSyncMesas(erro)) return;
          throw erro;
        }
      }
    },
    async enviarPedidosPendentes(sessaoStore) {
      const pedidosPendentes = Object.values(this.pedidos).filter((pedido) => pedido.pendenteSync);
      if (!pedidosPendentes.length) return;

      try {
        await mesasApi.sincronizarPedidos({
          registerId: sessaoStore.registerId,
          cashSessionId: sessaoStore.cashSessionId,
          pedidos: pedidosPendentes.map((pedido) => ({
            id: pedido.id,
            diningTableId: pedido.mesaId,
            description: pedido.descricao,
            itens: pedido.itens,
          })),
        });
        for (const pedido of pedidosPendentes) {
          pedido.pendenteSync = false;
        }
      } catch (erro) {
        if (ignorarErroSyncMesas(erro)) return;
        throw erro;
      }
    },
    async enviarFechosPendentes() {
      if (!this.fechosPendentes.length) return;

      const restantes = [];
      for (const fecho of this.fechosPendentes) {
        try {
          await mesasApi.fecharPedido(fecho.pedidoId, { saleId: fecho.saleId });
        } catch (erro) {
          if (ignorarErroSyncMesas(erro)) {
            restantes.push(fecho);
            continue;
          }
          restantes.push(fecho);
        }
      }
      this.fechosPendentes = restantes;
    },
    async receberEstadoRemoto(sessaoStore) {
      const resposta = await mesasApi.listarMesas({ registerId: sessaoStore.registerId });
      const mesasRemotas = Array.isArray(resposta?.data) ? resposta.data : [];
      const remotasNormalizadas = mesasRemotas.map((mesa) => mapearMesaRemota(mesa));

      const idsRemotos = new Set(remotasNormalizadas.map((mesa) => mesa.id));
      const codigosRemotos = new Set(remotasNormalizadas.map((mesa) => normalizarCodigoMesa(mesa.codigo)));

      for (const mesa of this.mesas) {
        const remota = remotasNormalizadas.find(
          (item) => normalizarCodigoMesa(item.codigo) === normalizarCodigoMesa(mesa.codigo)
        );
        if (remota && remota.id !== mesa.id) {
          this.remapearPedidosParaMesa(mesa.id, remota.id);
          mesa.pendenteSync = false;
        }
      }

      const locaisPendentes = this.mesas.filter((mesa) => {
        if (idsRemotos.has(mesa.id)) return false;
        if (codigosRemotos.has(normalizarCodigoMesa(mesa.codigo))) return false;
        return true;
      });

      this.mesas = mesclarMesasSemDuplicar(locaisPendentes, remotasNormalizadas);

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
      this.reconciliarPedidosComMesas();
      this.actualizarOcupacaoMesas();
    },
    async criarMesa({ codigo, nome = "", descricao = "" }) {
      const codigoNormalizado = normalizarCodigoMesa(codigo);
      if (!codigoNormalizado) throw new Error("Código da mesa é obrigatório.");
      if (this.mesas.some((mesa) => normalizarCodigoMesa(mesa.codigo) === codigoNormalizado)) {
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
        pendenteSync: temApiConfigurada(),
      };

      this.mesas.push(nova);
      this.mesas = mesclarMesasSemDuplicar(this.mesas, []);
      this.salvar();
      this.agendarSincronizacaoMesas();
      return nova;
    },
    abrirPedido(mesaId, descricao = "") {
      this.reconciliarPedidosComMesas();

      const existente = this.obterPedidoDaMesa(mesaId);
      if (existente) {
        const texto = String(descricao || "").trim();
        const descricaoMudou = texto && texto !== existente.descricao;
        if (descricaoMudou) {
          existente.descricao = texto;
          existente.pendenteSync = true;
        }
        this.pedidos = { ...this.pedidos, [mesaId]: { ...existente, mesaId } };
        this.reconciliarPedidosComMesas();
        this.actualizarOcupacaoMesas();
        if (descricaoMudou) {
          this.agendarSincronizacaoMesas();
        }
        return this.obterPedidoDaMesa(mesaId);
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
      this.agendarSincronizacaoMesas();
      return pedido;
    },
    adicionarProduto(mesaId, produto, quantidade = 1) {
      const pedido = this.obterPedidoDaMesa(mesaId);
      if (!pedido) throw new Error("Abra a mesa antes de adicionar produtos.");
      const mesaIdReal = pedido.mesaId;

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
      this.pedidos = { ...this.pedidos, [mesaIdReal]: { ...pedido } };
      this.reconciliarPedidosComMesas();
      this.salvar();
      this.agendarSincronizacaoMesas();
    },
    removerItem(mesaId, itemId) {
      const pedido = this.obterPedidoDaMesa(mesaId);
      if (!pedido) return;

      pedido.itens = pedido.itens.filter((item) => item.id !== itemId);
      pedido.pendenteSync = true;
      this.pedidos = { ...this.pedidos, [pedido.mesaId]: { ...pedido } };
      this.reconciliarPedidosComMesas();
      this.salvar();
      this.agendarSincronizacaoMesas();
    },
    transferirItens({ deMesaId, paraMesaId, itemIds = [] }) {
      if (deMesaId === paraMesaId) throw new Error("Seleccione uma mesa de destino diferente.");

      this.reconciliarPedidosComMesas();
      const pedidoOrigem = this.obterPedidoDaMesa(deMesaId);
      if (!pedidoOrigem) throw new Error("A mesa de origem não tem comanda aberta.");

      const mesaDestino = this.mesas.find((mesa) => mesa.id === paraMesaId);
      if (!mesaDestino) throw new Error("Mesa de destino não encontrada.");

      let pedidoDestino = this.obterPedidoDaMesa(paraMesaId);
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

      const pedidosActualizados = {
        ...this.pedidos,
        [pedidoDestino.mesaId]: { ...pedidoDestino },
      };

      if (!pedidoOrigem.itens.length) {
        delete pedidosActualizados[pedidoOrigem.mesaId];
        if (pedidoOrigem.id && temApiConfigurada()) {
          this.fechosPendentes.push({
            pedidoId: pedidoOrigem.id,
            saleId: null,
            criadoEm: new Date().toISOString(),
          });
        }
      } else {
        pedidosActualizados[pedidoOrigem.mesaId] = { ...pedidoOrigem };
      }

      this.pedidos = pedidosActualizados;
      this.reconciliarPedidosComMesas();
      this.actualizarOcupacaoMesas();
      this.agendarSincronizacaoMesas();
    },
    fecharPedidoLocal(mesaId, saleId = null) {
      const pedido = this.obterPedidoDaMesa(mesaId);
      if (!pedido) return null;

      if (pedido.id && temApiConfigurada()) {
        this.fechosPendentes.push({
          pedidoId: pedido.id,
          saleId: saleId || null,
          criadoEm: new Date().toISOString(),
        });
      }

      const pedidos = { ...this.pedidos };
      delete pedidos[pedido.mesaId];
      this.pedidos = pedidos;
      this.reconciliarPedidosComMesas();
      this.actualizarOcupacaoMesas();
      this.agendarSincronizacaoMesas();

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
  },
});
