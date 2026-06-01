export const INTERVALO_RAPIDO_LEITOR_MS = 80;
export const INTERVALO_RESET_LEITOR_MS = 150;

export function criarEstadoLeitorCodigoBarras() {
  return {
    instanteUltimaTecla: 0,
    teclaPendenteLeitor: "",
    modoLeitorCodigo: false,
  };
}

export function processarTeclaLeitorCodigoBarras(estado, event, agora = Date.now()) {
  const intervalo = estado.instanteUltimaTecla ? agora - estado.instanteUltimaTecla : Number.MAX_SAFE_INTEGER;
  const novoEstado = {
    ...estado,
    instanteUltimaTecla: agora,
  };

  if (event.key === "Enter") {
    const eraLeitor = estado.modoLeitorCodigo;
    novoEstado.modoLeitorCodigo = false;
    novoEstado.teclaPendenteLeitor = "";
    return { estado: novoEstado, acao: eraLeitor ? "confirmar-leitor" : "confirmar-manual" };
  }

  if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) {
    novoEstado.modoLeitorCodigo = false;
    novoEstado.teclaPendenteLeitor = "";
    return { estado: novoEstado, acao: "ignorar" };
  }

  if (estado.modoLeitorCodigo) {
    return { estado: novoEstado, acao: "continuar-leitor" };
  }

  if (intervalo <= INTERVALO_RAPIDO_LEITOR_MS && estado.teclaPendenteLeitor) {
    novoEstado.modoLeitorCodigo = true;
    novoEstado.teclaPendenteLeitor = "";
    return {
      estado: novoEstado,
      acao: "iniciar-leitor",
      valor: `${estado.teclaPendenteLeitor}${event.key}`,
    };
  }

  if (intervalo > INTERVALO_RESET_LEITOR_MS) {
    novoEstado.teclaPendenteLeitor = event.key;
  } else {
    novoEstado.teclaPendenteLeitor = "";
  }
  novoEstado.modoLeitorCodigo = false;

  return { estado: novoEstado, acao: "normal" };
}
