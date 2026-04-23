export const apiConfig = {
  baseUrl: String(import.meta.env.VITE_API_URL || "").replace(/\/+$/, ""),
  timeoutMs: Number(import.meta.env.VITE_API_TIMEOUT_MS || 15000),
  usarBackend: String(import.meta.env.VITE_API_MODE || "mock").toLowerCase() === "api",
};

export function temApiConfigurada() {
  return apiConfig.usarBackend && !!apiConfig.baseUrl;
}

