import { httpRequest } from "../httpClient";

export const companyApi = {
  async obterPerfil() {
    return httpRequest("/company-profile");
  },
  async atualizarPerfil(payload) {
    return httpRequest("/company-profile", {
      method: "PUT",
      body: payload,
    });
  },
};

