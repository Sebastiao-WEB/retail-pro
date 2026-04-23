import { httpRequest } from "../httpClient";

export const purchasesApi = {
  async listar(params = {}) {
    const query = new URLSearchParams();
    if (params.store_id) query.set("store_id", String(params.store_id));
    const rota = query.toString() ? `/purchases?${query}` : "/purchases";
    return httpRequest(rota);
  },
};

