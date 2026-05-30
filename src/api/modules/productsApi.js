import { httpRequest } from "../httpClient";

export const productsApi = {
  async listar(params = {}) {
    const query = new URLSearchParams();
    if (params.search) query.set("search", params.search);
    if (params.store_id) query.set("store_id", String(params.store_id));
    if (params.source_location_id) query.set("source_location_id", String(params.source_location_id));
    if (params.location_id) query.set("location_id", String(params.location_id));
    const rota = query.toString() ? `/products?${query}` : "/products";
    return httpRequest(rota);
  },
  async criar(payload) {
    return httpRequest("/products", { method: "POST", body: payload });
  },
  async atualizar(id, payload) {
    return httpRequest(`/products/${id}`, { method: "PUT", body: payload });
  },
};

