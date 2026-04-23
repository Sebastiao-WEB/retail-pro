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
};

