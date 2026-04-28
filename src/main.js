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

const app = createApp(App);

app.use(createPinia());
app.use(router);

app.mount("#app");
