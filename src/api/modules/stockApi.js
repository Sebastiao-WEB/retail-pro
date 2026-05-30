import { httpRequest } from "../httpClient";

export const stockApi = {
  async consultarDisponibilidade({ location_id, product_ids = [] }) {
    const ids = [...new Set(product_ids.filter(Boolean))];
    if (!location_id || !ids.length) {
      return { data: {} };
    }

    const query = new URLSearchParams();
    query.set("location_id", String(location_id));
    query.set("product_ids", ids.join(","));

    return httpRequest(`/stock/availability?${query}`);
  },
};
