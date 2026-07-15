import { temApiConfigurada } from "../api";
import { redeDisponivel, isErroRedeOuIndisponivel } from "./offline/networkError";
import { useSessaoStore } from "../store/useSessaoStore";
import { useProdutoStore } from "../store/useProdutoStore";
import { useVendaStore } from "../store/useVendaStore";
import { useOfflineStore } from "../store/useOfflineStore";
import { useMesaStore } from "../store/useMesaStore";

/** Sincronização periódica do POS (inventário, vendas pendentes, histórico). */
export const INTERVALO_SYNC_POS_MS = 5 * 60 * 1000;

let timerSyncPos = null;
let sincronizacaoEmCurso = false;

export function filtrosInventarioPosSessao(sessaoStore) {
  const locationId = sessaoStore?.sourceLocationId;
  if (!locationId) return {};
  return { source_location_id: locationId };
}

export async function executarSincronizacaoPosBackground() {
  if (!temApiConfigurada() || !redeDisponivel() || sincronizacaoEmCurso) {
    return { ok: false, ignorado: true };
  }

  const sessaoStore = useSessaoStore();
  sessaoStore.hidratar();
  if (!sessaoStore.estaLogado) {
    return { ok: false, ignorado: true };
  }

  sincronizacaoEmCurso = true;
  const filtros = filtrosInventarioPosSessao(sessaoStore);
  const produtoStore = useProdutoStore();
  const vendaStore = useVendaStore();
  const offlineStore = useOfflineStore();

  try {
    try {
      await offlineStore.sincronizarPendentes();
    } catch {
      // Mantém fila para a próxima janela de sync.
    }

    try {
      await produtoStore.sincronizarInventarioBackground(filtros);
    } catch (erro) {
      if (!isErroRedeOuIndisponivel(erro)) {
        // Inventário local continua válido para venda no balcão.
      }
    }

    try {
      await vendaStore.sincronizarHistorico();
    } catch {
      // Histórico local do turno permanece disponível.
    }

    try {
      if (sessaoStore.registerId) {
        await sessaoStore.sincronizarTurnoRemoto();
      }
    } catch {
      // Turno local não é bloqueado.
    }

    try {
      const mesaStore = useMesaStore();
      await mesaStore.sincronizarMesasBackground();
    } catch {
      // Comandas locais permanecem disponíveis.
    }

    if (typeof window !== "undefined") {
      window.dispatchEvent(new CustomEvent("retailpro:pos-sync-background"));
    }

    return { ok: true };
  } finally {
    sincronizacaoEmCurso = false;
  }
}

export function iniciarSincronizacaoPeriodicaPos() {
  pararSincronizacaoPeriodicaPos();
  if (!temApiConfigurada()) return;

  timerSyncPos = window.setInterval(() => {
    void executarSincronizacaoPosBackground();
  }, INTERVALO_SYNC_POS_MS);
}

export function pararSincronizacaoPeriodicaPos() {
  if (timerSyncPos) {
    window.clearInterval(timerSyncPos);
    timerSyncPos = null;
  }
}
