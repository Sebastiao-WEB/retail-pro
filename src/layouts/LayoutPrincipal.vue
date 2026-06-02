<script setup>
import { onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import CabecalhoApp from "../components/CabecalhoApp.vue";
import SidebarApp from "../components/SidebarApp.vue";
import { useClienteStore } from "../store/useClienteStore";
import { useVendaStore } from "../store/useVendaStore";
import { useSessaoStore } from "../store/useSessaoStore";
import { temApiConfigurada } from "../api";
import { isErroRedeOuIndisponivel } from "../services/offline/networkError";
import { mostrarToastSwal } from "../services/toast";
import {
  executarSincronizacaoPosBackground,
  iniciarSincronizacaoPeriodicaPos,
  pararSincronizacaoPeriodicaPos,
} from "../services/posBackgroundSync";

const router = useRouter();
const { t } = useI18n();
const clienteStore = useClienteStore();
const vendaStore = useVendaStore();
const sessaoStore = useSessaoStore();

onMounted(async () => {
  sessaoStore.hidratar();
  if (!sessaoStore.estaLogado) {
    router.replace("/login");
    return;
  }
  try {
    if (temApiConfigurada() && sessaoStore.registerId) {
      await sessaoStore.sincronizarTurnoRemoto();
    }
  } catch (erro) {
    if (!isErroRedeOuIndisponivel(erro)) {
      mostrarToastSwal(erro?.message || t("common.syncFailed"), "error");
    }
  }
  void Promise.all([clienteStore.carregarClientes(), vendaStore.sincronizarHistorico()]).catch((erro) => {
    if (!isErroRedeOuIndisponivel(erro)) {
      mostrarToastSwal(erro?.message || t("common.syncFailed"), "error");
    }
  });

  iniciarSincronizacaoPeriodicaPos();
});

onUnmounted(() => {
  pararSincronizacaoPeriodicaPos();
});
</script>

<template>
  <div class="flex h-full bg-[var(--bg-app)]">
    <SidebarApp />
    <div class="flex min-w-0 flex-1 flex-col">
      <CabecalhoApp />
      <main class="min-h-0 flex-1 overflow-auto p-4">
        <div class="h-full rounded-xl border border-[var(--border)] bg-[var(--panel-muted)] p-4">
          <RouterView />
        </div>
      </main>
    </div>
  </div>
</template>
