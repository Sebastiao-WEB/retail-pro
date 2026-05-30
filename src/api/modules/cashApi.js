import { httpRequest } from "../httpClient";

export const cashApi = {
  async listar(params = {}) {
    const query = new URLSearchParams();
    if (params.register_id) query.set("register_id", String(params.register_id));
    if (params.status) query.set("status", String(params.status));
    if (params.page) query.set("page", String(params.page));
    if (params.per_page) query.set("per_page", String(params.per_page));
    const rota = query.toString() ? `/cash-sessions?${query}` : "/cash-sessions";
    return httpRequest(rota);
  },
  async sessaoAtiva(registerId) {
    const query = registerId ? `?register_id=${encodeURIComponent(registerId)}` : "";
    return httpRequest(`/cash-sessions/active${query}`);
  },
  async abrirSessao(payload) {
    return httpRequest("/cash-sessions/open", { method: "POST", body: payload });
  },
  async fecharSessao(id, payload) {
    return httpRequest(`/cash-sessions/${id}/close`, { method: "POST", body: payload });
  },
  async listarMovimentos(sessaoId) {
    return httpRequest(`/cash-sessions/${sessaoId}/movements`);
  },
};

