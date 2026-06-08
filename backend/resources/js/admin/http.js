import axios from 'axios';
import { trackRequestEnd, trackRequestStart } from './preloader.js';

const http = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (token) {
    http.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

http.interceptors.request.use(
    (config) => {
        if (!config.skipPreloader) {
            trackRequestStart();
        }
        return config;
    },
    (error) => {
        if (!error?.config?.skipPreloader) {
            trackRequestEnd();
        }
        return Promise.reject(error);
    }
);

http.interceptors.response.use(
    (response) => {
        if (!response.config?.skipPreloader) {
            trackRequestEnd();
        }
        return response;
    },
    (error) => {
        if (!error?.config?.skipPreloader) {
            trackRequestEnd();
        }
        const message =
            error?.response?.data?.message ||
            error?.response?.data?.errors?.[Object.keys(error?.response?.data?.errors || {})[0]]?.[0] ||
            error?.message ||
            'Erro de comunicação com o servidor.';

        return Promise.reject({
            status: error?.response?.status,
            message,
            errors: error?.response?.data?.errors || {},
            original: error,
        });
    }
);

export default http;
