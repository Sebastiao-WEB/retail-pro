export const LOCALE_STORAGE_KEY = 'retailpro:locale';

export const SUPPORTED_LOCALES = ['pt_MZ', 'so_SO'];

export const DEFAULT_LOCALE = 'pt_MZ';

export const LOCALE_LABELS = {
  pt_MZ: 'Português (Moçambique)',
  so_SO: 'Soomaali',
};

export function isValidLocale(locale) {
  return SUPPORTED_LOCALES.includes(locale);
}

export function getStoredLocale() {
  try {
    const stored = localStorage.getItem(LOCALE_STORAGE_KEY);
    return isValidLocale(stored) ? stored : DEFAULT_LOCALE;
  } catch {
    return DEFAULT_LOCALE;
  }
}

export function setStoredLocale(locale) {
  if (!isValidLocale(locale)) return;
  localStorage.setItem(LOCALE_STORAGE_KEY, locale);
}

export function intlLocale(locale) {
  return locale === 'so_SO' ? 'so-SO' : 'pt-MZ';
}

export function htmlLang(locale) {
  return locale === 'so_SO' ? 'so' : 'pt-MZ';
}
