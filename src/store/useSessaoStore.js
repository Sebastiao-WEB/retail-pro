import { defineStore } from "pinia";
import { limparTokens, salvarTokens } from "../services/authStorage";

const CHAVE_SESSAO = "retailpro:sessao";

export const useSessaoStore = defineStore("sessao", {
  state: () => ({
    carregado: false,
    utilizador: null,
    perfil: "CASHIER",
    caixaAtribuido: "",
    registerId: null,
    registerCodigo: "",
    sourceLocationId: null,
    sourceLocationCodigo: "",
    sourceLocationNome: "",
    turnoAberto: false,
    fundoInicial: 0,
    aberturaEm: "",
    historicoFecho: [],
  }),
  getters: {
    estaLogado: (state) => !!state.utilizador,
  },
  actions: {
    normalizarUuid(valor) {
      if (valor === null || valor === undefined) return null;
      const texto = String(valor).trim();
      return texto ? texto : null;
    },
    hidratar() {
      if (this.carregado) return;
      this.carregado = true;
      try {
        const raw = localStorage.getItem(CHAVE_SESSAO);
        if (!raw) return;
        const dados = JSON.parse(raw);
        Object.assign(this, dados);
      } catch {
        // mantém estado inicial se houver erro de parsing
      }
    },
    salvar() {
      const payload = {
        utilizador: this.utilizador,
        perfil: this.perfil,
        caixaAtribuido: this.caixaAtribuido,
        registerId: this.registerId,
        registerCodigo: this.registerCodigo,
        sourceLocationId: this.sourceLocationId,
        sourceLocationCodigo: this.sourceLocationCodigo,
        sourceLocationNome: this.sourceLocationNome,
        turnoAberto: this.turnoAberto,
        fundoInicial: this.fundoInicial,
        aberturaEm: this.aberturaEm,
        historicoFecho: this.historicoFecho,
      };
      localStorage.setItem(CHAVE_SESSAO, JSON.stringify(payload));
    },
    login({
      username,
      caixa,
      perfil,
      token,
      refreshToken,
      registerId,
      registerCodigo,
      sourceLocationId,
      sourceLocationCodigo,
      sourceLocationNome,
    }) {
      this.utilizador = username;
      this.caixaAtribuido = caixa;
      this.perfil = perfil || "CASHIER";
      this.registerId = this.normalizarUuid(registerId);
      this.registerCodigo = String(registerCodigo || "");
      this.sourceLocationId = this.normalizarUuid(sourceLocationId);
      this.sourceLocationCodigo = String(sourceLocationCodigo || "");
      this.sourceLocationNome = String(sourceLocationNome || "");
      if (token || refreshToken) {
        salvarTokens({
          accessToken: token || "",
          refreshToken: refreshToken || "",
        });
      }
      this.salvar();
    },
    logout() {
      this.utilizador = null;
      this.perfil = "CASHIER";
      this.caixaAtribuido = "";
      this.registerId = null;
      this.registerCodigo = "";
      this.sourceLocationId = null;
      this.sourceLocationCodigo = "";
      this.sourceLocationNome = "";
      this.turnoAberto = false;
      this.fundoInicial = 0;
      this.aberturaEm = "";
      limparTokens();
      this.salvar();
    },
    abrirTurno({ fundoInicial }) {
      this.turnoAberto = true;
      this.fundoInicial = Number(fundoInicial || 0);
      this.aberturaEm = new Date().toISOString();
      this.salvar();
    },
    fecharTurno(relatorio) {
      this.historicoFecho.unshift({
        ...relatorio,
        fechadoEm: new Date().toISOString(),
      });
      this.turnoAberto = false;
      this.fundoInicial = 0;
      this.aberturaEm = "";
      this.salvar();
    },
  },
});
