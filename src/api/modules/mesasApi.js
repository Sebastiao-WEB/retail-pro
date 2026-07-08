import { httpRequest } from "../httpClient";

export const mesasApi = {
  async listarMesas(params = {}) {
    const query = new URLSearchParams();
    if (params.register_id) query.set("register_id", String(params.register_id));
    if (params.registerId) query.set("registerId", String(params.registerId));
    const rota = query.toString() ? `/dining-tables?${query}` : "/dining-tables";
    return httpRequest(rota);
  },

  async criarMesa(payload) {
    return httpRequest("/dining-tables", { method: "POST", body: payload });
  },

  async actualizarMesa(id, payload) {
    return httpRequest(`/dining-tables/${id}`, { method: "PUT", body: payload });
  },

  async listarPedidos(params = {}) {
    const query = new URLSearchParams();
    if (params.register_id) query.set("register_id", String(params.register_id));
    if (params.registerId) query.set("registerId", String(params.registerId));
    if (params.status) query.set("status", params.status);
    const rota = query.toString() ? `/table-orders?${query}` : "/table-orders";
    return httpRequest(rota);
  },

  async abrirPedido(payload) {
    return httpRequest("/table-orders", { method: "POST", body: payload });
  },

  async obterPedido(id) {
    return httpRequest(`/table-orders/${id}`);
  },

  async adicionarItens(pedidoId, payload) {
    return httpRequest(`/table-orders/${pedidoId}/items`, { method: "POST", body: payload });
  },

  async removerItem(pedidoId, itemId) {
    return httpRequest(`/table-orders/${pedidoId}/items/${itemId}`, { method: "DELETE" });
  },

  async transferir(pedidoId, payload) {
    return httpRequest(`/table-orders/${pedidoId}/transfer`, { method: "POST", body: payload });
  },

  async fecharPedido(pedidoId, payload = {}) {
    return httpRequest(`/table-orders/${pedidoId}/close`, { method: "POST", body: payload });
  },

  async sincronizarPedidos(payload) {
    return httpRequest("/table-orders/sync", { method: "POST", body: payload });
  },
};
