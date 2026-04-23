import { apiConfig } from "./config";

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

function obterToken() {
  return localStorage.getItem("retailpro:token") || "";
}

export async function httpRequest(path, opcoes = {}) {
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
    const json = texto ? JSON.parse(texto) : null;

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

