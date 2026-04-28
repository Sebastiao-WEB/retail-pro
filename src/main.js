import { createApp } from "vue";
import "sweetalert2/dist/sweetalert2.min.css";
import "./style.css";
import App from "./App.vue";
import { createPinia } from "pinia";
import router from "./router";
import logoRetailPro from "./assets/rp.png";

const favicon =
  document.querySelector("link[rel='icon']") ||
  document.querySelector("link[rel='shortcut icon']") ||
  document.createElement("link");
favicon.rel = "icon";
favicon.href = logoRetailPro;
if (!favicon.parentNode) {
  document.head.appendChild(favicon);
}

const ESTADO_LOADING_ATTR = "data-rp-loading";
const ESTADO_HTML_ATTR = "data-rp-original-html";
const ESTADO_WIDTH_ATTR = "data-rp-original-width";
const ESTADO_DISABLED_ATTR = "data-rp-original-disabled";
const managedButtons = new Set();
let pendingRequests = 0;

function svgSpinnerInline() {
  return `
    <span class="inline-flex items-center justify-center">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="animate-spin" aria-hidden="true">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3"></circle>
        <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3"></path>
      </svg>
    </span>
  `;
}

function marcarBotaoEmLoading(botao) {
  if (!botao || botao.hasAttribute(ESTADO_LOADING_ATTR) || botao.hasAttribute("data-rp-managed")) return;
  botao.setAttribute(ESTADO_LOADING_ATTR, "1");
  botao.setAttribute(ESTADO_HTML_ATTR, botao.innerHTML);
  botao.setAttribute(ESTADO_WIDTH_ATTR, String(botao.getBoundingClientRect().width || 0));
  botao.setAttribute(ESTADO_DISABLED_ATTR, botao.disabled ? "1" : "0");
  botao.disabled = true;
  botao.style.minWidth = `${Number(botao.getAttribute(ESTADO_WIDTH_ATTR) || 0)}px`;
  botao.innerHTML = svgSpinnerInline();
  managedButtons.add(botao);
}

function restaurarBotoesLoading() {
  managedButtons.forEach((botao) => {
    const htmlOriginal = botao.getAttribute(ESTADO_HTML_ATTR);
    const disabledOriginal = botao.getAttribute(ESTADO_DISABLED_ATTR) === "1";
    if (htmlOriginal !== null) botao.innerHTML = htmlOriginal;
    botao.disabled = disabledOriginal;
    botao.style.minWidth = "";
    botao.removeAttribute(ESTADO_LOADING_ATTR);
    botao.removeAttribute(ESTADO_HTML_ATTR);
    botao.removeAttribute(ESTADO_WIDTH_ATTR);
    botao.removeAttribute(ESTADO_DISABLED_ATTR);
  });
  managedButtons.clear();
}

function atualizarEstadoLoadingGlobal() {
  if (pendingRequests <= 0) {
    pendingRequests = 0;
    restaurarBotoesLoading();
  }
}

const fetchOriginal = window.fetch.bind(window);
window.fetch = async (...args) => {
  pendingRequests += 1;
  try {
    return await fetchOriginal(...args);
  } finally {
    pendingRequests -= 1;
    atualizarEstadoLoadingGlobal();
  }
};

document.addEventListener(
  "click",
  (evento) => {
    const botao = evento.target instanceof Element ? evento.target.closest("button") : null;
    if (!botao || botao.disabled) return;
    marcarBotaoEmLoading(botao);
    window.setTimeout(() => {
      if (pendingRequests === 0) atualizarEstadoLoadingGlobal();
    }, 100);
  },
  true
);

const app = createApp(App);

app.use(createPinia());
app.use(router);

app.mount("#app");
