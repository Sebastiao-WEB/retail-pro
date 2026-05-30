<script setup>
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import CabecalhoApp from "../components/CabecalhoApp.vue";
import SidebarApp from "../components/SidebarApp.vue";
import { useClienteStore } from "../store/useClienteStore";
import { useVendaStore } from "../store/useVendaStore";
import { useSessaoStore } from "../store/useSessaoStore";
import { mostrarToastSwal } from "../services/toast";

const router = useRouter();
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
    await Promise.all([clienteStore.carregarClientes(), vendaStore.sincronizarHistorico()]);
  } catch (erro) {
    mostrarToastSwal(erro?.message || "Falha ao sincronizar dados do backend.", "error");
  }
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
