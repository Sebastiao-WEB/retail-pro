const { contextBridge, ipcRenderer } = require("electron");

const api = {
  ping: () => "pong",
  listarImpressoras: () => ipcRenderer.invoke("pos:listar-impressoras"),
  imprimirTalao: (payload) => ipcRenderer.invoke("pos:imprimir-talao", payload),
  imprimirRelatorioFecho: (payload) => ipcRenderer.invoke("pos:imprimir-relatorio-fecho", payload),
};

contextBridge.exposeInMainWorld("api", api);
contextBridge.exposeInMainWorld("desktopInfo", {
  preloadOk: true,
  apiVersion: "1.1.0",
});
