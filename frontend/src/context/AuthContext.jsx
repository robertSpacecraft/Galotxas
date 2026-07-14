import { useCallback, useState, useEffect } from 'react';
import api from '../api/client';
import {
    AUTH_SESSION_CLEARED_EVENT,
    clearAuthSession,
    clearStoredAuth,
    getStoredAuthToken,
} from '../api/authSession';
import { AuthContext } from './authContext';

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        try {
            const storedToken = getStoredAuthToken();
            const storedUser = localStorage.getItem('user');
            
            if (storedToken && storedUser) {
                setToken(storedToken);
                setUser(JSON.parse(storedUser));
            } else if (storedToken || storedUser) {
                clearStoredAuth();
            }
        } catch (error) {
            console.error("Error loading auth from localStorage", error);
            clearStoredAuth();
        } finally {
            setLoading(false);
        }
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
        
        let userData = rawData.user ? { ...rawData.user } : { ...rawData };
        if (rawData.player) {
            userData.player = rawData.player;
        }
        
        localStorage.setItem('token', rawData.token);
        localStorage.setItem('user', JSON.stringify(userData));
        
        setToken(rawData.token);
        setUser(userData);
        return userData;
    };

    const register = async (userDataInput) => {
        const response = await api.post('/auth/register', userDataInput);
        const rawData = response.data.data;
        
        let userData = rawData.user ? { ...rawData.user } : { ...rawData };
        if (rawData.player) {
            userData.player = rawData.player;
        }
        
        localStorage.setItem('token', rawData.token);
        localStorage.setItem('user', JSON.stringify(userData));
        
        setToken(rawData.token);
        setUser(userData);
        return userData;
    };

    const createPlayerProfile = async (playerData) => {
        const response = await api.post('/me/player-profile', playerData);
        const newPlayer = response.data.data;
        setUser(prev => {
            // Merge with prev or fallback to localStorage if state is lost
            const currentBase = prev || JSON.parse(localStorage.getItem('user')) || {};
            const updatedUser = { ...currentBase, player: newPlayer };
            localStorage.setItem('user', JSON.stringify(updatedUser));
            return updatedUser;
        });
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
            if (status !== 401 && status !== 403) {
                console.error("Error revoking current access token:", error);
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
            
            let userData = rawData.user ? { ...rawData.user } : { ...rawData };
            if (rawData.player) {
                userData.player = rawData.player;
            }
            
            localStorage.setItem('user', JSON.stringify(userData));
            setUser(userData);
            return userData;
        } catch (error) {
            const status = error.response?.status;

            if (status === 401 || status === 403) {
                if (getStoredAuthToken()) {
                    clearAuthSession(`refresh-http-${status}`);
                }
            } else {
                console.error("Error refreshing user data:", error);
            }

            throw error;
        }
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
        isAuthenticated: !!token && !!user,
        isAdmin: user?.role === 'admin'
    };

    return (
        <AuthContext.Provider value={value}>
            {!loading && children}
        </AuthContext.Provider>
    );
};
