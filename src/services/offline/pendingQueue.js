const CHAVE_FILA = "retailpro:offline:fila";

function gerarIdFila() {
  if (typeof globalThis.crypto?.randomUUID === "function") {
    return globalThis.crypto.randomUUID();
  }
  return `fila-${Date.now()}-${Math.floor(Math.random() * 100000)}`;
}

function lerFila() {
  if (typeof localStorage === "undefined") return [];
  try {
    const raw = localStorage.getItem(CHAVE_FILA);
    const lista = raw ? JSON.parse(raw) : [];
    return Array.isArray(lista) ? lista : [];
  } catch {
    return [];
  }
}

function gravarFila(lista) {
  if (typeof localStorage === "undefined") return;
  localStorage.setItem(CHAVE_FILA, JSON.stringify(lista));
}

export function listarFilaPendente() {
  return lerFila().sort((a, b) => {
    const ordem = { cash_open: 0, sale: 1, cash_close: 2 };
    const pa = ordem[a.tipo] ?? 9;
    const pb = ordem[b.tipo] ?? 9;
    if (pa !== pb) return pa - pb;
    return String(a.criadoEm || "").localeCompare(String(b.criadoEm || ""));
  });
}

export function contarFilaPendente() {
  return lerFila().length;
}

export function adicionarFilaPendente(item) {
  const fila = lerFila();
  const registo = {
    id: item.id || gerarIdFila(),
    tipo: item.tipo,
    criadoEm: item.criadoEm || new Date().toISOString(),
    tentativas: Number(item.tentativas || 0),
    ultimoErro: String(item.ultimoErro || ""),
    payload: item.payload,
  };
  fila.push(registo);
  gravarFila(fila);
  return registo;
}

export function removerFilaPendente(id) {
  const fila = lerFila().filter((item) => item.id !== id);
  gravarFila(fila);
}

export function atualizarFilaPendente(id, patch) {
  const fila = lerFila();
  const indice = fila.findIndex((item) => item.id === id);
  if (indice === -1) return null;
  fila[indice] = { ...fila[indice], ...patch };
  gravarFila(fila);
  return fila[indice];
}

export function remapearCashSessionNaFila(idAntigo, idNovo) {
  if (!idAntigo || !idNovo || idAntigo === idNovo) return;
  const fila = lerFila().map((item) => {
    const payload = { ...item.payload };
    if (payload.cash_session_id === idAntigo) payload.cash_session_id = idNovo;
    if (payload.cashSessionId === idAntigo) payload.cashSessionId = idNovo;
    if (item.tipo === "cash_close" && payload.cashSessionId === idAntigo) {
      payload.cashSessionId = idNovo;
    }
    if (item.tipo === "cash_open" && payload.localSessionId === idAntigo) {
      payload.localSessionId = idNovo;
    }
    return { ...item, payload };
  });
  gravarFila(fila);
}

export function enfileirarVendaPendente(venda) {
  return adicionarFilaPendente({ tipo: "sale", payload: venda });
}

export function enfileirarAberturaCaixaPendente(payload) {
  return adicionarFilaPendente({ tipo: "cash_open", payload });
}

export function enfileirarFechoCaixaPendente(payload) {
  return adicionarFilaPendente({ tipo: "cash_close", payload });
}
