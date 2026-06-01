import { ApiError } from "../../api/httpClient";

export function isErroRedeOuIndisponivel(erro) {
  if (!erro) return true;
  if (erro?.name === "AbortError") return true;

  if (erro instanceof ApiError) {
    if (!erro.status) return true;
    if (erro.status >= 500) return true;
    return false;
  }

  return true;
}

export function isErroNegocioVenda(erro) {
  return erro instanceof ApiError && erro.status === 422;
}

export function isErroConectividadeMensagem(mensagem) {
  const texto = String(mensagem || "").toLowerCase();
  return (
    texto.includes("timeout") ||
    texto.includes("communication") ||
    texto.includes("comunicação") ||
    texto.includes("comunicacao") ||
    texto.includes("failed to fetch") ||
    texto.includes("network") ||
    texto.includes("abort") ||
    texto.includes("alcancar") ||
    texto.includes("alcançar")
  );
}

export function redeDisponivel() {
  if (typeof navigator !== "undefined" && navigator.onLine === false) return false;
  return true;
}

export function podeTentarOperacaoOffline(erroOuMensagem) {
  if (!redeDisponivel()) return true;
  if (typeof erroOuMensagem === "string") {
    return isErroConectividadeMensagem(erroOuMensagem);
  }
  return isErroRedeOuIndisponivel(erroOuMensagem);
}
