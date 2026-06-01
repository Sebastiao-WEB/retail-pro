import { temApiConfigurada } from "../../api";
import { ApiError } from "../../api/httpClient";
import {
  abrirTurnoIntegrado,
  criarVendaIntegrada,
  fecharTurnoIntegrado,
} from "../integracaoApi";
import { useSessaoStore } from "../../store/useSessaoStore";
import { useVendaStore } from "../../store/useVendaStore";
import {
  isErroNegocioVenda,
  isErroRedeOuIndisponivel,
  redeDisponivel,
} from "./networkError";
import {
  listarFilaPendente,
  removerFilaPendente,
  remapearCashSessionNaFila,
  atualizarFilaPendente,
} from "./pendingQueue";

export async function sincronizarFilaOffline() {
  if (!temApiConfigurada() || !redeDisponivel()) {
    return { enviados: 0, pendentes: listarFilaPendente().length, interrompido: true };
  }

  const fila = listarFilaPendente();
  let enviados = 0;

  for (const item of fila) {
    try {
      if (item.tipo === "cash_open") {
        await sincronizarAberturaCaixa(item);
      } else if (item.tipo === "sale") {
        await criarVendaIntegrada(item.payload);
      } else if (item.tipo === "cash_close") {
        await sincronizarFechoCaixa(item);
      } else {
        removerFilaPendente(item.id);
        continue;
      }

      removerFilaPendente(item.id);
      enviados += 1;
    } catch (erro) {
      atualizarFilaPendente(item.id, {
        tentativas: Number(item.tentativas || 0) + 1,
        ultimoErro: erro?.message || String(erro),
      });

      if (isErroNegocioVenda(erro)) {
        removerFilaPendente(item.id);
        continue;
      }

      if (isErroRedeOuIndisponivel(erro)) {
        return { enviados, pendentes: listarFilaPendente().length, interrompido: true, erro };
      }

      throw erro;
    }
  }

  if (enviados > 0) {
    try {
      await useVendaStore().sincronizarHistorico();
    } catch {
      // Histórico remoto pode falhar; vendas já foram enviadas.
    }
  }

  return { enviados, pendentes: listarFilaPendente().length, interrompido: false };
}

async function sincronizarAberturaCaixa(item) {
  const payload = item.payload || {};
  const resposta = await abrirTurnoIntegrado({
    register_id: payload.register_id,
    opening_balance: payload.opening_balance,
    opened_at: payload.opened_at,
  });

  if (!resposta?.ok && !resposta?.data?.id) {
    throw new ApiError(resposta?.erro || "Falha ao sincronizar abertura de caixa.");
  }

  const idServidor = resposta.data?.id;
  const idLocal = payload.localSessionId;
  if (idServidor && idLocal && idServidor !== idLocal) {
    remapearCashSessionNaFila(idLocal, idServidor);
    const sessaoStore = useSessaoStore();
    sessaoStore.hidratar();
    if (sessaoStore.cashSessionId === idLocal) {
      sessaoStore.cashSessionId = idServidor;
      sessaoStore.salvar();
    }
  }
}

async function sincronizarFechoCaixa(item) {
  const payload = item.payload || {};
  const cashSessionId = payload.cashSessionId || payload.cash_session_id;
  if (!cashSessionId) {
    throw new ApiError("Sessão de caixa em falta na fila offline.");
  }

  const resposta = await fecharTurnoIntegrado(cashSessionId, {
    closing_balance: Number(payload.closing_balance ?? payload.closingBalance ?? 0),
    note: String(payload.note || ""),
    closed_at: payload.closed_at || payload.closedAt || new Date().toISOString(),
    report_snapshot: payload.report_snapshot || payload.reportSnapshot,
  });

  if (!resposta?.ok) {
    throw new ApiError(resposta?.erro || "Falha ao sincronizar fecho de caixa.");
  }
}
