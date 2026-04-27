const baseUrlRaw = String(import.meta.env.VITE_API_URL || "").replace(/\/+$/, "");
const versionRaw = String(import.meta.env.VITE_API_VERSION || "v1").trim().replace(/^\/+|\/+$/g, "");
const baseJaVersionada = /\/v\d+$/i.test(baseUrlRaw);

export const apiConfig = {
  baseUrl: baseUrlRaw,
  timeoutMs: Number(import.meta.env.VITE_API_TIMEOUT_MS || 15000),
  usarBackend: String(import.meta.env.VITE_API_MODE || "mock").toLowerCase() === "api",
  versionPrefix: baseJaVersionada || !versionRaw ? "" : `/${versionRaw}`,
};

export function modoApiAtivo() {
  return apiConfig.usarBackend;
}

export function temApiConfigurada() {
  return apiConfig.usarBackend && !!apiConfig.baseUrl;
}

export function garantirBackendDisponivel() {
  if (!modoApiAtivo()) return;
  if (temApiConfigurada()) return;
  throw new Error("VITE_API_MODE=api ativo, mas VITE_API_URL não está configurado.");
}

