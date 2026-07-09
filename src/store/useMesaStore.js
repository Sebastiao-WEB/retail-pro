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

function mesaTemIdRemoto(id) {
  const valor = String(id || "");
  return valor.length > 0 && !valor.startsWith("local-");
}

function escolherMesaPreferida(atual, candidata) {
  if (!atual) return candidata;
  if (!candidata) return atual;

  if (atual.pendenteSync && !candidata.pendenteSync) return candidata;
  if (!atual.pendenteSync && candidata.pendenteSync) return atual;

  const atualRemota = mesaTemIdRemoto(atual.id);
  const candidataRemota = mesaTemIdRemoto(candidata.id);
  if (!atualRemota && candidataRemota) return candidata;
  if (atualRemota && !candidataRemota) return atual;

  return atual;
}

function mesclarMesasSemDuplicar(locais, remotas) {
  const resultado = new Map();

  for (const mesa of remotas) {
    const codigo = normalizarCodigoMesa(mesa.codigo);
    if (!codigo) continue;
    resultado.set(codigo, escolherMesaPreferida(resultado.get(codigo), { ...mesa, pendenteSync: false }));
  }

  for (const mesa of locais) {
    const codigo = normalizarCodigoMesa(mesa.codigo);
    if (!codigo) continue;
    resultado.set(codigo, escolherMesaPreferida(resultado.get(codigo), mesa));
  }

  return [...resultado.values()];
}

function obterPedidoDaMesaNoEstado(pedidos, mesas, mesaId) {
  if (!mesaId) return null;
  if (pedidos[mesaId]) return pedidos[mesaId];

  const porMesaId = Object.values(pedidos).find((pedido) => pedido.mesaId === mesaId);
  if (porMesaId) return porMesaId;

  const mesa = mesas.find((item) => item.id === mesaId);
  if (!mesa) return null;

  const codigo = normalizarCodigoMesa(mesa.codigo);
  return (
    Object.values(pedidos).find(
      (pedido) => normalizarCodigoMesa(pedido.mesaCodigo) === codigo
    ) || null
  );
}

function resolverMesaIdCanonico(mesas, mesaId, pedidos = {}) {
  if (!mesaId) return null;
  if (mesas.some((mesa) => mesa.id === mesaId)) return mesaId;

  const pedido = obterPedidoDaMesaNoEstado(pedidos, mesas, mesaId);
  if (pedido?.mesaId && mesas.some((mesa) => mesa.id === pedido.mesaId)) {
    return pedido.mesaId;
  }

  const codigo = pedido?.mesaCodigo ? normalizarCodigoMesa(pedido.mesaCodigo) : "";
  if (codigo) {
    const mesa = mesas.find((item) => normalizarCodigoMesa(item.codigo) === codigo);
    if (mesa) return mesa.id;
  }

  return mesaId;
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
      const mesa = state.mesas.find((item) => item.id === state.mesaSeleccionadaId) || null;
      if (!mesa) return null;
      const pedido = obterPedidoDaMesaNoEstado(state.pedidos, state.mesas, mesa.id);
      return {
        ...mesa,
        ocupada: !!pedido,
        pedidoAbertoId: pedido?.id || null,
      };
    },
    pedidoSeleccionado(state) {
      const mesaId = state.mesaSeleccionadaId;
      if (!mesaId) return null;
      return obterPedidoDaMesaNoEstado(state.pedidos, state.mesas, mesaId);
    },
    mesasOrdenadas(state) {
      return [...state.mesas]
        .sort((a, b) => String(a.codigo).localeCompare(String(b.codigo)))
        .map((mesa) => {
          const pedido = obterPedidoDaMesaNoEstado(state.pedidos, state.mesas, mesa.id);
          return {
            ...mesa,
            ocupada: !!pedido,
            pedidoAbertoId: pedido?.id || null,
          };
        });
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
      this.mesas = estado.mesas;
      this.pedidos = estado.pedidos;
      this.fechosPendentes = estado.fechosPendentes;
      this.deduplicarMesasEstado();
      this.reconciliarPedidosComMesas();
      this.actualizarOcupacaoMesas();
    },
    deduplicarMesasEstado() {
      const idsAntigos = new Map(this.mesas.map((mesa) => [mesa.id, mesa]));
      const mesasUnicas = mesclarMesasSemDuplicar(this.mesas, []);
      const idsNovos = new Set(mesasUnicas.map((mesa) => mesa.id));

      for (const [idAntigo, mesa] of idsAntigos) {
        if (idsNovos.has(idAntigo)) continue;
        const codigo = normalizarCodigoMesa(mesa.codigo);
        const substituta = mesasUnicas.find((item) => normalizarCodigoMesa(item.codigo) === codigo);
        if (substituta) {
          this.remapearPedidosParaMesa(idAntigo, substituta.id);
        }
      }

      this.mesas = mesasUnicas;
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
      return obterPedidoDaMesaNoEstado(this.pedidos, this.mesas, mesaId);
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

        if (this.mesaSeleccionadaId === pedido.mesaId && pedido.mesaId !== mesa.id) {
          this.mesaSeleccionadaId = mesa.id;
        }

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
            await this.adoptarMesaRemotaPorCodigo(mesa, sessaoStore);
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
    async adoptarMesaRemotaPorCodigo(mesaLocal, sessaoStore) {
      const codigo = normalizarCodigoMesa(mesaLocal.codigo);
      if (!codigo) {
        mesaLocal.pendenteSync = false;
        return;
      }

      try {
        const resposta = await mesasApi.listarMesas({ registerId: sessaoStore.registerId });
        const remotas = Array.isArray(resposta?.data) ? resposta.data : [];
        const remota = remotas.find((item) => normalizarCodigoMesa(item.code) === codigo);
        if (!remota) {
          mesaLocal.pendenteSync = false;
          return;
        }

        const mapeada = mapearMesaRemota(remota);
        if (mapeada.id !== mesaLocal.id) {
          this.remapearPedidosParaMesa(mesaLocal.id, mapeada.id);
        }

        const indice = this.mesas.findIndex((item) => item.id === mesaLocal.id);
        if (indice >= 0) {
          this.mesas[indice] = mapeada;
        } else {
          const duplicada = this.mesas.findIndex(
            (item) => normalizarCodigoMesa(item.codigo) === codigo && item.id !== mapeada.id
          );
          if (duplicada >= 0) {
            this.remapearPedidosParaMesa(this.mesas[duplicada].id, mapeada.id);
            this.mesas.splice(duplicada, 1);
          }
          this.mesas.push(mapeada);
        }

        this.deduplicarMesasEstado();
        mesaLocal.pendenteSync = false;
      } catch {
        mesaLocal.pendenteSync = false;
      }
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
      this.deduplicarMesasEstado();

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
      this.deduplicarMesasEstado();

      const codigoNormalizado = normalizarCodigoMesa(codigo);
      if (!codigoNormalizado) throw new Error("Código da mesa é obrigatório.");

      const existente = this.mesas.find(
        (mesa) => normalizarCodigoMesa(mesa.codigo) === codigoNormalizado
      );
      if (existente) {
        const nomeNormalizado = String(nome || "").trim();
        const descricaoNormalizada = String(descricao || "").trim();
        if (nomeNormalizado) existente.nome = nomeNormalizado;
        if (descricaoNormalizada) existente.descricao = descricaoNormalizada;
        this.mesaSeleccionadaId = existente.id;
        this.salvar();
        return { ...existente, jaExistia: true };
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
      this.deduplicarMesasEstado();
      this.mesaSeleccionadaId = nova.id;
      this.salvar();
      this.agendarSincronizacaoMesas();
      return { ...nova, jaExistia: false };
    },
    abrirPedido(mesaId, descricao = "") {
      this.deduplicarMesasEstado();
      this.reconciliarPedidosComMesas();

      const mesa = this.mesas.find((item) => item.id === mesaId);
      if (!mesa) throw new Error("Mesa não encontrada.");
      mesaId = mesa.id;

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

      this.deduplicarMesasEstado();
      this.reconciliarPedidosComMesas();

      const mesaOrigemId = resolverMesaIdCanonico(this.mesas, deMesaId, this.pedidos);
      const mesaDestinoId = resolverMesaIdCanonico(this.mesas, paraMesaId, this.pedidos);

      if (mesaOrigemId === mesaDestinoId) {
        throw new Error("Seleccione uma mesa de destino diferente.");
      }

      const pedidoOrigemActual = this.obterPedidoDaMesa(mesaOrigemId);
      if (!pedidoOrigemActual) throw new Error("A mesa de origem não tem comanda aberta.");

      const mesaDestino = this.mesas.find((mesa) => mesa.id === mesaDestinoId);
      if (!mesaDestino) throw new Error("Mesa de destino não encontrada.");

      const ids = itemIds.length ? itemIds : pedidoOrigemActual.itens.map((item) => item.id);
      const itensTransferir = pedidoOrigemActual.itens.filter((item) => ids.includes(item.id));
      if (!itensTransferir.length) throw new Error("Nenhum item seleccionado.");

      const pedidoOrigem = {
        ...pedidoOrigemActual,
        itens: pedidoOrigemActual.itens.filter((item) => !ids.includes(item.id)),
        pendenteSync: true,
      };

      const pedidoDestinoActual = this.obterPedidoDaMesa(mesaDestinoId);
      const pedidoDestino = pedidoDestinoActual
        ? { ...pedidoDestinoActual, itens: [...pedidoDestinoActual.itens], pendenteSync: true }
        : {
            id: gerarIdLocal(),
            mesaId: mesaDestinoId,
            mesaCodigo: mesaDestino.codigo,
            descricao: `Transferência de ${pedidoOrigemActual.mesaCodigo}`,
            itens: [],
            abertoEm: new Date().toISOString(),
            status: "OPEN",
            pendenteSync: true,
          };

      for (const item of itensTransferir) {
        const existente = pedidoDestino.itens.find((linha) => linha.produtoId === item.produtoId);
        if (existente) {
          existente.quantidade = Number((existente.quantidade + item.quantidade).toFixed(3));
          existente.subtotal = calcularSubtotal(existente.quantidade, existente.precoVenda);
        } else {
          pedidoDestino.itens.push({ ...item, id: gerarIdLocal() });
        }
      }

      const pedidosActualizados = { ...this.pedidos, [pedidoDestino.mesaId]: pedidoDestino };

      const chavesOrigem = new Set(
        [mesaOrigemId, pedidoOrigemActual.mesaId].filter(Boolean)
      );

      if (!pedidoOrigem.itens.length) {
        for (const chave of chavesOrigem) {
          delete pedidosActualizados[chave];
        }
        if (pedidoOrigemActual.id && temApiConfigurada()) {
          this.fechosPendentes.push({
            pedidoId: pedidoOrigemActual.id,
            saleId: null,
            criadoEm: new Date().toISOString(),
          });
        }
      } else {
        pedidosActualizados[mesaOrigemId] = { ...pedidoOrigem, mesaId: mesaOrigemId };
        for (const chave of chavesOrigem) {
          if (chave !== mesaOrigemId) delete pedidosActualizados[chave];
        }
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
