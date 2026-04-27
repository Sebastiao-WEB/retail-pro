import { apiConfig, modoApiAtivo, temApiConfigurada } from "../api";
import { obterToken } from "./authStorage";

function construirUrlTeste() {
  const base = String(apiConfig.baseUrl || "").replace(/\/+$/, "");
  const prefixo = String(apiConfig.versionPrefix || "").replace(/\/+$/, "");
  return `${base}${prefixo}/products`;
}

export async function verificarConexaoBackend(timeoutMs = 4000) {
  const endpoint = construirUrlTeste();
  if (!modoApiAtivo()) {
    return { ativo: false, conectado: false, motivo: "mock", endpoint };
  }

  if (!temApiConfigurada()) {
    return { ativo: true, conectado: false, motivo: "sem_url", endpoint };
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const resposta = await fetch(construirUrlTeste(), {
      method: "GET",
      headers: {
        Accept: "application/json",
        ...(obterToken() ? { Authorization: `Bearer ${obterToken()}` } : {}),
      },
      signal: controller.signal,
    });

    if (resposta.ok) return { ativo: true, conectado: true, motivo: "ok", endpoint, statusHttp: resposta.status };

    // Mesmo com 401/403/422, o backend está alcançável.
    if ([400, 401, 403, 404, 405, 422].includes(resposta.status)) {
      return { ativo: true, conectado: true, motivo: `http_${resposta.status}`, endpoint, statusHttp: resposta.status };
    }

    return { ativo: true, conectado: false, motivo: `http_${resposta.status}`, endpoint, statusHttp: resposta.status };
  } catch {
    return { ativo: true, conectado: false, motivo: "network", endpoint };
  } finally {
    clearTimeout(timer);
  }
}
