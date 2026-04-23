import { httpRequest } from "../httpClient";

export const customersApi = {
  async listar(params = {}) {
    const query = new URLSearchParams();
    if (params.search) query.set("search", params.search);
    const rota = query.toString() ? `/customers?${query}` : "/customers";
    return httpRequest(rota);
  },
  async criar(payload) {
    return httpRequest("/customers", { method: "POST", body: payload });
  },
  async atualizar(id, payload) {
    return httpRequest(`/customers/${id}`, { method: "PUT", body: payload });
  },
};

