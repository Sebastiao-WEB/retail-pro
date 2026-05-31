const LOCALE_STORAGE_KEY = 'retailpro:locale';
const DEFAULT_LOCALE = 'pt_MZ';
const SUPPORTED_LOCALES = ['pt_MZ', 'so_SO'];

function isValidLocale(locale) {
    return SUPPORTED_LOCALES.includes(locale);
}

const COOKIE_NAME = 'app_locale';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

function readCookie(name) {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : null;
}

function writeCookie(name, value) {
    document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=${COOKIE_MAX_AGE};SameSite=Lax`;
}

export function getCurrentLocale() {
    try {
        const stored = localStorage.getItem(LOCALE_STORAGE_KEY);
        if (isValidLocale(stored)) {
            return stored;
        }
    } catch {
        // ignore
    }

    const cookieLocale = readCookie(COOKIE_NAME);
    return isValidLocale(cookieLocale) ? cookieLocale : DEFAULT_LOCALE;
}

export function syncLocaleStorageToCookie() {
    const locale = getCurrentLocale();
    try {
        localStorage.setItem(LOCALE_STORAGE_KEY, locale);
    } catch {
        // ignore
    }
    writeCookie(COOKIE_NAME, locale);
}

export function switchLocale(locale) {
    if (!isValidLocale(locale)) {
        return;
    }

    try {
        localStorage.setItem(LOCALE_STORAGE_KEY, locale);
    } catch {
        // ignore
    }

    writeCookie(COOKIE_NAME, locale);
    window.location.reload();
}

export function initLocaleSync() {
    const locale = getCurrentLocale();
    syncLocaleStorageToCookie();
    document.documentElement.lang = locale === 'so_SO' ? 'so' : 'pt-MZ';

    window.retailLocale = {
        current: getCurrentLocale,
        supported: SUPPORTED_LOCALES,
        switch: switchLocale,
    };
}

initLocaleSync();
