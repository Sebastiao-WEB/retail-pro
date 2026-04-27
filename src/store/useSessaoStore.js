import { defineStore } from "pinia";
import { temApiConfigurada } from "../api";
import { limparTokens, salvarTokens } from "../services/authStorage";
import { abrirTurnoIntegrado, fecharTurnoIntegrado, obterSessaoAtivaIntegrada } from "../services/integracaoApi";

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
    cashSessionId: null,
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
        cashSessionId: this.cashSessionId,
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
      this.cashSessionId = null;
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
      this.cashSessionId = null;
      this.turnoAberto = false;
      this.fundoInicial = 0;
      this.aberturaEm = "";
      limparTokens();
      this.salvar();
    },
    async abrirTurno({ fundoInicial }) {
      const fundo = Number(fundoInicial || 0);
      let cashSessionId = null;
      let remoto = null;

      if (temApiConfigurada()) {
        if (!this.registerId) {
          return { remotoOk: false, erro: "Operador sem register_id para abrir caixa na API." };
        }
        remoto = await abrirTurnoIntegrado({
          register_id: this.registerId,
          opening_balance: fundo,
          opened_at: new Date().toISOString(),
        });
        if (!remoto?.ok) {
          return { remotoOk: false, erro: remoto?.erro || "Falha ao abrir sessão na API." };
        }
        cashSessionId = this.normalizarUuid(remoto.data?.id);
      }

      this.turnoAberto = true;
      this.fundoInicial = fundo;
      this.aberturaEm = new Date().toISOString();
      this.cashSessionId = cashSessionId;
      this.salvar();
      return { remotoOk: !!remoto?.ok, erro: remoto?.erro || "" };
    },
    async fecharTurno(relatorio) {
      let remoto = null;
      if (temApiConfigurada()) {
        if (!this.cashSessionId) {
          return { remotoOk: false, erro: "Sessão remota não encontrada para fecho na API." };
        }
        remoto = await fecharTurnoIntegrado(this.cashSessionId, {
          closing_balance: Number(relatorio?.dinheiroReal || 0),
          note: String(relatorio?.justificativaDiferenca || "").trim(),
          closed_at: new Date().toISOString(),
        });
        if (!remoto?.ok) {
          return { remotoOk: false, erro: remoto?.erro || "Falha ao fechar sessão na API." };
        }
      }

      this.historicoFecho.unshift({
        ...relatorio,
        fechadoEm: new Date().toISOString(),
        cashSessionId: this.cashSessionId,
      });
      this.turnoAberto = false;
      this.fundoInicial = 0;
      this.aberturaEm = "";
      this.cashSessionId = null;
      this.salvar();
      return { remotoOk: !!remoto?.ok, erro: remoto?.erro || "" };
    },
    async sincronizarTurnoRemoto() {
      if (!temApiConfigurada()) return { remotoOk: false };
      if (!this.registerId) return { remotoOk: false, erro: "Operador sem register_id para consultar caixa." };

      const resposta = await obterSessaoAtivaIntegrada(this.registerId);
      if (!resposta?.ok) {
        return { remotoOk: false, erro: resposta?.erro || "Falha ao consultar sessão ativa." };
      }

      const sessao = resposta.data;
      if (!sessao?.id) {
        this.turnoAberto = false;
        this.fundoInicial = 0;
        this.aberturaEm = "";
        this.cashSessionId = null;
        this.salvar();
        return { remotoOk: true, existeTurno: false };
      }

      this.turnoAberto = String(sessao.status || "").toUpperCase() === "OPEN";
      this.fundoInicial = Number(sessao.opening_balance || 0);
      this.aberturaEm = sessao.opened_at || "";
      this.cashSessionId = this.normalizarUuid(sessao.id);
      this.salvar();
      return { remotoOk: true, existeTurno: this.turnoAberto };
    },
  },
});
