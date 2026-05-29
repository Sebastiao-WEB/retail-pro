import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { spawn } from "node:child_process";
import { promisify } from "node:util";
import { execFile } from "node:child_process";

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

async function imprimirRawWindows(deviceName, buffer) {
  const tempDir = await fs.mkdtemp(path.join(os.tmpdir(), "retailpro-print-"));
  const tempFile = path.join(tempDir, "talao.bin");
  await fs.writeFile(tempFile, buffer);

  const script = `
$ErrorActionPreference = "Stop"
Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class RawPrinterHelper {
  [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
  public class DOCINFO {
    [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
    [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;
    [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
  }
  [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]
  public static extern bool OpenPrinter(string pPrinterName, out IntPtr phPrinter, IntPtr pDefault);
  [DllImport("winspool.drv", SetLastError = true)]
  public static extern bool ClosePrinter(IntPtr hPrinter);
  [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]
  public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In] DOCINFO di);
  [DllImport("winspool.drv", SetLastError = true)]
  public static extern bool EndDocPrinter(IntPtr hPrinter);
  [DllImport("winspool.drv", SetLastError = true)]
  public static extern bool StartPagePrinter(IntPtr hPrinter);
  [DllImport("winspool.drv", SetLastError = true)]
  public static extern bool EndPagePrinter(IntPtr hPrinter);
  [DllImport("winspool.drv", SetLastError = true)]
  public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);
  public static bool SendBytesToPrinter(string printerName, byte[] bytes) {
    IntPtr hPrinter;
    if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero)) return false;
    try {
      DOCINFO di = new DOCINFO();
      di.pDocName = "RetailPro Talao";
      di.pDataType = "RAW";
      if (!StartDocPrinter(hPrinter, 1, di)) return false;
      try {
        if (!StartPagePrinter(hPrinter)) return false;
        try {
          IntPtr unmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
          Marshal.Copy(bytes, 0, unmanagedBytes, bytes.Length);
          int written = 0;
          bool ok = WritePrinter(hPrinter, unmanagedBytes, bytes.Length, out written);
          Marshal.FreeCoTaskMem(unmanagedBytes);
          if (!ok) return false;
        } finally { EndPagePrinter(hPrinter); }
      } finally { EndDocPrinter(hPrinter); }
    } finally { ClosePrinter(hPrinter); }
    return true;
  }
}
"@
$bytes = [System.IO.File]::ReadAllBytes('${tempFile.replace(/\\/g, "\\\\")}')
$result = [RawPrinterHelper]::SendBytesToPrinter('${deviceName.replace(/'/g, "''")}', $bytes)
if (-not $result) { throw "Falha ao enviar RAW para a impressora." }
`;

  try {
    await execFileAsync("powershell.exe", ["-NoProfile", "-NonInteractive", "-Command", script]);
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
