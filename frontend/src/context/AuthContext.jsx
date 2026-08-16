import { useCallback, useState, useEffect } from 'react';
import api from '../api/client';
import {
    AUTH_SESSION_CLEARED_EVENT,
    clearAuthSession,
    clearStoredAuth,
    discardLegacyStoredUser,
    getStoredAuthToken,
    shouldInvalidateAuthSession,
    storeAuthToken,
} from '../api/authSession';
import { AuthContext } from './authContext';

const normalizeAuthUser = (rawData) => {
    const userData = rawData.user ? { ...rawData.user } : { ...rawData };

    if (rawData.player) {
        userData.player = rawData.player;
    }

    delete userData.token;

    return userData;
};

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let active = true;

        const bootstrapSession = async () => {
            discardLegacyStoredUser();
            const storedToken = getStoredAuthToken();

            if (!storedToken) {
                if (active) {
                    setLoading(false);
                }

                return;
            }

            setToken(storedToken);

            try {
                const response = await api.get('/me');

                if (active) {
                    setUser(normalizeAuthUser(response.data.data));
                }
            } catch (error) {
                const status = error.response?.status;

                if (shouldInvalidateAuthSession(error)) {
                    if (getStoredAuthToken()) {
                        clearAuthSession(`bootstrap-http-${status}`);
                    }
                } else {
                    console.error('No se ha podido restaurar la sesión autenticada.');
                }
            } finally {
                if (active) {
                    setLoading(false);
                }
            }
        };

        void bootstrapSession();

        return () => {
            active = false;
        };
    }, []);

    useEffect(() => {
        const handleSessionCleared = () => {
            setToken(null);
            setUser(null);
        };

        window.addEventListener(AUTH_SESSION_CLEARED_EVENT, handleSessionCleared);

        return () => {
            window.removeEventListener(AUTH_SESSION_CLEARED_EVENT, handleSessionCleared);
        };
    }, []);

    const login = async (email, password) => {
        const response = await api.post('/auth/login', { email, password });
        const rawData = response.data.data;
        const userData = normalizeAuthUser(rawData);

        discardLegacyStoredUser();
        storeAuthToken(rawData.token);

        setToken(rawData.token);
        setUser(userData);
        return userData;
    };

    const register = async (userDataInput) => {
        const response = await api.post('/auth/register', userDataInput);
        const rawData = response.data.data;
        const userData = normalizeAuthUser(rawData);

        discardLegacyStoredUser();
        storeAuthToken(rawData.token);

        setToken(rawData.token);
        setUser(userData);
        return userData;
    };

    const createPlayerProfile = async (playerData) => {
        const response = await api.post('/me/player-profile', playerData);
        const newPlayer = response.data.data;
        setUser((currentUser) => ({ ...currentUser, player: newPlayer }));
        return newPlayer;
    };

    const logout = useCallback(async () => {
        const currentToken = getStoredAuthToken();

        try {
            if (currentToken) {
                await api.post('/auth/logout');
            }
        } catch (error) {
            const status = error.response?.status;
            if (status !== 401 && status !== 403 && status !== 419) {
                console.error('No se ha podido revocar el token remoto durante el cierre de sesión.');
            }
        } finally {
            clearStoredAuth();
            setToken(null);
            setUser(null);
        }
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
                if (getStoredAuthToken()) {
                    clearAuthSession(`refresh-http-${status}`);
                }
            } else {
                console.error('No se han podido actualizar los datos de la cuenta.');
            }

            throw error;
        }
    }, []);

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
        return response.data;
    };

    const value = {
        user,
        token,
        login,
        register,
        logout,
        createPlayerProfile,
        forgotPassword,
        resetPassword,
        refreshUser,
        updateProfilePhoto,
        isAuthenticated: !!token && !!user,
        isAdmin: user?.role === 'admin'
    };

    return (
        <AuthContext.Provider value={value}>
            {!loading && children}
        </AuthContext.Provider>
    );
};
