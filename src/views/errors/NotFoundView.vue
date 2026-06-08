<script setup>
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import BotaoBase from "../../components/BotaoBase.vue";
import { ArrowLeft, Home } from "lucide-vue-next";
import logoRetailPro from "../../assets/rp.png";

const router = useRouter();
const { t, locale } = useI18n();

locale.value = "pt_MZ";

function voltar() {
  if (window.history.length > 1) {
    router.back();
    return;
  }
  router.push({ name: "pos" });
}
</script>

<template>
  <section class="relative flex min-h-screen items-center justify-center bg-[var(--bg-app)] p-6">
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
      <div class="absolute -left-16 top-10 h-56 w-56 rounded-full bg-[rgba(216,182,90,0.18)] blur-3xl"></div>
      <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-[rgba(17,19,26,0.08)] blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md rounded-xl border border-slate-200 bg-white/95 p-6 text-center shadow-lg backdrop-blur">
      <div class="mb-4 flex justify-center">
        <img :src="logoRetailPro" :alt="t('errors.logoAlt')" class="h-20 w-auto object-contain" />
      </div>

      <p class="bg-gradient-to-br from-[#b88d2f] via-[var(--gold)] to-[#f0d48a] bg-clip-text text-6xl font-extrabold leading-none text-transparent">
        404
      </p>

      <h1 class="mt-3 text-lg font-bold text-slate-900">{{ t("errors.notFound.title") }}</h1>
      <p class="mt-2 text-sm leading-6 text-slate-500">{{ t("errors.notFound.message") }}</p>

      <div class="mt-6 flex flex-wrap justify-center gap-2">
        <BotaoBase variante="secundario" @click="voltar">
          <span class="inline-flex items-center gap-1.5">
            <ArrowLeft :size="14" />
            {{ t("errors.goBack") }}
          </span>
        </BotaoBase>
        <BotaoBase variante="aviso" @click="router.push({ name: 'pos' })">
          <span class="inline-flex items-center gap-1.5">
            <Home :size="14" />
            {{ t("errors.goPos") }}
          </span>
        </BotaoBase>
      </div>
    </div>
  </section>
</template>
