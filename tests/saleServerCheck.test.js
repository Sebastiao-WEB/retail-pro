import { describe, expect, it, vi } from "vitest";
import { ApiError } from "../src/api/httpClient";
import { vendaJaRegistadaNoServidor } from "../src/services/offline/saleServerCheck";

vi.mock("../src/api", () => ({
  temApiConfigurada: () => true,
}));

describe("offline/saleServerCheck", () => {
  it("trata erro de rede como venda ainda não registada", async () => {
    const obter = vi.fn().mockRejectedValue(new ApiError("Failed to fetch", 0));
    await expect(vendaJaRegistadaNoServidor("venda-1", { obter })).resolves.toBe(false);
  });

  it("confirma venda existente quando GET responde", async () => {
    const obter = vi.fn().mockResolvedValue({ data: { id: "venda-1" } });
    await expect(vendaJaRegistadaNoServidor("venda-1", { obter })).resolves.toBe(true);
  });

  it("ignora 404 como venda inexistente", async () => {
    const obter = vi.fn().mockRejectedValue(new ApiError("not found", 404));
    await expect(vendaJaRegistadaNoServidor("venda-1", { obter })).resolves.toBe(false);
  });
});
