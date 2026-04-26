import Swal from "sweetalert2";

const CHAVE_CONFIGURACOES = "retailpro:configuracoes";

function somToastsAtivo() {
  try {
    const raw = localStorage.getItem(CHAVE_CONFIGURACOES);
    if (!raw) return true;
    const config = JSON.parse(raw);
    const valor = config?.somToastsAtivo;
    if (valor === false || valor === "false" || valor === 0 || valor === "0") return false;
    return true;
  } catch {
    return true;
  }
}

function tocarTom(ctx, frequencia, duracao, volume = 0.04, type = "sine") {
  const oscillator = ctx.createOscillator();
  const gainNode = ctx.createGain();
  oscillator.type = type;
  oscillator.frequency.setValueAtTime(frequencia, ctx.currentTime);
  gainNode.gain.setValueAtTime(volume, ctx.currentTime);
  gainNode.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duracao);
  oscillator.connect(gainNode);
  gainNode.connect(ctx.destination);
  oscillator.start();
  oscillator.stop(ctx.currentTime + duracao);
}

function tocarSomToast(tipo = "error") {
  if (!somToastsAtivo()) return;
  if (typeof window === "undefined") return;
  const Ctx = window.AudioContext || window.webkitAudioContext;
  if (!Ctx) return;

  try {
    const ctx = new Ctx();
    const t = String(tipo || "error").toLowerCase();
    if (t === "success") {
      tocarTom(ctx, 740, 0.09, 0.035, "triangle");
      setTimeout(() => tocarTom(ctx, 988, 0.12, 0.04, "triangle"), 90);
    } else if (t === "warning") {
      tocarTom(ctx, 520, 0.11, 0.045, "square");
      setTimeout(() => tocarTom(ctx, 460, 0.11, 0.04, "square"), 120);
    } else if (t === "info" || t === "question") {
      tocarTom(ctx, 640, 0.1, 0.035, "sine");
      setTimeout(() => tocarTom(ctx, 640, 0.1, 0.03, "sine"), 130);
    } else {
      tocarTom(ctx, 330, 0.12, 0.05, "sawtooth");
      setTimeout(() => tocarTom(ctx, 240, 0.14, 0.04, "sawtooth"), 110);
    }
    setTimeout(() => ctx.close().catch(() => {}), 450);
  } catch {
    // Som é opcional; falha não deve impactar o toast.
  }
}

export function mostrarToastSwal(mensagem, tipo = "error") {
  tocarSomToast(tipo);
  return Swal.fire({
    toast: true,
    position: "top-end",
    icon: tipo,
    title: mensagem,
    showConfirmButton: false,
    timer: 2600,
    timerProgressBar: true,
    customClass: {
      container: "retailpro-toast-container-top",
      popup: "retailpro-toast-top",
    },
  });
}

