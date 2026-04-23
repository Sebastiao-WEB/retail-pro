import { httpRequest } from "../httpClient";

export const salesApi = {
  async listar(params = {}) {
    const query = new URLSearchParams();
    if (params.cash_session_id) query.set("cash_session_id", String(params.cash_session_id));
    if (params.register_id) query.set("register_id", String(params.register_id));
    if (params.from) query.set("from", params.from);
    if (params.to) query.set("to", params.to);
    const rota = query.toString() ? `/sales?${query}` : "/sales";
    return httpRequest(rota);
  },
  async criar(payload) {
    return httpRequest("/sales", { method: "POST", body: payload });
  },
  async solicitarReversao(payload) {
    return httpRequest("/sale-reversal-requests", { method: "POST", body: payload });
  },
};

