import { defineStore } from "pinia";
import { temApiConfigurada } from "../api";
import { verificarConexaoBackend } from "../services/backendStatus";
import { contarFilaPendente } from "../services/offline/pendingQueue";
import { sincronizarFilaOffline } from "../services/offline/offlineSync";
import { redeDisponivel } from "../services/offline/networkError";

export const useOfflineStore = defineStore("offline", {
  state: () => ({
    backendConectado: true,
    sincronizando: false,
    ultimaSyncEm: "",
    ultimaSyncEnviados: 0,
  }),
  getters: {
    totalPendentes() {
      return contarFilaPendente();
    },
    modoOfflineOperacao(state) {
      return temApiConfigurada() && !state.backendConectado;
    },
  },
  actions: {
    async atualizarConectividade() {
      if (!temApiConfigurada()) {
        this.backendConectado = true;
        return { conectado: true };
      }

      if (!redeDisponivel()) {
        const eraOnline = this.backendConectado;
        this.backendConectado = false;
        return { conectado: false, eraOnline };
      }

      const status = await verificarConexaoBackend();
      const eraOnline = this.backendConectado;
      this.backendConectado = !!status.conectado;

      if (!eraOnline && this.backendConectado) {
        await this.sincronizarPendentes();
      }

      return { conectado: this.backendConectado, eraOnline };
    },
    async sincronizarPendentes() {
      if (!temApiConfigurada() || this.sincronizando || !contarFilaPendente()) {
        return { enviados: 0, pendentes: contarFilaPendente() };
      }

      this.sincronizando = true;
      try {
        const resultado = await sincronizarFilaOffline();
        this.ultimaSyncEm = new Date().toISOString();
        this.ultimaSyncEnviados = Number(resultado.enviados || 0);
        if (typeof window !== "undefined") {
          window.dispatchEvent(
            new CustomEvent("retailpro:offline-sync", { detail: resultado })
          );
        }
        return resultado;
      } finally {
        this.sincronizando = false;
      }
    },
  },
});
