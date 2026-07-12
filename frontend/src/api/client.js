import axios from 'axios';
import { clearAuthSession, getStoredAuthToken } from './authSession';

const configuredApiBaseUrl = import.meta.env.VITE_API_BASE_URL?.trim();
const defaultApiBaseUrl = import.meta.env.DEV
    ? 'http://localhost:8080/api/v1'
    : '/api/v1';

const api = axios.create({
    baseURL: configuredApiBaseUrl || defaultApiBaseUrl,
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
