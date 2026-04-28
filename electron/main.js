import { app, BrowserWindow, ipcMain } from "electron";
import path from "node:path";
import fs from "node:fs";
import { fileURLToPath } from "node:url";
import { execFile } from "node:child_process";
import { promisify } from "node:util";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
let mainWindow = null;
const execFileAsync = promisify(execFile);

async function executarLpstat(args) {
  const binarios = ["lpstat", "/usr/bin/lpstat", "/bin/lpstat"];
  let ultimoErro = null;

  for (const binario of binarios) {
    try {
      const resultado = await execFileAsync(binario, args);
      return { ...resultado, binario };
    } catch (error) {
      ultimoErro = error;
    }
  }

  throw ultimoErro || new Error("lpstat não encontrado");
}

async function listarImpressorasCups() {
  try {
    const [lista, destinoPadrao] = await Promise.all([
      executarLpstat(["-a"]),
      executarLpstat(["-d"]),
    ]);

    const listaRaw = lista.stdout || "";
    const defaultRaw = destinoPadrao.stdout || "";
    const impressoraPadrao = defaultRaw
      .split(":")
      .slice(1)
      .join(":")
      .trim();

    const impressoras = listaRaw
      .split("\n")
      .map((linha) => linha.trim())
      .filter(Boolean)
      .map((linha) => linha.split(/\s+/)[0])
      .map((nome) => ({
        name: nome,
        displayName: nome,
        isDefault: nome === impressoraPadrao,
        status: "online",
        source: "cups",
      }));
    return { impressoras, debug: { cupsBinario: lista.binario, cupsRaw: listaRaw, defaultRaw } };
  } catch {
    return { impressoras: [], debug: { cupsErro: "Falha ao executar lpstat." } };
  }
}

async function listarImpressorasWindows() {
  try {
    const comando =
      "Get-Printer | Select-Object Name,Default | ConvertTo-Json -Compress";
    const { stdout } = await execFileAsync("powershell.exe", [
      "-NoProfile",
      "-NonInteractive",
      "-Command",
      comando,
    ]);

    if (!stdout?.trim()) {
      return { impressoras: [], debug: { windowsRaw: "" } };
    }

    const dados = JSON.parse(stdout);
    const lista = Array.isArray(dados) ? dados : [dados];
    const impressoras = lista
      .filter((item) => item?.Name)
      .map((item) => ({
        name: item.Name,
        displayName: item.Name,
        isDefault: !!item.Default,
        status: "online",
        source: "windows",
      }));

    return { impressoras, debug: { windowsRaw: stdout } };
  } catch {
    return { impressoras: [], debug: { windowsErro: "Falha ao executar Get-Printer no PowerShell." } };
  }
}

async function obterDadosImpressoras(alvo) {
  const impressorasElectron = alvo
    ? (await alvo.webContents.getPrintersAsync()).map((impressora) => ({
        name: impressora.name,
        displayName: impressora.displayName || impressora.name,
        isDefault: !!impressora.isDefault,
        status: impressora.status,
        source: "electron",
      }))
    : [];

  let impressorasSistema = [];
  let debugSistema = {};
  if (process.platform === "linux") {
    const { impressoras, debug } = await listarImpressorasCups();
    impressorasSistema = impressoras;
    debugSistema = debug;
  } else if (process.platform === "win32") {
    const { impressoras, debug } = await listarImpressorasWindows();
    impressorasSistema = impressoras;
    debugSistema = debug;
  }

  const mapa = new Map();
  [...impressorasSistema, ...impressorasElectron].forEach((impressora) => {
    if (!mapa.has(impressora.name)) {
      mapa.set(impressora.name, impressora);
      return;
    }
    const atual = mapa.get(impressora.name);
    mapa.set(impressora.name, {
      ...atual,
      ...impressora,
      isDefault: atual.isDefault || impressora.isDefault,
    });
  });

  return {
    printers: [...mapa.values()],
    debug: {
      ...debugSistema,
      origemElectron: impressorasElectron.length,
      origemSistema: impressorasSistema.length,
      plataforma: process.platform,
    },
  };
}

function obterIconeAplicacao() {
  const candidatos = [
    path.join(app.getAppPath(), "src/assets/rp.png"),
    path.join(process.cwd(), "src/assets/rp.png"),
  ];
  return candidatos.find((caminho) => fs.existsSync(caminho));
}

function createWindow() {
  const iconeAplicacao = obterIconeAplicacao();
  const window = new BrowserWindow({
    width: 1280,
    height: 735,
    minWidth: 1280,
    minHeight: 735,
    maxWidth: 1280,
    maxHeight: 735,
    resizable: false,
    maximizable: false,
    fullscreenable: false,
    backgroundColor: "#0f172a",
    icon: iconeAplicacao,
    webPreferences: {
      preload: path.join(__dirname, "preload.cjs"),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
  });
  mainWindow = window;

  // Fallback para ambientes onde preload não injeta window.api.
  window.webContents.on("did-finish-load", async () => {
    try {
      const dados = await obterDadosImpressoras(window);
      const payload = JSON.stringify(dados.printers);
      window.webContents
        .executeJavaScript(`
          window.__APP_DESKTOP__ = true;
          window.__IMPRESSORAS_INSTALADAS = ${payload};
        `)
        .catch(() => {});

      const apiKeys = await window.webContents.executeJavaScript(
        "window.api ? Object.keys(window.api) : []"
      );
      const preloadInfo = await window.webContents.executeJavaScript(
        "window.desktopInfo || null"
      );
      console.log("[POS][api] window.api keys:", apiKeys);
      console.log("[POS][api] desktopInfo:", preloadInfo);
    } catch {
      // Ignora fallback silenciosamente.
    }
  });

  const devUrl = process.env.VITE_DEV_SERVER_URL || "http://localhost:5173";
  const indexLocal = path.join(__dirname, "../dist/index.html");
  const forcarLocal = process.env.POS_DESKTOP_LOCAL === "1";

  if (!app.isPackaged) {
    if (forcarLocal) {
      window.loadFile(indexLocal);
      return;
    }

    window
      .loadURL(devUrl)
      .then(() => {
        window.webContents.openDevTools({ mode: "detach" });
      })
      .catch(() => {
        // Se o Vite não estiver no ar, usa o build local.
        window.loadFile(indexLocal);
      });
    return;
  }

  window.loadFile(indexLocal);
}

ipcMain.handle("pos:listar-impressoras", async () => {
  console.log("[POS][print] iniciar listagem de impressoras");
  const alvo =
    BrowserWindow.getFocusedWindow() ||
    mainWindow ||
    BrowserWindow.getAllWindows()[0];
  const resultado = await obterDadosImpressoras(alvo);
  console.log(
    `[POS][print] plataforma=${resultado.debug.plataforma} electron=${resultado.debug.origemElectron} sistema=${resultado.debug.origemSistema}`
  );
  if (resultado.printers.length) {
    console.log("[POS][print] impressoras:", resultado.printers.map((p) => p.displayName || p.name).join(" | "));
  } else {
    console.log("[POS][print] nenhuma impressora detectada", resultado.debug);
  }
  return resultado;
});

ipcMain.handle("pos:listar-impressoras-fallback", async () => {
  const alvo =
    BrowserWindow.getFocusedWindow() ||
    mainWindow ||
    BrowserWindow.getAllWindows()[0];
  return (await obterDadosImpressoras(alvo)).printers;
});

ipcMain.handle("pos:imprimir-talao", async (_event, payload) => {
  const html = String(payload?.html || "");
  const deviceName = String(payload?.deviceName || "");
  const copies = Math.max(1, Number(payload?.copies || 1));
  if (!html.trim()) {
    return { ok: false, error: "Conteúdo do talão vazio." };
  }

  const janelaImpressao = new BrowserWindow({
    show: false,
    webPreferences: {
      sandbox: true,
      nodeIntegration: false,
      contextIsolation: true,
    },
  });

  try {
    await janelaImpressao.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(html)}`);

    const resultado = await new Promise((resolve) => {
      janelaImpressao.webContents.print(
        {
          silent: true,
          printBackground: true,
          deviceName: deviceName || undefined,
          copies,
        },
        (success, failureReason) => {
          if (!success) {
            resolve({ ok: false, error: failureReason || "Falha ao enviar para impressora." });
            return;
          }
          resolve({ ok: true });
        }
      );
    });

    return resultado;
  } catch (error) {
    return { ok: false, error: error?.message || "Erro inesperado ao imprimir." };
  } finally {
    if (!janelaImpressao.isDestroyed()) {
      janelaImpressao.destroy();
    }
  }
});

async function logDiagnosticoImpressorasStartup() {
  let sistema = [];
  if (process.platform === "linux") {
    sistema = (await listarImpressorasCups()).impressoras;
  } else if (process.platform === "win32") {
    sistema = (await listarImpressorasWindows()).impressoras;
  }
  console.log(`[POS][print][startup] sistema(${process.platform})=${sistema.length}`);
  if (sistema.length) {
    console.log("[POS][print][startup] sistema lista:", sistema.map((p) => p.displayName || p.name).join(" | "));
  }

  try {
    if (mainWindow?.webContents) {
      const electronPrinters = await mainWindow.webContents.getPrintersAsync();
      console.log(`[POS][print][startup] electron=${electronPrinters.length}`);
      if (electronPrinters.length) {
        console.log(
          "[POS][print][startup] electron lista:",
          electronPrinters.map((p) => p.displayName || p.name).join(" | ")
        );
      }
    }
  } catch (error) {
    console.log("[POS][print][startup] erro electron getPrintersAsync:", error?.message || error);
  }
}

app.whenReady().then(() => {
  createWindow();
  setTimeout(() => {
    logDiagnosticoImpressorasStartup();
  }, 2000);

  app.on("activate", () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on("window-all-closed", () => {
  if (process.platform !== "darwin") {
    app.quit();
  }
});
