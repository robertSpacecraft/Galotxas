import axios from 'axios';
import { clearAuthSession, getStoredAuthToken } from './authSession';
import { resolveApiBaseUrl } from './apiBaseUrl';

const api = axios.create({
    baseURL: resolveApiBaseUrl({
        configuredUrl: import.meta.env.VITE_API_BASE_URL,
        isDevelopment: import.meta.env.DEV,
    }),
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
    }
});

api.interceptors.request.use(config => {
    const token = getStoredAuthToken();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

api.interceptors.response.use(
    response => response,
    error => {
        const status = error.response?.status;

        if ((status === 401 || status === 403) && getStoredAuthToken()) {
            clearAuthSession(`http-${status}`);
        }

        return Promise.reject(error);
    }
);

export default api;
