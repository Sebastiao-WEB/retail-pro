import { httpRequest } from "../httpClient";

export const productsApi = {
  async listar(params = {}) {
    const query = new URLSearchParams();
    if (params.search) query.set("search", params.search);
    if (params.store_id) query.set("store_id", String(params.store_id));
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

