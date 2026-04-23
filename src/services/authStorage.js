const CHAVE_TOKEN = "retailpro:token";
const CHAVE_REFRESH_TOKEN = "retailpro:refresh_token";

export function obterToken() {
  return localStorage.getItem(CHAVE_TOKEN) || "";
}

export function obterRefreshToken() {
  return localStorage.getItem(CHAVE_REFRESH_TOKEN) || "";
}

export function salvarTokens({ accessToken, refreshToken }) {
  if (accessToken) localStorage.setItem(CHAVE_TOKEN, String(accessToken));
  if (refreshToken) localStorage.setItem(CHAVE_REFRESH_TOKEN, String(refreshToken));
}

export function limparTokens() {
  localStorage.removeItem(CHAVE_TOKEN);
  localStorage.removeItem(CHAVE_REFRESH_TOKEN);
}

