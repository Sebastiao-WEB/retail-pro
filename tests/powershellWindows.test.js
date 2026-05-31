import fs from "node:fs";
import path from "node:path";
import { afterEach, describe, expect, it, vi } from "vitest";
import { resolverBinariosPowerShellWindows } from "../electron/powershellWindows.js";

describe("powershellWindows", () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("prioriza o caminho completo do Windows PowerShell 5.1", () => {
    vi.spyOn(fs, "existsSync").mockImplementation((caminho) => {
      const normalizado = String(caminho).replace(/\\/g, "/");
      return normalizado.endsWith("WindowsPowerShell/v1.0/powershell.exe");
    });

    const binarios = resolverBinariosPowerShellWindows();
    const systemRoot = process.env.SystemRoot || process.env.windir || "C:\\Windows";

    expect(binarios[0]).toBe(
      path.join(systemRoot, "System32", "WindowsPowerShell", "v1.0", "powershell.exe")
    );
    expect(binarios.some((item) => item.endsWith("pwsh.exe"))).toBe(false);
  });

  it("usa fallback powershell.exe quando nenhum caminho absoluto existe", () => {
    vi.spyOn(fs, "existsSync").mockReturnValue(false);

    expect(resolverBinariosPowerShellWindows()).toEqual(["powershell.exe"]);
  });
});
