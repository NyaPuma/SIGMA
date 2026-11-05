/**
 * API Client Service
 * Centralized service for API communication
 */

import axios from 'axios';

/**
 * AXIOS CONFIGURATION
 */
const apiClient = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
    }
});

// CSRF Token
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrfToken) {
    apiClient.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

// Interceptors
apiClient.interceptors.request.use((config) => {
    const token = localStorage.getItem('api_token') || getCookie('api_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
}, (error) => Promise.reject(error));

apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            handleSessionExpiration();
        }
        return Promise.reject(error);
    }
);

// Exposes globally
window.axios = apiClient;

/**
 * HELPERS
 */
function i18n() {
    return window.SGM_UI_I18N || {};
}

function handleSessionExpiration() {
    localStorage.removeItem('api_token');
    localStorage.removeItem('user_role');
    localStorage.removeItem('user_name');

    if (window.showToast) {
        window.showToast(i18n().sessionExpired || 'Session expired. Redirecting...', 'error');
    }

    setTimeout(() => {
        window.location.href = '/ui/login';
    }, 1500);
}

function getCookie(name) {
    return document.cookie.split('; ').find(row => row.startsWith(`${name}=`))?.split('=')[1] || null;
}

// Global API Helper
window.api = {
    get: (url, cfg) => apiClient.get(url, cfg),
    post: (url, data, cfg) => apiClient.post(url, data, cfg),
    put: (url, data, cfg) => apiClient.put(url, data, cfg),
    patch: (url, data, cfg) => apiClient.patch(url, data, cfg),
    delete: (url, cfg) => apiClient.delete(url, cfg),
};

/**
 * NETWORK MONITORING
 */
window.addEventListener('offline', () => window.showToast?.(i18n().offlineMode || 'No connection to the server.', 'error'));
window.addEventListener('online', () => window.showToast?.(i18n().connectionRestored || 'Connection restored.', 'success'));

/**
 * Converts an HTMLFormElement or FormData into a plain JavaScript object
 * @param {HTMLFormElement|FormData} form 
 * @returns {Object}
 */
export function formToObject(form) {
    const formData = form instanceof FormData ? form : new FormData(form);
    return Object.fromEntries(formData.entries());
}
