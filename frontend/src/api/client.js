import axios from 'axios';
import {
    AUTH_MODE_HEADER,
    AUTH_MODE_SESSION,
    CSRF_HEADER,
    clearAuthSession,
    getCsrfToken,
    isSafeMethod,
    isSessionExpected,
    setCsrfToken,
    shouldInvalidateAuthSession,
} from './authSession';
import { resolveApiBaseUrl } from './apiBaseUrl';

const api = axios.create({
    baseURL: resolveApiBaseUrl({
        configuredUrl: import.meta.env.VITE_API_BASE_URL,
        isDevelopment: import.meta.env.DEV,
    }),
    withCredentials: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
    }
});

const usesSessionMode = (config) => config.sessionMode === true || isSessionExpected();

api.interceptors.request.use(config => {
    if (usesSessionMode(config)) {
        config.headers.set(AUTH_MODE_HEADER, AUTH_MODE_SESSION);

        const csrfToken = getCsrfToken();
        if (csrfToken && !isSafeMethod(config.method)) {
            config.headers.set(CSRF_HEADER, csrfToken);
        }
    }

    return config;
});

let csrfRefresh = null;

/**
 * Obtiene un token CSRF nuevo y lo guarda sólo en memoria. Las llamadas
 * simultáneas comparten la misma petición.
 */
export const refreshSpaCsrfToken = () => {
    csrfRefresh ??= api.get('/auth/csrf', { sessionMode: true, skipCsrfRetry: true })
        .then((response) => {
            const token = response.data?.data?.csrf_token;

            if (typeof token !== 'string' || token === '') {
                throw new Error('Respuesta CSRF no válida.');
            }

            setCsrfToken(token);

            return token;
        })
        .finally(() => {
            csrfRefresh = null;
        });

    return csrfRefresh;
};

const canRetryCsrf = (error) => {
    const config = error.config;

    return error.response?.status === 419
        && Boolean(config)
        && usesSessionMode(config)
        && config.skipCsrfRetry !== true
        && config.csrfRetried !== true;
};

api.interceptors.response.use(
    response => response,
    async error => {
        if (canRetryCsrf(error)) {
            try {
                await refreshSpaCsrfToken();
            } catch {
                return Promise.reject(error);
            }

            return api.request({ ...error.config, csrfRetried: true });
        }

        const status = error.response?.status;

        if (shouldInvalidateAuthSession(error) && isSessionExpected()) {
            clearAuthSession(`http-${status}`);
        }

        return Promise.reject(error);
    }
);

export default api;
