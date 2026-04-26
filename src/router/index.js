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
      { path: "clientes", name: "clientes", component: () => import("../views/clientes/ClientesView.vue") },
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
  const modoApi = String(import.meta.env.VITE_API_MODE || "mock").toLowerCase() === "api";
  const token = localStorage.getItem("retailpro:token");
  const estaLogado = modoApi ? !!sessao?.utilizador && !!token : !!sessao?.utilizador;
  if (to.name !== "login" && !estaLogado) return { name: "login" };
  if (to.name === "login" && estaLogado) return { name: "pos" };
  return true;
});

export default router;
