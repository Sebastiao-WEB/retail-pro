import { httpRequest } from "../httpClient";

export const authApi = {
  async login({ username, password, registerCode }) {
    return httpRequest("/auth/login", {
      method: "POST",
      body: {
        username,
        password,
        register_code: registerCode || null,
      },
    });
  },
  async refresh(refreshToken) {
    return httpRequest("/auth/refresh", {
      method: "POST",
      body: { refresh_token: refreshToken },
    });
  },
  async logout() {
    return httpRequest("/auth/logout", { method: "POST" });
  },
};

