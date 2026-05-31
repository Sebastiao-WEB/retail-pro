import { createI18n } from 'vue-i18n';
import pt_MZ from './locales/pt_MZ.json';
import so_SO from './locales/so_SO.json';
import { getStoredLocale, setStoredLocale } from '../services/localeStorage.js';

export { getStoredLocale, setStoredLocale };

const i18n = createI18n({
  legacy: false,
  locale: getStoredLocale(),
  fallbackLocale: 'pt_MZ',
  messages: {
    pt_MZ,
    so_SO,
  },
});

export default i18n;
