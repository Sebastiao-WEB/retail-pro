import { httpRequest } from "../httpClient";

export const cashApi = {
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

