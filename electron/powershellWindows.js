import fs from "node:fs";
import path from "node:path";
import { promisify } from "node:util";
import { execFile } from "node:child_process";

const execFileAsync = promisify(execFile);

export function resolverBinariosPowerShellWindows() {
  const systemRoot = process.env.SystemRoot || process.env.windir || "C:\\Windows";
  const candidatos = [
    path.join(systemRoot, "System32", "WindowsPowerShell", "v1.0", "powershell.exe"),
    path.join(systemRoot, "Sysnative", "WindowsPowerShell", "v1.0", "powershell.exe"),
  ];

  if (process.env.ProgramFiles) {
    candidatos.push(path.join(process.env.ProgramFiles, "PowerShell", "7", "pwsh.exe"));
  }

  const encontrados = candidatos.filter((binario) => {
    try {
      return fs.existsSync(binario);
    } catch {
      return false;
    }
  });

  if (encontrados.length) return encontrados;

  return ["powershell.exe"];
}

export async function executarPowerShellWindows(args, opcoes = {}) {
  const binarios = resolverBinariosPowerShellWindows();
  let ultimoErro = null;

  for (const binario of binarios) {
    try {
      return await execFileAsync(
        binario,
        ["-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass", ...args],
        {
          windowsHide: true,
          maxBuffer: opcoes.maxBuffer ?? 10 * 1024 * 1024,
        }
      );
    } catch (error) {
      ultimoErro = error;
      if (error?.code === "ENOENT") continue;
      throw error;
    }
  }

  throw new Error(
    "Windows PowerShell nao encontrado. Confirme que o PowerShell 5.1 esta instalado no sistema."
  );
}
