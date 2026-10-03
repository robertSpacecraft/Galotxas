import { useCallback, useState, useEffect } from 'react';
import api, { refreshSpaCsrfToken } from '../api/client';
import { meService } from '../api/me';
import {
    AUTH_EVENT_SESSION_CHANGED,
    AUTH_EVENT_SESSION_ENDED,
    AUTH_SESSION_CLEARED_EVENT,
    broadcastAuthEvent,
    cleanupLegacyAuthStorage,
    clearAuthSession,
    resetSessionState,
    setCsrfToken,
    setSessionExpected,
    shouldInvalidateAuthSession,
    subscribeAuthEvents,
} from '../api/authSession';
import { AuthContext } from './authContext';

const normalizeAuthUser = (rawData) => {
    const userData = rawData.user ? { ...rawData.user } : { ...rawData };

    if (rawData.player) {
        userData.player = rawData.player;
    }

    delete userData.token;
    delete userData.csrf_token;

    return userData;
};

const establishSession = (rawData) => {
    setCsrfToken(rawData.csrf_token);
    setSessionExpected(true);
};

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [restoring, setRestoring] = useState(true);
    const [sessionRestoreFailed, setSessionRestoreFailed] = useState(false);

    // La verdad de la sesión es el servidor: /me decide quién es el usuario.
    const restoreSession = useCallback(async ({ reason } = {}) => {
        if (reason !== 'sync') {
            setRestoring(true);
            setSessionRestoreFailed(false);
        }

        try {
            const response = await api.get('/me', { sessionMode: true });

            setUser(normalizeAuthUser(response.data.data));
            setSessionExpected(true);
            setSessionRestoreFailed(false);

            try {
                await refreshSpaCsrfToken();
            } catch {
                console.error('No se ha podido preparar la protección CSRF de la sesión.');
            }
        } catch (error) {
            if (shouldInvalidateAuthSession(error)) {
                resetSessionState();
                setUser(null);
                setSessionRestoreFailed(false);
            } else {
                console.error('No se ha podido restaurar la sesión autenticada.');
                setSessionRestoreFailed(true);
            }
        } finally {
            if (reason !== 'sync') {
                setRestoring(false);
            }
        }
    }, []);

    useEffect(() => {
        cleanupLegacyAuthStorage();
        void restoreSession();
    }, [restoreSession]);

    useEffect(() => {
        const handleSessionCleared = () => {
            setUser(null);
        };

        window.addEventListener(AUTH_SESSION_CLEARED_EVENT, handleSessionCleared);

        return () => {
            window.removeEventListener(AUTH_SESSION_CLEARED_EVENT, handleSessionCleared);
        };
    }, []);

    useEffect(() => subscribeAuthEvents((type) => {
        if (type === AUTH_EVENT_SESSION_ENDED) {
            resetSessionState();
            setUser(null);
        } else if (type === AUTH_EVENT_SESSION_CHANGED) {
            void restoreSession({ reason: 'sync' });
        }
    }), [restoreSession]);

    const login = async (email, password) => {
        await refreshSpaCsrfToken();

        const response = await api.post('/auth/session/login', { email, password }, { sessionMode: true });
        const rawData = response.data.data;
        const userData = normalizeAuthUser(rawData);

        establishSession(rawData);
        setUser(userData);
        broadcastAuthEvent(AUTH_EVENT_SESSION_CHANGED);

        return userData;
    };

    const register = async (userDataInput) => {
        await refreshSpaCsrfToken();

        const response = await api.post('/auth/session/register', userDataInput, { sessionMode: true });
        const rawData = response.data.data;
        const userData = normalizeAuthUser(rawData);

        establishSession(rawData);
        setUser(userData);
        broadcastAuthEvent(AUTH_EVENT_SESSION_CHANGED);

        return userData;
    };

    const createPlayerProfile = async (playerData) => {
        const response = await api.post('/me/player-profile', playerData);
        const newPlayer = response.data.data;
        setUser((currentUser) => ({
            ...currentUser,
            player: newPlayer,
            profile_declaration_required: false,
        }));
        return newPlayer;
    };

    const logout = useCallback(async () => {
        try {
            await api.post('/auth/session/logout', null, { sessionMode: true });
        } catch (error) {
            const status = error.response?.status;

            if (status === 401 || status === 419) {
                // La sesión ya no existía en el servidor: el estado local pasa a anónimo.
            } else {
                // Sin confirmación del servidor no se afirma que la sesión se haya cerrado.
                console.error('No se ha podido cerrar la sesión en el servidor.');

                return false;
            }
        }

        resetSessionState();
        setUser(null);
        broadcastAuthEvent(AUTH_EVENT_SESSION_ENDED);

        return true;
    }, []);

    const refreshUser = useCallback(async () => {
        try {
            const response = await api.get('/me');
            const rawData = response.data.data;
            const userData = normalizeAuthUser(rawData);

            setUser(userData);
            return userData;
        } catch (error) {
            const status = error.response?.status;

            if (shouldInvalidateAuthSession(error)) {
                clearAuthSession(`refresh-http-${status}`);
            } else {
                console.error('No se han podido actualizar los datos de la cuenta.');
            }

            throw error;
        }
    }, []);

    const updatePlayerProfile = useCallback(async (playerData) => {
        const updatedPlayer = await meService.updatePlayerProfile(playerData);
        setUser((currentUser) => currentUser
            ? { ...currentUser, player: updatedPlayer }
            : currentUser);
        await refreshUser();

        return updatedPlayer;
    }, [refreshUser]);

    const updateProfilePhoto = useCallback((profilePhoto) => {
        setUser((currentUser) => currentUser
            ? { ...currentUser, profile_photo: profilePhoto }
            : currentUser);
    }, []);

    const forgotPassword = async (email) => {
        const response = await api.post('/auth/forgot-password', { email });
        return response.data;
    };

    const resetPassword = async (data) => {
        const response = await api.post('/auth/reset-password', data);

        // El servidor ya ha revocado todas las sesiones de la cuenta.
        resetSessionState();
        setUser(null);
        broadcastAuthEvent(AUTH_EVENT_SESSION_ENDED);

        return response.data;
    };

    // restoring | failed (servidor/red no alcanzable) | authenticated | anonymous (confirmado)
    let authStatus = user ? 'authenticated' : 'anonymous';
    if (restoring) {
        authStatus = 'restoring';
    } else if (!user && sessionRestoreFailed) {
        authStatus = 'failed';
    }

    const value = {
        user,
        authStatus,
        sessionRestoreFailed,
        retrySessionRestore: restoreSession,
        login,
        register,
        logout,
        createPlayerProfile,
        updatePlayerProfile,
        forgotPassword,
        resetPassword,
        refreshUser,
        updateProfilePhoto,
        isAuthenticated: !!user,
        isAdmin: user?.role === 'admin'
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
};
