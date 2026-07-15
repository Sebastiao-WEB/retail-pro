const { contextBridge, ipcRenderer } = require("electron");

const api = {
  ping: () => "pong",
  listarImpressoras: () => ipcRenderer.invoke("pos:listar-impressoras"),
  imprimirTalao: (payload) => ipcRenderer.invoke("pos:imprimir-talao", payload),
  imprimirRelatorioFecho: (payload) => ipcRenderer.invoke("pos:imprimir-relatorio-fecho", payload),
  abrirGaveta: (payload) => ipcRenderer.invoke("pos:abrir-gaveta", payload),
  fecharJanela: () => ipcRenderer.invoke("pos:fechar-janela"),
  minimizarJanela: () => ipcRenderer.invoke("pos:minimizar-janela"),
  alternarMaximizarJanela: () => ipcRenderer.invoke("pos:alternar-maximizar-janela"),
  estadoJanela: () => ipcRenderer.invoke("pos:estado-janela"),
  onEstadoJanela: (callback) => {
    if (typeof callback !== "function") return () => {};
    const handler = (_event, estado) => callback(estado);
    ipcRenderer.on("pos:estado-janela", handler);
    return () => ipcRenderer.removeListener("pos:estado-janela", handler);
  },
};

contextBridge.exposeInMainWorld("api", api);
contextBridge.exposeInMainWorld("desktopInfo", {
  preloadOk: true,
  apiVersion: "1.2.0",
});
