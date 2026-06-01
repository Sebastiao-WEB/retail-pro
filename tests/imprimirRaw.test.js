import fs from "node:fs";
import path from "node:path";
import { afterEach, describe, expect, it, vi } from "vitest";
import { resolverScriptImpressaoRaw } from "../electron/imprimirRaw.js";

describe("resolverScriptImpressaoRaw", () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("ignora o script dentro do app.asar e usa app.asar.unpacked", () => {
    const baseDir = "C:\\Program Files\\RetailPro POS\\resources\\app.asar\\electron";
    const scriptDesempacotado =
      "C:\\Program Files\\RetailPro POS\\resources\\app.asar.unpacked\\electron\\imprimirRawWindows.ps1";

    vi.spyOn(fs, "existsSync").mockImplementation((caminho) => {
      const normalizado = String(caminho).replace(/\\/g, "/");
      return normalizado.endsWith("app.asar.unpacked/electron/imprimirRawWindows.ps1");
    });

    expect(resolverScriptImpressaoRaw(baseDir).replace(/\\/g, "/")).toBe(
      scriptDesempacotado.replace(/\\/g, "/")
    );
  });

  it("usa o script local em desenvolvimento fora do asar", () => {
    const baseDir = "/home/dev/retail-pro/electron";
    const scriptLocal = path.join(baseDir, "imprimirRawWindows.ps1");

    vi.spyOn(fs, "existsSync").mockImplementation((caminho) => String(caminho) === scriptLocal);

    expect(resolverScriptImpressaoRaw(baseDir)).toBe(scriptLocal);
  });

  it("falha com mensagem clara quando o script nao existe", () => {
    vi.spyOn(fs, "existsSync").mockReturnValue(false);

    expect(() =>
      resolverScriptImpressaoRaw("C:\\Program Files\\RetailPro POS\\resources\\app.asar\\electron")
    ).toThrow(/Script de impressao RAW nao encontrado/);
  });
});
