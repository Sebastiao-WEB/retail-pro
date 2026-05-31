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
  async twoFactorChallenge({ twoFactorToken, code, recoveryCode }) {
    return httpRequest("/auth/two-factor-challenge", {
      method: "POST",
      body: {
        two_factor_token: twoFactorToken,
        code: code || null,
        recovery_code: recoveryCode || null,
      },
    });
  },
  async twoFactorStatus() {
    return httpRequest("/auth/two-factor/status");
  },
  async enableTwoFactor(password) {
    return httpRequest("/auth/two-factor/enable", {
      method: "POST",
      body: { password },
    });
  },
  async confirmTwoFactor(code) {
    return httpRequest("/auth/two-factor/confirm", {
      method: "POST",
      body: { code },
    });
  },
  async disableTwoFactor(password) {
    return httpRequest("/auth/two-factor", {
      method: "DELETE",
      body: { password },
    });
  },
  async twoFactorQrCode() {
    return httpRequest("/auth/two-factor/qr-code");
  },
  async fetchRecoveryCodes(password) {
    return httpRequest("/auth/two-factor/recovery-codes", {
      method: "POST",
      body: { password },
    });
  },
  async regenerateRecoveryCodes(password) {
    return httpRequest("/auth/two-factor/recovery-codes/regenerate", {
      method: "POST",
      body: { password },
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
