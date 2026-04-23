import { createApp } from "vue";
import "sweetalert2/dist/sweetalert2.min.css";
import "./style.css";
import App from "./App.vue";
import { createPinia } from "pinia";
import router from "./router";

const app = createApp(App);

app.use(createPinia());
app.use(router);

app.mount("#app");
