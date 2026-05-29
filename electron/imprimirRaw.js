import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawn } from "node:child_process";
import { promisify } from "node:util";
import { execFile } from "node:child_process";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const execFileAsync = promisify(execFile);

function executarComando(comando, args, entrada = null) {
  return new Promise((resolve, reject) => {
    const processo = spawn(comando, args, { stdio: ["pipe", "pipe", "pipe"] });
    let stdout = "";
    let stderr = "";

    processo.stdout.on("data", (chunk) => {
      stdout += chunk.toString();
    });
    processo.stderr.on("data", (chunk) => {
      stderr += chunk.toString();
    });

    processo.on("error", reject);
    processo.on("close", (code) => {
      if (code === 0) {
        resolve({ stdout, stderr });
        return;
      }
      reject(new Error(stderr.trim() || stdout.trim() || `Comando ${comando} falhou (${code}).`));
    });

    if (entrada) {
      processo.stdin.write(entrada);
    }
    processo.stdin.end();
  });
}

async function imprimirRawLinux(deviceName, buffer) {
  await executarComando("lp", ["-d", deviceName, "-o", "raw"], buffer);
}

async function imprimirRawDarwin(deviceName, buffer) {
  await executarComando("lp", ["-d", deviceName, "-o", "raw"], buffer);
}

async function executarPowerShellWindows(args) {
  const candidatos = [
    process.env.SystemRoot ? path.join(process.env.SystemRoot, "System32", "WindowsPowerShell", "v1.0", "powershell.exe") : null,
    "powershell.exe",
    "pwsh.exe",
  ].filter(Boolean);

  let ultimoErro = null;
  for (const binario of candidatos) {
    try {
      return await execFileAsync(binario, ["-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass", ...args], {
        windowsHide: true,
        maxBuffer: 10 * 1024 * 1024,
      });
    } catch (error) {
      ultimoErro = error;
    }
  }

  throw ultimoErro || new Error("PowerShell nao encontrado no Windows.");
}

async function imprimirRawWindows(deviceName, buffer) {
  const tempDir = await fs.mkdtemp(path.join(os.tmpdir(), "retailpro-print-"));
  const tempFile = path.join(tempDir, "talao.bin");
  const scriptPath = path.join(__dirname, "imprimirRawWindows.ps1");

  await fs.writeFile(tempFile, buffer);

  try {
    await executarPowerShellWindows([
      "-File",
      scriptPath,
      "-PrinterName",
      deviceName,
      "-FilePath",
      tempFile,
    ]);
  } finally {
    await fs.rm(tempDir, { recursive: true, force: true });
  }
}

export async function enviarRawParaImpressora(deviceName, buffer, copies = 1) {
  const nome = String(deviceName || "").trim();
  if (!nome) {
    throw new Error("Nome da impressora nao informado.");
  }
  if (!Buffer.isBuffer(buffer) || buffer.length === 0) {
    throw new Error("Buffer RAW vazio.");
  }

  const totalCopias = Math.max(1, Number(copies || 1));
  for (let indice = 0; indice < totalCopias; indice += 1) {
    if (process.platform === "win32") {
      await imprimirRawWindows(nome, buffer);
    } else if (process.platform === "darwin") {
      await imprimirRawDarwin(nome, buffer);
    } else {
      await imprimirRawLinux(nome, buffer);
    }
  }
}
