import { httpRequest } from "../httpClient";

export const cashApi = {
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

