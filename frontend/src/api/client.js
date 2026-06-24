import axios from 'axios';
import { clearAuthSession, getStoredAuthToken } from './authSession';

const api = axios.create({
    baseURL: 'http://localhost:8080/api/v1',
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
