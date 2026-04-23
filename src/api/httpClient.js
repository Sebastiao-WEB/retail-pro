import { apiConfig } from "./config";
import { limparTokens, obterRefreshToken, obterToken, salvarTokens } from "../services/authStorage";

export class ApiError extends Error {
  constructor(message, status = 0, payload = null) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.payload = payload;
  }
}

function construirUrl(path) {
  const rota = String(path || "").startsWith("/") ? path : `/${path}`;
  return `${apiConfig.baseUrl}${rota}`;
}

let promessaRefresh = null;

function tentarRedirecionarLogin() {
  if (typeof window === "undefined") return;
  window.dispatchEvent(new CustomEvent("retailpro:auth-expired"));
  if (!window.location.hash.includes("/login")) {
    window.location.hash = "#/login";
  }
}

function tratarNaoAutorizado() {
  limparTokens();
  localStorage.removeItem("retailpro:sessao");
  tentarRedirecionarLogin();
}

async function tentarRefreshToken() {
  const refreshToken = obterRefreshToken();
  if (!refreshToken || !apiConfig.baseUrl) return false;

  if (!promessaRefresh) {
    promessaRefresh = (async () => {
      try {
        const resposta = await fetch(construirUrl("/auth/refresh"), {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({ refresh_token: refreshToken }),
        });
        const texto = await resposta.text();
        const json = texto ? JSON.parse(texto) : null;
        if (!resposta.ok || !json?.access_token) return false;
        salvarTokens({
          accessToken: json.access_token,
          refreshToken: json.refresh_token || refreshToken,
        });
        return true;
      } catch {
        return false;
      } finally {
        promessaRefresh = null;
      }
    })();
  }

  return promessaRefresh;
}

export async function httpRequest(path, opcoes = {}, contexto = { refreshTentado: false }) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), apiConfig.timeoutMs);

  try {
    const resposta = await fetch(construirUrl(path), {
      method: opcoes.method || "GET",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...(obterToken() ? { Authorization: `Bearer ${obterToken()}` } : {}),
        ...(opcoes.headers || {}),
      },
      body: opcoes.body ? JSON.stringify(opcoes.body) : undefined,
      signal: controller.signal,
    });

    const texto = await resposta.text();
    let json = null;
    try {
      json = texto ? JSON.parse(texto) : null;
    } catch {
      json = null;
    }

    if (resposta.status === 401) {
      if (!contexto.refreshTentado && !String(path).includes("/auth/refresh")) {
        const renovado = await tentarRefreshToken();
        if (renovado) {
          return httpRequest(path, opcoes, { refreshTentado: true });
        }
      }
      tratarNaoAutorizado();
      throw new ApiError(json?.message || "Sessão expirada. Faça login novamente.", 401, json);
    }

    if (!resposta.ok) {
      throw new ApiError(
        json?.message || `Erro HTTP ${resposta.status}`,
        resposta.status,
        json
      );
    }

    return json;
  } catch (erro) {
    if (erro?.name === "AbortError") {
      throw new ApiError("Tempo limite da API excedido.");
    }
    if (erro instanceof ApiError) throw erro;
    throw new ApiError(erro?.message || "Falha ao comunicar com o backend.");
  } finally {
    clearTimeout(timeout);
  }
}

