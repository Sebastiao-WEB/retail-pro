import { createRouter, createWebHashHistory } from "vue-router";
import LayoutPrincipal from "../layouts/LayoutPrincipal.vue";

const routes = [
  {
    path: "/login",
    name: "login",
    component: () => import("../views/login/LoginView.vue"),
  },
  {
    path: "/",
    component: LayoutPrincipal,
    redirect: "/pos",
    children: [
      { path: "pos", name: "pos", component: () => import("../views/pos/PosView.vue") },
      { path: "historico-vendas", name: "historico-vendas", component: () => import("../views/historico/HistoricoVendasView.vue") },
      { path: "configuracoes", name: "configuracoes", component: () => import("../views/configuracoes/ConfiguracoesView.vue") },
    ],
  },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

router.beforeEach((to) => {
  const raw = localStorage.getItem("retailpro:sessao");
  let sessao = null;
  try {
    sessao = raw ? JSON.parse(raw) : null;
  } catch {
    sessao = null;
  }
  const estaLogado = !!sessao?.utilizador;
  if (to.name !== "login" && !estaLogado) return { name: "login" };
  if (to.name === "login" && estaLogado) return { name: "pos" };
  return true;
});

export default router;
